<?php declare(strict_types=1);

namespace App\XRPLParsers\Types;

use App\XRPLParsers\XRPLParserBase;

final class UNLModify extends XRPLParserBase
{
  private array $acceptedParsedTypes = [];

  /**
   * Parses TrustSet type fields and maps them to $this->data
   * @see https://xrpl.org/transaction-types.html
   * @return void
   */
  protected function parseTypeFields(): void
  {
    # Counterparty is always transaction account (creator)
    $this->data['Counterparty'] = 'rrrrrrrrrrrrrrrrrrrrrhoLvTp';
    $this->data['In'] = false;
    $this->persist = false; //Do not store UNLModify transactions
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
      'hooks' => $this->data['hooks'],
    ];
    return $r;
  }
}