<?php declare(strict_types=1);

namespace App\XRPLParsers\Types;

use App\Models\B;
use App\XRPLParsers\XRPLParserBase;

final class Remit extends XRPLParserBase
{
  private array $acceptedParsedTypes = ['SENT','RECEIVED','REGULARKEYSIGNER','UNKNOWN'];

  /**
   * Parses Remit type fields and maps them to $this->data
   * The Remit transaction allows users to send multiple currencies, uritokens...
   * @see https://docs.xahau.network/technical/protocol-reference/transactions/transaction-types/remit
   * @return void
   */
  protected function parseTypeFields(): void
  {
    //dd($this->tx);
    $parsedType = $this->data['txcontext'];
    if(!in_array($parsedType, $this->acceptedParsedTypes))
      throw new \Exception('Unhandled parsedType ['.$parsedType.'] on Remit with HASH ['.$this->data['hash'].'] and perspective ['.$this->reference_address.']');

    if($this->tx->Account != $this->reference_address && $this->tx->Destination != $this->reference_address)
      $this->persist = false;

    # Counterparty is always transaction account (creator)
    $this->data['Counterparty'] = $this->tx->Destination;
    $this->data['In'] = false;

    if($this->tx->Destination == $this->reference_address) {
      $this->data['Counterparty'] = $this->tx->Account;
      $this->data['In'] = true;
    }

    # Source and destination tags
    $this->data['DestinationTag'] = isset($this->tx->DestinationTag) ? $this->tx->DestinationTag:null;
    $this->data['SourceTag'] = isset($this->tx->SourceTag) ? $this->tx->SourceTag:null;

    # sfURITokenIDs (nfts) that are transferred to new owner
    # @see 8B97407976C43956FB241B690D3B7D3FA2AC812375603722B2A343BC5488289E
    $this->data['nfts'] = [];
    if(isset($this->tx->URITokenIDs)) {
      $this->data['nfts'] = $this->tx->URITokenIDs; //
    }

    # Minted URIToken in Remit
    # @see E7DBB8255F4E6EE24A796702FCC43E7CDF98433309C65969AD5BB27A5B0B58A9
    //dd($this);
    if(isset($this->tx->MintURIToken)) {
      //extract via NFT reader or Extract by looping metadata (this one is faster)
      foreach($this->meta->AffectedNodes as $node) {
        if(isset($node->CreatedNode) && $node->CreatedNode->LedgerEntryType == 'URIToken') {
          $this->data['nft'] = $node->CreatedNode->LedgerIndex;
          break;
        }
        
      }
    }


    # Balances (Amount, Amount2 ... Issuer, Issuer2 ..., Currency, Currency2...)
    $i = 1;
    foreach($this->data['balanceChangesExclFee'] as $bc) {
      $index = $i == 1 ? '':$i;
      $this->data['Amount'.$index] = $bc['value'];
      if(count($bc) == 3) {
        //TOKEN
        $this->data['Issuer'.$index] = $bc['counterparty'];
        $this->data['Currency'.$index] = $bc['currency'];
      }
      $i++;
    }

    if(count($this->data['balanceChangesExclFee']) > 3) { //3
      $this->data['AmountX'] = [];
      $this->data['IssuerX'] = [];
      $this->data['CurrencyX'] = [];
      
      $i = 1;
      foreach($this->data['balanceChangesExclFee'] as $bc) {
        if($i > 3) { //3
          $this->data['AmountX'][] = $bc['value'];
          if(count($bc) == 3) {
            //TOKEN
            $this->data['IssuerX'][] = $bc['counterparty'];
            $this->data['CurrencyX'][] = $bc['currency'];
          } else {
            $this->data['IssuerX'][] = null;
            $this->data['CurrencyX'][] = null;
          }
        }
        $i++;
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
      'ax' => [],
      'ix' => [],
      'cx' => [],
      'nfts' => $this->data['nfts'],
      'offers' => [],
      'nftoffers' => [],
      'hooks' => $this->data['hooks'],
    ];

    if(\array_key_exists('nft', $this->data))
      $r['nft'] = $this->data['nft'];

    //Note: In amounts can be XRP as seperate amount (that is special transaciton cost)
    //If there is XRP sent then this amount is XRP+Special transaction cost
    //1st amount
    if(\array_key_exists('Amount', $this->data))
      $r['a'] = $this->data['Amount'];
    
    if(\array_key_exists('Issuer', $this->data))
      $r['i'] = $this->data['Issuer'];

    if(\array_key_exists('Currency', $this->data))
      $r['c'] = $this->data['Currency'];

    //2nd amount
    if(\array_key_exists('Amount2', $this->data))
      $r['a2'] = $this->data['Amount2'];
    
    if(\array_key_exists('Issuer2', $this->data))
      $r['i2'] = $this->data['Issuer2'];

    if(\array_key_exists('Currency2', $this->data))
      $r['c2'] = $this->data['Currency2'];

    //3rd amount
    if(\array_key_exists('Amount3', $this->data))
      $r['a3'] = $this->data['Amount3'];
    
    if(\array_key_exists('Issuer3', $this->data))
      $r['i3'] = $this->data['Issuer3'];

    if(\array_key_exists('Currency3', $this->data))
      $r['c3'] = $this->data['Currency3'];

    //ax,ix,cx
    if(\array_key_exists('AmountX', $this->data))
      $r['ax'] = $this->data['AmountX'];
    
    if(\array_key_exists('IssuerX', $this->data))
      $r['ix'] = $this->data['IssuerX'];

    if(\array_key_exists('CurrencyX', $this->data))
      $r['cx'] = $this->data['CurrencyX'];

    if(\array_key_exists('Fee', $this->data))
      $r['fee'] = $this->data['Fee'];

    return $r;
  }
}