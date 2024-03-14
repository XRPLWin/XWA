<?php declare(strict_types=1);

namespace App\XRPLParsers\Types;

use App\XRPLParsers\XRPLParserBase;
use XRPLWin\XRPLNFTTxMutatationParser\NFTTxMutationParser;

final class NFTokenBurn extends XRPLParserBase
{
  private array $acceptedParsedTypes = ['SET','UNKNOWN','REGULARKEYSIGNER'];

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
  
    $nftparser = new NFTTxMutationParser($this->reference_address, $this->tx);
    $nftparserResult = $nftparser->result();

    $this->data['nft'] = $nftparserResult['nft'];

    if(\in_array(NFTTxMutationParser::ROLE_OWNER,$nftparserResult['ref']['roles'])) { //owner or owner+burner
      $this->data['In'] = true;
      $this->data['Counterparty'] = $this->tx->Account;
    } else {

      //unknown or burner
      $this->data['In'] = false;
      $this->persist = false;
      $this->data['Counterparty'] = $this->tx->Account;

      if(isset($this->tx->Owner)) {
        //This NFT is burned in behalf of another account
        $this->data['Counterparty'] = $this->tx->Owner;
      }

      if(\in_array(NFTTxMutationParser::ROLE_BURNER,$nftparserResult['ref']['roles'])) {
        //burner
        $this->persist = true;
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
      'nfts' => [],
      'offers' => [],
      'nft' => (string)$this->data['nft'],
      'nftoffers' => [],
      'hooks' => $this->data['hooks'],
    ];

    if(\array_key_exists('Fee', $this->data))
      $r['fee'] = $this->data['Fee'];

    return $r;
  }
}