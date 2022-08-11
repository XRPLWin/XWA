<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use XRPLWin\XRPL\Client;
use App\Utilities\AccountLoader;
use App\Models\DAccount;
use App\Models\DTransactionPayment;
use App\Models\DTransactionActivation;
use App\Models\DTransactionTrustset;

use App\XRPLParsers\Types\Payment as PaymentParser;


//old below - todo delete
use App\Statics\XRPL;
use App\Statics\Account as StaticAccount;
use App\Models\Account;
use App\Models\Activation;

use App\Models\TransactionPayment;
use App\Models\TransactionTrustset;
use App\Models\TransactionAccountset;
use App\Models\TransactionOffer;

class XwaAccountSync extends Command
{
    /**
     * The name and signature of the console command.
     * @sample php artisan xwa:accountsync rAcct...
     * @var string
     */
    protected $signature = 'xwa:accountsync
                            {address : XRP account address}
                            {--recursiveaccountqueue : Enable to create additional queues for other accounts}';

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
     * Current ledger being scanned.
     *
     * @var bool
     */
    private int $ledger_current = -1;

    /**
     * XRPL API Client instance
     *
     * @var \XRPLWin\XRPL\Client
     */
    protected readonly Client $XRPLClient;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->XRPLClient = app(Client::class);
    }

    
    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
      //dd('test',config_static('xrpl.address_ignore.rBKPS4oLSaV2KVVuHH8EpQqMGgGefGFQs72'));
      $address = $this->argument('address');
      $this->recursiveaccountqueue = $this->option('recursiveaccountqueue'); //bool
      $this->ledger_current = $this->XRPLClient->api('ledger_current')->send()->finalResult();

      $account = AccountLoader::getOrCreate($address);

      //Test only start
      $account->l = 1;
      $account->save();
      //Test only end
      
      if( config_static('xrpl.address_ignore.'.$account->address) !== null ) {
        $this->info('History sync skipped (ignored)');
        //modify $account todo
        return 0;
      }

      $account_tx = $this->XRPLClient->api('account_tx')
          ->params([
            'account' => $account->address,
            'ledger_index' => 'current',
            'ledger_index_min' => (int)$account->l, //Ledger index this account is scanned to.
            'ledger_index_max' => $this->ledger_current,
            'binary' => false,
            'forward' => true,
            'limit' => 400, //400
          ]);

          
      $do = true;
      while($do) {
        try {
            $account_tx->send();
        } catch (\XRPLWin\XRPL\Exceptions\XWException $e) {
            // Handle errors
            $do = false;
            throw $e;
        }
        $txs = $account_tx->finalResult();
        $this->info('');
        $this->info('Starting batch of '.count($txs).' transactions');
        $bar = $this->output->createProgressBar(count($txs));
        $bar->start();

        //Do the logic here
        foreach($txs as $tx)
        {
          $this->processTransaction($account,$tx);
          $bar->advance();
        }
        $bar->finish();

        if($account_tx = $account_tx->next()) {
          //continuing to next page
        }
        else
          $do = false;
      }

      # Save last scanned ledger index
      $account->l = $this->ledger_current;
      $account->save();

      //TODO start data analysis
      //$analyzed = StaticAccount::analyzeData($account);

      return 0;
    }


    /**
     * Calls appropriate method.
     * @return Callable
     */
    private function processTransaction(DAccount $account, \stdClass $tx)
    {
      $type = $tx->tx->TransactionType;
      $method = 'processTransaction_'.$type;

      if($tx->meta->TransactionResult != 'tesSUCCESS')
      {
        //$this->info($tx['meta']['TransactionResult'].': '.\json_encode($tx));
        return null; //do not log failed transactions
      }

      //this is faster than call_user_func()
      $this->{$method}($account, $tx);
    }

    /**
    * Executed offer
    */
    private function processTransaction_OfferCreate(DAccount $account, \stdClass $tx)
    {
      return null; //TODO
      $txhash = $tx['hash'];

      $TransactionOfferCheck = TransactionTrustset::where('txhash',$txhash)->count();
      if($TransactionOfferCheck)
        return null; //nothing to do, already stored




      if(isset($meta['AffectedNodes']) && is_array($meta['AffectedNodes'])) {

        foreach($meta['AffectedNodes'] as $anode) {

          if(isset($anode['ModifiedNode']))
          {

          //  if(!isset($anode['ModifiedNode']['FinalFields']['Account'] ))
          //    dd($anode);

          //if($anode['ModifiedNode']['LedgerEntryType'] == 'AccountRoot' && !isset($anode['ModifiedNode']['FinalFields']))
          //  dd($anode);

            if($anode['ModifiedNode']['LedgerEntryType'] == 'AccountRoot' &&
                isset($anode['ModifiedNode']['FinalFields']) &&
                $anode['ModifiedNode']['FinalFields']['Account'] == $account->account) {
              $newBalance = (int)$anode['ModifiedNode']['FinalFields']['Balance']; //drops
              $oldBalance = (int)$anode['ModifiedNode']['PreviousFields']['Balance']; //drops
              $gainLossXrp = (($newBalance - $oldBalance)+$tx['Fee']) / 1000000;

              $TransactionOffer = new TransactionOffer;
              $TransactionOffer->txhash = $txhash;
              $TransactionOffer->account_id = $account->id;
              $TransactionOffer->fee = $tx['Fee']; //in drops
              $TransactionOffer->time_at = ripple_epoch_to_carbon($tx['date']);
              $TransactionOffer->amount = $gainLossXrp;
              if($TransactionOffer->amount !== 0)
                $TransactionOffer->save();

              //dd($txhash,$newBalance,$oldBalance,($newBalance - $oldBalance));
            }
            //dd($anode);
          }
        }
      }
    }

    /**
    * Payment to or from in any currency.
    * @modifies DTransaction $account
    * @return void
    */
    private function processTransaction_Payment(DAccount $account, \stdClass $tx)
    {
      $parser = new PaymentParser($tx);
      $parsedData = $parser->toStandardizedArray();
      dd($parsedData,$parser);
      //dd($parser->tx);
      $meta = $tx->meta;
      $tx = $tx->tx;
     
      $txhash = $tx->hash;

      //TODO do something with this?
      //$is_partialPayment = false;
      //if(isset($tx->Flags) && xrpl_has_flag($tx->Flags,131072)) {
      //  $is_partialPayment = true;
      //}

      $destination_tag = isset($tx->DestinationTag) ? $tx->DestinationTag:null;
      $source_tag = isset($tx->SourceTag) ? $tx->SourceTag:null;

      # Is this transaction IN or OUT
      $in = ($account->address != $tx->Account) ? true:false;

      # Counterparty
      $cp = (!$in) ? $tx->Destination:$tx->Account;

      $model = new DTransactionPayment();
      $model->PK = $account->address.'-'.DTransactionPayment::TYPE;
      $model->SK = $tx->ledger_index;
      $model->in = $in;
      if($in) $model->r = $cp;
      if(is_int($destination_tag))  $model->dt = $destination_tag;
      if(is_int($source_tag))       $model->st = $source_tag;

      $model->fe = $tx->Fee; //in drops
      $model->t = $tx->date; //ripple_epoch_to_carbon($tx->date)->toIso8601String();

     
      if(is_object($tx->Amount)) //it is payment currency
      {
        //dd($tx->Amount);
        //it is payment in currency
        //if( !$is_partialPayment )
        //  $amount = $tx['Amount']['value'];
        //else {
          $amount = $meta->delivered_amount->value;
          //$this->info('[T] '.$meta['delivered_amount']['value'].' - '.$tx['Amount']['value'].' HSH: '.$txhash);
        //}
        //if($meta['delivered_amount']['currency'] != $tx['Amount']['currency']) dd($meta['delivered_amount']);

        $model->a = $amount; //base-10 representation of double number
        
        $model->i = $tx->Amount->issuer;
        $model->c = $meta->delivered_amount->currency;
      }
      else //it is payment in XRP
      {
        //if(!$is_partialPayment)
        //  $amount = $tx['Amount'];
        //else {
          $amount = $meta->delivered_amount;
          //$this->info($meta['delivered_amount'].' - '.$tx['Amount'].' HSH: '.$txhash);
        //}
        $model->a = drops_to_xrp($amount);
      }


      $model->save();

      # Activations by payment

      if(isset($meta->AffectedNodes)) {
        foreach($meta->AffectedNodes as $AffectedNode) {
          if(isset($AffectedNode->CreatedNode)) {
            if(isset($AffectedNode->CreatedNode->LedgerEntryType) && $AffectedNode->CreatedNode->LedgerEntryType ==  'AccountRoot')
            {
              $this->info('');
              if($in) {
                # This address is activated by $cp address
                $this->info('Activation: Activated by '.$cp);
                $account->by = $AffectedNode->CreatedNode->NewFields->Account;
                $account->save();
              } else {
                $this->info('Activation: '.$AffectedNode->CreatedNode->NewFields->Account);
                $Activation = new DTransactionActivation;
                $Activation->PK = $account->address.'-'.DTransactionActivation::TYPE;
                $Activation->SK = $tx->ledger_index;
                $Activation->r = $AffectedNode->CreatedNode->NewFields->Account;
                $Activation->save();
              }

              if($this->recursiveaccountqueue)
              {
                if(!$in)
                {
                  //parent is created this account, queue parent
                  $this->info('Queued account: '.$AffectedNode->CreatedNode->NewFields->Account);
                  //$source_account->sync(true);
                  $newAccount = AccountLoader::getOrCreate($AffectedNode->CreatedNode->NewFields->Account);
                  $newAccount->sync(true);
                }
              }

            }
            //dd($TransactionPayment,$account,$AffectedNode['CreatedNode']);
            break;
          }
        }
      }


      return;


      //$model = AccountLoader::getOrCreatePayment($account->address, $tx->ledger_index);



      #################### FUN BEGINS ##################### OLD BELOW
      // Check existing tx
      $TransactionPaymentCheck = TransactionPayment::where('txhash',$txhash)->count();
      if($TransactionPaymentCheck)
        return null; //nothing to do, already stored

      $is_partialPayment = false;
      if(isset($tx['Flags']) && xrpl_has_flag($tx['Flags'],131072)) {
        $is_partialPayment = true;
      }

      $destination_tag = isset($tx['DestinationTag']) ? $tx['DestinationTag']:null;
      $source_tag = isset($tx['SourceTag']) ? $tx['SourceTag']:null;
      //$this->info($tx['DestinationTag']);
      //dd($tx);


      if($account->account == $tx['Account'])
      {
        $source_account = $account;
        $destination_account = StaticAccount::GetOrCreate($tx['Destination'],$this->ledger_current);
      }
      else
      {
        $source_account = StaticAccount::GetOrCreate($tx['Account'],$this->ledger_current);
        $destination_account = $account;
      }

      $TransactionPayment = new TransactionPayment;
      $TransactionPayment->txhash = $txhash;
      $TransactionPayment->source_account_id = $source_account->id;
      $TransactionPayment->destination_account_id = $destination_account->id;
      $TransactionPayment->destination_tag = $destination_tag;
      $TransactionPayment->source_tag = $source_tag;
      $TransactionPayment->fee = $tx['Fee']; //in drops
      $TransactionPayment->time_at = ripple_epoch_to_carbon($tx['date']);
      if(is_array($tx['Amount']))
      {
        //it is payment in currency
        //if( !$is_partialPayment )
        //  $amount = $tx['Amount']['value'];
        //else {
          $amount = $meta['delivered_amount']['value'];
          //$this->info('[T] '.$meta['delivered_amount']['value'].' - '.$tx['Amount']['value'].' HSH: '.$txhash);
        //}
        //if($meta['delivered_amount']['currency'] != $tx['Amount']['currency']) dd($meta['delivered_amount']);

        $TransactionPayment->amount = $amount; //base-10 auto-converted to double in DB
        $TransactionPayment->issuer_account_id = StaticAccount::GetOrCreate($tx['Amount']['issuer'],$this->ledger_current)->id;
        $TransactionPayment->currency = $meta['delivered_amount']['currency'];
      }
      else
      {
        //it is payment in XRP

        //if(!$is_partialPayment)
        //  $amount = $tx['Amount'];
        //else {
          $amount = $meta['delivered_amount'];
          //$this->info($meta['delivered_amount'].' - '.$tx['Amount'].' HSH: '.$txhash);
        //}

        $TransactionPayment->amount = drops_to_xrp($amount);
        $TransactionPayment->issuer_account_id = null;
        $TransactionPayment->currency = '';
      }

      $TransactionPayment->is_issuing = false;

      //Check if this transaction is issuing of tokens
      if($TransactionPayment->source_account_id == $TransactionPayment->issuer_account_id)
      {
        if($TransactionPayment->source_account_id == $TransactionPayment->destination_account_id)
        {
          //this is token burn
        }
        else
        {
          $TransactionPayment->is_issuing = true;
        }

      }


      $TransactionPayment->save();

      //if($txhash == 'E5C12811A01BADFA8418D89156981284C1A282BFEDFB464379154DB521E17916') dd($tx,$meta,$TransactionPayment);

      //Check account activation
      if(isset($meta['AffectedNodes'])) {

        foreach($meta['AffectedNodes'] as $AffectedNode)
        {
          if(isset($AffectedNode['CreatedNode']))
          {
            /*
            $AffectedNode['CreatedNode'] = array:3 [
              "LedgerEntryType" => "AccountRoot"
              "LedgerIndex" => "6E705A13D395F1CD1E5E69C88D7F03C67588ECF551E37F64CF7A9EABDEF78606"
              "NewFields" => array:3 [
                "Account" => "r3rdCdrFdKG1YqMgJjXDZqeStGayzxQzWy"
                "Balance" => "26250000"
                "Sequence" => 1
              ]
            ]
            */
            if(isset($AffectedNode['CreatedNode']['LedgerEntryType']) && $AffectedNode['CreatedNode']['LedgerEntryType'] ==  'AccountRoot')
            {

              // save account activation $TransactionPayment->source_account_id created $AffectedNode['CreatedNode'].NewFields.Account ($TransactionPayment->destination_account_id)

              $this->info('Activation: '.$source_account->account.' created '.$AffectedNode['CreatedNode']['NewFields']['Account']);
              $Activation = new Activation;
              $Activation->tx_payment_id = $TransactionPayment->id;
              $Activation->source_account_id = $TransactionPayment->source_account_id;
              $Activation->destination_account_id = $TransactionPayment->destination_account_id;
              $Activation->save();

              //

              if($this->recursiveaccountqueue)
              {
                if($account->account != $source_account->account)
                {
                  //parent is created this account, queue parent
                  $this->info('### Queued account: '.$source_account->account);
                  $source_account->sync(true);
                }
                /*dd($tx,$destination_account,$account->account);
                if($destination_account->account == $AffectedNode['CreatedNode']['NewFields']['Account'])
                {
                  $this->info('### Queued account: '.$destination_account->account);
                  $destination_account->sync(true);
                }
                else
                {
                  dd($AffectedNode['CreatedNode']['NewFields']['Account'], $destination_account);
                }*/

                //queue fullsync of account: $TransactionPayment->destination_account_id
                /*Artisan::queue('xrpl:accountsync', [
                    'address' => $AffectedNode['CreatedNode']['NewFields']['Account'],
                    '--recursiveaccountqueue' => true,
                    '--queue' => 'default'
                ]);*/
                //dd($AffectedNode['CreatedNode']['NewFields']['Account']);
              }

            }
            //dd($TransactionPayment,$account,$AffectedNode['CreatedNode']);
            break;
          }
        }
      }
      //dd($meta['AffectedNodes']);


      //$this->info($tx['Destination'].' '.$tx['Account']);
      return;
    }




    private function processTransaction_OfferCancel(DAccount $account, \stdClass $tx)
    {
      return;
    }

    private function processTransaction_TrustSet(DAccount $account, \stdClass $tx)
    {
      
      $meta = $tx->meta;
      $tx = $tx->tx;
      $txhash = $tx->hash;

    
      # Is this transaction IN or OUT
      $in = ($account->address != $tx->Account) ? true:false;
      //dd($tx);
      # Counterparty
      //$cp = (!$in) ? $tx->Account:null;

      

      $model = new DTransactionTrustset();
      $model->PK = $account->address.'-'.DTransactionTrustset::TYPE;
      $model->SK = $tx->ledger_index;
      $model->in = $in;
      //if($cp) $model->r = $cp;
      $model->fe = $tx->Fee; //in drops
      $model->t = $tx->date; //ripple_epoch_to_carbon($tx->date)->toIso8601String(); //todo sa


      if($tx->LimitAmount->value == 0)
        $model->s = false; //state deleted
      else
        $model->s = true; //state created
      

      if($tx->LimitAmount->value == 0)
        $model->state = 0; //deleted
      else
        $model->state = 1; //created
      
      $model->c = $tx->LimitAmount->currency;
      $model->a = $tx->LimitAmount->value;
      $model->cp = $tx->LimitAmount->issuer;

      $model->save();
      return;

      $TransactionTrustset->issuer_account_id = StaticAccount::GetOrCreate($tx['LimitAmount']['issuer'],$this->ledger_current)->id;
      $TransactionTrustset->currency = $tx['LimitAmount']['currency'];
      $TransactionTrustset->amount = $tx['LimitAmount']['value'];
      $TransactionTrustset->time_at = ripple_epoch_to_carbon($tx['date']);

      $TransactionTrustset->save();



      dd($txhash);
      return;

      $TransactionTrustsetCheck = TransactionTrustset::where('txhash',$txhash)->count();
      if($TransactionTrustsetCheck)
        return null; //nothing to do, already stored

      $TransactionTrustset = new TransactionTrustset;
      $TransactionTrustset->txhash = $txhash;

      if($account->account == $tx['Account'])
        $TransactionTrustset->source_account_id = $account->id; //reuse it
      else
        $TransactionTrustset->source_account_id = StaticAccount::GetOrCreate($tx['Account'],$this->ledger_current)->id;

      $TransactionTrustset->fee = $tx['Fee']; //in drops

      if($tx['LimitAmount']['value'] == 0)
        $TransactionTrustset->state = 0; //deleted
      else
        $TransactionTrustset->state = 1; //created

      $TransactionTrustset->issuer_account_id = StaticAccount::GetOrCreate($tx['LimitAmount']['issuer'],$this->ledger_current)->id;
      $TransactionTrustset->currency = $tx['LimitAmount']['currency'];
      $TransactionTrustset->amount = $tx['LimitAmount']['value'];
      $TransactionTrustset->time_at = ripple_epoch_to_carbon($tx['date']);

      $TransactionTrustset->save();

      return null;
    }

    private function processTransaction_AccountSet(DAccount $account, \stdClass $tx)
    {
      return; //not used yet

      $txhash = $tx['hash'];
      $TransactionAccountsetCheck = TransactionAccountset::where('txhash',$txhash)->count();
      if($TransactionAccountsetCheck)
        return; //nothing to do, already stored

      $TransactionAccountset = new TransactionAccountset;
      $TransactionAccountset->txhash = $txhash;

      if($account->account == $tx['Account'])
        $TransactionAccountset->source_account_id = $account->id; //reuse it
      else
        $TransactionAccountset->source_account_id = StaticAccount::GetOrCreate($tx['Account'],$this->ledger_current)->id;

      $TransactionAccountset->fee = $tx['Fee']; //in drops
      $TransactionAccountset->time_at = ripple_epoch_to_carbon($tx['date']);

      //$TransactionAccountset->set_flag = $tx['SetFlag'];

      $TransactionAccountset->save();



      return;
    }

    private function processTransaction_AccountDelete(DAccount $account, \stdClass $tx)
    {
      return;
    }

    private function processTransaction_SetRegularKey(DAccount $account, \stdClass $tx)
    {
      return;
    }

    private function processTransaction_SignerListSet(DAccount $account, \stdClass $tx)
    {
      return;
    }

    private function processTransaction_EscrowCreate(DAccount $account, \stdClass $tx)
    {
      return;
    }

    private function processTransaction_EscrowFinish(DAccount $account, \stdClass $tx)
    {
      return;
    }

    private function processTransaction_EscrowCancel(DAccount $account, \stdClass $tx)
    {
      return;
    }

    private function processTransaction_PaymentChannelCreate(DAccount $account, \stdClass $tx)
    {
      return;
    }

    private function processTransaction_PaymentChannelFund(DAccount $account, \stdClass $tx)
    {
      return;
    }

    private function processTransaction_PaymentChannelClaim(DAccount $account, \stdClass $tx)
    {
      return;
    }

    private function processTransaction_DepositPreauth(DAccount $account, \stdClass $tx)
    {
      return;
    }
}
