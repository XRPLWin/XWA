<?php declare(strict_types=1);

namespace App\XRPLParsers\Types;

use App\XRPLParsers\XRPLParserBase;
use XRPLWin\XRPLNFTTxMutatationParser\NFTTxMutationParser;
#use XRPLWin\XRPLTxParticipantExtractor\TxParticipantExtractor;

final class URITokenCreateSellOffer extends XRPLParserBase
{
  private array $acceptedParsedTypes = ['SET','RECEIVED','REGULARKEYSIGNER','UNKNOWN'];

  /**
   * Parses URITokenCreateSellOffer type fields and maps them to $this->data
   * Only thing that is persisted is Initiator (Account) perspective.
   * @see https://xrpl.org/transaction-types.html
   * @return void
   */
  protected function parseTypeFields(): void
  {
    $parsedType = $this->data['txcontext'];
    if(!in_array($parsedType, $this->acceptedParsedTypes))
      throw new \Exception('Unhandled parsedType ['.$parsedType.'] on URITokenCreateSellOffer with HASH ['.$this->data['hash'].'] and perspective ['.$this->reference_address.']');

    $nftparser = new NFTTxMutationParser($this->reference_address, $this->tx);
    $nftparserResult = $nftparser->result();

    if($parsedType == 'REGULARKEYSIGNER') {
      $this->persist = false;
    }

    $this->data['In'] = false;
    $this->data['Counterparty'] = $this->tx->Account;
    $this->data['nft'] = $nftparserResult['nft'];

    if($this->reference_address != $this->tx->Account) {
      $this->persist = false;
    }

    if(isset($this->tx->Destination)){
      if($this->reference_address == $this->tx->Destination) {
        $this->data['In'] = true;
      }
    }
    
    # Balance changes from eventList (primary/secondary, both, one, or none)
    # THIS RETURNS NO DATA PER SPECIFICATION OF URITokenCreateSellOffer TYPE!
    if(isset($this->data['eventList']['primary'])) {
      $this->data['Amount'] = $this->data['eventList']['primary']['value'];
      if($this->data['eventList']['primary']['currency'] !== 'XRP') {
        $this->data['Issuer'] = $this->data['eventList']['primary']['counterparty'];
        $this->data['Currency'] = $this->data['eventList']['primary']['currency'];
      }
    }

    # Amount is the offer asking price or null on a2
    if(isset($this->tx->Amount)) {
      if(\is_string($this->tx->Amount)) {
        $this->data['Amount2'] = $this->tx->Amount; //NATIVE
      } else {
        //IOU
        $this->data['Amount2'] = $this->tx->Amount->value;
        $this->data['Issuer2'] = $this->tx->Amount->issuer;
        $this->data['Currency2'] = $this->tx->Amount->currency;
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
      'offers' => [],
      'nft' => $this->data['nft'],
      'nftoffers' => [],
      'hooks' => $this->data['hooks'],
    ];

    if(\array_key_exists('Amount', $this->data))
      $r['a'] = $this->data['Amount'];
    if(\array_key_exists('Amount2', $this->data))
      $r['a2'] = $this->data['Amount2'];
    
    if(\array_key_exists('Issuer', $this->data))
      $r['i'] = $this->data['Issuer'];
    if(\array_key_exists('Issuer2', $this->data))
      $r['i2'] = $this->data['Issuer2'];

    if(\array_key_exists('Currency', $this->data))
      $r['c'] = $this->data['Currency'];
    if(\array_key_exists('Currency2', $this->data))
      $r['c2'] = $this->data['Currency2'];

    if(\array_key_exists('Fee', $this->data))
      $r['fee'] = $this->data['Fee'];

    return $r;
  }
}