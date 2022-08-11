<?php declare(strict_types=1);

namespace App\XRPLParsers;

/**
 * This class takes full Transaction object returned from XRPL and parses it.
 */
abstract class XRPLParserBase implements XRPLParserInterface
{
  protected readonly \stdClass $tx;
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
  public function __construct(\stdClass $tx, string $reference_address)
  {
    $this->tx = $tx;
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
    if(!\is_numeric($this->tx->tx->Fee))
      throw new \Exception('Fee not a number for transaction hash: '.$this->tx->tx->hash);

    $this->data['Fee'] = (int)$this->tx->tx->Fee;

    # In (bool)
    # Compare reference address to check if this is incoming or outgoing transaction
    $this->data['In'] = $this->reference_address != $this->tx->tx->Account;

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
    if(isset($this->tx->meta->AffectedNodes)) {
      foreach($this->tx->meta->AffectedNodes as $AffectedNode) {
        if(isset($AffectedNode->CreatedNode)) {
          if(isset($AffectedNode->CreatedNode->LedgerEntryType) && $AffectedNode->CreatedNode->LedgerEntryType == 'AccountRoot') {
            if($this->data['In']) {

              //Sanity check:
              if($i > 0)
                throw new \Exception('More than one activated by detected for transaction hash: '.$this->tx->tx->hash);
              
              # Reference address is activated by Counterpary address
              $this->activations['reference_activated_by'] = $this->data['Counterparty'];

              //Check consistancy:
              if($this->activations['reference_activated_by'] == $AffectedNode->CreatedNode->NewFields->Account) {
                throw new \Exception('Equal data in detectActivations (Counterparty and NewFields Account match for transaction hash: '.$this->tx->tx->hash);
              }
              $i++;
            } else {

              //Sanity check:
              if($i2 > 0)
                throw new \Exception('More than one activations detected for transaction hash: '.$this->tx->tx->hash);

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
  
}