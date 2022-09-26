<?php declare(strict_types=1);

namespace App\XRPLParsers;

/**
 * This class takes full Transaction object returned from XRPL and parses it.
 */
abstract class XRPLParserBase implements XRPLParserInterface
{
  protected readonly \stdClass $tx;
  protected readonly \stdClass $meta;
  protected array $data = [];
  protected readonly string $reference_address;
  protected array $activations = [
    'reference_activated_by' => null,
    'reference_activated' => null,
  ];

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
    $this->parseCommonFields();
    $this->parseTypeFields();
  }

  /**
   * Returns standardized array of relevant data for storing to Dynamo database.
   * key => value one dimensional array which correlates to column => value in DyDb.
   * @return array
   */
  public function toDArray(): array
  {
    throw new \Exception('toDArray Not implemented in final class');
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
    
    # Fee (int)
    if(!\is_numeric($this->tx->Fee))
      throw new \Exception('Fee not a number for transaction hash: '.$this->tx->hash);

    $this->data['Fee'] = (int)$this->tx->Fee;

    # In (bool)
    # Compare reference address to check if this is incoming or outgoing transaction
    $this->data['In'] = $this->reference_address != $this->tx->Account;

    # TransactionIndex (int)
    if(!is_int($this->meta->TransactionIndex))
      throw new \Exception('TransactionIndex not integer for transaction hash: '.$this->tx->hash);
    $this->data['TransactionIndex'] = $this->meta->TransactionIndex;

    # XRPL Epoch time
    $this->data['Date'] = $this->tx->date;
    if(!isset($this->tx->date))
      throw new \Exception('date not found for transaction hash: '.$this->tx->hash);

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
   * Returns SK (Sort Key), <ledger_indes>.<transaction_index> float number.
   * This is used as Sort key in DynamoDB table.
   * @return float
   */
  public function SK(): float
  {
    $ti = (string)$this->getTransactionIndex();
    if(\strlen($ti) >= 4)
      throw new \Exception('Transaction index of 4 or more characters detected: '.$ti. ' Db adjust required');
    return (float)($this->tx->ledger_index.'.'.\str_pad($ti,3,'0',STR_PAD_LEFT));
  }

  /**
   * This will detect activations from meta field. It is know that Payment type activates account.
   * If in future some other transaction type creates new account it can be easily adopted.
   * Fills $this->activations array.
   * @throws Exception
   * @return self
   */
  public function detectActivations(): self
  {
    $i = $i2 = 0;
    if(isset($this->meta->AffectedNodes)) {
      foreach($this->meta->AffectedNodes as $AffectedNode) {
        if(isset($AffectedNode->CreatedNode)) {
          if(isset($AffectedNode->CreatedNode->LedgerEntryType) && $AffectedNode->CreatedNode->LedgerEntryType == 'AccountRoot') {
            if($this->data['In']) {

              //Sanity check:
              if($i > 0)
                throw new \Exception('More than one activated by detected for transaction hash: '.$this->tx->hash);
              
              # Reference address is activated by Counterpary address
              $this->activations['reference_activated_by'] = $this->data['Counterparty'];

              //Check consistancy:
              if($this->activations['reference_activated_by'] == $AffectedNode->CreatedNode->NewFields->Account) {
                throw new \Exception('Equal data in detectActivations (Counterparty and NewFields Account match for transaction hash: '.$this->tx->hash);
              }
              $i++;
            } else {

              //Sanity check:
              if($i2 > 0)
                throw new \Exception('More than one activations detected for transaction hash: '.$this->tx->hash);

              $this->activations['reference_activated'] = $AffectedNode->CreatedNode->NewFields->Account;

              $i2++;
            }
          }
        }
      }
    }
    return $this;
  }

  public function getActivatedBy(): ?string
  {
    return $this->activations['reference_activated_by'];
  }

  public function getActivated(): ?string
  {
    return $this->activations['reference_activated'];
  }

  /**
   * Get balance changes. This is used to build aggregated value over time.
   * This funciton returns array of balance changes, XRP +-Value
   * Also returns token currency +-Value
   * -Fee
   * @return array ['Fee' => NUM, 'Value' => NUM, Currency => string 'XRP' or array [issuer, currency]]
   */
  public function balanceChanges(): array
  {
    throw new \Exception('balanceChanges Not implemented in final class');
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
}