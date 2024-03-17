<?php declare(strict_types=1);

namespace App\XRPLParsers\Types;

use App\XRPLParsers\XRPLParserBase;

final class AccountDelete extends XRPLParserBase
{
  /**
   * Parses account deletions fields and maps them to $this->data
   * @see https://xrpl.org/transaction-types.html
   * @return void
   */
  protected function parseTypeFields(): void
  {
    $this->data['Counterparty'] = $this->data['In'] ? $this->tx->Account:$this->tx->Destination;
    $this->data['DestinationTag'] = isset($this->tx->DestinationTag) ? $this->tx->DestinationTag:null;

    # Balance changes from eventList (primary, or none)
    if(isset($this->data['eventList']['primary'])) {
      $this->data['Amount'] = $this->data['eventList']['primary']['value'];
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
      //'fe' => $this->data['Fee'],
      'isin' => $this->data['In'],
      'r' => $this->data['Counterparty'],
      'h' => $this->data['hash'],
      'ax' => [],
      'ix' => [],
      'cx' => [],
      'nfts' => [],
      'offers' => [],
      'nftoffers' => [],
      'hooks' => $this->data['hooks'],
    ];

    if(\array_key_exists('Amount', $this->data))
      $r['a'] = $this->data['Amount'];

    if(\array_key_exists('Fee', $this->data))
      $r['fee'] = $this->data['Fee'];

    /**
     * dt - destination tag, stored as string
     */
    if($this->data['DestinationTag'] !== null)
      $r['dt'] = (string)$this->data['DestinationTag'];

    return $r;
  }
}