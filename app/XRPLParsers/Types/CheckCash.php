<?php declare(strict_types=1);

namespace App\XRPLParsers\Types;

use App\XRPLParsers\XRPLParserBase;

final class CheckCash extends XRPLParserBase
{
  private array $acceptedParsedTypes = ['SENT','UNKNOWN'];

  /**
   * Parses CheckCash type fields and maps them to $this->data
   * Balanche changes only happen when account creates check (pays fee), on destination balance changes when CheckCash event is executed.
   * @see https://xrpl.org/transaction-types.html
   * @see 67B71B13601CDA5402920691841AC27A156463678E106FABD45357175F9FF406 - rw57FJjcRdZ6r3qgwxMNGCD8EJtVkjw1Am - initiator
   * @see 67B71B13601CDA5402920691841AC27A156463678E106FABD45357175F9FF406 - rU2CAvi6DHACUMBTEKxHRSL2QLrdHgptnx - affected
   * @see 67B71B13601CDA5402920691841AC27A156463678E106FABD45357175F9FF406 - rKiCet8SdvWxPXnAgYarFUXMh1zCPz432Y - affected
   * @see 67B71B13601CDA5402920691841AC27A156463678E106FABD45357175F9FF406 - rw57FJjcRdZ6r3qgwxMNGCD8EJtVkjw1Am - affected
   * http://xlanalyzer.test/txtest?tx=67B71B13601CDA5402920691841AC27A156463678E106FABD45357175F9FF406&acc=rw57FJjcRdZ6r3qgwxMNGCD8EJtVkjw1Am
   * @return void
   */
  protected function parseTypeFields(): void
  {
    //dd('todo'); //check for "rippling" todo
    $parsedType = $this->data['txcontext'];
    if(!in_array($parsedType, $this->acceptedParsedTypes))
      throw new \Exception('Unhandled parsedType ['.$parsedType.'] on CheckCash with HASH ['.$this->data['hash'].'] and perspective ['.$this->reference_address.']');
    
    # Counterparty is always CheckCash initiator
    $this->data['Counterparty'] = $this->tx->Account;
    if($this->tx->Account == $this->reference_address)
      $this->data['In'] = false; 
    else
      $this->data['In'] = true; 

    # Balance changes from eventList (primary/secondary, both, one, or none)
    if(isset($this->data['eventList']['primary'])) {
      $this->data['Amount'] = $this->data['eventList']['primary']['value'];
      if($this->data['eventList']['primary']['currency'] !== 'XRP') {
        $this->data['Issuer'] = $this->data['eventList']['primary']['counterparty'];
        $this->data['Currency'] = $this->data['eventList']['primary']['currency'];
      }
    }
    if(isset($this->data['eventList']['secondary'])) {
      $this->data['Amount2'] = $this->data['eventList']['secondary']['value'];
      if($this->data['eventList']['secondary']['currency'] !== 'XRP') {
        if(is_array($this->data['eventList']['secondary']['counterparty'])) {
          //Secondary counterparty is rippled trough reference account
          //Counterparty is list of counterparty participants, and value is SUM of balance changes
          throw new \Exception('Unhandled Counterparty Array for parsedtype ['.$parsedType.'] on Payment with HASH ['.$this->data['hash'].'] for perspective ['.$this->reference_address.']');
        } else {
          $this->data['Issuer2'] = $this->data['eventList']['secondary']['counterparty'];
          $this->data['Currency2'] = $this->data['eventList']['secondary']['currency'];
        }
        
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
      'nftoffers' => [],
    ];

    if(\array_key_exists('Amount', $this->data))
      $r['a'] = $this->data['Amount'];
    if(\array_key_exists('Amount2', $this->data))
      $r['a2'] = $this->data['Amount2'];

    if(\array_key_exists('Issuer', $this->data))
      $r['i'] = $this->data['Issuer'];
    if(\array_key_exists('Issuer2', $this->data))
      $r['i2'] = $this->data['Issuer2'];

    if(\array_key_exists('Currency', $this->data))
      $r['c'] = $this->data['Currency'];
    if(\array_key_exists('Currency2', $this->data))
      $r['c2'] = $this->data['Currency2'];

    if(\array_key_exists('Fee', $this->data))
      $r['fee'] = $this->data['Fee'];

    return $r;
  }
}