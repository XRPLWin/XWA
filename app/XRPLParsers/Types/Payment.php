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
    $this->data['hash'] = $this->tx->hash;
    //if($this->data['hash'] == '3F69DB35ED5D17F809F967DC0248C67BE673D6D916646ED20BFAD8A51F564999')
    //  dd($this->tx);

    $this->data['Counterparty'] = $this->data['In'] ? $this->tx->Account:$this->tx->Destination;
    $this->data['DestinationTag'] = isset($this->tx->DestinationTag) ? $this->tx->DestinationTag:null;
    $this->data['SourceTag'] = isset($this->tx->SourceTag) ? $this->tx->SourceTag:null;

    $this->data['Issuer'] = $this->data['Currency'] = null;

    $this->data['IsPartialPayment'] = false;
    

    if(is_object($this->tx->Amount)) { //it is payment specific currency (token)
      $this->data['RequestedAmount'] = $this->tx->Amount->value;    //(string) base-10 representation of double number
      $this->data['Amount'] = $this->meta->delivered_amount->value; //(string) base-10 representation of double number
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

    if($this->data['In']) //to save space we only store true value
      $r['in'] = true;

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