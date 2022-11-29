<?php

namespace App\Utilities;
use App\Models\Map;
use App\Models\Ledgerindex;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\Cache;
use App\Repository\TransactionsRepository;

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

  public function getCondition(string $condition)
  {
    return isset($this->conditions[$condition]) ? $this->conditions[$condition]:null;
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
      
    $LedgerIndexLastForDay = Ledgerindex::getLedgerIndexLastForDay($to); //10k
    
    
    if($LedgerIndexLastForDay === null)
      throw new \Exception('Ledger index for end day is not yet synced');

    if($LedgerIndexLastForDay == -1) {
      //Viewing current day, account should be synced to today atleast
      $LedgerIndexLastForDay = Ledgerindex::getLedgerIndexFirstForDay($to)-1;
    }
    
    //Check if $this->address eg 75026591*10000 is synced within time ranges, if not then disallow search
    if(($account->l*10000) < $LedgerIndexLastForDay) {
      throw new \Exception('Account not synced to this ledger yet ('.($account->l*10000).' < '.$LedgerIndexLastForDay.')');
    }
    
    $period = CarbonPeriod::since($from)->until($to);

    $foundLedgerIndexesIds = [];
    foreach($this->conditions['txTypes'] as $txTypeNamepart) {
      $foundLedgerIndexesIds[$txTypeNamepart] = [];
    }

    # Phase 1 ALL days per Tx Type
    foreach($period as $day) {
      dump('a');
      $ledgerindex = Ledgerindex::getLedgerindexIdForDay($day);
      
      
      if($ledgerindex) {
        foreach($this->conditions['txTypes'] as $txTypeNamepart) {
          
          $page = 1;
          $next = null;
          $do = true;
          while($do) {
            $countWithNext = $this->fetchAllCount($ledgerindex, $txTypeNamepart, $page, $next); //first page
            
            if($countWithNext[0] > 0) { //has transactions
              $foundLedgerIndexesIds[$txTypeNamepart][$ledgerindex.'.'.\str_pad($page,4,'0',STR_PAD_LEFT)] = [
                'total' => $countWithNext[0],
                'found' => $countWithNext[0],
                'e' => 'eq',
                'first' => $countWithNext[1],
                'last' => $countWithNext[2]
              ];
            } else {
              //Sanity check:
              //if no transactions on first page then wont be on next pages (no next pages)
              if($countWithNext[2] !== null)
                throw new \Exception('Critical error: page count returned zero results but lastKey is evaluated');
            }

            if($countWithNext[2] === null) {
              //no more next pages
              $do = false;
            } else {
              $page++;
              $next = $countWithNext[2];
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
    return $foundLedgerIndexesIds;
  }

  public function applyQueryConditions(\BaoPham\DynamoDb\DynamoDbQueryBuilder $query) 
  {
    if(isset($this->conditions['dir']) && $this->conditions['dir'] == 'in') {
      $Filter = new Mapper\FilterIn($this->address,$this->conditions,[]);
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
   * From cache first, then from db, else get from BQ.
   * Appliable to ALL transaction types.
   * @param int $ledgerindex - id from ledgerindexes table
   * @param string $txTypeNamepart - \App\Models\BTransaction<NAMEPART>
   * @param ?int $next - latest evaluated SK*10000 for building lastKey for DynamoDB iterator
   * @return array [ int transaction count, string breakpoints ]
   * http://xlanalyzer.test/v1/account/search/r4GBuaRNykmkg5kZkVxnsERBVcJjYNb7Hx?from=2016-02-01&to=2016-02-28
   */
  private function fetchAllCount(int $ledgerindex, string $txTypeNamepart, int $subpage = 1, ?int $nextSK = null): array
  {
    $BModelName = '\\App\\Models\\BTransaction'.$txTypeNamepart;
    
    $cache_key = 'mpr'.$this->address.'_all_'.$ledgerindex.'_'.$subpage.'_'.$BModelName::TYPE;
    
    $r = Cache::get($cache_key);

    if($r === null) {
      $map = Map::select('count_num','first','last')
        ->where('address', $this->address)
        ->where('ledgerindex_id',$ledgerindex)
        ->where('txtype',$BModelName::TYPE)
        ->where('condition','all')
        ->where('page', $subpage)
        ->first();

      if(!$map)
      {
        //no records found, query BQ for this day, for this type and save
        $li = Ledgerindex::getLedgerindexData($ledgerindex);
        
        if(!$li) {
          //clear cache then then/instead exception?
          throw new \Exception('Unable to fetch Ledgerindex of ID (previously cached): '.$ledgerindex);
        }


        $_WHERE = 'PK = """'.$this->address.'-'.$BModelName::TYPE.'"""';
        if($li[1] === -1) //latest
          $_WHERE .= ' AND SK >= '.($li[0]/10000); //INCLUSIVE
        else
          $_WHERE .= ' AND SK BETWEEN  '.($li[0]/10000).' AND '.($li[1]/10000); //INCLUSIVE

dd($_WHERE);
        $r = TransactionsRepository::fetchOne($_WHERE,'COUNT(*) as c','');

        //get Last evaluated key


    
        dd($r,config('xwa.scan_limit'));
        ##############################
        $query = $BModelName::createContextInstance($this->address)->where('PK',$this->address.'-'.$BModelName::TYPE);

        $limit = config('xwa.scan_limit');
        if($limit)
          $query = $query->limit((int)$limit);
        
        if($li[1] === -1) //latest
          $query = $query->where('SK','>=',($li[0]/10000));
        else
          $query = $query->where('SK','between',[ ($li[0]/10000), ($li[1]/10000) ]);

        if($nextSK !== null) {
          $query->afterKey(['PK' => $this->address.'-'.$BModelName::TYPE, 'SK' => ($nextSK/10000)]);
        }

        $c = $query->pagedCount();
        $count = $c->count;
        
        $map = new Map;
        $map->address = $this->address;
        $map->ledgerindex_id = $ledgerindex;
        $map->txtype = $BModelName::TYPE;
        $map->condition = 'all';
        $map->count_num = $count;
        $map->page = $subpage;
        $map->first = $nextSK ? ($nextSK + 1) : null; //nullable
        $map->last = $c->lastKey ? stringDecimalX10000($c->lastKey['SK']['N']) : null; //nullable
        $map->created_at = \date('Y-m-d H:i:s');
        $map->save();
      }
      $r = [$map->count_num, $map->first, $map->last];
      Cache::put($cache_key, $r, 2629743); //2629743 seconds = 1 month
    }
    return $r;
  }
}