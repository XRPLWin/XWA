<?php declare(strict_types=1);

namespace App\XRPLParsers\Types;

use App\XRPLParsers\XRPLParserBase;

final class Payment extends XRPLParserBase
{
  private array $acceptedParsedTypes = ['SENT','RECEIVED','TRADE','REGULARKEYSIGNER','SET','UNKNOWN'];
  /**
   * Parses Payment type fields and maps them to $this->data
   * Accepted parsedType: SENT|RECEIVED|TRADE|UNKNOWN
   * UNKNOWN is special case when this accounts perspective offer is furfilled due to path taken.
   * @see https://xrpl.org/transaction-types.html
   * @return void
   */
  protected function parseTypeFields(): void
  {
    $parsedType = $this->data['txcontext'];
    if(!in_array($parsedType, $this->acceptedParsedTypes))
      throw new \Exception('Unhandled parsedType ['.$parsedType.'] on Payment with HASH ['.$this->data['hash'].'] and perspective ['.$this->reference_address.']');

    # Sub-Type
    if($parsedType === 'TRADE' || $parsedType === 'SET') {

      //Eg. Trade based on offer, or self to self, trustline shift, ...

      $this->transaction_type_class = 'Payment_BalanceChange';

      if($this->tx->Account == $this->tx->Destination) {
        /**
         * Source and destination are the same, this is exchange within TRADE context.
         * A payment can have the same account as both the source and destination, 
         * which allows account's to utilize pathfinding to convert from one currency to another.
         */
        $this->transaction_type_class = 'Payment_Exchange';
      }
    } else {
      //set this as balance change if ref account is issuer of traded currency
      //DeliverMax?
      if(isset($this->tx->Amount->issuer) && $this->tx->Amount->issuer == $this->reference_address)
        $this->transaction_type_class = 'Payment_BalanceChange';
    }

    # Counterparty
    if($parsedType === 'SENT') {
      // Counterparty is destination
      $this->data['Counterparty'] = $this->tx->Destination;
    } elseif( $parsedType === 'RECEIVED' ) {
      //Counterparty is source
      $this->data['Counterparty'] = $this->tx->Account;
    } elseif( $parsedType === 'TRADE' ) {
      $this->data['Counterparty'] = $this->tx->Account;
    } elseif( $parsedType === 'UNKNOWN' ) {
      $this->data['Counterparty'] = $this->tx->Account;
      if(!isset($this->data['eventList']['primary']) && !isset($this->data['eventList']['secondary']) && !\array_key_exists('Fee', $this->data)) {
        $this->persist = false; //do not persist if no balance changes at this account
      }
    } elseif( $parsedType === 'REGULARKEYSIGNER' ) {
      $this->data['Counterparty'] = $this->tx->Account;
      $this->persist = false;
    } elseif($parsedType === 'SET') {
      $this->data['Counterparty'] = $this->tx->Destination;
    } else {
      //todo get counterparty from $this->tx->Account if this is intermediate - check this
      throw new \Exception('Unhandled Counterparty for parsedtype ['.$parsedType.'] on Payment with HASH ['.$this->data['hash'].'] and perspective ['.$this->reference_address.']');
    }


    # Source and destination tags
    $this->data['DestinationTag'] = isset($this->tx->DestinationTag) ? $this->tx->DestinationTag:null;
    $this->data['SourceTag'] = isset($this->tx->SourceTag) ? $this->tx->SourceTag:null;

    //@see https://xrpl.org/payment.html#payment-flags
    //$isPartialPayment = xrpl_has_flag($this->tx->Flags, 131072);

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
          $this->data['Currency2'] = $this->data['eventList']['secondary']['currency'][0];
          //Counterparty is list of counterparty participants, and value is SUM of balance changes
          //throw new \Exception('Unhandled Counterparty Array for parsedtype ['.$parsedType.'] on Payment with HASH ['.$this->data['hash'].'] for perspective ['.$this->reference_address.']');
        } else {
          $this->data['Issuer2'] = $this->data['eventList']['secondary']['counterparty'];
          $this->data['Currency2'] = $this->data['eventList']['secondary']['currency'];
        }
        
      }
    }

    # Offers start (cross-currency payments and currency conversion can affect offers)
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

    /**
     * dt - destination tag, stored as string
     */
    if($this->data['DestinationTag'] !== null)
      $r['dt'] = (string)$this->data['DestinationTag'];
    
    /**
     * st - source tag, stored as string
     */
    if($this->data['SourceTag'] !== null)
      $r['st'] = (string)$this->data['SourceTag'];

    return $r;
  }



}