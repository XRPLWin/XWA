<?php declare(strict_types=1);

namespace App\XRPLParsers\Types;

use App\XRPLParsers\XRPLParserBase;
use XRPLWin\XRPLNFTTxMutatationParser\NFTTxMutationParser;

final class NFTokenAcceptOffer extends XRPLParserBase
{
  private array $acceptedParsedTypes = ['ACCEPT','TRADE','UNKNOWN','SET','REGULARKEYSIGNER'];

  /**
   * Parses NFTokenAcceptOffer type fields and maps them to $this->data
   * @see https://xrpl.org/transaction-types.html
   * @see 964474F04C4AE61A5CEDDB1543464118BF4F2E3C69CD502E893EAC5317ECEECD - rBgyjCQLVdSHwKVAhCZNTbmDsFHqLkzZdw - UNKNOWN
   * TODO 04EBA3FC9A54613CD782F8F659297E8FDAD8A2D1F7C6D7BE252419079D50483B nft accept offer with token currency
   * @return void
   */
  protected function parseTypeFields(): void
  {
    $parsedType = $this->data['txcontext'];
    if(!in_array($parsedType, $this->acceptedParsedTypes))
      throw new \Exception('Unhandled parsedType ['.$parsedType.'] on NFTokenAcceptOffer with HASH ['.$this->data['hash'].'] and perspective ['.$this->reference_address.']');
    
    $nftparser = new NFTTxMutationParser($this->reference_address, $this->tx);
    $nftparserResult = $nftparser->result();

    $this->data['nft'] = $nftparserResult['nft'];
    
    //SELL OR BUY
    $this->transaction_type_class = ($nftparserResult['context'] === 'SELL') ? 'NFTokenAcceptOffer_Sell':'NFTokenAcceptOffer_Buy';

    $this->data['Counterparty'] = $this->tx->Account;

    $this->data['In'] = true;
    if($this->reference_address == $this->tx->Account)
      $this->data['In'] = false;
    
    # Balance changes from eventList (primary/secondary, both, one, or none)
    if(isset($this->data['eventList']['primary'])) {
      $this->data['Amount'] = $this->data['eventList']['primary']['value'];
      if($this->data['eventList']['primary']['currency'] !== 'XRP') {
        $this->data['Issuer'] = $this->data['eventList']['primary']['counterparty'];
        $this->data['Currency'] = $this->data['eventList']['primary']['currency'];
      }
    }

    # Do not persist when no amount and no Fee, it is issuer's child trustlines movement
    //if(!isset($this->data['Amount']) && !isset($this->data['Fee'])) {
      //$this->persist = false;
    //}
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
      'nft' => $this->data['nft'],
    ];

    //if($this->data['nft'] !== null)
    //  $r['nft'] = (string)$this->data['nft'];

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