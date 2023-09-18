<?php declare(strict_types=1);

namespace App\XRPLParsers\Types;

use App\XRPLParsers\XRPLParserBase;

final class TicketCreate extends XRPLParserBase
{
  private array $acceptedParsedTypes = ['SET'];

  /**
   * Parses TicketCreate type fields and maps them to $this->data
   * @see https://xrpl.org/transaction-types.html
   * @see 7458B6FD22827B3C141CDC88F1F0C72658C9B5D2E40961E45AF6CD31DECC0C29 - rf1BiGeXwwQoi8Z2ueFYTEXSwuJYfV2Jpn
   * @return void
   */
  protected function parseTypeFields(): void
  {
    $parsedType = $this->data['txcontext'];
    if(!in_array($parsedType, $this->acceptedParsedTypes))
      throw new \Exception('Unhandled parsedType ['.$parsedType.'] on TicketCreate with HASH ['.$this->data['hash'].'] and perspective ['.$this->reference_address.']');

    $this->data['Counterparty'] = $this->tx->Account;
    $this->data['In'] = true;

    # Balance changes from eventList (primary/secondary, both, one, or none)
    if(isset($this->data['eventList']['primary'])) {
      $this->data['Amount'] = $this->data['eventList']['primary']['value'];
      if($this->data['eventList']['primary']['currency'] !== 'XRP') {
        throw new \Exception('Unhandled non XRP value on TicketCreate with HASH ['.$this->data['hash'].']');
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