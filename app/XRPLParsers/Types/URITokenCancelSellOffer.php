<?php declare(strict_types=1);

namespace App\XRPLParsers\Types;

use App\XRPLParsers\XRPLParserBase;
use XRPLWin\XRPLNFTTxMutatationParser\NFTTxMutationParser;

final class URITokenCancelSellOffer extends XRPLParserBase
{
  private array $acceptedParsedTypes = ['SET','REGULARKEYSIGNER','UNKNOWN'];

  /**
   * Parses URITokenCancelSellOffer type fields and maps them to $this->data
   * Only thing that is persisted is Initiator (Account) perspective.
   * @see https://docs.xahau.network/technical/protocol-reference/transactions/transaction-types/uritokencancelselloffer
   * @see 463F7826125CF50C20746C0B97DC8BA934D3D51CE8A96BD5E5C04357304A3C28 (rk signer)
   * @return void
   */
  protected function parseTypeFields(): void
  {
    $parsedType = $this->data['txcontext'];
    if(!in_array($parsedType, $this->acceptedParsedTypes))
      throw new \Exception('Unhandled parsedType ['.$parsedType.'] on URITokenCancelSellOffer with HASH ['.$this->data['hash'].'] and perspective ['.$this->reference_address.']');

    $nftparser = new NFTTxMutationParser($this->reference_address, $this->tx);
    $nftparserResult = $nftparser->result();
    
    $this->persist = true;
    $this->data['In'] = true;
    $this->data['Counterparty'] = $this->tx->Account;
    $this->data['nft'] = $nftparserResult['nft'];

    if($this->reference_address != $this->tx->Account) {
      $this->persist = false;
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
      'nft' => $this->data['nft'],
      'nftoffers' => [],
      'hooks' => $this->data['hooks'],
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