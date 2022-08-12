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


    if(is_object($this->tx->Amount)) { //it is payment specific currency (token)
      $this->data['Amount'] = $this->meta->delivered_amount->value; //base-10 representation of double number
      $this->data['Issuer'] = $this->tx->Amount->issuer;
      $this->data['Currency'] = $this->meta->delivered_amount->currency;
    }
    else
      $this->data['Amount'] = drops_to_xrp((int)$this->meta->delivered_amount);
  }


  /**
   * Returns standardized array of relevant data for storing to Dynamo database.
   * key => value one dimensional array which correlates to column => value in DyDb.
   * @return array
   */
  public function toDArray(): array
  {
    $r = [
      'fe' => $this->data['Fee'],
      //'in' => $this->data['In'],
      'r' => $this->data['Counterparty'],
      //'h' => $this->data['hash'],
    ];

    if($this->data['In']) //to save space we only store true value
      $r['in'] = true;

    if($this->data['DestinationTag'] !== null)
      $r['dt'] = $this->data['DestinationTag'];
    
    if($this->data['SourceTag'] !== null)
      $r['st'] = $this->data['SourceTag'];

    $r['a'] = $this->data['Amount'];

    if($this->data['Issuer'] !== null) { //it is payment specific currency (token)
      $r['i'] = $this->data['Issuer'];
      $r['c'] = $this->data['Currency'];
    }

    return $r;
  }



}