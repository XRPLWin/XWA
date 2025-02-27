<?php declare(strict_types=1);

namespace App\XRPLParsers\Types;

use App\XRPLParsers\XRPLParserBase;
/**
 * @todo 5EA126813D68B5049BA2E9D380759E117A274A7E55FC124AC2A14C81595078F9 - ok - interasting
 */
final class OfferCreate extends XRPLParserBase
{
  private array $acceptedParsedTypes = ['SET','TRADE','SENT','REGULARKEYSIGNER','UNKNOWN'];
  /**
   * Parses TrustSet type fields and maps them to $this->data
   * @see https://xrpl.org/transaction-types.html
   * @see https://playground.xrpl.win/play/xrpl-transaction-mutation-parser?hash=0CD69FD1F0A890CC57CDA430213FD294F7D65FF4A0F379A0D09D07A222D324E6&ref=rf1BiGeXwwQoi8Z2ueFYTEXSwuJYfV2Jpn
   *      Tis is older transaction with missing fileds txcontent is UNKNOWN
   * @see https://playground.xrpl.win/play/xrpl-transaction-mutation-parser?hash=D78DB577E7726AF26D5F48A36C968C10B33AFDAF79C2D110E151D3A328DB45C1&ref=r9XRCuhi5uDZY9gPvpiCy6kWDjeH3wM4LU
   * @see MULTI: https://playground.xrpl.win/play/xrpl-transaction-mutation-parser?hash=B36F2C42D4AEC872188BC2143D50936F8B3898F7D401ED014CF44ED062D2BBD3&ref=rsoLo2S1kiGeCcn6hCUXVrCpGMWLrRrLZz
   * @see EB7EECA6DBA1C9F9D6FE0848F9A1D8D3BBCF133C688ACF782B24D5AC885A11BE rhDdsLwq7xYxGWGgDbMzPkKPD6WS2bhHDQ - regularkeysigner
   * @return void
   */
  protected function parseTypeFields(): void
  {
    $parsedType = $this->data['txcontext'];
    if(!in_array($parsedType, $this->acceptedParsedTypes)) {
      //dd($this->data);
      throw new \Exception('Unhandled parsedType ['.$parsedType.'] on OfferCreate with HASH ['.$this->data['hash'].'] and perspective ['.$this->reference_address.']');
    }
    
     # Sub-Type
    if($parsedType === 'TRADE' || $parsedType === 'SENT') {
      //Eg. Trade based on offer, this can be auto-furfilled offer or partially furfilled offer when creating new.
      $this->transaction_type_class = 'OfferCreate_Trade';
    } elseif ($parsedType == 'REGULARKEYSIGNER') {
      $this->persist = false;
    }

    # Counterparty is always transaction account (creator)
    $this->data['Counterparty'] = $this->tx->Account;

    # Direction IN if this is not reference account's offer
    if($this->tx->Account !== $this->reference_address)
      $this->data['In'] = true;

    # Balance changes from eventList (primary/secondary, both, one, or none)
    if(isset($this->data['eventList']['primary'])) {
      $this->data['Amount'] = $this->data['eventList']['primary']['value'];
      if($this->data['eventList']['primary']['currency'] !== 'XRP') {
        $this->data['Issuer'] = $this->data['eventList']['primary']['counterparty'];
        $this->data['Currency'] = $this->data['eventList']['primary']['currency'];
      }
    }
    
    if(isset($this->data['eventList']['secondary'])) {
      $this->data['Amount2'] = $this->data['eventList']['secondary']['value'];
      if($this->data['eventList']['secondary']['currency'] !== 'XRP') {
        if(is_array($this->data['eventList']['secondary']['counterparty'])) {
          //Secondary counterparty is rippled trough reference account
         
          $this->data['Issuer2'] = $this->data['eventList']['secondary']['counterparty'][0];
          if(is_array($this->data['eventList']['secondary']['currency'])) {
            $this->data['Currency2'] = $this->data['eventList']['secondary']['currency'][0];
          } else {
            $this->data['Currency2'] = $this->data['eventList']['secondary']['currency'];
          }
          //Counterparty is list of counterparty participants, and value is SUM of balance changes
          //throw new \Exception('Unhandled Counterparty Array for parsedtype ['.$parsedType.'] on Payment with HASH ['.$this->data['hash'].'] for perspective ['.$this->reference_address.']');
        } else {
          $this->data['Issuer2'] = $this->data['eventList']['secondary']['counterparty'];
          $this->data['Currency2'] = $this->data['eventList']['secondary']['currency'];
        }
        
      }
    }

    # Offers start
    $this->data['offers'] = [];
    //search for affected offers in metadata
    if(isset($this->meta->AffectedNodes)) {
      foreach($this->meta->AffectedNodes as $n) {
        if(isset($n->CreatedNode) && $n->CreatedNode->LedgerEntryType == 'Offer') {
          if(isset($n->CreatedNode->NewFields->Sequence)) {
            $this->data['offers'][] = $n->CreatedNode->NewFields->Account.':'.$n->CreatedNode->NewFields->Sequence;
          }
        }
        if(isset($n->ModifiedNode) && $n->ModifiedNode->LedgerEntryType == 'Offer') {
          if(isset($n->ModifiedNode->FinalFields->Sequence)) {
            $this->data['offers'][] = $n->ModifiedNode->FinalFields->Account.':'.$n->ModifiedNode->FinalFields->Sequence;
          }
        }
        if(isset($n->DeletedNode) && $n->DeletedNode->LedgerEntryType == 'Offer') {
          if(isset($n->DeletedNode->FinalFields->Sequence)) {
            $this->data['offers'][] = $n->DeletedNode->FinalFields->Account.':'.$n->DeletedNode->FinalFields->Sequence;
          }
        }
      }
    }
    $this->data['offers'] = \array_unique($this->data['offers']);
    # Offers end
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
      'offers' => (array)$this->data['offers'],
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