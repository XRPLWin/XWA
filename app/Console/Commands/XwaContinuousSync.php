<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Utilities\Ledger;
use Illuminate\Support\Facades\Log;
use XRPLWin\XRPLTxParticipantExtractor\TxParticipantExtractor;
use App\Utilities\AccountLoader;
use App\Repository\Base\BatchInterface;
use App\Repository\Sql\Batch as SqlBatch;
use App\Repository\Bigquery\Batch as BigqueryBatch;
use App\XRPLParsers\Parser;
use App\Models\BAccount;

class XwaContinuousSync extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'xwa:continuoussync
                           {ledger_index_start : Starting ledger index}
                           {ledger_index_end? : Ending ledger index}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync transactions in ledgers, one by one.';

    /**
     * How much commands will be sent in single loop to XRPL Node
     * 
     * @var int
     */
    protected int $wsbatchlimit = 1; //10

    /**
     * How much transactions will be prepared before batch-insert
     * to database. This setting affects how much memory this job consumes.
     * 
     * @var int
     */
    protected int $txbatchlimit = 10; //400

    /**
     * Ledger index tracking variables
     */
    private int $ledger_index_start;
    private ?int $ledger_index_end = null;
    private int $ledger_index_current;

    /**
     * WS client instance
     */
    protected \WebSocket\Client $client;

    /**
     * When debugging enabled it will log output to log file.
     */
    private bool $debug = true;
    private string $debug_id = '';

    /**
     * Execute the console command.
     * @see https://github.com/Textalk/websocket-php/blob/master/docs/Client.md#exceptions
     * Sample: php artisan xwa:continuoussync 32570 32610
     * Sample: php artisan xwa:continuoussync 73806933 73806953
     * @return int
     */
    public function handle()
    {
      if(config('xwa.sync_type') != 'continuous')
        return self::FAILURE;

      $this->debug = config('app.debug');
      $this->debug_id = \substr(\md5(rand(1,999).\time()),0,5);

      # Setup start
      $ws_uri = 'wss://'.config('xrpl.'.config('xrpl.net').'.server_wss');
      //dd($ws_uri);
      //$ws_uri = 'wss://s.altnet.rippletest.net';
      $context = stream_context_create();
      stream_context_set_option($context, 'ssl', 'verify_peer', false);
      stream_context_set_option($context, 'ssl', 'verify_peer_name', false);

      $this->client = new \WebSocket\Client($ws_uri,[
        'context' => $context,
        'filter' => ['text', 'binary', 'ping'],
        'headers' => [ // Additional headers, used to specify subprotocol
          'User-Agent' => 'XRPLWin XWA (v'.config('xwa.version').') instanceid: '.instanceid(),
        ],
        'persistent' => true,
        'timeout' => 10
      ]);

      //this is max ledger we can query
      //$this->latest_ledger = Ledger::current();
      //$this->ledger_current = (int)config('xrpl.genesis_ledger');
      //todo check tracker where $start_ledger left of...

      $this->ledger_index_start = (int)$this->argument('ledger_index_start'); //32570
      $this->ledger_index_current = $this->ledger_index_start;
      $this->ledger_index_end = $this->argument('ledger_index_end');

      // Validate parameters:
      if($this->ledger_index_end !== null) $this->ledger_index_end = (int)$this->ledger_index_end;
      if($this->ledger_index_start > $this->ledger_index_end)
        throw new \Exception('Ledger index parameters invalid');
      
      $transactions = [];

      $do = true;
      while($do) {
        $pulledTxs = $this->pullData();
        if($pulledTxs === null) {
          $do = false;
          $this->log('Disconnecting...');
          $this->client->close();
        } else {
          foreach($pulledTxs as $_v) {
            \array_push($transactions,$_v);
          }

          if($this->txbatchlimit <= count($transactions)) {
            //Process queued transactions:
            $this->processTransactions($transactions);
            //Empty queue:
            $transactions = [];
          }
          
        }
      }

      if(count($transactions) > 0) {
        //Process queued transactions (what is left over):
        $this->processTransactions($transactions);
        //Empty queue:
        $transactions = [];
      }
      
      $this->log('Disconnecting...');
      $this->client->close();


      //Do tx parse on $ledgerdata:
      
      dd(count($transactions));

      # Setup end

      

      $this->client->text('{"id" : 1, "command" : "ping"}');
      $this->line($this->client->receive());
      /*$client->text('{"id" : 2, "command" : "ping"}');
      $this->line($client->receive());
      $client->text('{"id" : 9, "command" : "ping"}');
      $this->line($client->receive());
      $client->text('{"id" : 1, "command" : "ping"}');
      $this->line($client->receive());*/
      /*$client->text('{"id" : 2, "command" : "ping"}');
      $this->line($client->receive());
      $client->text('{"id" : 9, "command" : "ping"}');
      $this->line($client->receive());
      $client->text('{"id" : 1, "command" : "ping"}');
      $this->line($client->receive());
      $client->text('{"id" : 2, "command" : "ping"}');
      $this->line($client->receive());
      $client->text('{"id" : 9, "command" : "ping"}');
      $this->line($client->receive());*/


      //$client->text('{"id" : 1, "command" : "ping"}');
      //echo $client->receive();
      $this->client->close();

  
      return Command::SUCCESS;
    }

    /**
     * Loop all $txs, process them and store them in database.
     * @return void
     */
    private function processTransactions(array $txs): void
    {
      $parsedDatas = []; //list of sub-method results (parsed transactions)
      # Prepare Batch instance which will hold list of queries to be executed at once to BigQuery
      if(config('xwa.database_engine') == 'bigquery')
        $batch = new BigqueryBatch;
      else
        $batch = new SqlBatch;

      $this->log('Starting processing batch of '.count($txs).' transactions...');

      foreach($txs as $transaction) {
        if($transaction->metaData->TransactionResult != 'tesSUCCESS')
          continue; //do not log failed transactions

        $type = $transaction->TransactionType;
        $method = 'processTransaction_'.$type;
        $this->log('Inserting: '.$transaction->hash.' ('.$method.') l: '.$transaction->ledger_index.' li: '.$transaction->metaData->TransactionIndex);
        

        
        
        $extractor = new TxParticipantExtractor($transaction);
        $participants = $extractor->result();
        foreach($participants as $participant) {
          $this->log('- '.$participant);
          $account = AccountLoader::getOrCreate($participant);
          
          $parsedDatas[] = $this->{$method}($account, $transaction, $batch);
        }


        //dd($transaction->_xwa_ledger_index);

        dd($parsedDatas);
        //this is faster than call_user_func()
        //return $this->{$method}($account, $transaction, $batch);
      }
      



      dd($txs[0]);
    }

    /**
     * Created/Executed offer
     * @return array
     */
    private function processTransaction_OfferCreate(BAccount $account, \stdClass $transaction, BatchInterface $batch): array
    {
      /** @var \App\XRPLParsers\Types\OfferCreate */
      $parser = Parser::get($transaction, $transaction->metaData, $account->address);
      $parsedData = $parser->toBArray();

      if($parser->getPersist() === false)
        return $parsedData;
      
      $TransactionClassName = '\\App\\Models\\BTransaction'.$parser->getTransactionTypeClass();
      $model = new $TransactionClassName($parsedData);
      $model->address = $account->address;
      $model->xwatype = $TransactionClassName::TYPE;
      $batch->queueModelChanges($model);
      //$model->save();
      return $parsedData;
    }

    /**
     * Pulls x ledgers. If returned null - no more ledgers to pull.
     * @return ?array
     */
    private function pullData(): ?array
    {
      //check if reached end:
      if($this->ledger_index_current > $this->ledger_index_end)
        return null;

      $this->info('Pulling ledgers from '.$this->ledger_index_current.' to '.($this->ledger_index_current+$this->wsbatchlimit-1).' (overflow)');
      $data = [];
      $do = true;
      $i = 0;
      while($do) {
        $curr_l = $this->ledger_index_current+$i;
        $i++;
        if($i >= $this->wsbatchlimit)
          $do = false;
        if($curr_l >= $this->ledger_index_end)
          $do = false;

        $this->line('Pulling ledger '.$curr_l);

        //Do node lookup:
        $params = [
          'id'            => $this->debug_id,
          'command'       => 'ledger',
          'ledger_index'  => $curr_l,
          'full'          => false,
          'accounts'      => false,
          'transactions'  => true,
          'expand'        => true,
          'owner_funds'   => false
        ];
        //dd(\json_encode($params));
        $this->client->text(\json_encode($params));
        $response = \json_decode($this->client->receive());
        if($response->status != 'success') {
          throw new \Exception('Unsucessful response from wss endpoint');
          //retry?
        }

        $this->info('Found '.count($response->result->ledger->transactions).' txs');
        foreach($response->result->ledger->transactions as $tx) {
          $tx->ledger_index = $curr_l;
          if(isset($tx->date)) {
            dd("WARNING!!! DATE ALREDY RESERVED");
          }

          $tx->date = $response->result->ledger->close_time;
          \array_push($data,$tx);
        }
       
        
        //$this->line($response);
        //exit;
      }
      
      $this->ledger_index_current = $curr_l+1;

      $this->info('Total txs is: '.count($data));
      return $data;
    }

    private function log(string $logline)
    {
      $logline = '['.$this->debug_id.'] '.$logline;
      $this->info($logline);

      if(!$this->debug)
        return;

      Log::channel('syncjobcontinuous')->info($logline);
    }
}
