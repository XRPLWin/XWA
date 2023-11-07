<?php declare(strict_types=1);

namespace App\XRPLParsers\Types;

use App\XRPLParsers\XRPLParserBase;

final class DepositPreauth extends XRPLParserBase
{
  private array $acceptedParsedTypes = ['SET','UNKNOWN'];

  /**
   * Parses DepositPreauth type fields and maps them to $this->data
   * @see https://xrpl.org/transaction-types.html
   * @see CB1BF910C93D050254C049E9003DA1A265C107E0C8DE4A7CFF55FADFD39D5656 - rf1BiGeXwwQoi8Z2ueFYTEXSwuJYfV2Jpn - initiator
   * @see CB1BF910C93D050254C049E9003DA1A265C107E0C8DE4A7CFF55FADFD39D5656 - ra5nK24KXen9AHvsdFTKHSANinZseWnPcX - authorized account
   * @return void
   */
  protected function parseTypeFields(): void
  {
    $parsedType = $this->data['txcontext'];
    if(!in_array($parsedType, $this->acceptedParsedTypes))
      throw new \Exception('Unhandled parsedType ['.$parsedType.'] on DepositPreauth with HASH ['.$this->data['hash'].'] and perspective ['.$this->reference_address.']');

    $this->transaction_type_class = 'DepositPreauth_Authorize';
    if(isset($this->tx->Unauthorize))
      $this->transaction_type_class = 'DepositPreauth_Unauthorize';

    if($this->tx->Account == $this->reference_address) {
      $this->data['In'] = false;
      if($this->transaction_type_class == 'DepositPreauth_Authorize') {
        $this->data['Counterparty'] = $this->tx->Authorize;
      } else {
        $this->data['Counterparty'] = $this->tx->Unauthorize;
      }
    } else {
      $this->data['Counterparty'] = $this->tx->Account;
      $this->data['In'] = true;
    }
    

    # Balance changes from eventList (primary/secondary, both, one, or none)
    if(isset($this->data['eventList']['primary'])) {
      $this->data['Amount'] = $this->data['eventList']['primary']['value'];
      if($this->data['eventList']['primary']['currency'] !== 'XRP') {
        throw new \Exception('Unhandled non XRP value on DepositPreauth with HASH ['.$this->data['hash'].']');
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