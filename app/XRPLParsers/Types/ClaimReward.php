<?php declare(strict_types=1);

namespace App\XRPLParsers\Types;

use App\XRPLParsers\XRPLParserBase;

final class ClaimReward extends XRPLParserBase
{
private array $acceptedParsedTypes = ['SET','UNKNOWN','REGULARKEYSIGNER'];

  /**
   * Parses ClaimReward type fields and maps them to $this->data
   * @see https://docs.xahau.network/technical/protocol-reference/transactions/transaction-types/claimreward
   * @return void
   */
  protected function parseTypeFields(): void
  {
    $parsedType = $this->data['txcontext'];
    if(!in_array($parsedType, $this->acceptedParsedTypes))
      throw new \Exception('Unhandled parsedType ['.$parsedType.'] on ClaimReward with HASH ['.$this->data['hash'].'] and perspective ['.$this->reference_address.']');

    if($parsedType == 'UNKNOWN' || $parsedType == 'REGULARKEYSIGNER')
      $this->persist = false;

    # Counterparty is always transaction account (creator)
    $this->data['Counterparty'] = $this->tx->Account;
    $this->data['In'] = false;
    if($this->reference_address == $this->tx->Account)
      $this->data['In'] = true;

    $this->transaction_type_class = 'ClaimReward';
    if(isset($this->tx->Flags) && $this->tx->Flags == 1) {
      $this->transaction_type_class = 'ClaimReward_OptOut';
    } else {
      if(isset($this->tx->Issuer) && $this->tx->Issuer) {
        //SPECIAL CASE: we store Issuer in 'i' field, to be searchable (may not be needed)
        //$this->data['Amount'] = '0';
        $this->data['Issuer'] = $this->tx->Issuer;
        //$this->data['Currency'] = 'XRP';
      }
    }
    
    
    # Balance changes from eventList (primary/secondary, both, one, or none)
    if(isset($this->data['eventList']['primary'])) {
      $this->data['Amount'] = $this->data['eventList']['primary']['value'];
      if($this->data['eventList']['primary']['currency'] !== 'XRP') {
        $this->data['Issuer'] = $this->data['eventList']['primary']['counterparty'];
        $this->data['Currency'] = $this->data['eventList']['primary']['currency'];
      }
    }

    /*if(isset($this->data['Amount'])) {
      $this->transaction_type_class = 'ClaimReward_Claim';
    }*/

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
    
    if(\array_key_exists('Issuer', $this->data))
      $r['i'] = $this->data['Issuer'];

    if(\array_key_exists('Currency', $this->data))
      $r['c'] = $this->data['Currency'];

    if(\array_key_exists('Fee', $this->data))
      $r['fee'] = $this->data['Fee'];

    return $r;
  }
}