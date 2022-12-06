<?php declare(strict_types=1);

namespace App\XRPLParsers\Types;

use App\XRPLParsers\XRPLParserBase;

final class OfferCreate extends XRPLParserBase
{
  private array $acceptedParsedTypes = ['SET','TRADE'];
  /**
   * Parses TrustSet type fields and maps them to $this->data
   * @see https://xrpl.org/transaction-types.html
   * @return void
   */
  protected function parseTypeFields(): void
  {
    $parsedType = $this->data['txcontext'];
    if(!in_array($parsedType, $this->acceptedParsedTypes))
      throw new \Exception('Unhandled parsedType ['.$parsedType.'] on Payment with HASH ['.$this->data['hash'].']');
    
     # Sub-Type
    if($parsedType === 'TRADE') {
      //Eg. Trade based on offer, this can be auto-furfilled offer or partially furfilled offer when creating new.
      $this->transaction_type_class = 'Offercreate_Trade';
    }

    # Counterparty
    # If 
    if($parsedType === 'SET') {
      //Counterparty is source
      $this->data['Counterparty'] = $this->tx->Account;
    } elseif($parsedType == 'TRADE') {

    }
    dd($this->transaction_type_class);

    dd($parsedType,$this->data, $this->tx);

    $this->data['Currency'] = $this->tx->LimitAmount->currency;
    $this->data['Amount'] = $this->tx->LimitAmount->value;
    $this->data['Issuer'] = $this->tx->LimitAmount->issuer;
  }

  /**
   * Returns standardized array of relevant data for storing to Dynamo database.
   * key => value one dimensional array which correlates to column => value in DyDb.
   * @return array
   */
  public function toDArray(): array
  {
    $r = [
      't' => ripple_epoch_to_carbon((int)$this->data['Date'])->format('Y-m-d H:i:s.uP'),
      //'fe' => $this->data['Fee'],
      'isin' => $this->data['In'],
      's' => $this->data['StateCreated'],
      'a' => $this->data['Amount'],
      'c' => $this->data['Currency'],
      'r' => $this->data['Issuer'], //counterparty =
      'i' => $this->data['Issuer'], //= issuer
      'h' => $this->data['hash'],
    ];

    if(\array_key_exists('Fee', $this->data))
      $r['fee'] = $this->data['Fee'];

    return $r;
  }
}