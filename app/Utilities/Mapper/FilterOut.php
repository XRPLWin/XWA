<?php

namespace App\Utilities\Mapper;

use Illuminate\Support\Facades\Cache;
use App\Models\Map;
use App\Models\Ledgerindex;

class FilterOut extends FilterBase {
    
  private readonly string $address;
  private readonly array $foundLedgerIndexesIds;
  private readonly array $conditions;
  private array $allowedTxTypes = ['Payment','Trustset'];
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
   * Returns array with count information for this filter.
   * @return array
   */
  public function reduce(): array
  {
    $r = [];

    foreach($this->txTypes as $txTypeNamepart) {
      $r[$txTypeNamepart] = [];
      if(isset($this->foundLedgerIndexesIds[$txTypeNamepart])) {
        foreach($this->foundLedgerIndexesIds[$txTypeNamepart] as $ledgerindex => $countTotalReduced) {
          $r[$txTypeNamepart][$ledgerindex] = ['total' => $countTotalReduced['total'], 'found' => 0, 'e' => 'eq', 'breakpoints' => $countTotalReduced['breakpoints']];
          if($countTotalReduced['total'] == 0 || $countTotalReduced['found'] == 0) continue; //no transactions here, skip

          $count = $this->fetchCount($ledgerindex, $txTypeNamepart);
          if($count > 0) { //has transactions
            $r[$txTypeNamepart][$ledgerindex] = ['total' => $countTotalReduced['total'], 'found' => $count, 'e' => self::calcEqualizer($countTotalReduced['e'], 'eq'), 'breakpoints' => $countTotalReduced['breakpoints']];
          }
          unset($count);
        }
      }
    }
    return $r;
  }

  private function conditionName()
  {
    return 'dirout';
  }

  private function fetchCount(int $ledgerindex, string $txTypeNamepart): int
  {
    $DModelName = '\\App\\Models\\DTransaction'.$txTypeNamepart;
    $cond = $this->conditionName();
    $cache_key = 'mpr'.$this->address.'_'.$cond.'_'.$ledgerindex.'_'.$DModelName::TYPE;
    $r = Cache::get($cache_key);
    if($r === null) {
      $map = Map::select('count_num')
        ->where('address', $this->address)
        ->where('ledgerindex_id',$ledgerindex)
        ->where('txtype',$DModelName::TYPE)
        ->where('condition',$cond)
        ->first();
  
      if(!$map)
      {
        //no records found, query DyDB for this day, for this type and save
        //$li = Ledgerindex::select('ledger_index_first','ledger_index_last')->where('id',$ledgerindex)->first();
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

        $DModelTxCount = $this->applyQueryCondition($DModelTxCount); //check value presence (in attribute always does not exists if out)
          //->toDynamoDbQuery()
          //->count();
        $count = \App\Utilities\PagedCounter::count($DModelTxCount);
        
        $map = new Map;
        $map->address = $this->address;
        $map->ledgerindex_id = $ledgerindex;
        $map->txtype = $DModelName::TYPE;
        $map->condition = $cond;
        $map->count_num = $count;
        $map->breakpoints = '';
        //$map->breakpoints = $count['breakpoints'];
        $map->created_at = now();
        $map->save();
      }
  
      $r = $map->count_num;
      Cache::put( $cache_key, $r, 2629743); //2629743 seconds = 1 month
    }
    
    return $r;
  }

  /**
   * Check if DyDB item has $value in its data.
   * Checked field depends on filter.
   * @return bool
   */
  public static function itemHasFilter(\App\Models\DTransaction $item, string|int|float|bool $value): bool
  {
    if(!isset($item->in)) return true;
    return (isset($item->in) && !$item->in);
  }

  /**
   * Adds WHERE conditions to query builder if any.
   * @return \BaoPham\DynamoDb\DynamoDbQueryBuilder
   */
  public function applyQueryCondition(\BaoPham\DynamoDb\DynamoDbQueryBuilder $query, ...$params)
  {
    return $query->whereNull('in');
  }

}