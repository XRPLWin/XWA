<?php declare(strict_types=1);

namespace App\XRPLParsers\Utils;

use Brick\Math\BigDecimal;

/**
 * Retrieves list of balance changes for all involving accounts in provided XRPL transaction metadata.
 * @see https://github.com/XRPLF/xrpl.js/blob/main/packages/xrpl/src/utils/getBalanceChanges.ts
 */
final class BalanceChanges
{
  private readonly \stdClass $meta;
  private readonly \stdClass $tx;
  private array $result = [];

  /**
   * @param \stdClass $metadata - Transaction metadata
   * @param \stdClass $tx - Transaction
   */
  public function __construct(\stdClass $metadata, \stdClass $tx)
  {
    $this->meta = $metadata;
    $this->tx = $tx;

    # Parse start
    $normalized = $this->normalizeNodes();
  
    $quantities = [];
    foreach($normalized as $node) { 
      if($node->LedgerEntryType == 'AccountRoot') {
        $xrpQuantity = $this->getXRPQuantity($node);
        if($xrpQuantity !== null)
          $quantities[$xrpQuantity['account']][] = $xrpQuantity;
      }
    
      if($node->LedgerEntryType == 'RippleState') {
        $trustlineQuantity = $this->getTrustlineQuantity($node);
        if($trustlineQuantity !== null) {
          $quantities[$trustlineQuantity[0]['account']][] = $trustlineQuantity[0];
          $quantities[$trustlineQuantity[1]['account']][] = $trustlineQuantity[1]; //flipped
        }
      }
    }
    # Reorganize quantities array
    $final = [];
    foreach($quantities as $account => $values) {
      //init
      if(!isset($final[$account])) {
        $final[$account] = [
          'account' => $account,
          'balances' => [],
        ];
      }
      foreach($values as $value) {
        $final[$account]['balances'][] = $value['balance'];
      }
    }
    
    $this->result = $final;
  }

  /**
   * @see https://github.com/ripple/rippled-historical-database/blob/1ada26fa83ebc5a224e01ff49ccb95c5ac453827/lib/ledgerParser/balanceChanges.js#L23
   */
  private function computeBalanceChangeType(array $computedData): ?string
  {
    $tx = $this->tx;

    // exchange issuer/intermediary
    if($tx->TransactionType == 'OfferCreate' && BigDecimal::of($computedData['balance']['value'])->isLessThan(0))
      return 'intermediary';

    // offer creates are all exchanges
    if($tx->TransactionType == 'OfferCreate')
      return 'exchange';

    if($tx->TransactionType == 'Payment') {

      // not a real payment issuer on exchange
      if($tx->Account === $tx->Destination && BigDecimal::of($computedData['balance']['value'])->isLessThan(0)) {
        return 'intermediary';
      }

      // not a real payment just an exchange
      if($tx->Account === $tx->Destination)
        return 'exchange';
  
      // payment currency and destination account
      if($computedData['account'] === $tx->Destination && isset($tx->Amount->currency) && $tx->Amount->currency === $computedData['balance']['currency'])
        return 'payment_destination';

      // payment currency = XRP and destination account
      if($computedData['account'] === $tx->Destination && !isset($tx->Amount->currency) && $computedData['balance']['currency'] === 'XRP')
        return 'payment_destination';

      // source currency and source account
      if($computedData['account'] === $tx->Account && 
        isset($tx->SendMax->currency) && 
        $tx->SendMax->currency === $computedData['balance']['currency']
      )
        return 'payment_source';

      // source currency = XRP and source account
      if($computedData['account'] === $tx->Account && 
        isset($tx->SendMax) &&  //TODO check this!
        $computedData['balance']['currency'] === 'XRP'
      )
        return 'payment_source';

      // source account and destination currency
      if($computedData['account'] === $tx->Account && 
        isset($tx->Amount->currency) &&
        $tx->Amount->currency === $computedData['balance']['currency']
      )
        return 'payment_source';
      
      // source account and destination currency
      if($computedData['account'] === $tx->Account && 
        !isset($tx->Amount->currency) &&
        $computedData['balance']['currency'] === 'XRP'
      )
        return 'payment_source';

      // issuer
      if(BigDecimal::of($computedData['balance']['value'])->isLessThan(0))
        return 'intermediary';

      // not sender, receiver, or different currency
      return 'exchange';

    }
    
    return null;
  }

