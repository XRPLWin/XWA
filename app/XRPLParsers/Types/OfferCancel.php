<?php declare(strict_types=1);

namespace App\XRPLParsers\Types;

use App\XRPLParsers\XRPLParserBase;

final class OfferCancel extends XRPLParserBase
{
  private array $acceptedParsedTypes = ['SET','UNKNOWN'];
  /**
   * Parses TrustSet type fields and maps them to $this->data
   * @see https://xrpl.org/transaction-types.html
   * @see https://playground.xrpl.win/play/xrpl-transaction-mutation-parser?hash=E7697D162A606FCC138C5732BF0D2A4AED49386DC59235FC3E218650AAC19744&ref=rf1BiGeXwwQoi8Z2ueFYTEXSwuJYfV2Jpn
   *      UNKNOWN transaction
   * @return void
   */
  protected function parseTypeFields(): void
  {
    $parsedType = $this->data['txcontext'];
    if(!in_array($parsedType, $this->acceptedParsedTypes))
      throw new \Exception('Unhandled parsedType ['.$parsedType.'] on OfferCancel with HASH ['.$this->data['hash'].'] and perspective ['.$this->reference_address.']');
    
  

    # Counterparty is always transaction account (creator)
    $this->data['Counterparty'] = $this->tx->Account;

    # OfferCancel is always OUT
    $this->data['In'] = false;

    # Balance changes from eventList (primary/secondary, both, one, or none)
    if(isset($this->data['eventList']['primary'])) {
      $this->data['Amount'] = $this->data['eventList']['primary']['value'];
      if($this->data['eventList']['primary']['currency'] !== 'XRP') {
        $this->data['Issuer'] = $this->data['eventList']['primary']['counterparty'];
        $this->data['Currency'] = $this->data['eventList']['primary']['currency'];
      }
    }

    # We expect fee only but this will catch eventual balance changes if any
    if(isset($this->data['eventList']['secondary'])) {
      $this->data['Amount2'] = $this->data['eventList']['secondary']['value'];
      if($this->data['eventList']['secondary']['currency'] !== 'XRP') {
        if(is_array($this->data['eventList']['secondary']['counterparty'])) {
          //Secondary counterparty is rippled trough reference account
          //Counterparty is list of counterparty participants, and value is SUM of balance changes
          throw new \Exception('Unhandled Counterparty Array for parsedtype ['.$parsedType.'] on Payment with HASH ['.$this->data['hash'].'] for perspective ['.$this->reference_address.']');
        } else {
          $this->data['Issuer2'] = $this->data['eventList']['secondary']['counterparty'];
          $this->data['Currency2'] = $this->data['eventList']['secondary']['currency'];
        }
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
    if(\array_key_exists('Amount2', $this->data))
      $r['a2'] = $this->data['Amount2'];

    if(\array_key_exists('Issuer', $this->data))
      $r['i'] = $this->data['Issuer'];
    if(\array_key_exists('Issuer2', $this->data))
      $r['i2'] = $this->data['Issuer2'];

    if(\array_key_exists('Currency', $this->data))
      $r['c'] = $this->data['Currency'];
    if(\array_key_exists('Currency2', $this->data))
      $r['c2'] = $this->data['Currency2'];

    if(\array_key_exists('Fee', $this->data))
      $r['fee'] = $this->data['Fee'];

    return $r;
  }
}