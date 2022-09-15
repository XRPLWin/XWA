<?php declare(strict_types=1);

namespace App\XRPLParsers\Types;

use App\XRPLParsers\XRPLParserBase;

final class TrustSet extends XRPLParserBase
{
  /**
   * Parses TrustSet type fields and maps them to $this->data
   * @see https://xrpl.org/transaction-types.html
   * @return void
   */
  protected function parseTypeFields(): void
  {
    $this->data['hash'] = $this->tx->hash;
    # StateCreated - true if trustline created, false if deleted
    $this->data['StateCreated'] = !($this->tx->LimitAmount->value == 0);

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
      'fe' => $this->data['Fee'],
      //'in' => $this->data['In'],
      's' => $this->data['StateCreated'],
      'a' => $this->data['Amount'],
      'c' => $this->data['Currency'],
      'i' => $this->data['Issuer'],
      'h' => $this->data['hash'],
    ];

    if($this->data['In']) //to save space we only store true value
      $r['in'] = true;

    return $r;
  }
}