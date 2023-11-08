<?php declare(strict_types=1);

namespace App\XRPLParsers\Types;

use App\XRPLParsers\XRPLParserBase;

final class SetHook extends XRPLParserBase
{
  private array $acceptedParsedTypes = ['SET','REGULARKEYSIGNER','UNKNOWN'];

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
      throw new \Exception('Unhandled parsedType ['.$parsedType.'] on SetHook with HASH ['.$this->data['hash'].'] and perspective ['.$this->reference_address.']');

    if($this->tx->Account != $this->reference_address)
      $this->persist = false;

    # Counterparty is always transaction account (creator)
    $this->data['Counterparty'] = $this->tx->Account;
    $this->data['In'] = true;

    //dd($this->hook_parser->installedHooks(),$this->hook_parser->uninstalledHooks());
    //$this->transaction_type_class = 'SetHook'; //this includes updates and no-op

    //Cant split txs, we can use context later ad-hoc (see trustlines)
    /*if(count($this->hook_parser->installedHooks()))
      $this->transaction_type_class = 'SetHook_Install';
    elseif($this->hook_parser->uninstalledHooks())
      $this->transaction_type_class = 'SetHook_Deinstall';
    elseif (false)
      $this->transaction_type_class = 'SetHook_Reset'; //Reset hook namespace*/
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