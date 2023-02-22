<?php declare(strict_types=1);

namespace App\XRPLParsers\Types;

use App\XRPLParsers\XRPLParserBase;

final class CheckCash extends XRPLParserBase
{
  private array $acceptedParsedTypes = ['SENT','RECEIVED'];

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
    dd('todo'); //check for "rippling" todo
    $parsedType = $this->data['txcontext'];
    if(!in_array($parsedType, $this->acceptedParsedTypes))
      throw new \Exception('Unhandled parsedType ['.$parsedType.'] on CheckCash with HASH ['.$this->data['hash'].']');

    if($parsedType == 'SENT') {
      $this->data['Counterparty'] = $this->tx->Destination;
      $this->data['In'] = false;
    } elseif($parsedType == 'RECEIVED') {
      $this->data['Counterparty'] = $this->tx->Account;
      $this->data['In'] = true;
    }

    # Balance changes from eventList (primary/secondary, both, one, or none)
    if(isset($this->data['eventList']['primary'])) {
      $this->data['Amount'] = $this->data['eventList']['primary']['value'];
      if($this->data['eventList']['primary']['currency'] !== 'XRP') {
        $this->data['Issuer'] = $this->data['eventList']['primary']['counterparty'];
        $this->data['Currency'] = $this->data['eventList']['primary']['currency'];
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
      'isin' => $this->data['In'],
      'r' => (string)$this->data['Counterparty'],
      'h' => (string)$this->data['hash'],
    ];

    if(\array_key_exists('Amount', $this->data))
      $r['a'] = $this->data['Amount'];

    if(\array_key_exists('Fee', $this->data))
      $r['fee'] = $this->data['Fee'];

    if(\array_key_exists('Issuer', $this->data))
      $r['i'] = $this->data['Issuer'];

    if(\array_key_exists('Currency', $this->data))
      $r['c'] = $this->data['Currency'];

    return $r;
  }
}