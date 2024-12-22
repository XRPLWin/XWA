<?php

namespace App\Console\Commands;

use App\Models\B;
use Illuminate\Console\Command;
#use App\Utilities\Ledger;
use Illuminate\Support\Facades\Log;
use XRPLWin\XRPLTxParticipantExtractor\TxParticipantExtractor;
use App\Utilities\AccountLoader;
use App\Utilities\HookLoader;
use App\Repository\Base\BatchInterface;
use App\Repository\Sql\Batch as SqlBatch;
use App\Repository\Bigquery\Batch as BigqueryBatch;
use App\XRPLParsers\Parser;
#use App\XRPLParsers\XRPLParserBase;
use App\Models\BAccount;
use App\Models\BHook;
use App\Models\BHookTransaction;
use App\Models\BTransactionActivation;
use App\Models\Synctracker;
use App\Models\Oracle;
#use App\Models\BHook;
use XRPLWin\XRPLHookParser\TxHookParser;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use App\Utilities\RecentAggrBatcher;
use App\Utilities\NFTAggrBatcher;
use App\Utilities\AggrBatcher;
use Illuminate\Support\Facades\DB;

class XwaContinuousAggrSyncProc extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'xwa:continuousaggrsyncproc
                           {ledger_index_start : Starting ledger index}
                           {ledger_index_end : Ending ledger index}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync transaction aggregations in ledgers, one by one. This is single process. In production env it if recommended to invoke this via other command.';

    /**
     * How much commands will be sent in single loop to XRPL Node
     * 
     * @var int
     */
    protected int $wsbatchlimit = 1; //DO NOT CHANGE THIS VALUE - ledger aggr depends on it

    /**
     * How much transactions will be prepared before batch-insert
     * to database. This setting affects how much memory this job consumes.
     * 
     * @var int
     */
    protected int $txbatchlimit = 9999999; //9999999 - infinite, we need to process all transactions of single ledger at once

    protected int $proc_timeout = 600; //1200 - must be same as in XWAStartAggrSyncer

    /**
     * Ledger index tracking variables
     */
    private int $ledger_index_start;
    private int $ledger_index_end;
    private int $ledger_index_current;

    /**
     * WS client instance
     */
    protected \WebSocket\Client $client;

    protected ?Synctracker $synctracker;

    /**
     * A place to keep models in memory and referenced
     */
    //protected array $_mem_hookmodels = [];

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
      if(config('xwa.sync_mode') != 'aggregations')
        return self::FAILURE;

      if(config('xwa.sync_type') != 'continuous')
        return self::FAILURE;

      $this->debug = config('app.debug');
      $this->debug_id = \substr(\md5(rand(1,999).\time()),0,5);

      set_time_limit(($this->proc_timeout-10));
      
      # Set initial ranges:
      $this->ledger_index_start = (int)$this->argument('ledger_index_start'); //32570
      $this->ledger_index_current = $this->ledger_index_start; //ledger to be pulled next
      $this->ledger_index_end = (int)$this->argument('ledger_index_end');

      # Validate ranges:
      if($this->ledger_index_start < 1)
        throw new \Exception('Invalid starting range');
      if($this->ledger_index_start > $this->ledger_index_end)
        throw new \Exception('Starting range can not be bigger than ending range');

      $this->synctracker = Synctracker::select(['id','first_l','last_synced_l','last_l','created_at','updated_at'])
        ->where('first_l',$this->ledger_index_start)
        ->first();
        

      if($this->synctracker !== null) {
        //check if job timed out, if yes continue
        if($this->synctracker->updated_at->addSeconds($this->proc_timeout)->isPast()) {
          //Job has timed out
          $this->log('Job has previously timed out, adjusting tracker...'); //it might timed out somewhere in the middle, mozda ovo ni ne treba?
          $this->ledger_index_current = $this->synctracker->last_synced_l+1;
          $this->ledger_index_end = $this->synctracker->last_l;
          
        } else {
          $this->log('Job already running, exiting.');
          return self::SUCCESS;
        }
      }
      
      if($this->synctracker === null) {
        //reserve spot
        $this->synctracker = new Synctracker();
        $this->synctracker->first_l = $this->ledger_index_start;
        $this->synctracker->last_synced_l = $this->ledger_index_current-1;
        $this->synctracker->last_l = $this->ledger_index_end;
        $this->synctracker->last_lt = null;
        //$this->synctracker->last_lt = ripple_epoch_to_carbon(config('xrpl.'.config('xrpl.net').'.genesis_ledger_close_time'))->format('Y-m-d H:i:s.uP');
        $this->synctracker->is_completed = false;
        $this->synctracker->save();
      }

      $this->log('Job params: s'.$this->ledger_index_start.' c'.$this->ledger_index_current.' e'.$this->ledger_index_end);
      
      # Setup start
      $ws_uris = config('xrpl.'.config('xrpl.net').'.server_wss_syncer');
      $ws_pick = rand(0,count($ws_uris)-1);
      $ws_uri = $ws_uris[$ws_pick];
      $this->log('Using: '.$ws_uri);
      
      //$ws_uri = 'wss://s.altnet.rippletest.net';
      $context = stream_context_create();
      stream_context_set_option($context, 'ssl', 'verify_peer', false);
      stream_context_set_option($context, 'ssl', 'verify_peer_name', false);

      $this->client = new \WebSocket\Client($ws_uri,[
        'context' => $context,
        'filter' => ['text'],// ['text', 'binary', 'ping'],
        'headers' => [ // Additional headers, used to specify subprotocol
          'User-Agent' => 'XRPLWin XWA (v'.config('xwa.version').') instanceid: '.instanceid(),
          //'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:129.0) Gecko/20100101 Firefox/129.0',
        ],
        'persistent' => true,
        'timeout' => 30 //30
      ]);

      //this is max ledger we can query
      //$this->latest_ledger = Ledger::validated();
      //$this->ledger_current = (int)config('xrpl.'.config('xrpl.net').'.genesis_ledger');
      //todo check tracker where $start_ledger left of...
      
      $transactions = [];
      $last_ledger_date = null;

      $do = true;
      while($do) {
        $pullData = $this->pullData();
        $pulledTxs = $pullData['data'];
        $ledgerIntervalSeconds = $pullData['result_ledger_interval_s'];
        $ledgerIntervalPrevNext = $pullData['result_ledger_interval_prev_next'];
        if($pulledTxs === null) {
          $do = false;
          $this->log('Disconnecting...');
          try {
            //Try catch because client might already timed out (xwa took too long to store transaction and ws timed out)
            $this->client->close();
          } catch (\WebSocket\ConnectionException $e) {
            if($e->getMessage() == 'Empty read; connection dead?') {
              //Connection was already closed (timed out)
            } else {
              throw $e; //unexpected response, throw
            }
          }
        } else {
          foreach($pulledTxs as $_v) {
            \array_push($transactions,$_v);
          }
          $last_ledger_date = $pullData['closed'];
          //dd($pullData['closed'],count($transactions),$this->txbatchlimit <= count($transactions));
          //if($this->txbatchlimit <= count($transactions)) {
            //Process queued transactions:
            //$last_ledger_date = $pullData['closed']; //latest pulled close time
            //dd($last_ledger_date);
            try {
              $last_ledger_date = $this->processSingleLedgerTransactions($transactions,$ledgerIntervalSeconds,$ledgerIntervalPrevNext);
            } catch (\Throwable $e) {
              $this->log('Error logged (1): '.$e->getMessage());
              $this->logError($e->getMessage(),$e);
              throw $e;
            }
            $this->synctracker->last_synced_l = $this->ledger_index_current-1;
            if($last_ledger_date !== null)
              $this->synctracker->last_lt = ripple_epoch_to_carbon($last_ledger_date)->format('Y-m-d H:i:s.uP');
            else
              $this->synctracker->last_lt = null;
            $this->synctracker->save();
            //Empty the queue:
            $transactions = [];
          //}
        }
      }

      /*if(count($transactions) > 0) {
        //Process queued transactions (what is left over):
        try {
          $this->processSingleLedgerTransactions($transactions,$ledgerIntervalSeconds,$ledgerIntervalPrevNext);
        } catch (\Throwable $e) {
          $this->log('Error logged (2): '.$e->getMessage());
          $this->logError($e->getMessage(),$e);
          throw $e;
        }
      }*/
      
      $this->log('Saving tracker...');
      $this->synctracker->last_synced_l = $this->ledger_index_current-1;
      if($last_ledger_date !== null)
        $this->synctracker->last_lt = ripple_epoch_to_carbon($last_ledger_date)->format('Y-m-d H:i:s.uP');
      else
        $this->synctracker->last_lt = null;
      $this->synctracker->is_completed = true;
      $this->synctracker->save();
      $this->log('Tracker saved.');
      $this->client->close();
      $this->log('Disconnected.');

      //Check if last ledger_index was yesterday, if yes we need to aggr and close day

      return Command::SUCCESS;
    }

    /**
     * Loop all $txs of single ledger, process them and store aggregations in database.
     * @return ?int
     */
    private function processSingleLedgerTransactions(array $txs, ?int $thisLedgerIntervalSeconds, ?array $ledgerIntervalPrevNext): ?int
    {
      $last_ledger_date = null;
      //$parsedDatas = []; //list of sub-method results (parsed transactions)
      //$accounts = []; //list of account models which will be pushed to queue
      # Prepare Batch instance which will hold list of queries to be executed at once to BigQuery
      //$batch = (config('xwa.database_engine') == 'bigquery') ? new BigqueryBatch : new SqlBatch;

      $this->log('Starting processing batch of '.count($txs).' transactions (at ledger: '.($this->ledger_index_current-1).') ...');
      $bar = $this->output->createProgressBar(count($txs));
      $bar->start();

      $batcher = new AggrBatcher($this->ledger_index_current-1, $thisLedgerIntervalSeconds, $ledgerIntervalPrevNext);

      foreach($txs as $transaction) {
        //$YMD = ripple_epoch_to_carbon($transaction->date)->format('Y-m-d');
        
        $last_ledger_date = $transaction->date;
        
        $batcher->addTx($transaction);
        $bar->advance();
      }
      $bar->finish();
      DB::beginTransaction();
      try {
        $batcher->execute();
      } catch (\Throwable $e) {
        DB::rollBack();
        throw $e;
      }
      DB::commit();

      $this->log('- DONE (processed '.count($txs).' transactions)');
      return $last_ledger_date;
    }


    /**
     * Pulls x ledgers. If returned [data=null] - no more ledgers to pull.
     * This pulls only one ledger at the time !important
     * @return array [closed => ?int ,data => ?array, result_ledger_interval_s => ?int, result_ledger_interval_prev_next => ?array]
     */
    private function pullData(): array
    {
      //check if reached end:
      if($this->ledger_index_current > $this->ledger_index_end)
        return ['closed' => null, 'data' => null, 'result_ledger_interval_s' => null, 'result_ledger_interval_prev_next' => null];

      $lbl_to = $this->ledger_index_current+$this->wsbatchlimit-1;
      if($lbl_to > $this->ledger_index_end)
        $lbl_to = $this->ledger_index_end;
      $this->info('Pulling ledgers from '.$this->ledger_index_current.' to '.$lbl_to);
      
      $data = [];
      
      $closed = null;
      $result_ledger_interval_s = null; //how much seconds from previous ledger
      $result_ledger_interval_prev_next = null;
      $do = true;
      $i = 0;
      while($do) {
        //$this->info('Outer While loop started');
        $curr_l = $this->ledger_index_current+$i;
        $i++;
        if($i >= $this->wsbatchlimit)
          $do = false;
        if($curr_l >= $this->ledger_index_end)
          $do = false;

        
        //if($curr_l == 1500) throw new \Exception('Debug exc');
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
        //$this->info('About to send text to ws client...');
        $this->client->text(\json_encode($params));
        //$this->info('Sent');
        //$receive = $this->client->receive();
        //$response = \json_decode($receive);
        $response = null;

        # Mini loop to ensure rate limiting...
        while(true) {
          //$this->info('Inner While loop started');
          $receive = null;
          try {
            $receive = $this->client->receive();
          } catch (\WebSocket\ConnectionException $e) {
            if($e->getMessage() == 'Empty read; connection dead?') {
              //Connection was already closed (timed out) - will cooldown and retry below
              $this->log($e->getMessage());
            } else {
              throw $e; //unexpected response, throw
            }
          }
          //$this->info('receive executed');
          
          if($receive === null) {
            $this->client->close();
            $this->log('Connection closed');
            $this->log('Cooling down (3 seconds)...');
            sleep(3);
            $this->client->text(\json_encode($params));
          } elseif($receive === '') {
            $e = new \Exception('WSS: Unknown response (Ping?)');
            $this->logError($e->getMessage(),$e);
            throw $e;
          } else {
            $response = \json_decode($receive);
            break;
          }
        }

        if($response === null) {
          $e = new \Exception('Unhandled: ws response not filled');
          $this->logError($e->getMessage(),$e);
          throw $e;
        }

        # Mini loop end
        if($response->status != 'success') {
          if($response->error == 'lgrNotFound') {
            //skip this
            $this->log('Skipped ledger '.$curr_l.' (lgrNotFound)');
            continue;
          } else {
            //known errors: slowDown
            $e = new \Exception('Unsupported response from wss endpoint (status: '.$response->error.')');
            $this->logError($e->getMessage(),$e);
            throw $e;
          }
        }

        $closed = $response->result->ledger->close_time;

        if(count($response->result->ledger->transactions) > 0)
          $this->log('Pulled ledger '.$curr_l.' found '.count($response->result->ledger->transactions).' txs');

        $txs_collection = collect($response->result->ledger->transactions)->sortBy('metaData.TransactionIndex');
        foreach($txs_collection as $tx) {
          
          $tx->ledger_index = $curr_l;

          if(isset($tx->date)) { //this can be removed
            dd("WARNING!!! DATE ALREDY RESERVED");
          }

          $tx->date = $response->result->ledger->close_time;
          $result_ledger_interval_s = $response->result->ledger->close_time - $response->result->ledger->parent_close_time;
          $result_ledger_interval_prev_next = [$response->result->ledger->parent_close_time,$response->result->ledger->close_time];
          \array_push($data,$tx);
        }
      } //end while($do)
      
      $this->ledger_index_current = $curr_l+1; //+1 next ledger in line to be synced
      //$this->line('Set to: '.$this->ledger_index_current);
      $this->info('Total txs is: '.count($data));
      return ['closed' => $closed, 'data' => $data, 'result_ledger_interval_s' => $result_ledger_interval_s, 'result_ledger_interval_prev_next' => $result_ledger_interval_prev_next];
    }

    private function log(string $logline)
    {
      $logline = '['.$this->debug_id.'] '.$logline;
      $this->info($logline);

      if(!$this->debug)
        return;

      Log::channel('syncaggrjobcontinuous')->info($logline);
    }

    private function logError(string $logline, ?\Throwable $e = null)
    {
      $logline = '['.$this->debug_id.'] '.$logline;
      $this->error($logline);

      //if(!$this->debug)
      //  return;

      if($e)
        Log::channel('syncaggrjobcontinuous_error')->error($e);
      else
        Log::channel('syncaggrjobcontinuous_error')->error($logline);
    }
}
