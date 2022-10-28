<?php declare(strict_types=1);

namespace App\XRPLParsers\Utils;

use Brick\Math\BigDecimal;

final class BalanceChanges
{

  private readonly \stdClass $meta;
  private array $result = [];

  /**
   * @param \stdClass $metadata - Payment transaction metadata
   */
  public function __construct(\stdClass $metadata)
  {
    $this->meta = $metadata;

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
          $quantities[$trustlineQuantity[1]['account']][] = $trustlineQuantity[1];
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
    $this->result = \array_values($final);
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

    return [
      'account' => (string)$account,
      'balance' => [
        'currency' => 'XRP',
        'value' => (string)BigDecimal::of(drops_to_xrp($value->toInt()))->stripTrailingZeros()
      ]
    ];
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
        'value' => (string)$value->stripTrailingZeros()
      ]
    ];
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
    return [
      'account' => $balanceChange['balance']['issuer'],
      'balance' => [
        'issuer' => $balanceChange['account'],
        'currency' => $balanceChange['balance']['currency'],
        'value' => (string)$negatedBalance->stripTrailingZeros()
      ]
    ];
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
      $node->PreviousTxnLgrSeq = isset($node->PreviouTxnLgrSeq) ? $node->PreviouTxnLgrSeq : null; //artefact?
      $r[] = $node;
    }
    return $r;
  }

  /**
   * @return array [ [ 'account' => string, 'balances' => [ ['currency', 'issuer', 'value' ], ... ] ], ... ]
   */
  public function result(): array
  {
    return $this->result;
  }
}