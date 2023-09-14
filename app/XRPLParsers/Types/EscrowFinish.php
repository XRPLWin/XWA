<?php declare(strict_types=1);

namespace App\XRPLParsers\Types;

use App\XRPLParsers\XRPLParserBase;

final class EscrowFinish extends XRPLParserBase
{
  private array $acceptedParsedTypes = ['SET','UNKNOWN','SENT'];

  /**
   * Parses EscrowFinish type fields and maps them to $this->data
   * @see https://xrpl.org/transaction-types.html
   * @see 317081AF188CDD4DBE55C418F41A90EC3B959CDB3B76105E0CBE6B7A0F56C5F7 - rf1BiGeXwwQoi8Z2ueFYTEXSwuJYfV2Jpn - initiator
   * @see 317081AF188CDD4DBE55C418F41A90EC3B959CDB3B76105E0CBE6B7A0F56C5F7 - rKDvgGUsNPZxsgmoemfrgXPS2Not4co2op - reciever (xrp destination)
   * @return void
   */
  protected function parseTypeFields(): void
  {
    $parsedType = $this->data['txcontext'];
    if(!in_array($parsedType, $this->acceptedParsedTypes))
      throw new \Exception('Unhandled parsedType ['.$parsedType.'] on EscrowFinish with HASH ['.$this->data['hash'].'] and perspective ['.$this->reference_address.']');

    # Counterparty is always EscrowFinish initiator
    $this->data['Counterparty'] = $this->tx->Account;
    if($this->tx->Account == $this->reference_address)
      $this->data['In'] = false; 
    else
      $this->data['In'] = true; 

    # Balance changes from eventList (primary/secondary, both, one, or none)
    if(isset($this->data['eventList']['primary'])) {
      $this->data['Amount'] = $this->data['eventList']['primary']['value'];
      if($this->data['eventList']['primary']['currency'] !== 'XRP') {
        throw new \Exception('Unhandled non XRP value on EscrowFinish with HASH ['.$this->data['hash'].'] and perspective ['.$this->reference_address.']');
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
      'nftoffers' => [],
    ];

    if(\array_key_exists('Amount', $this->data))
      $r['a'] = $this->data['Amount'];

    if(\array_key_exists('Fee', $this->data))
      $r['fee'] = $this->data['Fee'];

    return $r;
  }
}