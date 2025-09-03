<?php declare(strict_types=1);

namespace App\XRPLParsers\Types;

use App\XRPLParsers\XRPLParserBase;

final class CredentialCreate extends XRPLParserBase
{
  private array $acceptedParsedTypes = ['SET','UNKNOWN','REGULARKEYSIGNER'];

  /**
   * Parses type fields and maps them to $this->data
   * @see https://xrpl.org/transaction-types.html
   * @return void
   */
  protected function parseTypeFields(): void
  {
    $parsedType = $this->data['txcontext'];
    if(!in_array($parsedType, $this->acceptedParsedTypes))
      throw new \Exception('Unhandled parsedType ['.$parsedType.'] on CredentialCreate with HASH ['.$this->data['hash'].'] and perspective ['.$this->reference_address.']');

    # Counterparty is always transaction account (creator)
    $this->data['Counterparty'] = $this->tx->Account;
    if($parsedType == 'SET')
      $this->data['In'] = true;
    else
      $this->data['In'] = false;

    # Balance changes from eventList (primary/secondary, both, one, or none)
    if(isset($this->data['eventList']['primary'])) {
      $this->data['Amount'] = $this->data['eventList']['primary']['value'];
      if($this->data['eventList']['primary']['currency'] !== 'XRP') {
        throw new \Exception('Unhandled non XRP value on CredentialCreate with HASH ['.$this->data['hash'].'] and perspective ['.$this->reference_address.']');
      }
    }

    if($parsedType == 'UNKNOWN') {
      $this->persist = false;
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
      'ax' => [],
      'ix' => [],
      'cx' => [],
      'nfts' => [],
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