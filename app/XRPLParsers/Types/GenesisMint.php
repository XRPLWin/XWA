<?php declare(strict_types=1);

namespace App\XRPLParsers\Types;

use App\XRPLParsers\XRPLParserBase;

final class GenesisMint extends XRPLParserBase
{
  private array $acceptedParsedTypes = ['SET','REGULARKEYSIGNER','UNKNOWN'];

  /**
   * Parses GenesisMint type fields and maps them to $this->data
   * @see C47FA26FD959F1F5981F3647212FDDCFBA11A644B68D4D63BE3559D34413B4A1 xahau testnet
   * @return void
   */
  protected function parseTypeFields(): void
  {
    $parsedType = $this->data['txcontext'];
    if(!in_array($parsedType, $this->acceptedParsedTypes))
      throw new \Exception('Unhandled parsedType ['.$parsedType.'] on GenesisMint with HASH ['.$this->data['hash'].'] and perspective ['.$this->reference_address.']');

    if($parsedType == 'REGULARKEYSIGNER') {
      $this->persist = false;
    }

    $this->data['Counterparty'] = $this->tx->Account; //always initiator even for initiator, since there are multiple counterparties
    if($this->reference_address != $this->tx->Account)
      $this->data['In'] = true;

    # Balance changes from eventList (primary, or none)
    if(isset($this->data['eventList']['primary'])) {
      $this->data['Amount'] = $this->data['eventList']['primary']['value'];
    }
  }

  /**
   * Returns standardized array of relevant data for storing to Dynamo database.
   * key => value one dimensional array which correlates to column => value in DyDb.
   * @return array
   */
  public function toBArray(): array
  {
    $r = [
      't' => ripple_epoch_to_carbon((int)$this->data['Date'])->format('Y-m-d H:i:s.uP'),
      'l' => $this->data['LedgerIndex'],
      'li' => $this->data['TransactionIndex'],
      'isin' => $this->data['In'],
      'r' => (string)$this->data['Counterparty'],
      'h' => (string)$this->data['hash'],
      'offers' => [],
      'nftoffers' => [],
      'hooks' => $this->data['hooks'],
    ];

    if(\array_key_exists('Amount', $this->data))
      $r['a'] = $this->data['Amount'];
    
    if(\array_key_exists('Fee', $this->data))
      $r['fee'] = $this->data['Fee'];

    return $r;
  }
}