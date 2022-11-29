<?php declare(strict_types=1);

namespace App\XRPLParsers\Types;

use App\XRPLParsers\XRPLParserBase;

final class Payment extends XRPLParserBase
{
  private array $acceptedParsedTypes = ['SENT','RECEIVED','SET','TRADE'];
  /**
   * Parses Payment type fields and maps them to $this->data
   * Accepted parsedType: SENT|RECEIVED|TRADE
   * @see https://xrpl.org/transaction-types.html
   * @return void
   */
  protected function parseTypeFields(): void
  {

    # $this->data['In'] is bool
    //dd($this);

    $parsedType = $this->data['txcontext'];
    if(!in_array($parsedType, $this->acceptedParsedTypes))
      throw new \Exception('Unhandled parsedType ['.$parsedType.'] on Payment with HASH ['.$this->data['hash'].']');

    # Sub-Type
    if($parsedType === 'TRADE') {

      //Eg. Trade based on offer, or self to self, trustline shift, ...

      $this->transaction_type_class = 'Payment_BalanceChange';

      if($this->tx->Account == $this->tx->Destination) {
        /**
         * Source and destination are the same, this is exchange within TRADE context.
         * A payment can have the same account as both the source and destination, 
         * which allows account's to utilize pathfinding to convert from one currency to another.
         */
        $this->transaction_type_class = 'Payment_Exchange';
      }
    }


    # Counterparty
    if($parsedType === 'SENT') {
      // Counterparty is destination
      $this->data['Counterparty'] = $this->tx->Destination;
    } elseif( $parsedType === 'RECEIVED' ) {
      //Counterparty is source
      $this->data['Counterparty'] = $this->tx->Account;
    } elseif( $parsedType === 'TRADE' ) {
      $this->data['Counterparty'] = $this->tx->Account;
    } else {
      //todo get counterparty from $this->tx->Account if this is intermediate - check this
      throw new \Exception('Unhandled Counterparty for parsedtype ['.$parsedType.'] on Payment with HASH ['.$this->data['hash'].']');
    }


    # Source and destination tags
    $this->data['DestinationTag'] = isset($this->tx->DestinationTag) ? $this->tx->DestinationTag:null;
    $this->data['SourceTag'] = isset($this->tx->SourceTag) ? $this->tx->SourceTag:null;

    //@see https://xrpl.org/payment.html#payment-flags
    //$isPartialPayment = xrpl_has_flag($this->tx->Flags, 131072);

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
  public function toDArray(): array
  {
    //dd($this->data);
    $r = [
      't' => ripple_epoch_to_carbon((int)$this->data['Date'])->format('Y-m-d H:i:s.uP'),
      //'fe' => $this->data['Fee'], //now optional
      'isin' => $this->data['In'],
      'r' => (string)$this->data['Counterparty'], //OK
      'h' => (string)$this->data['hash'],
      //'a' => $this->data['Amount'] //now optional
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

    //if($this->data['IsPartialPayment'])
    //  $r['rqa'] = $this->data['RequestedAmount']; //rqa - requested amount - this field only exists when there is partial payment

    /**
     * dt - destination tag, stored as string
     */
    if($this->data['DestinationTag'] !== null)
      $r['dt'] = (string)$this->data['DestinationTag'];
    
    /**
     * st - source tag, stored as string
     */
    if($this->data['SourceTag'] !== null)
      $r['st'] = (string)$this->data['SourceTag'];

    //if($this->data['Issuer'] !== null) { //it is payment specific currency (token)
    //  $r['i'] = $this->data['Issuer'];
    //  $r['c'] = $this->data['Currency'];
    //}

    return $r;
  }



}