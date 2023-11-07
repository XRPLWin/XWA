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
          $this->log('Job has previously timed out, adjusting tracker...');
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
              $this->logError($e->getMessage());
              throw $e;
            }
            //Empty queue:
            $transactions = [];
          }
          
        }
      }

      if(count($transactions) > 0) {
        //Process queued transactions (what is left over):
        try {
          $this->processTransactions($transactions);
        } catch (\Throwable $e) {
          $this->log($e->getMessage());
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
            $accounts[$participant] = AccountLoader::getOrCreate($participant);
          
          $parsedDatas[] = $this->{$method}($accounts[$participant], $transaction, $batch);

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
     * Canceled offer
     * @return array
     */
    private function processTransaction_OfferCancel(BAccount $account, \stdClass $transaction, BatchInterface $batch): array
    {
      /** @var \App\XRPLParsers\Types\OfferCancel */
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
    * Payment to or from in any currency.
    * @return array
    */
    private function processTransaction_Payment(BAccount $account, \stdClass $transaction, BatchInterface $batch): array
    {
      /** @var \App\XRPLParsers\Types\Payment */
      $parser = Parser::get($transaction, $transaction->metaData, $account->address);
      $parsedData = $parser->toBArray();

      if($parser->getPersist() === false)
        return $parsedData;

      $TransactionClassName = '\\App\\Models\\BTransaction'.$parser->getTransactionTypeClass();
      //dd($TransactionClassName);
      $model = new $TransactionClassName($parsedData);
      $model->address = $account->address;
      $model->xwatype = $TransactionClassName::TYPE;
      
      $batch->queueModelChanges($model);
      //$model->save();
      
      # Activations by payment:
      $parser->detectActivations();
      dd('Todo handle multiactivations');
      if($activatedAddress = $parser->getActivated()) {
        //$this->log('');
        //$this->log('Activation: '.$activatedAddress. ' on index '.$parser->SK());
        $Activation = new BTransactionActivation([
          'address' => $account->address,//.'-'.BTransactionActivation::TYPE,
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
        //$Activation->save();
      }

      if($activatedByAddress = $parser->getActivatedBy()) {
        //$this->log('');
        //$this->log('Activation: Activated by '.$activatedByAddress. ' on hash '.$parser->getData()['hash']);
        $account->activatedBy = $activatedByAddress;
        $account->isdeleted = false; //in case it is reactivated, this will remove field on model save
        $batch->queueModelChanges($account);
        //$account->save();
      }
      return $parsedData;
    }

    /**
     * TrustSet (set or unset)
     * @return array
     */
    private function processTransaction_TrustSet(BAccount $account, \stdClass $transaction, BatchInterface $batch): array
    {
      /** @var \App\XRPLParsers\Types\TrustSet */
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
     * AccountSet
     * @return array
     */
    private function processTransaction_AccountSet(BAccount $account, \stdClass $transaction, BatchInterface $batch): array
    {
      /** @var \App\XRPLParsers\Types\AccountSet */

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
     * AccountDelete
     * @return array
     */
    private function processTransaction_AccountDelete(BAccount $account, \stdClass $transaction, BatchInterface $batch): array
    {
      /** @var \App\XRPLParsers\Types\AccountDelete */
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
      
      if(!$parsedData['isin']) {
        //outgoing, this is deleted account, flag account deleted
        //$this->log('');
        //$this->log('Deleted');
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
    private function processTransaction_SetRegularKey(BAccount $account, \stdClass $transaction, BatchInterface $batch): array
    {
      /** @var \App\XRPLParsers\Types\SetRegularKey */
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
     * SignerListSet
     * ex. 09A9C86BF20695735AB03620EB1C32606635AC3DA0B70282F37C674FC889EFE7
     * @return array
     */
    private function processTransaction_SignerListSet(BAccount $account, \stdClass $transaction, BatchInterface $batch): array
    {
      /** @var \App\XRPLParsers\Types\SignerListSet */
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
     * CheckCreate
     * ex. 4E0AA11CBDD1760DE95B68DF2ABBE75C9698CEB548BEA9789053FCB3EBD444FB
     * @return array
     */
    private function processTransaction_CheckCreate(BAccount $account, \stdClass $transaction, BatchInterface $batch): array
    {
      /** @var \App\XRPLParsers\Types\CheckCreate */
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
     * CheckCash
     * ex. 67B71B13601CDA5402920691841AC27A156463678E106FABD45357175F9FF406
     * @return array
     */
    private function processTransaction_CheckCash(BAccount $account, \stdClass $transaction, BatchInterface $batch): array
    {
      /** @var \App\XRPLParsers\Types\CheckCash */
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
     * CheckCancel
     * ex. D3328000315C6DCEC1426E4E549288E3672752385D86A40D56856DBD10382953
     * @return array
     */
    private function processTransaction_CheckCancel(BAccount $account, \stdClass $transaction, BatchInterface $batch): array
    {
      /** @var \App\XRPLParsers\Types\CheckCancel */
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
     * EscrowCreate
     * ex. C44F2EB84196B9AD820313DBEBA6316A15C9A2D35787579ED172B87A30131DA7
     * @return array
     */
    private function processTransaction_EscrowCreate(BAccount $account, \stdClass $transaction, BatchInterface $batch): array
    {
      /** @var \App\XRPLParsers\Types\EscrowCreate */
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
     * EscrowFinish
     * ex. 317081AF188CDD4DBE55C418F41A90EC3B959CDB3B76105E0CBE6B7A0F56C5F7
     * @return array
     */
    private function processTransaction_EscrowFinish(BAccount $account, \stdClass $transaction, BatchInterface $batch): array
    {
      /** @var \App\XRPLParsers\Types\EscrowFinish */
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
     * EscrowCancel
     * ex. B24B9D7843F99AED7FB8A3929151D0CCF656459AE40178B77C9D44CED64E839B
     * @return array
     */
    private function processTransaction_EscrowCancel(BAccount $account, \stdClass $transaction, BatchInterface $batch): array
    {
      /** @var \App\XRPLParsers\Types\EscrowCancel */
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
     * PaymentChannelCreate
     * @return array
     */
    private function processTransaction_PaymentChannelCreate(BAccount $account, \stdClass $transaction, BatchInterface $batch): array
    {
      /** @var \App\XRPLParsers\Types\PaymentChannelCreate */
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
     * PaymentChannelFund
     * @return array
     */
    private function processTransaction_PaymentChannelFund(BAccount $account, \stdClass $transaction, BatchInterface $batch): array
    {
      /** @var \App\XRPLParsers\Types\PaymentChannelFund */
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
     * PaymentChannelClaim
     * @return array
     */
    private function processTransaction_PaymentChannelClaim(BAccount $account, \stdClass $transaction, BatchInterface $batch): array
    {
      /** @var \App\XRPLParsers\Types\PaymentChannelClaim */
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
     * DepositPreauth
     * ex. CB1BF910C93D050254C049E9003DA1A265C107E0C8DE4A7CFF55FADFD39D5656
     * @return array
     */
    private function processTransaction_DepositPreauth(BAccount $account, \stdClass $transaction, BatchInterface $batch): array
    {
      /** @var \App\XRPLParsers\Types\DepositPreauth */
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
     * TicketCreate
     * ex. 7458B6FD22827B3C141CDC88F1F0C72658C9B5D2E40961E45AF6CD31DECC0C29 - rf1BiGeXwwQoi8Z2ueFYTEXSwuJYfV2Jpn
     * @return array
     */
    private function processTransaction_TicketCreate(BAccount $account, \stdClass $transaction, BatchInterface $batch): array
    {
      /** @var \App\XRPLParsers\Types\TicketCreate */
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
     * NFTokenCreateOffer
     * ex. 36E42A76F46711318C27247E4DA3AE962E6976EC6F44917F15E37EC5A9DA2352
     * @return array
     */
    private function processTransaction_NFTokenCreateOffer(BAccount $account, \stdClass $transaction, BatchInterface $batch): array
    {
      /** @var \App\XRPLParsers\Types\NFTokenCreateOffer */
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
     * NFTokenAcceptOffer
     * ex. 9D9BC8AA88DC3ED64F7A7CBD1F7676438751E01A3A94CA8A606022EC2CAE3BE5 - rBKXVs4NBYLVBvaeCBVFsdJSYBjoHhf1yY
     * @return array
     */
    private function processTransaction_NFTokenAcceptOffer(BAccount $account, \stdClass $transaction, BatchInterface $batch): array
    {
      /** @var \App\XRPLParsers\Types\NFTokenAcceptOffer */
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
     * NFTokenCancelOffer
     * ex. DF3137FA90575D6F75EE6F5B9D51DFA9722AF7CBB18B19ADBBB8E20D15CFD238 - rBgyjCQLVdSHwKVAhCZNTbmDsFHqLkzZdw
     * @return array
     */
    private function processTransaction_NFTokenCancelOffer(BAccount $account, \stdClass $transaction, BatchInterface $batch): array
    {
      /** @var \App\XRPLParsers\Types\NFTokenCancelOffer */
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
     * NFTokenMint
     * ex. 97F547EEDD12D5FC8F555B359FB7098A26D09C9E4E8B7FD9CEC1560ABEBF4341 - rKgR5LMCU1opzENpP7Qz7bRsQB4MKPpJb4
     * @return array
     */
    private function processTransaction_NFTokenMint(BAccount $account, \stdClass $transaction, BatchInterface $batch): array
    {
      /** @var \App\XRPLParsers\Types\NFTokenMint */
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
     * NFTokenBurn
     * ex. 97F547EEDD12D5FC8F555B359FB7098A26D09C9E4E8B7FD9CEC1560ABEBF4341 - rKgR5LMCU1opzENpP7Qz7bRsQB4MKPpJb4
     * @return array
     */
    private function processTransaction_NFTokenBurn(BAccount $account, \stdClass $transaction, BatchInterface $batch): array
    {
      /** @var \App\XRPLParsers\Types\NFTokenBurn */
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

    # HOOKS start

    /**
     * SetHook
     * @return array
     */
    private function processTransaction_SetHook(BAccount $account, \stdClass $transaction, BatchInterface $batch): array
    {
      /** @var \App\XRPLParsers\Types\SetHook */
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
     * Invoke
     * @return array
     */
    private function processTransaction_Invoke(BAccount $account, \stdClass $transaction, BatchInterface $batch): array
    {
      /** @var \App\XRPLParsers\Types\Invoke */
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

    # HOOKS end

    /**
     * URITokenBuy
     * @return array
     */
    private function processTransaction_URITokenBuy(BAccount $account, \stdClass $transaction, BatchInterface $batch): array
    {
      /** @var \App\XRPLParsers\Types\URITokenBuy */
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
     * EnableAmendment
     * @return array
     */
    private function processTransaction_EnableAmendment(BAccount $account, \stdClass $transaction, BatchInterface $batch): array
    {
      /** @var \App\XRPLParsers\Types\EnableAmendment */
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

    private function logError(string $logline)
    {
      $logline = '['.$this->debug_id.'] '.$logline;
      $this->error($logline);

      if(!$this->debug)
        return;

      Log::channel('syncjobcontinuous_error')->info($logline);
    }
}
