<?php

namespace App\Utilities\Mapper;

use Illuminate\Support\Facades\Cache;
use App\Models\Map;
use App\Models\Ledgerindex;

class FilterSourcetag extends FilterBase {
    
  private readonly string $address;
  private readonly array $foundLedgerIndexesIds;
  private readonly array $conditions;
  private array $allowedTxTypes = ['Payment'];
  private readonly array $txTypes;

  public function __construct(string $address, array $conditions, array $foundLedgerIndexesIds)
  {
    $this->address = $address;
    $this->conditions = $conditions;
    $this->foundLedgerIndexesIds = $foundLedgerIndexesIds;

    $txTypes = [];
    foreach($this->conditions['txTypes'] as $txType) {
      if(in_array($txType,$this->allowedTxTypes))
        $txTypes[] = $txType;
    }
    $this->txTypes = $txTypes;
  }

  /**
   * 123456... = 12
   * @return string
   */
  public static function parseToNonDefinitiveParam(string $param): string
  {
    return \substr($param,0,2);
  }

  /**
   * Returns array with count information for this filter.
   * @return array
   */
  public function reduce(): array
  {
    $FirstFewLetters = self::parseToNonDefinitiveParam($this->conditions['st']);
    $r = [];
    foreach($this->txTypes as $txTypeNamepart) {
      $r[$txTypeNamepart] = [];
      if(isset($this->foundLedgerIndexesIds[$txTypeNamepart])) {
        foreach($this->foundLedgerIndexesIds[$txTypeNamepart] as $ledgerindex => $countTotalReduced) {
          $r[$txTypeNamepart][$ledgerindex] = [
            'total' => $countTotalReduced['total'],
            'found' => 0,
            'e' => 'eq',
            'first' => $countTotalReduced['first'],
            'next' => $countTotalReduced['next']
          ];
          if($countTotalReduced['total'] == 0 || $countTotalReduced['found'] == 0) continue; //no transactions here, skip
  
          $ledgerindexEx = $this->explodeLedgerindex($ledgerindex);
          $count = $this->fetchCount($ledgerindexEx[0], $ledgerindexEx[1], $txTypeNamepart, $FirstFewLetters, $countTotalReduced['first'], $countTotalReduced['next']);
          if($count > 0) { //has transactions
            $r[$txTypeNamepart][$ledgerindex] = [
              'total' => $countTotalReduced['total'],
              'found' => $count,
              'e' => self::calcEqualizer($countTotalReduced['e'], 'lte'),
              'first' => $countTotalReduced['first'],
              'next' => $countTotalReduced['next']
            ];
          }
          unset($count);
        }
      }
    }
    return $r;
  }

  private function conditionName(string $FirstFewLetters)
  {
    return 'st_'.$FirstFewLetters;
  }

  /**
   * @param int $ledgerindex - local internal LedgerIndex->id
   * @param int $subpage - subpage within LedgerIndex
   * @param string $txTypeNamepart
   * @param string $FirstFewLetters - part of filter to do non-definitive filtering on
   * @param ?string $first_exclusive
   *   - if null then use LedgerIndex->ledger_index_first as inclusive starting SK starting point
   *   - if string then use that for afterKey (exclusive)
   */
  private function fetchCount(int $ledgerindex, int $subpage, string $txTypeNamepart, string $FirstFewLetters, ?string $first_exclusive, ?string $nextSK): int
  {
    $DModelName = '\\App\\Models\\DTransaction'.$txTypeNamepart;
    $cond = $this->conditionName($FirstFewLetters);
    $cache_key = 'mpr'.$this->address.'_'.$cond.'_'.$ledgerindex.'_'.$subpage.'_'.$DModelName::TYPE;

    $r = Cache::get($cache_key);
    
    if($r === null) {
      $map = Map::select('count_num')
        ->where('address', $this->address)
        ->where('ledgerindex_id',$ledgerindex)
        ->where('page',$subpage)
        ->where('txtype',$DModelName::TYPE)
        ->where('condition',$cond)
        ->first();
      
      if(!$map)
      {
        $li = Ledgerindex::getLedgerindexData($ledgerindex);
        if(!$li) {
          //clear cache then then/instead exception?
          throw new \Exception('Unable to fetch Ledgerindex of ID (previously cached): '.$ledgerindex);
          //return 0; //something went wrong
        }
        $DModelTxCount = $DModelName::where('PK',$this->address.'-'.$DModelName::TYPE);

        if($li[1] == -1)
          $DModelTxCount = $DModelTxCount->where('SK','>=',$li[0]);
        else
          $DModelTxCount = $DModelTxCount->where('SK','between',[$li[0],$li[1] + 0.9999]);

        $DModelTxCount = $this->applyQueryCondition($DModelTxCount,$FirstFewLetters);

        if($first_exclusive !== null)
          $DModelTxCount->afterKey(['PK' => $this->address.'-'.$DModelName::TYPE, 'SK' => (float)$first_exclusive]);

        $count = $DModelTxCount->pagedCount();

        # Sanity check start
        if($count->lastKey) {
          if($count->lastKey['SK']['N'] != $nextSK)
            throw new \Exception('Critical error: page count in filter returned lastKey evaluation which does not match inherited next SK');
        } else {
          if($nextSK !== null)
            throw new \Exception('Critical error: page count in filter did not returned lastKey evaluation which does not match inherited next SK');
        }
        # Sanity check end

        $map = new Map;
        $map->address = $this->address;
        $map->ledgerindex_id = $ledgerindex;
        $map->txtype = $DModelName::TYPE;
        $map->condition = $cond;
        $map->count_num = $count->count;
        $map->page = $subpage;
        $map->created_at = now();
        $map->save();
      }
  
      $r = $map->count_num;
      Cache::put( $cache_key, $r, 2629743); //2629743 seconds = 1 month
    }
    
    return $r;
  }

  /**
   * Adds WHERE conditions to query builder if any.
   * @return \BaoPham\DynamoDb\DynamoDbQueryBuilder
   */
  public function applyQueryCondition(\BaoPham\DynamoDb\DynamoDbQueryBuilder $query, ...$params)
  {
    return $query->where('st', 'begins_with',$params[0]);
  }

  /**
   * Check if DyDB item has $value in its data.
   * Checked field is 'st', must be exact.
   * @return bool
   */
  public static function itemHasFilter(\App\Models\DTransaction $item, string|int|float|bool $value): bool
  {
    return ((string)$item->st == (string)$value);
  }
}