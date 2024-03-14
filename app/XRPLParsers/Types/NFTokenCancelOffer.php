<?php declare(strict_types=1);

namespace App\XRPLParsers\Types;

use App\XRPLParsers\XRPLParserBase;

final class NFTokenCancelOffer extends XRPLParserBase
{
  private array $acceptedParsedTypes = ['SET','REGULARKEYSIGNER','UNKNOWN'];

  /**
   * Parses NFTokenCancelOffer type fields and maps them to $this->data
   * @see https://xrpl.org/transaction-types.html
   * @see DF3137FA90575D6F75EE6F5B9D51DFA9722AF7CBB18B19ADBBB8E20D15CFD238 - rBgyjCQLVdSHwKVAhCZNTbmDsFHqLkzZdw - created
   * @see F5A6234B644F15F44EBFD4CE9A03F2A000F20BC084D8D23875C7E3FCC7AFBAA9 - rBgyjCQLVdSHwKVAhCZNTbmDsFHqLkzZdw - UNKNOWN
   * @return void
   */
  protected function parseTypeFields(): void
  {
    $parsedType = $this->data['txcontext'];
    if(!in_array($parsedType, $this->acceptedParsedTypes))
      throw new \Exception('Unhandled parsedType ['.$parsedType.'] on NFTokenCancelOffer with HASH ['.$this->data['hash'].'] and perspective ['.$this->reference_address.']');

    $this->data['Counterparty'] = $this->tx->Account;

    $this->data['In'] = true;
    if($this->reference_address == $this->tx->Account)
      $this->data['In'] = false;
    
    # Balance changes from eventList (primary/secondary, both, one, or none)
    if(isset($this->data['eventList']['primary'])) {
      $this->data['Amount'] = $this->data['eventList']['primary']['value'];
      if($this->data['eventList']['primary']['currency'] !== 'XRP') {
        $this->data['Issuer'] = $this->data['eventList']['primary']['counterparty'];
        $this->data['Currency'] = $this->data['eventList']['primary']['currency'];
      }
    }

    # Do not persist when no amount and no Fee
    //if(!isset($this->data['Amount']) && !isset($this->data['Fee'])) {
    //  $this->persist = false;
    //}

    $this->data['nftoffers'] = $this->tx->NFTokenOffers;

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
      //no nft here
      'nftoffers' => (array)$this->data['nftoffers'],
      'hooks' => $this->data['hooks'],
    ];

    if(\array_key_exists('Amount', $this->data))
      $r['a'] = $this->data['Amount'];

    if(\array_key_exists('Issuer', $this->data))
      $r['i'] = $this->data['Issuer'];

    if(\array_key_exists('Currency', $this->data))
      $r['c'] = $this->data['Currency'];

    if(\array_key_exists('Fee', $this->data))
      $r['fee'] = $this->data['Fee'];

    return $r;
  }
}