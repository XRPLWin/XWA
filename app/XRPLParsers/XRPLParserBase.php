<?php declare(strict_types=1);

namespace App\XRPLParsers;

use XRPLWin\XRPLTxMutatationParser\TxMutationParser;

/**
 * This class takes full Transaction object returned from XRPL and parses it.
 */
abstract class XRPLParserBase implements XRPLParserInterface
{
  protected readonly \stdClass $tx;
  protected readonly \stdClass $meta;
  protected array $data = [];
  protected array $parsedData = [];
  protected readonly string $reference_address;

  /**
   * Flag to indicate if this parsed transaction needs to be persisted in reference_address transaction store.
   * If false this tx will not store to db for reference_address perspective.
   * Example: token exchange trough issuer, issuer will not have this record in db, 
   * since its balance did not change. +party1 -party2 => issuer is unchanged.
   */
  protected bool $persist = true;

  protected array $activations = [
    'reference_activated_by' => null,
    'reference_activated' => [],
  ];

  protected array $hooks = [];

  /**
   * Eg. Payment for DTransactionPayment, or Payment_BalanceChange for DTransactionPayment_BalanceChange ...
   */
  protected string $transaction_type_class;

  /**
   * Constructor
   * @param \stdClass $tx
   * @param string $reference_address
   */
  public function __construct(\stdClass $tx, \stdClass $meta, string $reference_address)
  {
    $this->tx = $tx;
    $this->meta = $meta;
    $this->reference_address = $reference_address;
    

    //Normalize Account field:
    if(isset($this->tx->Account) && $this->tx->Account === "") {
      $this->tx->Account = 'rrrrrrrrrrrrrrrrrrrrrhoLvTp'; //ACCOUNT_ZERO (happens in UNLReport transaction)
    }

    /**
     * Modifies $this->data
     * @createsKey array eventList [primary, ?secondary]
     * @createsKey string txcontext
     */
    $this->parseMutations();
    
    /**
     * Modifies $this->data
     * @createsKey int TransactionIndex
     * @createsKey int Date - XRPL Epoch time
     * @fills $this->transaction_type_class
     */
    $this->parseCommonFields();
    
    /**
     * Modifies $this->data
     * @createsKey bool In
     * @createsKey int Fee (optional)
     * Modifies $this->persist
     */
    $this->parseType();
    
    /**
     * Modifies $this->data
     * @fills (maybe) $this->transaction_type_class
     * @see underlying classes
     */
    $this->parseTypeFields();
    
    /**
     * @fills (maybe) $this->activations
     */
    $this->detectActivations();

    $this->detectHooks();
  }

  /**
   * Returns standardized array of relevant data for storing to Dynamo database.
   * key => value one dimensional array which correlates to column => value in DyDb.
   * @return array
   */
  public function toBArray(): array
  {
    throw new \Exception('toBArray Not implemented in final class');
  }

  /**
   * Parses and decides which type of transaction is this.
   * For Payment it can be sub-type of payment depending on reference account.
   */
  protected function parseType()
  {
    # In (bool)
    # Compare reference address to check if this is incoming or outgoing transaction
    # Note: when reference_address is only participant of this tx then In will be false, 
    # in that case this can be overriden via parseType() via balance changes state.
    //$this->data['In'] = $this->reference_address != $this->tx->Account;

    $this->data['In'] = null;

    if(isset($this->tx->Destination) && $this->tx->Destination == $this->reference_address)
      $this->data['In'] = true;
    elseif( $this->tx->Account == $this->reference_address )
      $this->data['In'] = false;

    if($this->data['In'] === null) { //unable determine direction via Account or Destination fields
      $this->data['In'] = false;

      if($this->data['txcontext'] === 'RECEIVED' || $this->data['txcontext'] == 'ACCEPT') {
        //If context is RECEIVED or ACCEPT then this is incomming transaction for reference account
        $this->data['In'] = true;
      }
    }

    # Fee (int) (optional)
    # Did reference account pay for fee? If yes then include Fee
    if(isset($this->parsedData['self']['feePayer']) && $this->parsedData['self']['feePayer'] === true)
      $this->data['Fee'] = (int)$this->tx->Fee;
  }

  /**
   * Parses specific type fields as defined in XRPL documentation.
   * @see https://xrpl.org/transaction-types.html
   * @return void
   */
  protected function parseTypeFields(): void
  {
    throw new \Exception('parseTypeFields Not implemented in final class');
  }

