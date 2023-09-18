<?php declare(strict_types=1);

namespace App\XRPLParsers\Types;

use App\XRPLParsers\XRPLParserBase;

final class SetRegularKey extends XRPLParserBase
{
  private array $acceptedParsedTypes = ['SET','SENT','UNKNOWN'];

  /**
   * Parses TrustSet type fields and maps them to $this->data
   * @see https://xrpl.org/transaction-types.html
   * @see 7905E05FB8D6C696553CD5EFF0CBF749E4717E62C90E71E1BDD6CA87733F1065 - zero fee with SENT context
   * @see 6AA6F6EAAAB56E65F7F738A9A2A8A7525439D65BA990E9BA08F6F4B1C2D349B4
   * @return void
   */
  protected function parseTypeFields(): void
  {
    $parsedType = $this->data['txcontext'];
    if(!in_array($parsedType, $this->acceptedParsedTypes))
      throw new \Exception('Unhandled parsedType ['.$parsedType.'] on SetRegularKey with HASH ['.$this->data['hash'].'] and perspective ['.$this->reference_address.']');

    # Counterparty is always transaction account (creator)
    $this->data['Counterparty'] = $this->tx->Account;
    $this->data['In'] = true;
    if(isset($this->tx->RegularKey) && $this->tx->RegularKey == $this->reference_address)
    $this->data['In'] = false;

    # Balance changes from eventList (primary/secondary, both, one, or none)
    if(isset($this->data['eventList']['primary'])) {
      $this->data['Amount'] = $this->data['eventList']['primary']['value'];
      if($this->data['eventList']['primary']['currency'] !== 'XRP') {
        throw new \Exception('Unhandled non XRP value on SetRegularKey with HASH ['.$this->data['hash'].'] and perspective ['.$this->reference_address.']');
      }
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
    ];

    if(\array_key_exists('Amount', $this->data))
      $r['a'] = $this->data['Amount'];

    if(\array_key_exists('Fee', $this->data))
      $r['fee'] = $this->data['Fee'];

    return $r;
  }
}