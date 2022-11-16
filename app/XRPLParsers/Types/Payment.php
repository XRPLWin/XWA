<?php declare(strict_types=1);

namespace App\XRPLParsers\Types;

use App\XRPLParsers\XRPLParserBase;

final class Payment extends XRPLParserBase
{
  /**
   * Parses Payment type fields and maps them to $this->data
   * @see https://xrpl.org/transaction-types.html
   * @return void
   */
  protected function parseTypeFields(): void
  {

    # $this->data['In'] is bool
    //dd($this);

    $parsedType = $this->parsedData['type'];
    
    if($parsedType == 'SET') { //own balance change is fee only
      throw new \Exception('Unhandled SET parsed type on Payment');
    }

    if($parsedType == 'TRADE') {

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

    $this->data['hash'] = $this->tx->hash;

    # OLD BELOW ##########################################

    //get counterparty
    $this->data['CounterpartyDestination'] = null;

    if($this->data['In'] === true)
      $this->data['Counterparty'] = $this->tx->Account;
    elseif($this->data['In'] === false)
      $this->data['Counterparty'] = $this->tx->Destination;
    else {
      $this->data['Counterparty'] = $this->tx->Account; // for participating in transactions counterparty is transaction initiator
      $this->data['CounterpartyDestination'] = $this->tx->Destination;
    }
      

    // source and destination tags
    $this->data['DestinationTag'] = isset($this->tx->DestinationTag) ? $this->tx->DestinationTag:null;
    $this->data['SourceTag'] = isset($this->tx->SourceTag) ? $this->tx->SourceTag:null;

    //TODO CHECK FLAGS FOR PARTIAL PAYMENT
    //@see https://xrpl.org/payment.html#payment-flags
    $isPartialPayment = xrpl_has_flag($this->tx->Flags, 131072);
    
    //https://github.com/ripple/rippled-historical-database/blob/d20ab42fc983cd394bc0dcca27a6ae3bdedaa891/lib/hbase/hbase-thrift/data.js
    //napravi ovu logiku u posebnoj funkciji:
    if($this->data['In'] !== null) { //in or out
      if(is_object($this->tx->Amount)) { //it is payment specific currency (token)
        $this->data['RequestedAmount'] = $this->tx->Amount->value;    //(string) base-10 representation of double number
        $this->data['Amount'] = isset($this->meta->delivered_amount->value) ? $this->meta->delivered_amount->value : $this->tx->Amount->value; //(string) base-10 representation of double number ("unavailable" possible for transactions before 2014-01-20 for partial payments)
        $this->data['Issuer'] = $this->tx->Amount->issuer;
        $this->data['Currency'] = $this->meta->delivered_amount->currency;
      } else {
        $this->data['RequestedAmount'] = drops_to_xrp((int)$this->tx->Amount); //test this
        $this->data['Amount'] = drops_to_xrp((int)$this->meta->delivered_amount);
      }
    }
    ########## OLD

    $this->data['Counterparty'] = ($this->data['In'] === true) ? $this->tx->Account:$this->tx->Destination;
    $this->data['DestinationTag'] = isset($this->tx->DestinationTag) ? $this->tx->DestinationTag:null;
    $this->data['SourceTag'] = isset($this->tx->SourceTag) ? $this->tx->SourceTag:null;

    $this->data['Issuer'] = $this->data['Currency'] = null;

    if(is_object($this->tx->Amount)) { //it is payment specific currency (token)
      $this->data['RequestedAmount'] = $this->tx->Amount->value;    //(string) base-10 representation of double number
      $this->data['Amount'] = $this->meta->delivered_amount->value; //(string) base-10 representation of double number ("unavailable" possible for transactions before 2014-01-20 for partial payments)
      $this->data['Issuer'] = $this->tx->Amount->issuer;
      $this->data['Currency'] = $this->meta->delivered_amount->currency;
    } else {
      $this->data['RequestedAmount'] = drops_to_xrp((int)$this->tx->Amount); //test this
      $this->data['Amount'] = drops_to_xrp((int)$this->meta->delivered_amount);
    }

    dd($this);



    //TODO REMOVE BELOW (DEPRECATED):

    $this->data['IsPartialPayment'] = false;
    
    
    if(is_object($this->tx->Amount)) { //it is payment specific currency (token)
      $this->data['RequestedAmount'] = $this->tx->Amount->value;    //(string) base-10 representation of double number
      $this->data['Amount'] = $this->meta->delivered_amount->value; //(string) base-10 representation of double number ("unavailable" possible for transactions before 2014-01-20 for partial payments)
      $this->data['Issuer'] = $this->tx->Amount->issuer;
      $this->data['Currency'] = $this->meta->delivered_amount->currency;
    } else {
      $this->data['RequestedAmount'] = drops_to_xrp((int)$this->tx->Amount); //test this
      $this->data['Amount'] = drops_to_xrp((int)$this->meta->delivered_amount);
    }

    # Check if this is partial payment
    if($this->data['Amount'] !== $this->data['RequestedAmount']) {
      $this->data['IsPartialPayment'] = true;
    }
    
    /*if($this->data['hash'] != '9E7D83EF9968AE0493E8328F23F01DF87C1D1BA709A9AD2BC4479C1943C4CD57')
    {
      dd($this->data);
    }*/
      
  }


  /**
   * Returns standardized array of relevant data for storing to Dynamo database.
   * key => value one dimensional array which correlates to column => value in DyDb.
   * @return array
   */
  public function toDArray(): array
  {
    $r = [
      't' => $this->data['Date'],
      'fe' => $this->data['Fee'],
      //'in' => $this->data['In'],
      'r' => $this->data['Counterparty'],
      'h' => $this->data['hash'],
      'a' => $this->data['Amount']
    ];

    if($this->data['CounterpartyDestination'] !== null)
      $r['rd'] = (string)$this->data['CounterpartyDestination']; //this is filled when referenced account is participating in tx only

    //if($this->data['Counterparty'] !== null)
    //  $r['r'] = (string)$this->data['Counterparty'];

    if($this->data['In'] === true) //to save space we only store true value
      $r['in'] = true;
    elseif($this->data['In'] === null)
      $r['in'] = null; //for participating in transaction we will put NULL until decided otherwise, mapper will validate this as IN

    if($this->data['IsPartialPayment'])
      $r['rqa'] = $this->data['RequestedAmount']; //rqa - requested amount - this field only exists when there is partial payment

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

    if($this->data['Issuer'] !== null) { //it is payment specific currency (token)
      $r['i'] = $this->data['Issuer'];
      $r['c'] = $this->data['Currency'];
    }

    return $r;
  }



}