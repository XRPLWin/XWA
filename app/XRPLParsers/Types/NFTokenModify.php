<?php declare(strict_types=1);

namespace App\XRPLParsers\Types;

use App\XRPLParsers\XRPLParserBase;
use XRPLWin\XRPLNFTTxMutatationParser\NFTTxMutationParser;

final class NFTokenModify extends XRPLParserBase
{
  private array $acceptedParsedTypes = ['SET','REGULARKEYSIGNER','RECEIVED','UNKNOWN'];

  /**
   * Parses NFTokenModify type fields and maps them to $this->data
   * @see https://xrpl.org/transaction-types.html
   * @see BCDA3D25790B4AC670123851260C2CA918970A0264DA21A7A8835E6AD2ED1C56 - modified by (for self): rWinEUKtN3BmYdDoGU6HZ7tTG54BeCAiz
   * 
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

    $this->data['Counterparty'] = $this->tx->Account;

    if($this->reference_address == $this->tx->Account) {
      $this->data['In'] = true;
    } else {
      $this->persist = false;
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