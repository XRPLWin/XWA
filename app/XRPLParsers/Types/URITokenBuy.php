<?php declare(strict_types=1);

namespace App\XRPLParsers\Types;

use App\XRPLParsers\XRPLParserBase;
use XRPLWin\XRPLNFTTxMutatationParser\NFTTxMutationParser;
use XRPLWin\XRPLTxParticipantExtractor\TxParticipantExtractor;

final class URITokenBuy extends XRPLParserBase
{
  private array $acceptedParsedTypes = ['SENT','SET','REGULARKEYSIGNER','UNKNOWN'];

  /**
   * Parses TrustSet type fields and maps them to $this->data
   * @see https://xrpl.org/transaction-types.html
   * @see DCE7F2DE79CF47C4B6006B1C9A8DC69614C2AC204B4465F0D9CAA16C7E3F9420
   * @return void
   */
  protected function parseTypeFields(): void
  {
    $parsedType = $this->data['txcontext'];
    if(!in_array($parsedType, $this->acceptedParsedTypes))
      throw new \Exception('Unhandled parsedType ['.$parsedType.'] on URITokenBuy with HASH ['.$this->data['hash'].'] and perspective ['.$this->reference_address.']');

    if($parsedType == 'REGULARKEYSIGNER') {
      $this->persist = false;
    }

    $nftparser = new NFTTxMutationParser($this->reference_address, $this->tx);
    $nftparserResult = $nftparser->result();
    //dd($nftparserResult);

    $this->data['In'] = false;
    $this->data['Counterparty'] = $this->tx->Account;
    $this->data['nft'] = $nftparserResult['nft'];

    if($nftparserResult['ref']['direction'] == 'IN') {
      $this->data['In'] = true;
      //counterparty is other acc
      $participants = new TxParticipantExtractor($this->tx);
      $participants = $participants->accounts();

      foreach($participants as $pAcc => $pRoles) {
        if($pAcc == $this->tx->Account) continue;
        if(\in_array('ACCOUNTROOT_NFTOKENOWNER',$pRoles)) {
          $this->data['Counterparty'] = $pAcc;
          break;
        }
      }
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