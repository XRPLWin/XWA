<?php

namespace App\Utilities\Mapper;

use App\Utilities\Mapper\FilterInterface;
use Illuminate\Support\Facades\Cache;
use App\Models\Map;
use App\Models\Ledgerindex;

class FilterCounterparty implements FilterInterface {
    
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
   * Returns array with count information for this filter.
   * @return array
   */
  public function reduce(): array
  {
    $cpFirstFewLetters = \substr($this->conditions['cp'],1,2); //rAccount.. = Ac

    $r = [];
    //dd($this->foundLedgerIndexesIds);
    foreach($this->txTypes as $txTypeNamepart) {
      $r[$txTypeNamepart] = [];
      foreach($this->foundLedgerIndexesIds[$txTypeNamepart] as $ledgerindex => $countTotalReduced) {
        if($countTotalReduced['total'] == 0 || $countTotalReduced['found'] == 0) continue; //no transactions here, skip
        $r[$txTypeNamepart][$ledgerindex] = ['total' => $countTotalReduced['total'], 'found' => 0, 'e' => 'eq'];

        $count = $this->fetchCount($ledgerindex, $txTypeNamepart, $cpFirstFewLetters);
        if($count > 0) { //has transactions
          $r[$txTypeNamepart][$ledgerindex] = ['total' => $countTotalReduced['total'], 'found' => $count, 'e' => 'lte'];
        }
        unset($count);
      }
    }
    return $r;
  }

  private function conditionName(string $cpFirstFewLetters)
  {
    return 'cp_'.$cpFirstFewLetters;
  }

  private function fetchCount(int $ledgerindex, string $txTypeNamepart, string $cpFirstFewLetters): int
  {
    $DModelName = '\\App\\Models\\DTransaction'.$txTypeNamepart;
    $cond = $this->conditionName($cpFirstFewLetters);
    $cache_key = 'mpr'.$this->address.'_'.$cond.'_'.$ledgerindex.'_'.$DModelName::TYPE;
    $r = Cache::get($cache_key);
    //$r = null;
    if($r === null) {
      $map = Map::select('count_num')
        ->where('address', $this->address)
        ->where('ledgerindex_id',$ledgerindex)
        ->where('txtype',$DModelName::TYPE)
        ->where('condition',$cond)
        ->first();
      //$map = null;
      if(!$map)
      {
        //no records found, query DyDB for this day, for this type and save
        $li = Ledgerindex::select('ledger_index_first','ledger_index_last')->where('id',$ledgerindex)->first();
        if(!$li) {
          //clear cache then then/instead exception?
          throw new \Exception('Unable to fetch Ledgerindex of ID (previously cached): '.$ledgerindex);
          //return 0; //something went wrong
        }
        $DModelTxCount = $DModelName::where('PK',$this->address.'-'.$DModelName::TYPE)
          ->where('SK','between',[$li->ledger_index_first,$li->ledger_index_last + 0.9999])
          ->where('r', 'begins_with','r'.$cpFirstFewLetters) //check value presence (in attribute always does not exists if out)
          //->toDynamoDbQuery()
          ->count();

        $map = new Map;
        $map->address = $this->address;
        $map->ledgerindex_id = $ledgerindex;
        $map->txtype = $DModelName::TYPE;
        $map->condition = $cond;
        $map->count_num = $DModelTxCount;
        $map->created_at = now();
        $map->save();
      }
  
      $r = $map->count_num;
      Cache::put( $cache_key, $r, 2629743); //2629743 seconds = 1 month
    }
    
    return $r;
  }
}