<?php declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

use XRPLWin\XRPL\Client;
use App\Utilities\AccountLoader;
use App\Utilities\Ledger;
use App\Models\BAccount;
use App\Models\BTransactionActivation;
use App\XRPLParsers\Parser;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use App\Repository\Batch;
use App\Repository\TransactionsRepository;

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
     * @var bool
     */
    protected int $batchlimit = 0;

    /**
     * Current batch being executed.
     *
     * @var bool
     */
    protected int $batch_current = 0;

    /**
     * Current ledger being scanned.
     *
     * @var bool
     */
    private int     $ledger_current = -1;
    private readonly string  $ledger_current_time;

    /**
     * Each new run ledger index that will be queried is last accessed -1.
     * This means few transaction might be already stored in database. To prevent
     * duplication of transactions last hash is extracted from transactions table,
     * then compared when that transactions in reached in pulled new transactions list
     * this will became true and inserting can continue.
     * This will be set as false if atleast one transaction is already inserted in db.
     */
    private bool $transaction_flow_valid = true;

    /**
     * XRPL API Client instance
     *
     * @var \XRPLWin\XRPL\Client
     */
    protected Client $XRPLClient;

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
      $this->XRPLClient = app(Client::class);

      //dd('test',config_static('xrpl.address_ignore.rBKPS4oLSaV2KVVuHH8EpQqMGgGefGFQs72'));
      $address = $this->argument('address');
      $this->recursiveaccountqueue = $this->option('recursiveaccountqueue'); //bool
      $this->batchlimit = (int)$this->option('limit'); //int
      
      //$this->ledger_current = $this->XRPLClient->api('ledger_current')->send()->finalResult();
      
      $this->ledger_current = Ledger::current();
      $this->ledger_current_time = \Carbon\Carbon::now()->format('Y-m-d H:i:s.uP'); //not 100% on point due to Ledger::current() can be stale up to 10 seconds
     
      //clear account cache
      Cache::forget('daccount:'.$address);
      Cache::forget('daccount_fti:'.$address);
      
      $account = AccountLoader::getOrCreate($address);
      
      //dd($account);
      //dd($account);
      //If this account is issuer (by checking obligations) set t field to 1.
      //if($account->checkIsIssuer())
      //  $account->t = 1;
      //else
      //  unset($account->t); //this will remove field on model save

      //dd($account);
      
      //Test only start (comment this)
      //$account->l = 73806933;$account->save();exit;
      //$account->l = 74139050;$account->save();exit;
      //Test only end

      //$this->ledger_current = 66055480;
     // $account->l = 66055470;
      
      /*if( config_static('xrpl.address_ignore.'.$account->address) !== null ) {
        $this->info('History sync skipped (ignored)');
        //modify $account todo
        return 0;
      }*/

      $ledger_index_min = (int)$account->l;

      # Find last inserted transaction in transactions table for check to prevent duplicates
      $last_inserted_tx = TransactionsRepository::fetchOne('address = """'.$address.'"""', 'h','t DESC'); //{t,xwatype,h}
      if($last_inserted_tx !== null) {
        //At least one transaction exists, query one less ledger index just in case something wont be missed
        $ledger_index_min--;
        $this->transaction_flow_valid = false;
      }

      $account_tx = $this->XRPLClient->api('account_tx')
          ->params([
            'account' => $account->address,
            //'ledger_index' => 'current',
            'ledger_index_min' => $ledger_index_min, //Ledger index this account is scanned to.
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
          $this->info('');
          $this->info('Error catched: '.$e->getMessage());
          //throw $e;
        }

        //Handles sucessful response from ledger with unsucessful message.
        //Hanldes rate limited responses.
        $is_success = $account_tx->isSuccess();

        if(!$is_success) {
          $this->info('');
          $this->info('Unsuccessful response (code 1), trying again: Ledger from '.(int)$account->l.' to '.$this->ledger_current);
          $this->info('Retrying in 3 seconds...');
          sleep(3);
        }
        else
        {
          $this->batch_current++;
          //dd($account_tx);
          
          $txs = $account_tx->finalResult();
          
          $this->info('');
          $this->info('Starting batch of '.count($txs).' transactions: Ledger from '.(int)$account->l.' to '.$this->ledger_current);
          $bar = $this->output->createProgressBar(count($txs));
          $bar->start();
          
          $parsedDatas = []; //list of sub-method results (parsed transactions)

          # Prepare Batch instance which will hold list of queries to be executed at once to BigQuery
          $batch = new Batch;

          
          # Parse each transaction and prepare batch execution queries
          foreach($txs as $tx) {
            $parsedDatas[] = $this->processTransaction($account,$tx,$batch,$last_inserted_tx);
            $bar->advance();
          }
          unset($txs);

          # Execute batch queries
          $this->info('');
          $this->info('Executing batch of queries...');
          $processed_rows = $batch->execute();
          unset($batch);

          $this->info('- DONE (processed '.$processed_rows.' rows)');
          //$this->info('Process memory usage: '.memory_get_usage_formatted());

          # Post processing results (flush cache)
          foreach($parsedDatas as $parsedData) {
            if(!empty($parsedData)) {
              if($dayToFlush === null) {
                
                $dayToFlush = bqtimestamp_to_carbon($parsedData['t'])->format('Y-m-d');
                $this->info('Flushing day (initial) '.$dayToFlush);
                $this->flushDayCache($account->address,$dayToFlush);
              } else {
                $txDayToFlush = bqtimestamp_to_carbon($parsedData['t'])->format('Y-m-d');
                if($dayToFlush !== $txDayToFlush) {
                  $this->info('Flushing day '.$dayToFlush);
                  $this->flushDayCache($account->address,$dayToFlush);
                  unset($dayToFlush);
                  $dayToFlush = $txDayToFlush;
                }
              }
            }
          }
          unset($parsedDatas);
          
          $bar->finish();
          unset($bar);
         
          if($account_tx = $account_tx->next()) {
            
            //update last synced ledger index to account metadata
            $account->l = $tx->tx->ledger_index;
            $account->lt = ripple_epoch_to_carbon($tx->tx->date)->format('Y-m-d H:i:s.uP');

            # Commit changes to $account every 20th iteration (for improved performace)
            if($i % 20 === 0) {
              $this->info('Committing to account...');
              $account->save();
            }
            //continuing to next page
          }
          else
            $do = false;

          if($do) {
            if($this->batchlimit > 0 && $this->batch_current >= $this->batchlimit) {
              # batch limit reached
              $do = false; //stop
              $isLast = false; //flat it is not last run
              $this->info('Committing to account in preparation to requeue...');
              $account->save();
              $this->info('');
              $this->info('Batch limit ('.$this->batch_current.') reached, requeuing job');
              $account->sync($this->recursiveaccountqueue, true, $this->batchlimit);
              sleep(1);
            }
          }
        }
      }

      # Save last scanned ledger index
      if($isLast) {
        $account->l = $this->ledger_current;
        $account->lt = $this->ledger_current_time;
        $this->info('Committing to account (last)...');
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
    private function processTransaction(BAccount $account, \stdClass $transaction, Batch $batch, ?\stdClass $last_inserted_tx): ?array
    {
      if($transaction->meta->TransactionResult != 'tesSUCCESS')
        return null; //do not log failed transactions

      $type = $transaction->tx->TransactionType;
      $method = 'processTransaction_'.$type;
      
      if($this->transaction_flow_valid === false) {
        // this transaction hash might be already inserted
        if($transaction->tx->hash == $last_inserted_tx->h) {
          // this is latest inserted transaction into database, flag flow as valid end exit
          // Note: there can be more than one transaction with same hash for current account, all other transactions (autogenerated, eg Activation)
          //       are coupled and inserted in the same time, we are free to skip to next transaction hash.
          $this->transaction_flow_valid = true;
          $this->info('Already inserted: '.$transaction->tx->hash.' (skipping, latest)');
        } else {
          $this->info('Already inserted: '.$transaction->tx->hash.' (skipping)');
        }
        return null;
      }
      
      //this is faster than call_user_func()
      return $this->{$method}($account, $transaction, $batch);
    }

    /**
     * Created/Executed offer
     * @return array
     */
    private function processTransaction_OfferCreate(BAccount $account, \stdClass $transaction, Batch $batch): array
    {
      /** @var \App\XRPLParsers\Types\OfferCreate */
      $parser = Parser::get($transaction->tx, $transaction->meta, $account->address);
      $parsedData = $parser->toBArray();

      $TransactionClassName = '\\App\\Models\\BTransaction'.$parser->getTransactionTypeClass();
      $model = new $TransactionClassName($parsedData);
      $model->address = $account->address;
      $model->xwatype = $TransactionClassName::TYPE;
      $batch->queueModelChanges($model);
      //$model->save();
      return $parsedData;
    }

    /**
     * Canceled offer
     * @return array
     */
    private function processTransaction_OfferCancel(BAccount $account, \stdClass $transaction, Batch $batch): array
    {
      /** @var \App\XRPLParsers\Types\OfferCancel */
      $parser = Parser::get($transaction->tx, $transaction->meta, $account->address);
      $parsedData = $parser->toBArray();
      
      $TransactionClassName = '\\App\\Models\\BTransaction'.$parser->getTransactionTypeClass();
      $model = new $TransactionClassName($parsedData);
      $model->address = $account->address;
      $model->xwatype = $TransactionClassName::TYPE;
      $batch->queueModelChanges($model);
      //$model->save();
      return $parsedData;
      return []; //TODO
    }

    /**
    * Payment to or from in any currency.
    * @return array
    */
    private function processTransaction_Payment(BAccount $account, \stdClass $transaction, Batch $batch): array
    {
      /** @var \App\XRPLParsers\Types\Payment */
      $parser = Parser::get($transaction->tx, $transaction->meta, $account->address);
      $parsedData = $parser->toBArray();

      $TransactionClassName = '\\App\\Models\\BTransaction'.$parser->getTransactionTypeClass();
      $model = new $TransactionClassName($parsedData);
      $model->address = $account->address;
      $model->xwatype = $TransactionClassName::TYPE;
      $batch->queueModelChanges($model);
      //$model->save();
      
      # Activations by payment:
      $parser->detectActivations();

      if($activatedAddress = $parser->getActivated()) {
        //$this->info('');
        //$this->info('Activation: '.$activatedAddress. ' on index '.$parser->SK());
        $Activation = new BTransactionActivation([
          'address' => $account->address,//.'-'.BTransactionActivation::TYPE,
          'xwatype' => BTransactionActivation::TYPE,
          'h' => $parsedData['h'],
          't' => ripple_epoch_to_carbon((int)$parser->getDataField('Date'))->format('Y-m-d H:i:s.uP'),
          'r' => $activatedAddress,
          'isin' => true,
        ]);
        $batch->queueModelChanges($Activation);
        //$Activation->save();
      }

      if($activatedByAddress = $parser->getActivatedBy()) {
        $this->info('');
        $this->info('Activation: Activated by '.$activatedByAddress. ' on hash '.$parser->getData()['hash']);
        $account->activatedBy = $activatedByAddress;
        $account->isdeleted = false; //in case it is reactivated, this will remove field on model save
        $batch->queueModelChanges($account);
        //$account->save();

        if($this->recursiveaccountqueue)
        {
          //parent created this account, queue parent
          $this->info('Queued account: '.$activatedByAddress. ' on hash '.$parser->getData()['hash']);
          //$source_account->sync(true);
          $newAccount = AccountLoader::getOrCreate($activatedByAddress);
          $newAccount->sync(
            $this->recursiveaccountqueue,
            false,
            $this->batchlimit
          );
        }
      }
      return $parsedData;
    }

    /**
     * TrustSet (set or unset)
     * @return array
     */
    private function processTransaction_TrustSet(BAccount $account, \stdClass $transaction, Batch $batch): array
    {
      /** @var \App\XRPLParsers\Types\TrustSet */
      $parser = Parser::get($transaction->tx, $transaction->meta, $account->address);

      $parsedData = $parser->toBArray();

      $TransactionClassName = '\\App\\Models\\BTransaction'.$parser->getTransactionTypeClass();
      $model = new $TransactionClassName($parsedData);
      $model->address = $account->address;
      $model->xwatype = $TransactionClassName::TYPE;
      $batch->queueModelChanges($model);
      //$model->save();
      return $parsedData;
    }

    /**
     * AccountSet
     * @return array
     */
    private function processTransaction_AccountSet(BAccount $account, \stdClass $transaction, Batch $batch): array
    {
      /** @var \App\XRPLParsers\Types\AccountSet */

      $parser = Parser::get($transaction->tx, $transaction->meta, $account->address);

      $parsedData = $parser->toBArray();

      $TransactionClassName = '\\App\\Models\\BTransaction'.$parser->getTransactionTypeClass();
      $model = new $TransactionClassName($parsedData);
      $model->address = $account->address;
      $model->xwatype = $TransactionClassName::TYPE;
      $batch->queueModelChanges($model);
      //$model->save();
      return $parsedData;
    }

    /**
     * AccountDelete
     * @return array
     */
    private function processTransaction_AccountDelete(BAccount $account, \stdClass $transaction, Batch $batch): array
    {
      /** @var \App\XRPLParsers\Types\AccountDelete */
      $parser = Parser::get($transaction->tx, $transaction->meta, $account->address);

      $parsedData = $parser->toBArray();

      $TransactionClassName = '\\App\\Models\\BTransaction'.$parser->getTransactionTypeClass();
      $model = new $TransactionClassName($parsedData);
      $model->address = $account->address;
      $model->xwatype = $TransactionClassName::TYPE;
      $batch->queueModelChanges($model);
      //$model->save();
      
      if(!$parsedData['isin']) {
        //outgoing, this is deleted account, flag account deleted
        $this->info('');
        $this->info('Deleted');
        $account->isdeleted = true;
        $batch->queueModelChanges($account);
        //$account->save();
      }

      return $parsedData;
    }

    /**
     * SetRegularKey
     * ex. rf1BiGeXwwQoi8Z2ueFYTEXSwuJYfV2Jpn
     * @return array
     */
    private function processTransaction_SetRegularKey(BAccount $account, \stdClass $transaction, Batch $batch): array
    {
      /** @var \App\XRPLParsers\Types\SetRegularKey */
      $parser = Parser::get($transaction->tx, $transaction->meta, $account->address);
      
      $parsedData = $parser->toBArray();
      
      $TransactionClassName = '\\App\\Models\\BTransaction'.$parser->getTransactionTypeClass();
      
      $model = new $TransactionClassName($parsedData);
      $model->address = $account->address;
      $model->xwatype = $TransactionClassName::TYPE;
      $batch->queueModelChanges($model);
      //$model->save();
      return $parsedData;
    }

    /**
     * SignerListSet
     * ex. 09A9C86BF20695735AB03620EB1C32606635AC3DA0B70282F37C674FC889EFE7
     * @return array
     */
    private function processTransaction_SignerListSet(BAccount $account, \stdClass $transaction, Batch $batch): array
    {
      /** @var \App\XRPLParsers\Types\SignerListSet */
      $parser = Parser::get($transaction->tx, $transaction->meta, $account->address);
      
      $parsedData = $parser->toBArray();
      
      $TransactionClassName = '\\App\\Models\\BTransaction'.$parser->getTransactionTypeClass();
      
      $model = new $TransactionClassName($parsedData);
      $model->address = $account->address;
      $model->xwatype = $TransactionClassName::TYPE;
      $batch->queueModelChanges($model);
      //$model->save();
      return $parsedData;
    }

    /**
     * CheckCreate
     * ex. 4E0AA11CBDD1760DE95B68DF2ABBE75C9698CEB548BEA9789053FCB3EBD444FB
     * @return array
     */
    private function processTransaction_CheckCreate(BAccount $account, \stdClass $transaction, Batch $batch): array
    {
      /** @var \App\XRPLParsers\Types\CheckCreate */
      $parser = Parser::get($transaction->tx, $transaction->meta, $account->address);
      
      $parsedData = $parser->toBArray();
      
      $TransactionClassName = '\\App\\Models\\BTransaction'.$parser->getTransactionTypeClass();
      
      $model = new $TransactionClassName($parsedData);
      $model->address = $account->address;
      $model->xwatype = $TransactionClassName::TYPE;
      $batch->queueModelChanges($model);
      //$model->save();
      return $parsedData;
    }

    /**
     * CheckCash
     * ex. 67B71B13601CDA5402920691841AC27A156463678E106FABD45357175F9FF406
     * @return array
     */
    private function processTransaction_CheckCash(BAccount $account, \stdClass $transaction, Batch $batch): array
    {
      /** @var \App\XRPLParsers\Types\CheckCash */
      $parser = Parser::get($transaction->tx, $transaction->meta, $account->address);
      
      $parsedData = $parser->toBArray();
      
      $TransactionClassName = '\\App\\Models\\BTransaction'.$parser->getTransactionTypeClass();
      
      $model = new $TransactionClassName($parsedData);
      $model->address = $account->address;
      $model->xwatype = $TransactionClassName::TYPE;
      $batch->queueModelChanges($model);
      //$model->save();
      return $parsedData;
    }

    /**
     * CheckCancel
     * ex. D3328000315C6DCEC1426E4E549288E3672752385D86A40D56856DBD10382953
     * @return array
     */
    private function processTransaction_CheckCancel(BAccount $account, \stdClass $transaction, Batch $batch): array
    {
      /** @var \App\XRPLParsers\Types\CheckCancel */
      $parser = Parser::get($transaction->tx, $transaction->meta, $account->address);
      
      $parsedData = $parser->toBArray();
      
      $TransactionClassName = '\\App\\Models\\BTransaction'.$parser->getTransactionTypeClass();
      
      $model = new $TransactionClassName($parsedData);
      $model->address = $account->address;
      $model->xwatype = $TransactionClassName::TYPE;
      $batch->queueModelChanges($model);
      //$model->save();
      return $parsedData;
    }

    /**
     * EscrowCreate
     * ex. C44F2EB84196B9AD820313DBEBA6316A15C9A2D35787579ED172B87A30131DA7
     * @return array
     */
    private function processTransaction_EscrowCreate(BAccount $account, \stdClass $transaction, Batch $batch): array
    {
      /** @var \App\XRPLParsers\Types\EscrowCreate */
      $parser = Parser::get($transaction->tx, $transaction->meta, $account->address);
      
      $parsedData = $parser->toBArray();
      
      $TransactionClassName = '\\App\\Models\\BTransaction'.$parser->getTransactionTypeClass();
      
      $model = new $TransactionClassName($parsedData);
      $model->address = $account->address;
      $model->xwatype = $TransactionClassName::TYPE;
      $batch->queueModelChanges($model);
      //$model->save();
      return $parsedData;
    }

    /**
     * EscrowFinish
     * ex. 317081AF188CDD4DBE55C418F41A90EC3B959CDB3B76105E0CBE6B7A0F56C5F7
     * @return array
     */
    private function processTransaction_EscrowFinish(BAccount $account, \stdClass $transaction, Batch $batch): array
    {
      /** @var \App\XRPLParsers\Types\EscrowFinish */
      $parser = Parser::get($transaction->tx, $transaction->meta, $account->address);
      
      $parsedData = $parser->toBArray();
      
      $TransactionClassName = '\\App\\Models\\BTransaction'.$parser->getTransactionTypeClass();
      
      $model = new $TransactionClassName($parsedData);
      $model->address = $account->address;
      $model->xwatype = $TransactionClassName::TYPE;
      $batch->queueModelChanges($model);
      //$model->save();
      return $parsedData;
    }

    /**
     * EscrowCancel
     * ex. B24B9D7843F99AED7FB8A3929151D0CCF656459AE40178B77C9D44CED64E839B
     * @return array
     */
    private function processTransaction_EscrowCancel(BAccount $account, \stdClass $transaction, Batch $batch): array
    {
      /** @var \App\XRPLParsers\Types\EscrowCancel */
      $parser = Parser::get($transaction->tx, $transaction->meta, $account->address);
      
      $parsedData = $parser->toBArray();
      
      $TransactionClassName = '\\App\\Models\\BTransaction'.$parser->getTransactionTypeClass();
      
      $model = new $TransactionClassName($parsedData);
      $model->address = $account->address;
      $model->xwatype = $TransactionClassName::TYPE;
      $batch->queueModelChanges($model);
      //$model->save();
      return $parsedData;
    }

    /**
     * PaymentChannelCreate
     * @return array
     */
    private function processTransaction_PaymentChannelCreate(BAccount $account, \stdClass $transaction, Batch $batch): array
    {
      /** @var \App\XRPLParsers\Types\PaymentChannelCreate */
      $parser = Parser::get($transaction->tx, $transaction->meta, $account->address);
      
      $parsedData = $parser->toBArray();
      
      $TransactionClassName = '\\App\\Models\\BTransaction'.$parser->getTransactionTypeClass();
      $model = new $TransactionClassName($parsedData);
      $model->address = $account->address;
      $model->xwatype = $TransactionClassName::TYPE;
      $batch->queueModelChanges($model);
      //$model->save();
      return $parsedData;
   
    }

    private function processTransaction_PaymentChannelFund(BAccount $account, \stdClass $transaction, Batch $batch): array
    {
      /** @var \App\XRPLParsers\Types\PaymentChannelFund */
      $parser = Parser::get($transaction->tx, $transaction->meta, $account->address);
      
      $parsedData = $parser->toBArray();
      
      $TransactionClassName = '\\App\\Models\\BTransaction'.$parser->getTransactionTypeClass();
      $model = new $TransactionClassName($parsedData);
      $model->address = $account->address;
      $model->xwatype = $TransactionClassName::TYPE;
      $batch->queueModelChanges($model);
      //$model->save();
      return $parsedData;
    }

    private function processTransaction_PaymentChannelClaim(BAccount $account, \stdClass $transaction, Batch $batch): array
    {
      /** @var \App\XRPLParsers\Types\PaymentChannelClaim */
      $parser = Parser::get($transaction->tx, $transaction->meta, $account->address);
      
      $parsedData = $parser->toBArray();
      
      $TransactionClassName = '\\App\\Models\\BTransaction'.$parser->getTransactionTypeClass();
      $model = new $TransactionClassName($parsedData);
      $model->address = $account->address;
      $model->xwatype = $TransactionClassName::TYPE;
      $batch->queueModelChanges($model);
      //$model->save();
      return $parsedData;
    }

    /**
     * DepositPreauth
     * ex. CB1BF910C93D050254C049E9003DA1A265C107E0C8DE4A7CFF55FADFD39D5656
     * @return array
     */
    private function processTransaction_DepositPreauth(BAccount $account, \stdClass $transaction, Batch $batch): array
    {
      /** @var \App\XRPLParsers\Types\DepositPreauth */
      $parser = Parser::get($transaction->tx, $transaction->meta, $account->address);
      
      $parsedData = $parser->toBArray();
      
      $TransactionClassName = '\\App\\Models\\BTransaction'.$parser->getTransactionTypeClass();
      $model = new $TransactionClassName($parsedData);
      $model->address = $account->address;
      $model->xwatype = $TransactionClassName::TYPE;
      $batch->queueModelChanges($model);
      //$model->save();
      return $parsedData;
    }

    /**
     * DepositPreauth
     * ex. 7458B6FD22827B3C141CDC88F1F0C72658C9B5D2E40961E45AF6CD31DECC0C29 - rf1BiGeXwwQoi8Z2ueFYTEXSwuJYfV2Jpn
     * @return array
     */
    private function processTransaction_TicketCreate(BAccount $account, \stdClass $transaction, Batch $batch): array
    {
      /** @var \App\XRPLParsers\Types\TicketCreate */
      $parser = Parser::get($transaction->tx, $transaction->meta, $account->address);
      
      $parsedData = $parser->toBArray();
      
      $TransactionClassName = '\\App\\Models\\BTransaction'.$parser->getTransactionTypeClass();
      $model = new $TransactionClassName($parsedData);
      $model->address = $account->address;
      $model->xwatype = $TransactionClassName::TYPE;
      $batch->queueModelChanges($model);
      //$model->save();
      return $parsedData;
    }

    private function processTransaction_NFTokenAcceptOffer(BAccount $account, \stdClass $transaction, Batch $batch): array
    {
      dd('todo NFTokenAcceptOffer');
      return [];
    }

    private function processTransaction_NFTokenBurn(BAccount $account, \stdClass $transaction, Batch $batch): array
    {
      dd('todo NFTokenBurn');
      return [];
    }

    private function processTransaction_NFTokenCancelOffer(BAccount $account, \stdClass $transaction, Batch $batch): array
    {
      dd('todo NFTokenCancelOffer');
      return [];
    }

    private function processTransaction_NFTokenCreateOffer(BAccount $account, \stdClass $transaction, Batch $batch): array
    {
      dd('todo NFTokenCreateOffer');
      return [];
    }

    private function processTransaction_NFTokenMint(BAccount $account, \stdClass $transaction, Batch $batch): array
    {
      dd('todo NFTokenMint');
      return [];
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
}
