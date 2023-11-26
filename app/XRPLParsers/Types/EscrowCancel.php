<?php declare(strict_types=1);

namespace App\XRPLParsers\Types;

use App\XRPLParsers\XRPLParserBase;

final class EscrowCancel extends XRPLParserBase
{
  private array $acceptedParsedTypes = ['SENT','RECEIVED','SET','REGULARKEYSIGNER','UNKNOWN'];

  /**
   * Parses EscrowCancel type fields and maps them to $this->data
   * @see https://xrpl.org/transaction-types.html
   * @see B24B9D7843F99AED7FB8A3929151D0CCF656459AE40178B77C9D44CED64E839B - rf1BiGeXwwQoi8Z2ueFYTEXSwuJYfV2Jpn - creator
   * @return void
   */
  protected function parseTypeFields(): void
  {
    $parsedType = $this->data['txcontext'];
    if(!in_array($parsedType, $this->acceptedParsedTypes))
      throw new \Exception('Unhandled parsedType ['.$parsedType.'] on EscrowCancel with HASH ['.$this->data['hash'].'] and perspective ['.$this->reference_address.']');

    $this->data['Counterparty'] = $this->tx->Account;
    $this->data['In'] = false;
    if($this->reference_address == $this->tx->Owner)
      $this->data['In'] = true;

    if($parsedType == 'UNKNOWN' || $parsedType == 'REGULARKEYSIGNER') {
      if($this->reference_address != $this->tx->Owner && $this->reference_address != $this->tx->Account) {
        $this->data['In'] = false;
        $this->persist = false;
      }
    } elseif($parsedType == 'SET') { //Account cancels Owners escrow
      $this->data['Counterparty'] = $this->tx->Owner;
    }

    # Balance changes from eventList (primary/secondary, both, one, or none)
    if(isset($this->data['eventList']['primary'])) {
      $this->data['Amount'] = $this->data['eventList']['primary']['value'];
      if($this->data['eventList']['primary']['currency'] !== 'XRP') {
        throw new \Exception('Unhandled non XRP value on EscrowCancel with HASH ['.$this->data['hash'].'] and perspective ['.$this->reference_address.']');
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
      'hooks' => $this->data['hooks'],
    ];

    if(\array_key_exists('Amount', $this->data))
      $r['a'] = $this->data['Amount'];

    if(\array_key_exists('Fee', $this->data))
      $r['fee'] = $this->data['Fee'];

    return $r;
  }
}