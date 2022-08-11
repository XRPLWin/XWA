<?php declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

use XRPLWin\XRPL\Client;
use App\Utilities\AccountLoader;
use App\Models\DAccount;
use App\Models\DTransactionPayment;
use App\Models\DTransactionActivation;
use App\Models\DTransactionTrustset;
use App\XRPLParsers\Parser;


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
      //$this->ledger_current = 73618219; //# this (comment)
      //$account->l = 1; //1
      //$account->save();
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
        return null; //do not log failed transactions

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
      /** @var \App\XRPLParsers\Types\Payment */
      $parser = Parser::get($tx, $account->address);

      $parsedData = $parser->toDArray();

      $model = new DTransactionPayment();
      $model->PK = $account->address.'-'.DTransactionPayment::TYPE;
      $model->SK = $parser->SK();
      foreach($parsedData as $key => $value) {
        $model->{$key} = $value;
      }
      $model->save();
      
      
      # Activations by payment:
      $parser->detectActivations();

      if($activatedAddress = $parser->getActivated()) {
        $this->info('');
        $this->info('Activation: '.$activatedAddress. 'on index '.$tx->tx->ledger_index);
        $Activation = new DTransactionActivation;
        $Activation->PK = $account->address.'-'.DTransactionActivation::TYPE;
        $Activation->SK = $tx->tx->ledger_index;
        $Activation->r = $activatedAddress;
        $Activation->save();
      }

      if($activatedByAddress = $parser->getActivatedBy()) {
        $this->info('');
        $this->info('Activation: Activated by '.$activatedByAddress. 'on index '.$tx->tx->ledger_index);
        $account->by = $activatedByAddress;
        $account->save();

        if($this->recursiveaccountqueue)
        {
          //parent created this account, queue parent
          $this->info('Queued account: '.$activatedByAddress. 'on index '.$tx->tx->ledger_index);
          //$source_account->sync(true);
          $newAccount = AccountLoader::getOrCreate($activatedByAddress);
          $newAccount->sync(true);
        }
      }
    }

    private function processTransaction_OfferCancel(DAccount $account, \stdClass $tx)
    {
      return;
    }

    private function processTransaction_TrustSet(DAccount $account, \stdClass $tx)
    {
      /** @var \App\XRPLParsers\Types\TrustSet */
      $parser = Parser::get($tx, $account->address);

      $parsedData = $parser->toDArray();

      $model = new DTransactionTrustset();
      $model->PK = $account->address.'-'.DTransactionTrustset::TYPE;
      $model->SK = $parser->SK();
      foreach($parsedData as $key => $value) {
        $model->{$key} = $value;
      }
      $model->save();
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
