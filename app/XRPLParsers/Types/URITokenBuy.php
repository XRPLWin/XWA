<?php declare(strict_types=1);

namespace App\XRPLParsers\Types;

use App\XRPLParsers\XRPLParserBase;
use XRPLWin\XRPLNFTTxMutatationParser\NFTTxMutationParser;

final class URITokenBuy extends XRPLParserBase
{
  private array $acceptedParsedTypes = ['SENT','UNKNOWN'];

  /**
   * Parses TrustSet type fields and maps them to $this->data
   * @see https://xrpl.org/transaction-types.html
   * @return void
   */
  protected function parseTypeFields(): void
  {
    $parsedType = $this->data['txcontext'];
    if(!in_array($parsedType, $this->acceptedParsedTypes))
      throw new \Exception('Unhandled parsedType ['.$parsedType.'] on Invoke with HASH ['.$this->data['hash'].'] and perspective ['.$this->reference_address.']');

    $nftparser = new NFTTxMutationParser($this->reference_address, $this->tx);
    $nftparserResult = $nftparser->result();
    dd($nftparserResult);
    $this->data['In'] = false;
    $this->data['nft'] = $this->tx->URITokenID;

    # Counterparty

    //$this->data['Counterparty'] = $this->tx->Account;
    //$this->data['In'] = false;
    if($parsedType == 'SENT') {
      $this->data['Counterparty'] = $this->tx->Account;
    } elseif($parsedType == 'UNKNOWN') {
      //This could be buyer
      $this->data['Counterparty'] = $this->tx->Account;
    }
    
    
    
    /*elseif($parsedType == 'RECEIVED') {
      $this->data['Counterparty'] = $this->tx->Account;
      $this->data['In'] = true;
    }*/

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