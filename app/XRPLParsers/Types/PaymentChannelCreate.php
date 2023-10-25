<?php declare(strict_types=1);

namespace App\XRPLParsers\Types;

use App\XRPLParsers\XRPLParserBase;

final class PaymentChannelCreate extends XRPLParserBase
{
  private array $acceptedParsedTypes = ['SENT','RECEIVED'];

  /**
   * Parses TrustSet type fields and maps them to $this->data
   * @see https://xrpl.org/transaction-types.html
   * @see https://xrpl.org/paymentchannelcreate.html#paymentchannelcreate
   * @see 98ABAD54B58A12A025F510CA50DE3296501530097434FE556E4F6D082CE15D29
   * @return void
   */
  protected function parseTypeFields(): void
  {
    //dd($this->reference_address);
    $parsedType = $this->data['txcontext'];
    if(!in_array($parsedType, $this->acceptedParsedTypes))
      throw new \Exception('Unhandled parsedType ['.$parsedType.'] on PaymentChannelCreate with HASH ['.$this->data['hash'].'] and perspective ['.$this->reference_address.']');
    
    if($this->reference_address == $this->tx->Account) {
      $this->data['Counterparty'] = $this->tx->Destination;
      $this->data['In'] = false;
    } else {
      $this->data['Counterparty'] = $this->tx->Account;
      $this->data['In'] = true;
    }

    # Balance changes from eventList (primary/secondary, both, one, or none)
    if(isset($this->data['eventList']['primary'])) {
      $this->data['Amount'] = $this->data['eventList']['primary']['value'];
      if($this->data['eventList']['primary']['currency'] !== 'XRP') {
        throw new \Exception('Unhandled non XRP value on PaymentChannelCreate with HASH ['.$this->data['hash'].'] and perspective ['.$this->reference_address.']');
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
      'hooks' => [],
    ];

    if(\array_key_exists('Amount', $this->data))
      $r['a'] = $this->data['Amount'];
    
    if(\array_key_exists('Fee', $this->data))
      $r['fee'] = $this->data['Fee'];

    return $r;
  }
}