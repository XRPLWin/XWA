<?php declare(strict_types=1);

namespace App\XRPLParsers\Types;

use App\XRPLParsers\XRPLParserBase;

final class CheckCancel extends XRPLParserBase
{
  private array $acceptedParsedTypes = ['SET'];
  /**
   * Parses TrustSet type fields and maps them to $this->data
   * @see https://xrpl.org/transaction-types.html
   * @see D3328000315C6DCEC1426E4E549288E3672752385D86A40D56856DBD10382953 - r4uZkneFvKrbNVJgtB3tBJooMBryzySnEa
   * @return void
   */
  protected function parseTypeFields(): void
  {
    $parsedType = $this->data['txcontext'];
    if(!in_array($parsedType, $this->acceptedParsedTypes))
      throw new \Exception('Unhandled parsedType ['.$parsedType.'] on CheckCancel with HASH ['.$this->data['hash'].'] and perspective ['.$this->reference_address.']');
    
    # Counterparty is always transaction account (creator)
    $this->data['Counterparty'] = $this->tx->Account;

    # CheckCancel is always OUT
    $this->data['In'] = false;

    # Balance changes from eventList (primary/secondary, both, one, or none)
    if(isset($this->data['eventList']['primary'])) {
      $this->data['Amount'] = $this->data['eventList']['primary']['value'];
      if($this->data['eventList']['primary']['currency'] !== 'XRP') {
        $this->data['Issuer'] = $this->data['eventList']['primary']['counterparty'];
        $this->data['Currency'] = $this->data['eventList']['primary']['currency'];
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
    ];

    # Standard fields:

    if(\array_key_exists('Amount', $this->data))
      $r['a'] = $this->data['Amount'];
    
    if(\array_key_exists('Fee', $this->data))
      $r['fee'] = $this->data['Fee'];

    return $r;
  }
}