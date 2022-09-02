<?php

namespace App\Utilities;
#use XRPLWin\XRPL\Client;
#use Illuminate\Support\Facades\Cache;
use App\Models\Map;
use App\Models\Ledgerindex;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\Cache;

/**
 * Maps transactions to local DB for search and calculates intersected Ledgerindex-es.
 */
class Mapper
{
  private array $conditions = [
    //from
    //to
    //txTypes
  ];
  private readonly string $address;

  public function setAddress(string $address): self
  {
    $this->address = $address;
    return $this;
  }

  public function addCondition(string $condition, mixed $value): self
  {
    $this->conditions[$condition] = $value;
    return $this;
  }

  /**
   * Depending ond conditions get list of intersected ledger indexes
   * in which transactions are present.
   * @return array
   */
  public function getIntersectedLedgerindexes(): array
  {
    if( !isset($this->conditions['from']) || !isset($this->conditions['to']) || !isset($this->conditions['txTypes']) )
      throw new \Exception('From To and txTypes conditions are not set');

    $from = Carbon::createFromFormat('Y-m-d', $this->conditions['from']);
    $to = Carbon::createFromFormat('Y-m-d', $this->conditions['to']);

    //fetch ledgerindexes from database in date range
    //$ledgerindexes = Ledgerindex::select('id','day')->whereBetween('day',[$from->format('Y-m-d'),$to->format('Y-m-d')])->get();
    //dd($ledgerindexes->pluck('day','id'));
    $period = CarbonPeriod::since($from)->until($to);

    $foundLedgerIndexesIds = [];
    foreach($this->conditions['txTypes'] as $txTypeNamepart) {
      $foundLedgerIndexesIds[$txTypeNamepart] = [];
    }

    # Phase 1 ALL days per Tx Type
    foreach($period as $day) {

      $ledgerindex = Ledgerindex::getLedgerIndexForDay($day);
      if($ledgerindex) {
        foreach($this->conditions['txTypes'] as $txTypeNamepart) {
          /**
           * Condition: All (all)
           */
          $count = $this->fetchAllCount($ledgerindex, $txTypeNamepart);
          if($count > 0) { //has transactions
            $foundLedgerIndexesIds[$txTypeNamepart]['all'][$ledgerindex] = $count;
          }
          unset($count);
        }
      } else {
        //something went wrong... or out of scope
      }
    }

    # Phase 2 CONDITIONAL IN OR OUT IF EXISTS CONDITION
    foreach($this->conditions['txTypes'] as $txTypeNamepart) { //few
      foreach($foundLedgerIndexesIds[$txTypeNamepart] as $fli) {
        foreach($fli as $ledgerindex => $ledgerIndexTxCount) {
          /**
           * Condition: Direction IN/OUT
           */
          if(isset($this->conditions['dir'])) {
            if($this->conditions['dir'] == 'in') {
              /**
               * Condition: Direction IN (dirin)
               */
              $count = $this->fetchDirinCount($ledgerindex, $txTypeNamepart);
              if($count > 0) { //has transactions
                $foundLedgerIndexesIds[$txTypeNamepart]['dirin'][$ledgerindex] = $count;
              }
              unset($count);
            }
            elseif($this->conditions['dir'] == 'out') {
              /**
               * Condition: Direction OUT (dirout)
               */
              $count = $this->fetchDiroutCount($ledgerindex, $txTypeNamepart);
              if($count > 0) { //has transactions
                $foundLedgerIndexesIds[$txTypeNamepart]['dirout'][$ledgerindex] = $count;
              }
              unset($count);
            }
          }
        }
      }
    }
    dd($foundLedgerIndexesIds);



    /**
     * Now we have all data we need,
     * now reduce ledger indexes to ones that intersect with all conditions
     */
    dd($foundLedgerIndexesIds);
  }

