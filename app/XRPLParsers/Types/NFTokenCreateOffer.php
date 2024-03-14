<?php declare(strict_types=1);

namespace App\XRPLParsers\Types;

use App\XRPLParsers\XRPLParserBase;
use XRPLWin\XRPLNFTTxMutatationParser\NFTTxMutationParser;

final class NFTokenCreateOffer extends XRPLParserBase
{
  private array $acceptedParsedTypes = ['SET','RECEIVED','REGULARKEYSIGNER','UNKNOWN'];

  /**
   * Parses NFTokenCreateOffer type fields and maps them to $this->data
   * @see https://xrpl.org/transaction-types.html
   * @see 36E42A76F46711318C27247E4DA3AE962E6976EC6F44917F15E37EC5A9DA2352 - rBgyjCQLVdSHwKVAhCZNTbmDsFHqLkzZdw - created
   * @see 0C9CF542A766EBC1211EEE4F6A5A972DA0309E6283CE07F7E65E45352322D650 - rBgyjCQLVdSHwKVAhCZNTbmDsFHqLkzZdw - destination
   * @return void
   */
  protected function parseTypeFields(): void
  {
    $parsedType = $this->data['txcontext'];
    if(!in_array($parsedType, $this->acceptedParsedTypes))
      throw new \Exception('Unhandled parsedType ['.$parsedType.'] on NFTokenCreateOffer with HASH ['.$this->data['hash'].'] and perspective ['.$this->reference_address.']');

    $nftparser = new NFTTxMutationParser($this->reference_address, $this->tx);
    $nftparserResult = $nftparser->result();

    $this->data['nft'] = $nftparserResult['nft'];
    $this->data['nftoffers'] = [];
    if(isset($this->meta->offer_id)) {
      $this->data['nftoffers'] = [$this->meta->offer_id];
    }

    $this->transaction_type_class = ($nftparserResult['context'] === 'SELL') ? 'NFTokenCreateOffer_Sell':'NFTokenCreateOffer_Buy';

    $this->data['Counterparty'] = $this->tx->Account;

    $this->data['In'] = false;
    if(isset($this->tx->Destination) && $this->reference_address == $this->tx->Destination)
      $this->data['In'] = true;
    
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
      'nft' => (string)$this->data['nft'],
      'nftoffers' => (array)$this->data['nftoffers'],
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