  /**
   * @return ?array [ 'account', 'balance' ]
   */
  private function getXRPQuantity(\stdClass $node): ?array
  {
    $value = $this->computeBalanceChange($node);
    if($value === null)
      return null;

    $account = '';
    if($node->FinalFields && $node->FinalFields->Account)
      $account = $node->FinalFields->Account;
    elseif($node->NewFields && $node->NewFields->Account)
      $account = $node->NewFields->Account;

    $result =  [
      'account' => (string)$account,
      'balance' => [
        'currency' => 'XRP',
        'value' => (string)BigDecimal::of(drops_to_xrp($value->toInt()))->stripTrailingZeros(),
        'type' => null,
      ]
    ];
    //dump($value->toInt());
    $result['balance']['type'] = $this->computeBalanceChangeType($result);
    return $result;
  }

  private function getTrustlineQuantity(\stdClass $node): ?array
  {
    $value = $this->computeBalanceChange($node);
    if($value === null)
      return null;
    
   /**
    * A trustline can be created with a non-zero starting balance.
    * If an offer is placed to acquire an asset with no existing trustline,
    * the trustline can be created when the offer is taken.
    */
    $fields = ($node->NewFields === null) ? $node->FinalFields : $node->NewFields;

    //the balance is always from low node's perspective

    $result = [
      'account' => (isset($fields->LowLimit->issuer)) ? $fields->LowLimit->issuer : '',
      'balance' => [
        'issuer' => (isset($fields->HighLimit->issuer)) ? $fields->HighLimit->issuer : '',
        'currency' => (isset($fields->Balance)) ? $fields->Balance->currency : '',
        'value' => (string)$value->stripTrailingZeros(),
        'type' => null,
      ]
    ];

    $result['balance']['type'] = $this->computeBalanceChangeType($result);

    return [$result,  $this->flipTrustlinePerspective($result)];
  }

  private function computeBalanceChange(\stdClass $node): ?BigDecimal
  {
    $value = null;

    if($node->NewFields !== null && isset($node->NewFields->Balance)) {
      $value = $this->getValue($node->NewFields->Balance);
    } elseif($node->PreviousFields !== null && isset($node->PreviousFields->Balance) && $node->FinalFields !== null && isset($node->FinalFields->Balance)) {
      $value = $this->getValue($node->FinalFields->Balance)->minus($this->getValue($node->PreviousFields->Balance));
    }

    if($value === null)
      return null;

    if($value->isEqualTo(0))
      return null;

      /*if((string)$value == 184) {
        dd($node);
      }*/

    return $value;
  }

  private function getValue($amount): BigDecimal
  {
    if(\is_string($amount))
      return BigDecimal::of($amount);
    return BigDecimal::of($amount->value);
  }

  private function flipTrustlinePerspective(array $balanceChange): array
  {
    $negatedBalance = BigDecimal::of($balanceChange['balance']['value'])->negated();
    $result = [
      'account' => $balanceChange['balance']['issuer'],
      'balance' => [
        'issuer' => $balanceChange['account'],
        'currency' => $balanceChange['balance']['currency'],
        'value' => (string)$negatedBalance->stripTrailingZeros(),
        'type' => null,
      ]
    ];
    
    $result['balance']['type'] = $this->computeBalanceChangeType($result);

    return $result;
  }

  /**
   * 'CreatedNode' | 'ModifiedNode' | 'DeletedNode'
   * @return array [ object, ... ]
   */
  private function normalizeNodes() : array
  {
    $r = [];
    foreach($this->meta->AffectedNodes as $n) {
      $diffType = \array_keys((array)$n)[0];
      $node = $n->{$diffType};

      $node->NodeType = $diffType;
      $node->LedgerEntryType = $node->LedgerEntryType;
      $node->LedgerIndex = $node->LedgerIndex;
      $node->NewFields = isset($node->NewFields) ? $node->NewFields : null;
      $node->FinalFields = isset($node->FinalFields) ? $node->FinalFields : null;
      $node->PreviousFields = isset($node->PreviousFields) ? $node->PreviousFields : null;
      $node->PreviousTxnID = isset($node->PreviousTxnID) ? $node->PreviousTxnID : null;
      $node->PreviousTxnLgrSeq = isset($node->PreviousTxnLgrSeq) ? $node->PreviousTxnLgrSeq : null;
      $r[] = $node;
    }
    return $r;
  }

  /**
   * @param bool $withKeys If true it will return account as Key in array, false (default) will return un-keyed array.
   * @return array [ ?'rAccount1' => [ 'account' => string, 'balances' => [ ['currency', 'issuer', 'value' ], ... ] ], ... ]
   */
  public function result(bool $withKeys = false): array
  {
    if($withKeys)
      return $this->result;
    
    return \array_values($this->result);
  }
}
