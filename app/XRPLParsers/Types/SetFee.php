<?php declare(strict_types=1);

namespace App\XRPLParsers\Types;

use App\XRPLParsers\XRPLParserBase;

final class SetFee extends XRPLParserBase
{
  private array $acceptedParsedTypes = ['SENT'];

  /**
   * Parses SetHook type fields and maps them to $this->data
   * The SetHook transaction allows users to install, update, delete, or perform other operations on hooks in the XRP Ledger.
   * @see https://docs.xahau.network/features/transaction-types/sethook
   * @return void
   */
  protected function parseTypeFields(): void
  {
    $parsedType = $this->data['txcontext'];
    if(!in_array($parsedType, $this->acceptedParsedTypes))
      throw new \Exception('Unhandled parsedType ['.$parsedType.'] on SetFee with HASH ['.$this->data['hash'].'] and perspective ['.$this->reference_address.']');

    if($this->tx->Account != $this->reference_address)
      $this->persist = false;

    # Counterparty is always transaction account (creator)
    $this->data['Counterparty'] = $this->tx->Account;
    $this->data['In'] = false;

    //fee is contained in FeeSettings object, we do not store it in db
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

    if(\array_key_exists('Fee', $this->data))
      $r['fee'] = $this->data['Fee'];

    return $r;
  }
}