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
    //...
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
   * Check if dates are correct
   * 1. From is less or equal to to
   * 2. Do not span more than 31 days
   * @return bool
   */
  public function dateRangeIsValid(): bool
  {
    if(!isset($this->conditions['from']) || !isset($this->conditions['to']))
      return false;

    if($this->conditions['from'] == $this->conditions['to'])
      return true;

    $from = Carbon::createFromFormat('Y-m-d', $this->conditions['from']);
    //check if days between dates do not exceed 31 days
    if($from->diffInDays($this->conditions['to']) > 31)
      return false;

    //'from' has to be before 'to'
    if(!$from->isBefore($this->conditions['to']))
      return false;

    //from and to needs to be current date or past
    if($from->isFuture())
      return false;

    $to = Carbon::createFromFormat('Y-m-d', $this->conditions['to']);
    if($to->isFuture())
      return false;
    
    return true;
  }

  /**
   * Depending ond conditions get list of intersected ledger indexes
   * in which transactions are present.
   * @return array
   */
  public function getIntersectedLedgerindexes(): array
  {
    //dump('aa');
    if( !isset($this->conditions['from']) || !isset($this->conditions['to']) || !isset($this->conditions['txTypes']) )
      throw new \Exception('From To and txTypes conditions are not set');

    $from = Carbon::createFromFormat('Y-m-d', $this->conditions['from']);
    $to = Carbon::createFromFormat('Y-m-d', $this->conditions['to']);
    
    //Check if $this->address is synced within time ranges, if not then disallow search.
    $account = AccountLoader::get($this->address);
    if(!$account)
      return [];
   
    $LedgerIndexLastForDay = Ledgerindex::getLedgerIndexLastForDay($to);
    if(!$LedgerIndexLastForDay)
      return [];
    
    if($LedgerIndexLastForDay == -1) {
      //Viewing current day, account should be synced to today atleast
      $LedgerIndexLastForDay = Ledgerindex::getLedgerIndexFirstForDay($to)-1;
    }
    
    if($account->l < $LedgerIndexLastForDay)
      return []; //not synced yet to this ledger index
  
    $period = CarbonPeriod::since($from)->until($to);

    $foundLedgerIndexesIds = [];
    foreach($this->conditions['txTypes'] as $txTypeNamepart) {
      $foundLedgerIndexesIds[$txTypeNamepart] = [];
    }

    # Phase 1 ALL days per Tx Type
    foreach($period as $day) {
      
      $ledgerindex = Ledgerindex::getLedgerindexIdForDay($day);
      //dd( $ledgerindex);
      if($ledgerindex) {
        foreach($this->conditions['txTypes'] as $txTypeNamepart) {
          $count = $this->fetchAllCount($ledgerindex, $txTypeNamepart);
          if($count > 0) { //has transactions
            $foundLedgerIndexesIds[$txTypeNamepart][$ledgerindex] = ['total' => $count, 'found' => $count, 'e' => 'eq']; //[total, reduced, eq (equalizer eq|lte)]
          }
          unset($count);
        }
      } else {
        //something went wrong... or out of scope
     
      }
    
    }
    
    # Phase 2 OPTIONAL CONDITIONS REDUCER:
    //dump($foundLedgerIndexesIds);
    
    if(isset($this->conditions['dir']) && $this->conditions['dir'] == 'in') {
      $Filter = new Mapper\FilterIn($this->address,$this->conditions,$foundLedgerIndexesIds);
      $foundLedgerIndexesIds = $Filter->reduce();
      unset($Filter);
      //echo 'DIRIN: ';dump($foundLedgerIndexesIds);
    } 
    elseif(isset($this->conditions['dir']) && $this->conditions['dir'] == 'out') {
      $Filter = new Mapper\FilterOut($this->address,$this->conditions,$foundLedgerIndexesIds);
      $foundLedgerIndexesIds = $Filter->reduce();
      unset($Filter);
      //echo 'DIROUT: ';dump($foundLedgerIndexesIds);
    }
    
    if(isset($this->conditions['cp'])) {
      
      $Filter = new Mapper\FilterCounterparty($this->address,$this->conditions,$foundLedgerIndexesIds);
      $foundLedgerIndexesIds = $Filter->reduce();
      unset($Filter);
      //echo 'CP: ';dump($foundLedgerIndexesIds);
    }

    if(isset($this->conditions['dt'])) {
      
      $Filter = new Mapper\FilterDestinationtag($this->address,$this->conditions,$foundLedgerIndexesIds);
      $foundLedgerIndexesIds = $Filter->reduce();
      unset($Filter);
      //echo 'DT: ';dump($foundLedgerIndexesIds);
    }

    if(isset($this->conditions['st'])) {
      
      $Filter = new Mapper\FilterSourcetag($this->address,$this->conditions,$foundLedgerIndexesIds);
      $foundLedgerIndexesIds = $Filter->reduce();
      unset($Filter);
      //echo 'ST: ';dump($foundLedgerIndexesIds);
    }
    //echo 'END';
    //dd($foundLedgerIndexesIds);
    return $foundLedgerIndexesIds;
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

    $cache_key = 'mpr'.$this->address.'_all_'.$ledgerindex.'_'.$DModelName::TYPE;
    $r = Cache::get($cache_key);
    //$r = null;
    if($r === null) {
      $map = Map::select('id','condition','count_num'/* ,count_indicator */)
        ->where('address', $this->address)
        ->where('ledgerindex_id',$ledgerindex)
        ->where('txtype',$DModelName::TYPE)
        ->where('condition','all')
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
        $DModelTxCount = $DModelName::where('PK',$this->address.'-'.$DModelName::TYPE);

        if($li->ledger_index_last == -1) //latest
          $DModelTxCount = $DModelTxCount->where('SK','>=',$li->ledger_index_first);
        else
          $DModelTxCount = $DModelTxCount->where('SK','between',[$li->ledger_index_first,$li->ledger_index_last + 0.9999]);

        $DModelTxCount = $DModelTxCount->count();

        //dd($DModelTxCount,$this->address.'-'.$DModelName::TYPE,[$li->ledger_index_first,$li->ledger_index_last + 0.9999]);
  
        $map = new Map;
        $map->address = $this->address;
        $map->ledgerindex_id = $ledgerindex;
        $map->txtype = $DModelName::TYPE;
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