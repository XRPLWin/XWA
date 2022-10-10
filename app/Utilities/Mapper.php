<?php

namespace App\Utilities;
#use XRPLWin\XRPL\Client;
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
  /**
   * Flag that indicates this search can be cached.
   * If false that means time range is out of scope or ledger index data is behind on sync.
   */
  //public $cacheable = true;

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
    
    
    try{
      $account = AccountLoader::get($this->address);
    } catch (\Throwable $e) {
      $account = null;
    }
    if(!$account)
      throw new \Exception('Invalid account or account address format');
      
    $LedgerIndexLastForDay = Ledgerindex::getLedgerIndexLastForDay($to);
    
    if($LedgerIndexLastForDay === null)
      throw new \Exception('Ledger index for end day is not yet synced');
    
    if($LedgerIndexLastForDay == -1) {
      //Viewing current day, account should be synced to today atleast
      $LedgerIndexLastForDay = Ledgerindex::getLedgerIndexFirstForDay($to)-1;
    }

    if($account->l < $LedgerIndexLastForDay) {
      throw new \Exception('Account not synced to this ledger index yet ('.$account->l.' < '.$LedgerIndexLastForDay.')');
    }

    //Check if $this->address is synced within time ranges, if not then disallow search
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
          $page = 1;
          $next = null;
          $do = true;
          while($do) {
            //if($next) dd($ledgerindex, $txTypeNamepart, $page, $next);
            $countWithNext = $this->fetchAllCount($ledgerindex, $txTypeNamepart, $page, $next); //first page
            if($countWithNext[0] > 0) { //has transactions
              $foundLedgerIndexesIds[$txTypeNamepart][$ledgerindex.'.'.\str_pad($page,4,'0',STR_PAD_LEFT)] = ['total' => $countWithNext[0], 'found' => $countWithNext[0], 'e' => 'eq', 'first' =>  $countWithNext[1], 'next' => $countWithNext[2]]; //[total, reduced, eq (equalizer eq|lte)]
            } else {
              //Sanity check:
              //if no transactions on first page then wont be on next pages (no next pages)
              if($countWithNext[2] !== null)
                throw new \Exception('Critical error: page count returned zero results but lastKey is evaluated');
            }
            //if($page == 4)
            //  dd($countWithNext);

            if($countWithNext[2] === null) {
              //no more next pages
              $do = false;
              //dump('DO = false');
            } else {
              $page++;
              $next = $countWithNext[2];
              //dd($countWithNext,$next);
            }
            
            unset($countWithNext);
          }
          unset($page);
          unset($next);
        }
      } else {
        //something went wrong... or out of scope
        throw new \Exception('Mapper count failed due to missing ledgerindex for day');
      }
    }
    
    # Phase 2 OPTIONAL CONDITIONS REDUCER:
    //dd($foundLedgerIndexesIds);
    
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
    
    if(isset($this->conditions['token'])) {
      $Filter = new Mapper\FilterToken($this->address,$this->conditions,$foundLedgerIndexesIds);
      $foundLedgerIndexesIds = $Filter->reduce();
      unset($Filter);
      //echo 'TOKEN: ';dump($foundLedgerIndexesIds);
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

  public function applyQueryConditions(\BaoPham\DynamoDb\DynamoDbQueryBuilder $query) 
  {
    if(isset($this->conditions['dir']) && $this->conditions['dir'] == 'in') {
      $Filter = new Mapper\FilterIn($this->address,$this->conditions);
      $query = $Filter->applyQueryCondition($query);
    }
    elseif(isset($this->conditions['dir']) && $this->conditions['dir'] == 'out') {
      $Filter = new Mapper\FilterOut($this->address,$this->conditions,[]);
      $query = $Filter->applyQueryCondition($query);
    }

    if(isset($this->conditions['token'])) {
      $Filter = new Mapper\FilterToken($this->address,$this->conditions,[]);
      $query = $Filter->applyQueryCondition($query, Mapper\FilterToken::parseToNonDefinitiveParam($this->conditions['token']));
    }

    if(isset($this->conditions['cp'])) {
      $Filter = new Mapper\FilterCounterparty($this->address,$this->conditions,[]);
      $query = $Filter->applyQueryCondition($query, Mapper\FilterCounterparty::parseToNonDefinitiveParam($this->conditions['cp']));
    }

    if(isset($this->conditions['dt'])) {
      $Filter = new Mapper\FilterDestinationtag($this->address,$this->conditions,[]);
      $query = $Filter->applyQueryCondition($query, Mapper\FilterDestinationtag::parseToNonDefinitiveParam($this->conditions['dt']));
    }

    if(isset($this->conditions['st'])) {
      $Filter = new Mapper\FilterSourcetag($this->address,$this->conditions,[]);
      $query = $Filter->applyQueryCondition($query, Mapper\FilterSourcetag::parseToNonDefinitiveParam($this->conditions['st']));
    }

    return $query;
  }
  
  /**
   * Fetches list of indexes for time range and types
   * From cache first, then from db, else generate from DyDB.
   * Appliable to ALL transaction types.
   * @param int $ledgerindex - id from ledgerindexes table
   * @param string $txTypeNamepart - \App\Models\DTransaction<NAMEPART>
   * @param ?string $next - latest evaluated SK for building lastKey for DynamoDB iterator
   * @return array [ int transaction count, string breakpoints ]
   */
  private function fetchAllCount(int $ledgerindex, string $txTypeNamepart, int $subpage = 1, ?string $nextSK = null): array
  {
   
    $DModelName = '\\App\\Models\\DTransaction'.$txTypeNamepart;
    
    $cache_key = 'mpr'.$this->address.'_all_'.$ledgerindex.'_'.$subpage.'_'.$DModelName::TYPE;
    $r = Cache::get($cache_key);
    
    if($r === null) {
      $map = Map::select('count_num','first_exclusive','next')
        ->where('address', $this->address)
        ->where('ledgerindex_id',$ledgerindex)
        ->where('txtype',$DModelName::TYPE)
        ->where('condition','all')
        ->where('page', $subpage)
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
        $query = $DModelName::where('PK',$this->address.'-'.$DModelName::TYPE); //In need of more performance, use this: ->take(2000);
        if($li[1] == -1) //latest
          $query = $query->where('SK','>=',$li[0]);
        else
          $query = $query->where('SK','between',[$li[0],$li[1]]);
        //dd($query);
        //dump($query->toDynamoDbQuery());
        
        if($nextSK !== null) {
          $query->afterKey(['PK' => $this->address.'-'.$DModelName::TYPE, 'SK' => (float)$nextSK]);
        }
          
        $c = $query->pagedCount();
        $count = $c->count;
        
        //$countWithBreakpoints = \App\Utilities\PagedCounter::countAndReturnBreakpointsForTransacitons($query);
        //$countWithBreakpoints = \App\Utilities\PagedCounter::countWithBreakpoints($query,null,['SK','N']);
        
        $map = new Map;
        $map->address = $this->address;
        $map->ledgerindex_id = $ledgerindex;
        $map->txtype = $DModelName::TYPE;
        $map->condition = 'all';
        $map->count_num = $count;//$countWithBreakpoints['count'];
        $map->page = $subpage;
        $map->next = $c->lastKey ? ($c->lastKey['SK']['N']-0.0001) : null;
        $map->first_exclusive = $nextSK; //(exclusive); If null then use LedgerIndex.ledger_index_first (inclusive)
        //$map->last_li = $c->lastKey['SK']['N'];
        //$map->breakpoints = $countWithBreakpoints['breakpoints'];
        //$map->count_indicator = '='; //indicates that count is exact (=)
        $map->created_at = now(); //TODO do not use now(), use \date() to improve performance
        $map->save();
      }
      $r = [$map->count_num, $map->first_exclusive, $map->next ? ($map->next+0.0001) : null];
      Cache::put($cache_key, $r, 2629743); //2629743 seconds = 1 month
    }
    
    return $r;
  }


}