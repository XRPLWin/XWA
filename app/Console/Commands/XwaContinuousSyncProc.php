<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
#use App\Utilities\Ledger;
use Illuminate\Support\Facades\Log;
use XRPLWin\XRPLTxParticipantExtractor\TxParticipantExtractor;
use App\Utilities\AccountLoader;
use App\Repository\Base\BatchInterface;
use App\Repository\Sql\Batch as SqlBatch;
use App\Repository\Bigquery\Batch as BigqueryBatch;
use App\XRPLParsers\Parser;
use App\Models\BAccount;
use App\Models\BTransactionActivation;
use App\Models\Synctracker;

class XwaContinuousSyncProc extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'xwa:continuoussyncproc
                           {ledger_index_start : Starting ledger index}
                           {ledger_index_end? : Ending ledger index}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync transactions in ledgers, one by one. This is single process. In production env it if recommended to invoke this via other command.';

    /**
     * How much commands will be sent in single loop to XRPL Node
     * 
     * @var int
     */
    protected int $wsbatchlimit = 10; //10

    /**
     * How much transactions will be prepared before batch-insert
     * to database. This setting affects how much memory this job consumes.
     * 
     * @var int
     */
    protected int $txbatchlimit = 400; //400

    protected int $proc_timeout = 600; //600 - must be same as in XWAStartSyncer

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

    protected ?Synctracker $synctracker;

    /**
     * When debugging enabled it will log output to log file.
     */
    private bool $debug = true;
    private string $debug_id = '';

    /**
     * Synces ledger between provided ledger indexes. Does not validate ledger index range.
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

      
     
      /*$this->log('started proc '.$this->debug_id);
      sleep(rand(5,20));
      $this->log('ended proc '.$this->debug_id);
      //throw new \Exception('test '.$this->debug_id);
      return self::SUCCESS;*/


      $this->ledger_index_start = $start_li = (int)$this->argument('ledger_index_start'); //32570
      if($this->ledger_index_start < 1) $this->ledger_index_start = $start_li = 1;

      $this->ledger_index_current = $this->ledger_index_start;
      $this->ledger_index_end = $end_li = $this->argument('ledger_index_end');

      $this->synctracker = Synctracker::select(['id','first_l','progress_l','last_l','updated_at'])
      //INNER:
      ->orWhere(function($q) use ($start_li,$end_li) {
        $q->whereBetween('first_l',[$start_li,$end_li]);
        $q->whereBetween('last_l',[$start_li,$end_li]);
      })
      //BORDER RIGHT:
      ->orWhere(function($q) use ($start_li,$end_li) {
        $q->whereBetween('first_l',[$start_li,$end_li]);
        $q->where('last_l','>=',$end_li);
      })
      //BORDER LEFT:
      ->orWhere(function($q) use ($start_li,$end_li) {
        $q->where('first_l','<=',$start_li);
        $q->whereBetween('last_l',[$start_li,$end_li]);
      })
      //OUTER:
      ->orWhere(function($q) use ($start_li,$end_li) {
        $q->where('first_l','<=',$start_li);
        $q->where('last_l','>=',$end_li);
      })
      ->orderBy('id','ASC')->first();

      if($this->synctracker !== null) {
        //check if job timed out, if yes continue
        if($this->synctracker->updated_at->addSeconds($this->proc_timeout)->isPast()) {
          //Job has timed out
          $this->log('Job has previously timed out, adjusting tracker...'); //it might timed out somewhere in the middle, mozda ovo ni ne treba?
          $this->ledger_index_start = $start_li = $this->synctracker->progress_l+1;
          $this->ledger_index_current = $this->ledger_index_start;
          $this->ledger_index_end = $end_li = $this->synctracker->last_l;
          $this->synctracker->delete();
          $this->synctracker = null;
        }
      }
      
      if($this->synctracker !== null) {
        $this->log('Job already running');
        return self::SUCCESS;
      } else {
        //reserve spot
        $this->synctracker = new Synctracker();
        $this->synctracker->first_l = $start_li;
        $this->synctracker->progress_l = $start_li;
        $this->synctracker->last_l = $end_li;
        $this->synctracker->is_completed = false;
        $this->synctracker->save();
      }
      unset($start_li);
      unset($end_li);
      unset($check);

      $this->log('Job params: '.$this->ledger_index_start.' '.$this->ledger_index_end);

      # Setup start
      $ws_uri = config('xrpl.'.config('xrpl.net').'.server_wss_syncer');
      
      //$ws_uri = 'wss://s.altnet.rippletest.net';
      $context = stream_context_create();
      stream_context_set_option($context, 'ssl', 'verify_peer', false);
      stream_context_set_option($context, 'ssl', 'verify_peer_name', false);

      $this->client = new \WebSocket\Client($ws_uri,[
        'context' => $context,
        'filter' => ['text'],// ['text', 'binary', 'ping'],
        'headers' => [ // Additional headers, used to specify subprotocol
          'User-Agent' => 'XRPLWin XWA (v'.config('xwa.version').') instanceid: '.instanceid(),
        ],
        'persistent' => true,
        'timeout' => 30
      ]);

      //this is max ledger we can query
      //$this->latest_ledger = Ledger::current();
      //$this->ledger_current = (int)config('xrpl.genesis_ledger');
      //todo check tracker where $start_ledger left of...

      

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
            try {
              $this->processTransactions($transactions);
            } catch (\Throwable $e) {
              $this->log('Error logged (1): '.$e->getMessage());
              $this->logError($e->getMessage(),$e);
              throw $e;
            }
            //Empty the queue:
            $transactions = [];
          }
          
        }
      }

      if(count($transactions) > 0) {
        //Process queued transactions (what is left over):
        try {
          $this->processTransactions($transactions);
        } catch (\Throwable $e) {
          $this->log('Error logged (2): '.$e->getMessage());
          $this->logError($e->getMessage(),$e);
          throw $e;
        }
        
        //Empty queue:
        $transactions = [];
      }
      
      $this->log('Disconnecting...');
      $this->client->close();
      $this->log('Saving tracker...');
      $this->synctracker->is_completed = true;
      $this->synctracker->save();
      $this->log('Done');
      return Command::SUCCESS;
    }

    /**
     * Loop all $txs, process them and store them in database.
     * @return void
     */
    private function processTransactions(array $txs): void
    {
      $parsedDatas = []; //list of sub-method results (parsed transactions)
      $accounts = []; //list of account models which will be pushed to queue
      # Prepare Batch instance which will hold list of queries to be executed at once to BigQuery
      $batch = (config('xwa.database_engine') == 'bigquery') ? new BigqueryBatch : new SqlBatch;

      $this->log('Starting processing batch of '.count($txs).' transactions (curr: '.$this->ledger_index_current.') ...');
      $bar = $this->output->createProgressBar(count($txs));
      $bar->start();


      foreach($txs as $transaction) {
        if($transaction->metaData->TransactionResult != 'tesSUCCESS')
          continue; //do not log failed transactions

        $type = $transaction->TransactionType;
        $method = 'processTransaction_'.$type;
        //$this->log('Inserting: '.$transaction->hash.' ('.$method.') l: '.$transaction->ledger_index.' li: '.$transaction->metaData->TransactionIndex);
        
        $extractor = new TxParticipantExtractor($transaction,['allowSpecialAccounts' => true]);
        $participants = $extractor->result();
        foreach($participants as $participant) {
          //$this->log('- '.$participant);

          if(!isset($accounts[$participant]))
            $accounts[$participant] = AccountLoader::getForUpdateOrCreate($participant);
          
          //$parsedDatas[] = $this->{$method}($accounts[$participant], $transaction, $batch);
          $parsedDatas[] = $this->processTransactions_sub($method,$accounts[$participant], $transaction, $batch);

          //update last synced ledger index to account metadata (not used in continuous syncer)
          //$accounts[$participant]->l = $transaction->ledger_index;
          //$accounts[$participant]->li = $transaction->metaData->TransactionIndex;
          //$accounts[$participant]->lt = ripple_epoch_to_carbon($transaction->date)->format('Y-m-d H:i:s.uP');
          //dd($account);
        }


        //dd($transaction->_xwa_ledger_index);

        //dd($parsedDatas);
        //this is faster than call_user_func()
        //return $this->{$method}($account, $transaction, $batch);
        $bar->advance();
      }
      $bar->finish();

      foreach($accounts as $a) {
        $batch->queueModelChanges($a);
      }

      $processed_rows = $batch->execute();
      $this->synctracker->progress_l = $this->ledger_index_current-1;
      $this->synctracker->save();
      $this->log('- DONE (processed '.$processed_rows.' rows)');

    }

    /**
     * One function to accommodate all transaction types
     */
    private function processTransactions_sub(string $method, BAccount $account, \stdClass $transaction, BatchInterface $batch): array
    {
      $is_account_changed = false;

      /** @var \App\XRPLParsers\Types\XRPLParserBase */
      
      try {
        $parser = Parser::get($transaction, $transaction->metaData, $account->address);
      } catch (\Throwable $e) {
        $this->logError($method.' '.$transaction->hash.' '.$account->address);
        throw $e;
      }
      
      $parsedData = $parser->toBArray();

      if($parser->getPersist() === false)
        return $parsedData;
      
      $TransactionClassName = '\\App\\Models\\BTransaction'.$parser->getTransactionTypeClass();
      $model = new $TransactionClassName($parsedData);
      $model->address = $account->address;
      $model->xwatype = $TransactionClassName::TYPE;
      $batch->queueModelChanges($model);

      # Activations
      $activatedAddresses = $parser->getActivated();
      foreach($activatedAddresses as $activatedAddress) {
        //$this->log('');$this->log('Activation: '.$activatedAddress. ' on hash '.$parser->getData()['hash']);
        $Activation = new BTransactionActivation([
          'address' => $activatedAddress,
          'xwatype' => BTransactionActivation::TYPE,
          'l' => $parsedData['l'],
          'li' => $parsedData['li'],
          'h' => $parsedData['h'],
          't' => ripple_epoch_to_carbon((int)$parser->getDataField('Date'))->format('Y-m-d H:i:s.uP'),
          'r' => $account->address,
          'isin' => true,
          'offers' => [],
          'nftoffers' => [],
          'hooks' => [],
        ]);
        $batch->queueModelChanges($Activation);
      }

      # Activations by
      if($activatedByAddress = $parser->getActivatedBy()) {
        $is_account_changed = true;
        //$this->log('');$this->log('Activation: Activated by '.$activatedByAddress. ' on hash '.$parser->getData()['hash']);
        $account->activatedBy = $activatedByAddress;
        $account->isdeleted = false; //in case it is reactivated, this will remove field on model save
      }

      //TODO ACCOUNT DELETE
      if($method == 'AccountDelete') {
        dd('account delete todo check code below');
        if(!$parsedData['isin']) {
          //outgoing, this is deleted account, flag account deleted
          //$this->log('');
          //$this->log('Deleted');
          $is_account_changed = true;
          $account->isdeleted = true;
        }
      }

      if($is_account_changed)
        $batch->queueModelChanges($account);

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
        //$receive = $this->client->receive();
        //$response = \json_decode($receive);
        $response = null;
        # Mini loop to ensure rate limiting...

        while(true) {
          $receive = $this->client->receive();
          
          if($receive === null) {
            $this->log('Connection closed');
            $this->client->close();
            $this->log('Cooling down (3 seconds)...');
            sleep(3);
            $this->client->text(\json_encode($params));
            //dump($params,$receive);
            
          } elseif($receive === '') {
            dd('Unknown response (Ping?)');
          } else {
            $response = \json_decode($receive);
            break;
          }
        }

        if($response === null)
          throw new \Exception('Unhandled: ws response not filled');

        # Mini loop end
        if($response->status != 'success') {
          throw new \Exception('Unsucessful response from wss endpoint');
          //retry?
        }
        if(count($response->result->ledger->transactions) > 0)
          $this->log('Pulled ledger '.$curr_l.' found '.count($response->result->ledger->transactions).' txs');
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

    private function logError(string $logline, ?\Throwable $e = null)
    {
      $logline = '['.$this->debug_id.'] '.$logline;
      $this->error($logline);

      //if(!$this->debug)
      //  return;

      if($e)
        Log::channel('syncjobcontinuous_error')->error($e);
      else
        Log::channel('syncjobcontinuous_error')->error($logline);
    }
}
