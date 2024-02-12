<?php declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

use XRPLWin\XRPL\Client;
use App\Utilities\AccountLoader;
use App\Utilities\Ledger;
use App\Models\BAccount;
use App\Models\BTransaction;
use App\Models\BTransactionActivation;
use App\XRPLParsers\Parser;
#use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use App\Repository\Base\BatchInterface;
use App\Repository\Sql\Batch as SqlBatch;
use App\Repository\Bigquery\Batch as BigqueryBatch;
#use App\Repository\TransactionsRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class XwaAccountSync extends Command
{
    /**
     * The name and signature of the console command.
     * @sample php artisan xwa:accountsync rAcct...
     * @sample php artisan xwa:accountsync rAcct... --limit=3
     * @var string
     */
    protected $signature = 'xwa:accountsync
                            {address : XRP account address}
                            {--recursiveaccountqueue : Enable to create additional queues for other accounts}
                            {--limit=0 : Limit batch jobs, if limit is set after x batch jobs new queue job will be created, leave empty for no limit}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Do a full sync of account';

    /**
     * Flag if this scan will generate new queues.
     *
     * @var bool
     */
    protected bool $recursiveaccountqueue = false;

    /**
     * How much batch jobs will be executed before requeuing
     * Requeuing will move job to end of queue.
     * 
     * @var int
     */
    protected int $batchlimit = 0;

    /**
     * Current batch being executed.
     *
     * @var int
     */
    protected int $batch_current = 0;

    /**
     * Current ledger being scanned.
     *
     * @var int
     */
    private int $ledger_current = -1;

    /**
     * Current ledger being scanned time.
     *
     * @var string
     */
    private string $ledger_current_time;

    /**
     * XRPL API Client instance
     *
     * @var \XRPLWin\XRPL\Client
     */
    protected Client $XRPLClient;

    /**
     * When debugging enabled it will log output to log file.
     */
    private bool $debug = true;
    private string $debug_id = '';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
      if(config('xwa.sync_type') != 'account')
        return self::FAILURE;

      dd('Disabled: missing hook create and destroy triggers!');

      $this->debug = config('app.debug');
      $this->debug_id = \substr(\md5(rand(1,999).\time()),0,5);
      
      $this->XRPLClient = app(Client::class);

      //dd('test',config_static('xrpl.address_ignore.rBKPS4oLSaV2KVVuHH8EpQqMGgGefGFQs72'));
      $address = $this->argument('address');
      $this->recursiveaccountqueue = $this->option('recursiveaccountqueue'); //bool
      $this->batchlimit = (int)$this->option('limit'); //int

      $this->log('######################### START');
      $this->log('Processing address: '.$address);

      //Flag job as started
      DB::beginTransaction();
      $jobExists = DB::table('jobs')->where('qtype_data', $address)->count();
      if($jobExists) {
        DB::update('update jobs set started_at = ? where qtype_data = ?', [\time(),$address]);
      }
      DB::commit();
      
      //$this->ledger_current = $this->XRPLClient->api('ledger_current')->send()->finalResult();
      
      $this->ledger_current = Ledger::validated();
      $this->ledger_current_time = \Carbon\Carbon::now()->format('Y-m-d H:i:s.uP'); //not 100% on point due to Ledger::validated() can be stale up to 10 seconds
     
      //clear account cache
      Cache::forget('daccount:'.$address);
      Cache::forget('daccount_fti:'.$address);

      $account = AccountLoader::getOrCreate($address);

      //$account->last_sync_started = \time();
      //$account->save();
      //If this account is issuer (by checking obligations) set t field to 1.
      //if($account->checkIsIssuer())
      //  $account->t = 1;
      //else
      //  unset($account->t); //this will remove field on model save

      //dd($account);
      
      //Test only start (comment this)
      //$account->l = 80676166;$account->save();exit;
      //$account->l = 74139050;$account->save();exit;
      //Test only end

      //$this->ledger_current = 66055480;
     // $account->l = 66055470;
      
      /*if( config_static('xrpl.address_ignore.'.$account->address) !== null ) {
        $this->log('History sync skipped (ignored)');
        //modify $account todo
        return 0;
      }*/
      
      $ledger_index_min = (int)$account->l;
      $ym = ripple_epoch_to_carbon(config('xrpl.'.config('xrpl.net').'.genesis_ledger_close_time'))->format('Ym');
      if($ledger_index_min != 0 && config('xwa.database_engine') == 'sql') {
        //find last tx in sharded db, back from today to genesis (first one found is it)
        $period_startdate = ripple_epoch_to_carbon(config('xrpl.'.config('xrpl.net').'.genesis_ledger_close_time'));
        $period = \Carbon\CarbonPeriod::create($period_startdate, '30 days', now());
        $period_array = [];
        foreach($period as $m) {
          $period_array[] = $m->format('Ym');
        }
        unset($m);
        $period_array = \array_reverse($period_array);

        foreach($period_array as $m) {
          $last_inserted_tx = BTransaction::repo_fetchone($m,['l','li'], ['address' => $address], [['t', 'desc'],['l','desc'],['li','desc']]);
          if($last_inserted_tx !== null) {
            break;
          }
        }
      } else {
        # Find last inserted transaction in transactions table for check to prevent duplicates
        $last_inserted_tx = BTransaction::repo_fetchone($ym,['l','li'], ['address' => $address], [['t', 'desc'],['l','desc'],['li','desc']]);
      }
      
      //$last_inserted_tx = TransactionsRepository::fetchOne('address = """'.$address.'"""','l,li','t DESC');
      //dd($last_inserted_tx);
      $this->log('last_inserted_tx: '.var_export($last_inserted_tx, true));
      //Log::debug(var_export($last_inserted_tx, true));
      
      if(is_object($last_inserted_tx)) {
        $this->log('last_inserted_tx: mark1');
        //At least one transaction exists, query one less ledger index just in case something wont be missed
        $ledger_index_min--;
      }
      $account_tx = $this->XRPLClient->api('account_tx')
          ->params([
            'account' => $account->address,
            //'ledger_index' => 'current',
            'ledger_index_min' => $ledger_index_min === 0 ? -1:$ledger_index_min, //Ledger index this account is scanned to.
            'ledger_index_max' => $this->ledger_current,
            'binary' => false,
            'forward' => true,
            'limit' => 400, //400
          ]);
      $account_tx->setCooldownHandler(
        /**
         * @param int $current_try Current try 1 to max
         * @param int $default_cooldown_seconds Predefined cooldown seconds
         * @see https://github.com/XRPLWin/XRPL#custom-rate-limit-handling
         * @return void|boolean return false to stop process
         */
        function(int $current_try, int $default_cooldown_seconds) {
            $sec = $default_cooldown_seconds * $current_try;
            if($sec > 300) {
              echo 'Cooldown threshold of '.$current_try.' tries reached exiting'.PHP_EOL;
              return false; //force stop
            }
            echo 'Cooling down for '.$sec.' seconds'.PHP_EOL;
            sleep($sec);
        }
      );
      
      $do = true;
      $isLast = true;
      $dayToFlush = null;
      $i = 0;
      while($do) {
        $i++;
        try {
          $account_tx->send();
        } catch (\XRPLWin\XRPL\Exceptions\XWException $e) {
          // Handle errors
          $this->log('');
          $this->log('Error catched: '.$e->getMessage());
          //throw $e;
        }

        //Handles sucessful response from ledger with unsucessful message.
        //Hanldes rate limited responses.
        $is_success = $account_tx->isSuccess();

        if(!$is_success) {
          $this->log('');
          $this->log('Unsuccessful response (code 1), trying again: Ledger from '.(int)$account->l.' to '.$this->ledger_current);
          $this->log('Retrying in 3 seconds...');
          sleep(3);
        }
        else
        {
          $this->batch_current++;
       
          $txs = $account_tx->finalResult();
          
          $this->log('');
          $this->log('Starting batch of '.count($txs).' transactions: Ledger from '.(int)$account->l.' to '.$this->ledger_current);
          $bar = $this->output->createProgressBar(count($txs));
          $bar->start();
          
          $parsedDatas = []; //list of sub-method results (parsed transactions)

          # Prepare Batch instance which will hold list of queries to be executed at once to BigQuery
          if(config('xwa.database_engine') == 'bigquery')
            $batch = new BigqueryBatch;
          else
            $batch = new SqlBatch;
          
          # Parse each transaction and prepare batch execution queries
          foreach($txs as $tx) {
            $parsedDatas[] = $this->processTransaction(
              $account,
              $tx,
              $batch,
              is_object($last_inserted_tx) ? $last_inserted_tx->l  : null,
              is_object($last_inserted_tx) ? $last_inserted_tx->li : null
            );
            $bar->advance();
          }
          unset($txs);
          # Execute batch queries
          $this->log('');
          $this->log('Executing batch of queries...');
          $processed_rows = $batch->execute();
          unset($batch);

          $this->log('- DONE (processed '.$processed_rows.' rows)');
          //$this->log('Process memory usage: '.memory_get_usage_formatted());

          # Post processing results START (flush cache)
          foreach($parsedDatas as $parsedData) {
            if(!empty($parsedData)) {
              if($dayToFlush === null) {
                
                $dayToFlush = bqtimestamp_to_carbon($parsedData['t'])->format('Y-m-d');
                $this->log('Flushing day (initial) '.$dayToFlush);
                $this->flushDayCache($account->address,$dayToFlush);
              } else {
                $txDayToFlush = bqtimestamp_to_carbon($parsedData['t'])->format('Y-m-d');
                if($dayToFlush !== $txDayToFlush) {
                  $this->log('Flushing day '.$dayToFlush);
                  $this->flushDayCache($account->address,$dayToFlush);
                  unset($dayToFlush);
                  $dayToFlush = $txDayToFlush;
                }
              }
            }
          }
          # Post processing results END
          unset($parsedDatas);
          
          $bar->finish();
          unset($bar);
         
          if($account_tx = $account_tx->next()) {
            
            //update last synced ledger index to account metadata
            $account->l = $tx->tx->ledger_index;
            $account->li = $tx->meta->TransactionIndex;
            $account->lt = ripple_epoch_to_carbon($tx->tx->date)->format('Y-m-d H:i:s.uP');

            # Commit changes to $account every 10th iteration (for improved performance)
            if($i == 1 || $i % 10 === 0) {
              $this->log('Committing to account...');
              $account->save();
            }
            //continuing to next page
          }
          else
            $do = false; //no more transactions to date

          if($do) {
            if($this->batchlimit > 0 && $this->batch_current >= $this->batchlimit) {
              # batch limit reached
              $do = false; //stop
              $isLast = false; //flat it is not last run
              $this->log('Committing to account in preparation to requeue...');
              $account->save();
              $this->log('');
              $this->log('Batch limit ('.$this->batch_current.') reached, requeuing job');
              $account->sync($this->recursiveaccountqueue, true, $this->batchlimit);
              sleep(1);
            }
          }
        }
      }

      # Save last scanned ledger index
      if($isLast) {
        $account->l = $this->ledger_current;
        $account->li = 0; //-1
        $account->lt = $this->ledger_current_time;
        $this->log('Committing to account (last)...');
        $account->save();
      }
      
      //TODO start data analysis
      //$analyzed = StaticAccount::analyzeData($account);

      return Command::SUCCESS;
    }


    /**
     * Calls appropriate method.
     * @return ?array
     */
    private function processTransaction(BAccount $account, \stdClass $transaction, BatchInterface $batch, ?int $last_ledger_index = null, ?int $last_transaction_index = null/*, ?\stdClass $last_inserted_tx*/): ?array
    {
      if($transaction->meta->TransactionResult != 'tesSUCCESS')
        return null; //do not log failed transactions

      $type = $transaction->tx->TransactionType;
      //$method = 'processTransaction_'.$type;
      
      if($last_ledger_index !== null && $last_transaction_index !== null) {
        //inserted to some ledger already...

        if((int)$last_ledger_index > (int)$transaction->tx->ledger_index) {
          $this->log('Already inserted: '.$transaction->tx->hash.' (skipping) ['.$last_ledger_index.' > '.$transaction->tx->ledger_index.']');
          return null;
        }

        if((int)$last_ledger_index == (int)$transaction->tx->ledger_index) {
          if((int)$last_transaction_index >= (int)$transaction->meta->TransactionIndex) {
            $this->log('Already inserted: '.$transaction->tx->hash.' (skipping, same ledgerindex) ['.$last_ledger_index.' == '.$transaction->tx->ledger_index.']['.$last_transaction_index.' >= '.$transaction->meta->TransactionIndex.']');
            return null;
          }
        }
      }
      $this->log('Inserting: '.$transaction->tx->hash.' ('.$type.') l: '.$transaction->tx->ledger_index.' li: '.$transaction->meta->TransactionIndex);

      $_tx = $transaction->tx;
      $_tx->metaData = $transaction->meta;
      return $this->processTransactions_sub($type, $account, $_tx, $batch);

      //this is faster than call_user_func()
      //return $this->{$method}($account, $transaction, $batch);
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

      # Activations by
      if($activatedByAddress = $parser->getActivatedBy()) {
        $is_account_changed = true;
        //$this->log('');$this->log('Activation: Activated by '.$activatedByAddress. ' on hash '.$parser->getData()['hash']);
        $account->activatedBy = $activatedByAddress;
        $account->isdeleted = false; //in case it is reactivated, this will remove field on model save
      }

      //TODO ACCOUNT DELETE
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
     * Flush account maps and cache for this day.
     * @deprecated
     */
    private function flushDayCache(string $address, string $day)
    {
      /*
      $carbon = Carbon::createFromFormat('Y-m-d',$day);
      $liId = Ledgerindex::getLedgerindexIdForDay($carbon);
      if($liId) {
        //flush from cache
        $search = Map::select('page','condition','txtype')->where('address',$address)->where('ledgerindex_id',$liId)->get();
        foreach($search as $v) {
          $cache_key = 'mpr'.$address.'_'.$v->condition.'_'.$liId.'_'.$v->page.'_'.$v->txtype;
          Cache::forget($cache_key);
        }
        //flush from maps table
        Map::where('address',$address)->where('ledgerindex_id',$liId)->delete();
      }*/
    }

    private function log(string $logline)
    {
      $logline = '['.$this->debug_id.'] '.$logline;
      $this->info($logline);

      if(!$this->debug)
        return;

      Log::channel('syncjob')->info($logline);
    }

    private function logError(string $logline, ?\Throwable $e = null)
    {
      $logline = '['.$this->debug_id.'] '.$logline;
      $this->error($logline);

      //if(!$this->debug)
      //  return;

      if($e)
        Log::channel('syncjob_error')->error($e);
      else
        Log::channel('syncjob_error')->error($logline);
    }
}
