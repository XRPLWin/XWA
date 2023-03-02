<?php declare(strict_types=1);

namespace App\XRPLParsers\Types;

use App\XRPLParsers\XRPLParserBase;

final class NFTokenMint extends XRPLParserBase
{
  private array $acceptedParsedTypes = ['SET','UNKNOWN'];

  /**
   * Parses NFTokenMint type fields and maps them to $this->data
   * @see https://xrpl.org/transaction-types.html
   * @see B42C7A0C9C3061463C619999942D0F25E4AE5FB051EA0D7A4EE1A924DB6DFEE8 - minted by (for self): rU2T6qNSab9N4SQZAEutwWnkzA7vUGWcfQ
   * @see D904ADB2D6DD9644B7ACC14E351536B8570F8451AAB01E946ADB47B1E381399F - minted by: rfx2mVhTZzc6bLXKeYyFKtpha2LHrkNZFT for issuer: rHeRoYtbiMSKhtXm4k7tff1PrcwYnCePR3
   * @return void
   */
  protected function parseTypeFields(): void
  {
    $parsedType = $this->data['txcontext'];
    if(!in_array($parsedType, $this->acceptedParsedTypes))
      throw new \Exception('Unhandled parsedType ['.$parsedType.'] on NFTokenMint with HASH ['.$this->data['hash'].'] and perspective ['.$this->reference_address.']');
  
    # Case 1: This NFT is minted in behalf of another account
    if(isset($this->tx->Issuer)) { 
      
      if($this->tx->Account == $this->reference_address) {
        $this->persist = true;
        $this->data['In'] = true;
        $this->data['Counterparty'] = $this->tx->Issuer;
      } elseif($this->tx->Issuer == $this->reference_address) {
        $this->persist = true;
        $this->data['In'] = true;
        $this->data['Counterparty'] = $this->tx->Account;
      } else {
        //Reference address does not have any part of this transaction
        $this->persist = false;
        $this->data['In'] = true;
        $this->data['Counterparty'] = $this->tx->Account;
      }
    }
    # Case 2: This NFT is minted for minter account (self)
    else { //
      if($this->tx->Account == $this->reference_address) {
        $this->persist = true;
        $this->data['In'] = true;
        $this->data['Counterparty'] = $this->tx->Account;
      } else {
        //Reference address does not have any part of this transaction
        $this->persist = false;
        $this->data['In'] = true;
        $this->data['Counterparty'] = $this->tx->Account;
      }
    }

    # Balance changes from eventList (primary/secondary, both, one, or none)
    if(isset($this->data['eventList']['primary'])) {
      $this->data['Amount'] = $this->data['eventList']['primary']['value'];
      if($this->data['eventList']['primary']['currency'] !== 'XRP') {
        throw new \Exception('Unhandled non XRP value on NFTokenMint with HASH ['.$this->data['hash'].'] and perspective ['.$this->reference_address.']');
      }
    }

    //TODO extract minted nft token ID from meta->AffectedNodes->ModifiedNode(NFTokenPage)->FinalFields/PreviousFields->NFTokens (by comparing)
    $this->data['nft'] = NULL; //TODO fill $this->data['nft']
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
      'nft' => (string)$this->data['nft'],
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