  private function fetchDiroutCount(int $ledgerindex, string $txTypeNamepart): int
  {
    $DModelName = '\\App\\Models\\DTransaction'.$txTypeNamepart;
    $cache_key = 'mapper_dirout_'.$ledgerindex.'_'.$DModelName::TYPE;
    $r = Cache::get($cache_key);
    if($r === null) {
      $map = Map::select('id','condition','count_num'/* ,count_indicator */)
        ->where('address', $this->address)
        ->where('ledgerindex_id',$ledgerindex)
        ->where('type',$DModelName::TYPE)
        ->where('condition','dirout')
        ->first();
  
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
          ->where('SK','between',[$li->ledger_index_first,$li->ledger_index_last + 0.99999])
          ->whereNull('in') //check value presence (in attribute always does not exists if out)
          ->count();
  
        $map = new Map;
        $map->address = $this->address;
        $map->ledgerindex_id = $ledgerindex;
        $map->type = $DModelName::TYPE;
        $map->condition = 'dirout';
        $map->count_num = $DModelTxCount;
        //$map->count_indicator = '='; //indicates that count is exact (=)
        $map->created_at = now();
        $map->save();
      }
  
      $r = $map->count_num;
      Cache::put( $cache_key, $r, 2629743); //2629743 seconds = 1 month
    }
    
    return $r;
  }

  private function fetchDirinCount(int $ledgerindex, string $txTypeNamepart): int
  {
    $DModelName = '\\App\\Models\\DTransaction'.$txTypeNamepart;
    $cache_key = 'mapper_dirin_'.$ledgerindex.'_'.$DModelName::TYPE;
    $r = Cache::get($cache_key);
    if($r === null) {
      $map = Map::select('id','condition','count_num'/* ,count_indicator */)
        ->where('address', $this->address)
        ->where('ledgerindex_id',$ledgerindex)
        ->where('type',$DModelName::TYPE)
        ->where('condition','dirin')
        ->first();
  
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
          ->where('SK','between',[$li->ledger_index_first,$li->ledger_index_last + 0.99999])
          ->whereNotNull('in') //check value presence (in attribute always true if in)
          ->count();
  
        $map = new Map;
        $map->address = $this->address;
        $map->ledgerindex_id = $ledgerindex;
        $map->type = $DModelName::TYPE;
        $map->condition = 'dirin';
        $map->count_num = $DModelTxCount;
        //$map->count_indicator = '='; //indicates that count is exact (=)
        $map->created_at = now();
        $map->save();
      }
  
      $r = $map->count_num;
      Cache::put( $cache_key, $r, 2629743); //2629743 seconds = 1 month
    }
    
    return $r;
  }

  /**
   * Fetches list of indexes for time range and types
   * From cache first, then from db, else generate from DyDB.
   * Appliable to ALL transaction types.
   * @param int $ledgerindex - id from ledgerindexes table
   * @param string $txTypeNamepart - \App\Models\DTransaction<NAMEPART>
   * @return int transactions count
   */
  private function fetchAllCount(int $ledgerindex, string $txTypeNamepart): int
  {
    $DModelName = '\\App\\Models\\DTransaction'.$txTypeNamepart;

    $cache_key = 'mapper_all_'.$ledgerindex.'_'.$DModelName::TYPE;
    $r = Cache::get($cache_key);
    if($r === null) {
      $map = Map::select('id','condition','count_num'/* ,count_indicator */)
        ->where('address', $this->address)
        ->where('ledgerindex_id',$ledgerindex)
        ->where('type',$DModelName::TYPE)
        ->where('condition','all')
        ->first();
  
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
          ->where('SK','between',[$li->ledger_index_first,$li->ledger_index_last + 0.99999])
          ->count();
  
        $map = new Map;
        $map->address = $this->address;
        $map->ledgerindex_id = $ledgerindex;
        $map->type = $DModelName::TYPE;
        $map->condition = 'all';
        $map->count_num = $DModelTxCount;
        //$map->count_indicator = '='; //indicates that count is exact (=)
        $map->created_at = now();
        $map->save();
      }
  
      $r = $map->count_num;
      Cache::put( $cache_key, $r, 2629743); //2629743 seconds = 1 month
    }
    
    return $r;
  }


}