  /**
   * Parses common fields as defined in XRPL documentation.
   * @see https://xrpl.org/transaction-common-fields.html
   * @throws Exception
   * @return void
   */
  protected function parseCommonFields(): void
  {
    # LedgerIndex
    $this->data['LedgerIndex'] = $this->tx->ledger_index;

    # TransactionIndex (int)
    if(!is_int($this->meta->TransactionIndex))
      throw new \Exception('TransactionIndex not integer for transaction hash: '.$this->tx->hash);
    $this->data['TransactionIndex'] = $this->meta->TransactionIndex;

    # XRPL Epoch time
    $this->data['Date'] = $this->tx->date;
    if(!isset($this->tx->date))
      throw new \Exception('date not found for transaction hash: '.$this->tx->hash);

    # Hash
    $this->data['hash'] = $this->tx->hash;
    
    # this value can be overriden in ->parseTypeFields()
    $this->transaction_type_class = $this->tx->TransactionType;
  }

  /**
   * @fills $this->parserData
   * @modifies $this->data
   * @return void
   */
  protected function parseMutations(): void
  {
    $tx = $this->tx;
    $tx->meta = $this->meta;
    $mp = new TxMutationParser($this->reference_address, $tx);
    $this->parsedData = $mp->result();

    $this->data['eventList'] = $this->parsedData['eventList'];
    $this->data['balanceChanges'] = $this->parsedData['self']['balanceChanges'];
    $this->data['txcontext'] = $this->parsedData['type'];
  }

  /**
   * Getter for TransactionIndex
   * @return int
   */
  public function getTransactionIndex(): int
  {
    return (int)$this->data['TransactionIndex'];
  }

  /**
   * Returns SK (Sort Key), <ledger_index>.<transaction_index> float number.
   * This is used as check for last inserted transaction to database.
   * @return float
   */
  /*public function SK(): float
  {
    $ti = (string)$this->getTransactionIndex();
    if(\strlen($ti) >= 4)
      throw new \Exception('Transaction index of 4 or more characters detected: '.$ti. ' DB adjust required');
    return (float)($this->tx->ledger_index.'.'.\str_pad($ti,3,'0',STR_PAD_LEFT));
  }*/

  /**
   * This will detect activations from meta field.
   * It is known that Payment type activates single account. Also Offers can activate, ammendments and other types.
   * Fills $this->activations array.
   * @throws Exception
   * @return self
   */
  public function detectActivations(): self
  {
    //$i = $i2 = 0;
    if(isset($this->meta->AffectedNodes)) {
      foreach($this->meta->AffectedNodes as $AffectedNode) {
        if(isset($AffectedNode->CreatedNode)) {
          if(isset($AffectedNode->CreatedNode->LedgerEntryType) && $AffectedNode->CreatedNode->LedgerEntryType == 'AccountRoot') {
            if($this->data['In'] && $this->activations['reference_activated_by'] === null) {

              # Reference address is activated by Counterpary address
              $this->activations['reference_activated_by'] = $this->data['Counterparty'];

              //Check consistancy:
              if($this->activations['reference_activated_by'] == $AffectedNode->CreatedNode->NewFields->Account) {
                throw new \Exception('Equal data in detectActivations (Counterparty and NewFields Account match for transaction hash: '.$this->tx->hash);
              }
              //$i++;
            } else if(!$this->data['In']) {
              $this->activations['reference_activated'][] = $AffectedNode->CreatedNode->NewFields->Account;
              //$i2++;
            }
          }
        }
      }
    }
    return $this;
  }

  /**
   * Detects executed (HookExecutions[]/HookExecution->HookAccount) - testnet: 82B1673BE11752C045EE70BD2A223B7AE7A2BB56EF296BF6B6F739B6D79ED8E1
   * Removed
   * Created Hook (HookDefinition) (first unique hook intruduced to ledger (unowned))
   * Installed hooks for reference account, via HookDefinition or Hook (created)
   * @modifies $this->data['hooks']
   * @return void
   */
  public function detectHooks(): self
  {
    $hooks = [
      'executed' => [],
      'installed' => [],
      'removed' => []
    ];
    if(isset($this->meta->AffectedNodes)) {

    }
    return $this;
  }

  public function getActivatedBy(): ?string
  {
    return $this->activations['reference_activated_by'];
  }

  public function getActivated(): array
  {
    return $this->activations['reference_activated'];
  }
  
  public function getTx()
  {
    return $this->tx;
  }

  public function getMeta()
  {
    return $this->meta;
  }

  public function getData()
  {
    return $this->data;
  }

  public function getDataField($key)
  {
    return $this->data[$key];
  }

  public function getTransactionTypeClass()
  {
    return $this->transaction_type_class;
  }

  public function getPersist(): bool
  {
    return $this->persist;
  }
}