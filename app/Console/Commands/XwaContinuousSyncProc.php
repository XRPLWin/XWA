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
#use App\Models\BHook;
use XRPLWin\XRPLHookParser\TxHookParser;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use App\Utilities\RecentAggrBatcher;
use Illuminate\Support\Facades\DB;

class XwaContinuousSyncProc extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'xwa:continuoussyncproc
                           {ledger_index_start : Starting ledger index}
                           {ledger_index_end : Ending ledger index}';

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

    protected int $proc_timeout = 600; //1200 - must be same as in XWAStartSyncer

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
      //$this->latest_ledger = Ledger::validated();
      //$this->ledger_current = (int)config('xrpl.'.config('xrpl.net').'.genesis_ledger');
      //todo check tracker where $start_ledger left of...
      
      $transactions = [];
      $last_ledger_date = null;

      $do = true;
      while($do) {
        $pullData = $this->pullData();
        $pulledTxs = $pullData['data'];
        if($pulledTxs === null) {
          $do = false;
          $this->log('Disconnecting...');
          $this->client->close();
        } else {
          foreach($pulledTxs as $_v) {
            \array_push($transactions,$_v);
          }
          $last_ledger_date = $pullData['closed'];
          //dd($pullData['closed'],count($transactions),$this->txbatchlimit <= count($transactions));
          if($this->txbatchlimit <= count($transactions)) {
            //Process queued transactions:
            //$last_ledger_date = $pullData['closed']; //latest pulled close time
            //dd($last_ledger_date);
            try {
              $last_ledger_date = $this->processTransactions($transactions);
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
      }
      
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
      return Command::SUCCESS;
    }

    /**
     * Loop all $txs, process them and store them in database.
     * @return ?int
     */
    private function processTransactions(array $txs): ?int
    {
      $last_ledger_date = null;
      $parsedDatas = []; //list of sub-method results (parsed transactions)
      $accounts = []; //list of account models which will be pushed to queue
      # Prepare Batch instance which will hold list of queries to be executed at once to BigQuery
      $batch = (config('xwa.database_engine') == 'bigquery') ? new BigqueryBatch : new SqlBatch;

      $this->log('Starting processing batch of '.count($txs).' transactions (curr: '.$this->ledger_index_current.') ...');
      $bar = $this->output->createProgressBar(count($txs));
      $bar->start();

      foreach($txs as $transaction) {

        # Handle hooks
        $hook_parser = new TxHookParser($transaction);
        $this->processHooks($hook_parser,$transaction);
        $this->processHooksTransaction($hook_parser,$transaction,$batch);
        # Handle hooks end
        
        if($transaction->metaData->TransactionResult != 'tesSUCCESS')
          continue; //do not log failed transactions

        if($transaction->TransactionType == 'Remit') {
          //https://github.com/XRPLF/XRPL-Standards/discussions/156
          //TODO MULTIPLE XWA rows for this!
          //For now skipped
          continue;
        }
        $last_ledger_date = $transaction->date;
        $type = $transaction->TransactionType;
        $method = 'processTransaction_'.$type;
        //$this->log('Inserting: '.$transaction->hash.' ('.$method.') l: '.$transaction->ledger_index.' li: '.$transaction->metaData->TransactionIndex);
        
        $extractor = new TxParticipantExtractor($transaction,['allowSpecialAccounts' => true]);
        $participants = $extractor->result();
        $iteration = 1;
        foreach($participants as $participant) {
          //$this->log('- '.$participant.' - '.$transaction->hash);

          if(!isset($accounts[$participant]))
            $accounts[$participant] = AccountLoader::getForUpdateOrCreate($participant);
          
          //$parsedDatas[] = $this->{$method}($accounts[$participant], $transaction, $batch);
          $parsedDatas[] = $this->processTransactions_sub($method, $accounts[$participant], $transaction, $batch, $iteration);

          //update last synced ledger index to account metadata (not used in continuous syncer)
          //$accounts[$participant]->l = $transaction->ledger_index;
          //$accounts[$participant]->li = $transaction->metaData->TransactionIndex;
          //$accounts[$participant]->lt = ripple_epoch_to_carbon($transaction->date)->format('Y-m-d H:i:s.uP');
          //dd($account);
          $iteration++;
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

      DB::beginTransaction();
      try {
        $recentAggrBatch = new RecentAggrBatcher;
        $recentAggrBatch->begin();
        foreach($txs as $transaction) {
          # Handle aggragations
          $recentAggrBatch->addTx($transaction);
          # Handle aggregations end
        }
        $recentAggrBatch->execute();
        $this->log('- Aggr DONE');

        $processed_rows = $batch->execute();
        
      } catch (\Throwable $e) {
        DB::rollBack();
        throw $e;
      }
      DB::commit();

      $this->log('- DONE (processed '.$processed_rows.' rows)');
      return $last_ledger_date;
    }

    /**
     * One function to accommodate all transaction types
     */
    private function processTransactions_sub(string $method, BAccount $account, \stdClass $transaction, BatchInterface $batch, int $iteration): array
    {
      $is_account_changed = false;
      //$this->line($transaction->hash);

      
      try {
        /** @var \App\XRPLParsers\XRPLParserBase */
        $parser = Parser::get($transaction, $transaction->metaData, $account->address);
      } catch (\Throwable $e) {
        $this->logError($method.' '.$transaction->hash.' '.$account->address);
        throw $e;
      }

      # Do something once:
      //if($iteration == 1) {}
      
      $parsedData = $parser->toBArray();

      if($parser->getPersist() === false)
        return $parsedData;
      
      $TransactionClassName = '\\App\\Models\\BTransaction'.$parser->getTransactionTypeClass();
      $model = new $TransactionClassName($parsedData);
      $model->address = $account->address;
      $model->xwatype = $TransactionClassName::TYPE;
      $batch->queueModelChanges($model);

      # Activations
      //if($iteration == 1) {
        $activatedAddresses = $parser->getActivated();
        foreach($activatedAddresses as $activatedAddress) {
          //$this->log('');$this->log('Activation: '.$activatedAddress. ' on hash '.$parser->getData()['hash']);
          //if($parser->getData()['hash'] == '88C746B8EB9405D46817973F6868C337D3BD633D7EE9342E043D01AEF6D919BE')
          // {
            //dump($parsedData);
            //dump($activatedAddresses);
          // } 
          $Activation = new BTransactionActivation([
            'address' => $account->address,
            'xwatype' => BTransactionActivation::TYPE,
            'l' => $parsedData['l'],
            'li' => $parsedData['li'],
            'h' => $parsedData['h'],
            't' => ripple_epoch_to_carbon((int)$parser->getDataField('Date'))->format('Y-m-d H:i:s.uP'),
            'r' => $activatedAddress,
            'isin' => true,
            'offers' => [],
            'nftoffers' => [],
            'hooks' => [],
          ]);
          $batch->queueModelChanges($Activation);
        }
      //}

     
      # Activations by
      if($activatedByAddress = $parser->getActivatedBy()) {
        $is_account_changed = true;
        //$this->log('');$this->log('Activation: Activated by '.$activatedByAddress. ' on hash '.$parser->getData()['hash']);
        $account->activatedBy = $activatedByAddress;
        $account->isdeleted = false; //in case it is reactivated, this will remove field on model save
      }

      if($method == 'AccountDelete') {
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
     * This method is executed only once per transaction.
     * Extracts and stores transaction to hook_transactions table, one or multiple rows
     * depending of affected hooks.
     * @return void
     */
    private function processHooksTransaction(TxHookParser $parser, \stdClass $transaction, BatchInterface $batch): void
    {
      $hooks = $parser->hooks();
      if(!count($hooks))
        return; //no hooks in this tx, nothing to do

      $meta =  $transaction->metaData;
      //$h = $transaction->hash;
      //$l = $transaction->ledger_index;
      //$li = $transaction->metaData->TransactionIndex;
      $ctid = encodeCTID($transaction->ledger_index,$transaction->metaData->TransactionIndex,config('xrpl.'.config('xrpl.net').'.networkid'));

      $t = ripple_epoch_to_carbon((int)$transaction->date)->format('Y-m-d H:i:s.uP');
      if(isset($transaction->Account) && $transaction->Account === "") {
        $r = 'rrrrrrrrrrrrrrrrrrrrrhoLvTp'; //ACCOUNT_ZERO
      } else {
        $r = $transaction->Account;
      }
      $txtype = $transaction->TransactionType;
      $tcode = $meta->TransactionResult;

      # Handle creations
      foreach($parser->createdHooks() as $ch) {
        $model = new BHookTransaction;
        $model->hook = $ch;
        $model->ctid = bchexdec($ctid);
        $model->t = $t;
        $model->r = $r;
        $model->txtype = $txtype;
        $model->tcode = $tcode;
        $model->hookaction = 1; //created
        $model->hookresult = 0; //no execution
        $model->hookreturnstring = '';
        $batch->queueModelChanges($model);
        unset($model);
      }
      unset($ch);

      foreach($parser->accounts() as $account) { //loop all affected accounts by hook(s)
          //ACCOUNT+HOOK combo
          # Handle installations
          foreach($parser->lookup($account,'Hook','installed') as $_hook) {
            
            $model = new BHookTransaction;
            $model->hook = $_hook;
            $model->ctid = bchexdec($ctid);
            $model->t = $t;
            $model->r = $account;
            $model->txtype = $txtype;
            $model->tcode = $tcode;
            $model->hookaction = 3; //installed (can be many accounts, we store only one row)
            $model->hookresult = 0; //no execution
            $model->hookreturnstring = '';
            $batch->queueModelChanges($model);
            unset($model);
          }

          # Handle modifications
          foreach($parser->lookup($account,'Hook','modified') as $_hook) {
            $model = new BHookTransaction;
            $model->hook = $_hook;
            $model->ctid = bchexdec($ctid);
            $model->t = $t;
            $model->r = $account;
            $model->txtype = $txtype;
            $model->tcode = $tcode;
            $model->hookaction = 5; //modified
            $model->hookresult = 0; //no execution
            $model->hookreturnstring = '';
            $batch->queueModelChanges($model);
            unset($model);
          }

          # Handle uninstallations (todo modify installation and update hookaction, do not store this below)
          foreach($parser->lookup($account,'Hook','uninstalled') as $_hook) {

            $model = new BHookTransaction;
            $model->hook = $_hook;
            $model->ctid = bchexdec($ctid);
            $model->t = $t;
            $model->r = $account;
            $model->txtype = $txtype;
            $model->tcode = $tcode;
            $model->hookaction = 4; //uninstalled
            $model->hookresult = 0; //no execution
            $model->hookreturnstring = '';
            $batch->queueModelChanges($model);
            unset($model);
          }
  
      }

      # Handle destroys
      foreach($parser->destroyedHooks() as $_hook) {
        $model = new BHookTransaction;
        $model->hook = $_hook;
        $model->ctid = bchexdec($ctid);
        $model->t = $t;
        $model->r = $transaction->Account;
        $model->txtype = $txtype;
        $model->tcode = $tcode;
        $model->hookaction = 2; //destroyed
        $model->hookresult = 0; //no execution
        $model->hookreturnstring = '';
        $batch->queueModelChanges($model);
        unset($model);
      }



      # Handle executions
      if(isset($meta->HookExecutions)) {
        foreach($meta->HookExecutions as $he) {
          $model = new BHookTransaction;
          $model->hook = $he->HookExecution->HookHash;
          $model->ctid = bchexdec($ctid);
          $model->t = $t;
          $model->r = $r;
          $model->txtype = $txtype;
          $model->tcode = $tcode;
          $model->hookaction = 0; //executed
          $model->hookresult = (int)$he->HookExecution->HookResult;
          $hookreturnstring = isset($he->HookExecution->HookReturnString) ? \hex2bin($he->HookExecution->HookReturnString) : '';
          //truncate to max 248 characters, ellipsis (...) added automatically if truncated
          $hookreturnstring = Str::limit(Str::ascii($hookreturnstring),248,' (...)');
          $model->hookreturnstring = $hookreturnstring;
          //todo save result string
          $batch->queueModelChanges($model);
          unset($model);
        }
      }
    }

    /**
     * This method is executed only once per transaction.
     * Extracts and stores hookdefinition to hooks table.
     * Syncer can collect ledgers in async/random manner, this function handles cases such as
     *   destroyed hook but not yet stored as created (syncer did not yet synced creation ledger).
     * @return void
     */
    private function processHooks(TxHookParser $parser, \stdClass $transaction): void
    {
      $li = $transaction->ledger_index;
      $meta =  $transaction->metaData;
      $ctid = encodeCTID($li,$meta->TransactionIndex,config('xrpl.'.config('xrpl.net').'.networkid'));

      foreach($parser->createdHooksDetailed() as $_hook => $_hookData) {
        Cache::tags(['hook'.$_hook])->delete('dhook:'.$_hook.'_'.$li.'_'.$meta->TransactionIndex);
        //this will create hook in db (immediately)
        HookLoader::getOrCreate(
          $_hook,
          $transaction->Account, //OK
          $ctid,
          //$_hookData->NewFields->HookSetTxnID,
          //$li,
          //$meta->TransactionIndex,
          isset($_hookData->NewFields->HookOn)?$_hookData->NewFields->HookOn:'0000000000000000000000000000000000000000000000000000000000000000', //all allowed except high ttSET_HOOK
          TxHookParser::toParams($_hookData),
          isset($_hookData->NewFields->HookNamespace)?$_hookData->NewFields->HookNamespace:'0000000000000000000000000000000000000000000000000000000000000000'
        );
      }

      foreach($parser->destroyedHooks() as $_hook) {
        //Cache::delete('dhook:'.$_hook);
        Cache::tags(['hook'.$_hook])->flush();
        $storedHook = null;
        //find deleted hookdefinition in meta to extract creation vars from FinalFields
        $createit_found = null;
        foreach($meta->AffectedNodes as $n) {
          if(isset($n->DeletedNode->LedgerEntryType) && $n->DeletedNode->LedgerEntryType == 'HookDefinition') {
            if(isset($n->DeletedNode->FinalFields->HookHash) && $n->DeletedNode->FinalFields->HookHash == $_hook) {
              $createit_found = $n->DeletedNode->FinalFields;
              break;
            }
          }
        }
        if($createit_found === null) {
          throw new \Exception('Tried to find creation in deleted definition of hook '.$_hook.' (li:'.$li.') but unable to find it');
        }

        //Query xrpl to find ledger_index of transaction
        $pulledTransaction = $this->pullTransaction($createit_found->HookSetTxnID);
        //Create TxHookParser::toParams($storedHook_params_prepared) compatible variable:
        $storedHook_params_prepared = new \stdClass();
        $storedHook_params_prepared->NewFields = $createit_found;

        //create it right away into database
        Cache::tags(['hook'.$_hook])->delete('dhook:'.$_hook.'_'.$pulledTransaction->result->ledger_index.'_'.$pulledTransaction->result->meta->TransactionIndex);
        $storedHook = HookLoader::getOrCreate(
          $_hook, //OK
          //$createit_found->HookSetTxnID, //OK
          $pulledTransaction->result->Account, //OK
          encodeCTID(
            $pulledTransaction->result->ledger_index,
            $pulledTransaction->result->meta->TransactionIndex,
            config('xrpl.'.config('xrpl.net').'.networkid')
          ),
          //$pulledTransaction->result->ledger_index, //find li of $createit_found->HookSetTxnID
          //$pulledTransaction->result->meta->TransactionIndex,
          isset($createit_found->HookOn)?$createit_found->HookOn:'0000000000000000000000000000000000000000000000000000000000000000', //all allowed except high ttSET_HOOK
          TxHookParser::toParams($storedHook_params_prepared), //WRONG
          isset($createit_found->HookNamespace)?$createit_found->HookNamespace:'0000000000000000000000000000000000000000000000000000000000000000'
        );
        if($storedHook === null) {
          throw new \Exception('Tried to flag hook '.$_hook.' (ctid:'.$ctid.'; ctid64'.bchexdec($ctid).') as destroyed but stored hook not found');
        }
        if($storedHook->ctid_to != 0) {
          if($storedHook->ctid_to != bchexdec($ctid)) {
            throw new \Exception('Tried to flag hook '.$_hook.' (ctid:'.$ctid.'; ctid64:'.bchexdec($ctid).') as destroyed but hook already flagged as destroyed in different ctid64:'.$storedHook->ctid_to);
          }
          //else retried operation, do nothing
        } else {
          $storedHook->ctid_to = bchexdec($ctid);
          /*$storedHook->l_to = $li;
          $storedHook->li_to = $meta->TransactionIndex;
          $storedHook->txid_last = $transaction->hash;*/
          $storedHook->save();
        }
        //$batch->queueModelChanges($storedHook);
      }
    }

    /**
     * Gets hook Model and stores it to _mem_hookmodels array to be referenced for queue
     * @return ?Bhook
     */
    /*private function _getHookModel($hook, $ledger_index): ?BHook
    {
      Cache::tags(['hook'.$hook])->flush();
      
      $k = $hook.'_'.$ledger_index;
      if(!isset($this->_mem_hookmodels[$k])) {
        $hm = HookLoader::get($hook,$ledger_index,false);
        if(!$hm) {
          //load closest to the provided ledger_index
          $hm = HookLoader::getClosestByHash($hook,$ledger_index);
        }
        $this->_mem_hookmodels[$k] = $hm;
      }
      return $this->_mem_hookmodels[$k];
    }*/

    private function pullTransaction(string $transactionID)
    {
      $params = [
        'id'            => $this->debug_id,
        'command'       => 'tx',
        'transaction'  => $transactionID
      ];
      $this->client->text(\json_encode($params));

      $response = null;

      # Mini loop to ensure rate limiting...
      while(true) {
        $receive = $this->client->receive();
        
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
        $e = new \Exception('Unhandled: ws response not filled (2)');
        $this->logError($e->getMessage(),$e);
        throw $e;
      }

      # Mini loop end
      if($response->status != 'success') {
        //known errors: slowDown
        $e = new \Exception('Unsupported response from wss endpoint (status: '.$response->error.') (2)');
        $this->logError($e->getMessage(),$e);
        throw $e;
      }
      return $response;
    }

    /**
     * Pulls x ledgers. If returned [data=null] - no more ledgers to pull.
     * @return array [closed => ?int ,data => ?array]
     */
    private function pullData(): array
    {
      //check if reached end:
      if($this->ledger_index_current > $this->ledger_index_end)
        return ['closed' => null, 'data' => null];

      $lbl_to = $this->ledger_index_current+$this->wsbatchlimit-1;
      if($lbl_to > $this->ledger_index_end)
        $lbl_to = $this->ledger_index_end;
      $this->info('Pulling ledgers from '.$this->ledger_index_current.' to '.$lbl_to);
      
      $data = [];
      $closed = null;
      $do = true;
      $i = 0;
      while($do) {
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
        $this->client->text(\json_encode($params));
        //$receive = $this->client->receive();
        //$response = \json_decode($receive);
        $response = null;

        # Mini loop to ensure rate limiting...
        while(true) {
          $receive = $this->client->receive();
          
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
          \array_push($data,$tx);
        }
      } //end while($do)
      
      $this->ledger_index_current = $curr_l+1; //+1 next ledger in line to be synced
      //$this->line('Set to: '.$this->ledger_index_current);
      $this->info('Total txs is: '.count($data));
      return ['closed' => $closed, 'data' => $data];
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
