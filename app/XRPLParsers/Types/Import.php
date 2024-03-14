<?php declare(strict_types=1);

namespace App\XRPLParsers\Types;

use App\XRPLParsers\XRPLParserBase;
use Brick\Math\BigDecimal;

final class Import extends XRPLParserBase
{
  private array $acceptedParsedTypes = ['SENT','REGULARKEYSIGNER','UNKNOWN'];

  /**
   * Parses Import type fields and maps them to $this->data
   * @see https://docs.xahau.network/technical/protocol-reference/transactions/transaction-types/import
   * @see 762384317F504BF6AAE8480AD8D79FC0F5EC3338A07B72837824405731E8EF81 xahau - regularkey
   * @see D9611904CC4CF9B871672DB4805114D18E22BC738671015ED96326EEB13E01D6 - emitted tx
   * @return void
   */
  protected function parseTypeFields(): void
  {
    $parsedType = $this->data['txcontext'];
    if(!in_array($parsedType, $this->acceptedParsedTypes))
      throw new \Exception('Unhandled parsedType ['.$parsedType.'] on Import with HASH ['.$this->data['hash'].'] and perspective ['.$this->reference_address.']');

    # Counterparty is always transaction account (creator)
    $this->data['Counterparty'] = $this->tx->Account;
    $this->data['In'] = false;
    if($this->reference_address == $this->tx->Account)
      $this->data['In'] = true;

    if($parsedType === 'UNKNOWN') {
      //this participant can come from emmitted tx see 
      $this->persist = false;
    }
    
    # Balance changes from eventList (primary/secondary, both, one, or none)
    if(isset($this->data['eventList']['primary'])) {
      $this->data['Amount'] = $this->data['eventList']['primary']['value'];
      if($this->data['eventList']['primary']['currency'] !== 'XRP') {
        $this->data['Issuer'] = $this->data['eventList']['primary']['counterparty'];
        $this->data['Currency'] = $this->data['eventList']['primary']['currency'];
      }
    }

    # Balance (Amount2) is actually burned XRP amount on XRPL extracted from blob field
    if($this->data['In'] && isset($this->tx->Blob)) {
      $_blob = \json_decode(\hex2bin($this->tx->Blob));
      $codec = new \XRPL_PHP\Core\RippleBinaryCodec\BinaryCodec;
      $_blob_transaction = $codec->decode($_blob->transaction->blob);
      $_total_burnedXRP = $_blob_transaction['Fee'];
      $this->data['Amount2'] = (string)BigDecimal::of($_total_burnedXRP)->exactlyDividedBy(1000000);
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
    if(\array_key_exists('Amount2', $this->data))
      $r['a2'] = $this->data['Amount2'];
    
    if(\array_key_exists('Issuer', $this->data))
      $r['i'] = $this->data['Issuer'];

    if(\array_key_exists('Currency', $this->data))
      $r['c'] = $this->data['Currency'];

    if(\array_key_exists('Fee', $this->data))
      $r['fee'] = $this->data['Fee'];

    return $r;
  }
}