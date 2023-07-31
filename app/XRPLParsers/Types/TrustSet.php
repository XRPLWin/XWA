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
  public function toBArray(): array
  {
    $r = [
      't' => ripple_epoch_to_carbon((int)$this->data['Date'])->format('Y-m-d H:i:s.uP'),
      'l' => $this->data['LedgerIndex'],
      'li' => $this->data['TransactionIndex'],
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