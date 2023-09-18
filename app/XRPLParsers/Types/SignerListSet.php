<?php declare(strict_types=1);

namespace App\XRPLParsers\Types;

use App\XRPLParsers\XRPLParserBase;

final class SignerListSet extends XRPLParserBase
{
  private array $acceptedParsedTypes = ['SET','UNKNOWN','REGULARKEYSIGNER'];

  /**
   * Parses TrustSet type fields and maps them to $this->data
   * @see https://xrpl.org/transaction-types.html
   * @see 09A9C86BF20695735AB03620EB1C32606635AC3DA0B70282F37C674FC889EFE7 - rf1BiGeXwwQoi8Z2ueFYTEXSwuJYfV2Jpn - signer list creator (SET)
   * @see 09A9C86BF20695735AB03620EB1C32606635AC3DA0B70282F37C674FC889EFE7 - ra5nK24KXen9AHvsdFTKHSANinZseWnPcX - signer list participant (UNKNOWN)
   * @see EB7CFF7BDD0E14FD57ECD4EC30CDEB87B6F51B97EE47073CFF6960254DD0A46D - rhssY88ZGmw82A1wXnxxG6ayQgpH3WMnJg - REGULARKEYSIGNER
   * @return void
   */
  protected function parseTypeFields(): void
  {
    $parsedType = $this->data['txcontext'];
    if(!in_array($parsedType, $this->acceptedParsedTypes))
      throw new \Exception('Unhandled parsedType ['.$parsedType.'] on SignerListSet with HASH ['.$this->data['hash'].'] and perspective ['.$this->reference_address.']');

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
        throw new \Exception('Unhandled non XRP value on SignerListSet with HASH ['.$this->data['hash'].'] and perspective ['.$this->reference_address.']');
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