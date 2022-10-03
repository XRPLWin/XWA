<?php
/**
 * Main search class
 *
 * @category   Search Engine
 * @package    XRPLWinAnalyzer
 * @author     Zvjezdan Grguric <zgrgric@xrplwin.com>
 */

namespace App\Utilities;

#use App\Models\Ledgerindex;
#use App\Utilities\Mapper;
#use XRPLWin\XRPL\Client;
use Illuminate\Support\Facades\Cache;
use App\Models\Ledgerindex;
use Illuminate\Support\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class Search
{
  private string $address;
  private readonly Collection $result;
  private readonly array $result_counts;
  private readonly array $params;
  //private readonly array $definitive_params;
  private bool $isExecuted = false;
  private array $errors = [];
  private array $parametersWhitelist = ['from','to','dir','cp','dt','st','token','types','page'];
  private array $txTypes = [
    // App\Models\DTransaction<VALUE_BELOW>::TYPE => App\Models\DTransaction<VALUE_BELOW>
    1 => 'Payment',
    2 => 'Activation',
    3 => 'Trustset',
    // ...todo
  ];

  public function __construct(string $address)
  {
    $this->address = $address;
  }

  public function buildFromArray(array $data): self
  {
    //ensure page existance
    $data['page'] = (!isset($data['page'])) ? 1 : (int)$data['page'];
    if(!$data['page']) $data['page'] = 1;
    $data['page'] = \abs($data['page']);

    $this->params = $data;
    return $this;
  }

  public function buildFromRequest(Request $request): self
  {
    return $this->buildFromArray($request->only($this->parametersWhitelist));
  }

  private function buildNonDefinitiveParams(array $params)
  {
    $r = [];
    foreach($params as $k => $v)
    {
      if($k == 'cp') {
        $r[$k] = \App\Utilities\Mapper\FilterCounterparty::parseToNonDefinitiveParam($v);
      } elseif($k == 'dt') {
        $r[$k] = \App\Utilities\Mapper\FilterDestinationtag::parseToNonDefinitiveParam($v);
      } elseif($k == 'st') {
        $r[$k] = \App\Utilities\Mapper\FilterSourcetag::parseToNonDefinitiveParam($v);
      } else {
        $r[$k] = $v;
      }
    }
    return $r;
  }

  public function hasErrors(): bool
  {
    if(count($this->errors))
      return true;
    return false;
  }

  public function getErrors(): array
  {
    return $this->errors;
  }

  /**
   * @throws \Exception
   * @return array ['counts' => $resultCounts, 'data' => $nonDefinitiveResults]
   */
  private function _execute_real(int $page = 1)
  {
    $mapper = new Mapper();
    $mapper->setAddress($this->address);

    $mapper
    ->addCondition('from',$this->param('from'))
    ->addCondition('to',$this->param('to'));

    if(!$mapper->dateRangeIsValid())
      throw new \Exception('From and to params spans more than allowed 31 days and *from* has to be before *to*. Dates must not be in future');

    //$txTypes = $this->txTypes; //thiese are all types
    $types = $this->param('types');
    $txTypes = [];
    if($types) {
      $txTypesFlipped = \array_flip($this->txTypes);
      //dd($txTypesFlipped);
      foreach($types as $type) {
        if(isset($txTypesFlipped[$type])) {
          $txTypes[$txTypesFlipped[$type]] = $type;
        }
      }
      unset($txTypesFlipped);
      //dd($txTypes);
    } else {
      //use all types
      $txTypes = $this->txTypes;
    }
    unset($types);
    
    //Todo get types from param

    $mapper->addCondition('txTypes',$txTypes);

    //Direction (in|out)
    $param_dir = $this->param('dir');
    
    if($param_dir && ($param_dir == 'in' || $param_dir == 'out'))
      $mapper->addCondition('dir',$param_dir);

    unset($param_dir);

    //Token (ISSUER+CURRENCY)
    $param_token = $this->param('token');
    if($param_token) {
      $param_token_ex = \explode('+',$param_token);
      if(count($param_token_ex) == 2) {
        if(isValidXRPAddressFormat($param_token_ex[0])) {
          $mapper->addCondition('token',$param_token);
        }
      }
      unset($param_token_ex);
    }
    unset($param_token);

    //Counterparty
    $param_cp = $this->param('cp');
    if($param_cp && isValidXRPAddressFormat($param_cp)) {
      $mapper->addCondition('cp',$param_cp);
    }
    unset($param_cp);

    //Destination Tag (int)
    $param_dt = $this->param('dt');
    if($param_dt && is_numeric($param_dt))
      $mapper->addCondition('dt',$param_dt);
    unset($param_dt);

    //Source Tag (int)
    $param_st = $this->param('st');
    if($param_st && is_numeric($param_st)) 
      $mapper->addCondition('st',$param_st);
    unset($param_st);
    
    /**
     * Execute counts and get intersection of transaction hits depending on sent conditions.
     */
    
    $intersected = $mapper->getIntersectedLedgerindexes();
    /**
     * Caculate optimal SCAN plan
     */
    $scanplan = $this->calculateScanPlan($intersected);
    //dd($scanplan);
    /**
     * Query the DyDB using $scanplan
     */
    //$nonDefinitiveResults = [];
    $nonDefinitiveResults = collect([]);

    //Count full array template with default zero values
    $resultCounts = [
      'total_filtered' => 0, //definitive total results
      'total_scanned' => 0, //non-definitive total results (informational only)
      //'total_e' => 'eq', //non-definitive equilizer (if eq then total_filtered = total)
    ];
    //dd($scanplan);

    /*

http://analyzer.xrplwin.test/v1/account/search/rEb8TK3gBgk5auZkwc6sHnwrGVJH8DuaLh?from=2017-12-01&to=2017-12-07&types[0]=Payment&page=2



http://analyzer.xrplwin.test/v1/account/search/rsmYqAFi4hQtTY6k6S3KPJZh7axhUwxT31?from=2021-12-01&to=2021-12-31











    */
    foreach($scanplan as $txTypeNamepart => $scanplanTypeData) {
      //$nonDefinitiveResults[$txTypeNamepart] = collect([]);
      $resultCounts['total_scanned'] += $scanplanTypeData['stats']['total_rows'];
      //$resultCounts['total_e'] = self::calcSearchEqualizer($resultCounts['total_e'],$scanplanTypeData['stats']['e']);
      foreach($scanplanTypeData['data'] as $page => $resultStats) { //TODO Ovjde ne treba loop jer znamo koji page tocno zelimo iz parametra...
        
        $ledgerindex_first_range = Ledgerindex::getLedgerindexData($resultStats['ledgerindex_first']);
        $ledgerindex_last_range = Ledgerindex::getLedgerindexData($resultStats['ledgerindex_last']);

        /** @var \App\Models\DTransaction */
        $DTransactionModelName = '\\App\\Models\\DTransaction'.$txTypeNamepart;
        $query = $DTransactionModelName::where('PK', $this->address.'-'.$DTransactionModelName::TYPE);

        if($ledgerindex_last_range[1] == -1)
          $query = $query->where('SK','>=',$ledgerindex_first_range[0]);
        else
          $query = $query->where('SK','between',[$ledgerindex_first_range[0],$ledgerindex_last_range[1] + 0.9999]);

        $results = $query->get();
        
        //TODO next page to dynamodb!


        //dd($results,$results->lastKey(),$query->afterKey($results->lastKey())->limit(2)->all());
        //$nonDefinitiveResults[$txTypeNamepart] =  $nonDefinitiveResults[$txTypeNamepart]->merge($results);
        $nonDefinitiveResults = $nonDefinitiveResults->merge($results);

      }
      //Add total counts
      //foreach($definitiveResults as $v){
      //  $resultCounts['total'] += $v->count();
      //}
    }
    //sort by SK
    $nonDefinitiveResults = $nonDefinitiveResults->sortByDesc('SK');
    
    //apply paginator
    $limit = config('xwa.max_search_results_per_page');
    $skip = ($page * $limit) - $limit;
    $nonDefinitiveResultsPart = $nonDefinitiveResults->skip($skip)->take($limit)->values();
    
    $resultCounts['page'] = $page;
    $resultCounts['total_pages'] = (int)\ceil($resultCounts['total_scanned']/$limit);
    if($resultCounts['total_pages'] < 1) $resultCounts['total_pages'] = 1;
    if($page > $resultCounts['total_pages'])
      throw new \Exception('Page out of range');
   
    return ['counts' => $resultCounts, 'data' => $nonDefinitiveResultsPart];
  }

  /**
   * Will cache result of existance check of a file on S3.
   * Will cache existance flag only if exist.
   */
  private function checkIfCacheFileExists(string $searchIdentifier, int $page, string $filepath): bool
  {
    $cachekey = 'searchcacheexist:'.$searchIdentifier.'_'.config('xwa.max_search_results_per_page').'_'.$page;
    if (Cache::has($cachekey)) {
      return true;
    }
    $exists = Storage::disk(config('xwa.searchcachedisk'))->exists($filepath);
    if($exists) {
      Cache::put($cachekey, 1, 600); //1 byte of cache data
    }
    return $exists;
  }

  /**
   * Removes related cache for this search.
   * @param string $what all|fileexistanceflag|disk
   */
  private function flushCache(string $what = 'all', $page = 1)
  {
    if($what == 'all' || $what == 'disk') {
      if(!isset($searchIdentifier)) $searchIdentifier = $this->getSearchIdentifier();
      // delete file from disk
      Storage::disk(config('xwa.searchcachedisk'))->delete($this->searchIdentifierToFilepath($searchIdentifier,$page)); //todo delete all pages
    }
    if($what == 'all' || $what == 'fileexistanceflag') {
      if(!isset($searchIdentifier)) $searchIdentifier = $this->getSearchIdentifier();
      $searchcacheexistKey = 'searchcacheexist:'.$searchIdentifier.'_'.config('xwa.max_search_results_per_page').'_'.$page;
      // remove file flag from cache
      Cache::forget($searchcacheexistKey);
    }
  }

  /**
   * Generates filepath from searchIdentifier
   */
  private function searchIdentifierToFilepath(string $searchIdentifier, $page = 1)
  {
    return config('xwa.searchcachedir').'/'.\strtolower(\substr($searchIdentifier,0,3)).'/'.$searchIdentifier.'_'.config('xwa.max_search_results_per_page').'_'.$page;
  }

  /**
   * Sample: /v1/account/search/rhotcWYdfn6qxhVMbPKGDF3XCKqwXar5J4?from=2022-05-01&to=2022-05-28&st=1&cp=r3mmzMZxRQaiuLRsKDATciyegSgZod88uT
   */
  public function execute(): self
  {
    $page = $this->param('page');
    $to = Carbon::createFromFormat('Y-m-d', $this->param('to'));
    $LedgerIndexLastForDay = Ledgerindex::getLedgerIndexLastForDay($to);
    if($LedgerIndexLastForDay == -1) {
      
      // this search is incomplete and should not be cached to disk
      try{
        $data = $this->_execute_real($page);
      } catch (\Throwable $e) {
        $this->errors[] = $e->getMessage().' on line '.$e->getLine().' on line '.$e->getLine(). ' in file '.$e->getFile();
        return $this;
      }
      
    } else {
   
      // this search is complete and wont change
    
      ########### DISK CACHE ###########
      # Check if this search indentifier already exists as json dump on Disk.
      $searchIdentifier = $this->getSearchIdentifier();
      //Eg: "searchv1_store/f61/f619f823c40900418b4f808ac32947bde56eba68259616efab26e3ca869e993c"
      $filepath = $this->searchIdentifierToFilepath($searchIdentifier, $page);
      $exists = $this->checkIfCacheFileExists($searchIdentifier, $page, $filepath);
      
      //$exists = true;
      if(!$exists) {
        try{
          $data = $this->_execute_real($page);
        } catch (\Throwable $e) {
          $this->errors[] = $e->getMessage().' on line '.$e->getLine(). ' in file '.$e->getFile();
          return $this;
        }
        # Save this $data to S3
        Storage::disk(config('xwa.searchcachedisk'))->put($filepath,\serialize($data));
      }
      else {

         /* 
        function aaconvert($size)
          {
              $unit=array('b','kb','mb','gb','tb','pb');
              return @round($size/pow(1024,($i=floor(log($size,1024)))),2).' '.$unit[$i];
          }
        */
        # Retrieve from S3
        $data = Storage::disk(config('xwa.searchcachedisk'))->get($filepath); //could rack up to 100 MB
        //dd($data);
        //echo aaconvert(memory_get_usage(true)); // 123 kb
        //exit;
       
        if($data === null) {
 
          //disk file recently deleted and cache did not picked up yet, or disk unavailable
          $this->flushCache('fileexistanceflag',$page);
          try{
            $data = $this->_execute_real($page);
          } catch (\Throwable $e) {
            $this->errors[] = $e->getMessage().' on line '.$e->getLine(). ' in file '.$e->getFile();
            return $this;
          }
        }
        else
          $data = \unserialize($data);
          //echo aaconvert(memory_get_usage(true)); // 123 kb
          //exit;
      }
      #
      ########### DISK CACHE ###########
    }

    //$this->flushCache('all',$page);exit;

    $definitiveResults = $this->applyDefinitiveFilters($data['data']);
    $definitiveResults = $definitiveResults->sortByDesc('SK')->values();
    $this->result = $definitiveResults;

    $result_counts = [
      'filtered' => $definitiveResults->count(),
      'scanned' => $data['counts']['total_scanned'],
      'page' => $data['counts']['page'],
      'pages' => $data['counts']['total_pages'],
    ];

    if($result_counts['pages'] > $result_counts['page']) {
      $result_counts['next'] = true;
    }

    $this->result_counts = $result_counts;

    $this->isExecuted = true;
    return $this;
  }

  /**
   * Filters items via $this->params (precise)
   * @return Collection - filtered collection
   */
  private function applyDefinitiveFilters(Collection $results): Collection
  {
    $filter_dir = $this->param('dir');
    $filter_st = $this->param('st');
    $filter_dt = $this->param('dt');
    $filter_token = $this->param('token');
    $filter_cp = $this->param('cp');

    //todo all filters via Class objects!

    $r = [];
    /** @var \App\Models\DTransaction */
    foreach($results as $v) {

      //On each item apply non eq filter:
      if($filter_dir) {
        if($filter_dir == 'in') {
          if(!Mapper\FilterIn::itemHasFilter($v, true)) continue;
        } elseif($filter_dir == 'out') {
          if(!Mapper\FilterOut::itemHasFilter($v, true)) continue;
        }
      }

      if($filter_st !== null) {
        if(!Mapper\FilterSourcetag::itemHasFilter($v, $filter_st)) continue;
      }
      if($filter_dt !== null) {
        if(!Mapper\FilterDestinationtag::itemHasFilter($v, $filter_dt)) continue;
      }
      if($filter_token !== null) {
        if(!Mapper\FilterToken::itemHasFilter($v, $filter_token)) continue;
      }
      if($filter_cp !== null) {
        if(!Mapper\FilterCounterparty::itemHasFilter($v, $filter_cp)) continue;
      }
      $r[] = $v;
    }
    //todo filter items via $this->param($name)
    return collect($r);
  }

  private function calculateScanPlan_calcPageShift(int $count, int $breakpoint): int
  {
    return ($count >= $breakpoint) ? 1:0;
  }

  /**
   * How much db items until new page is created, scan limit may overflow over this value - default: 500
   * Changing this value will break tests.
   * @return int
   */
  public static function getPaginatorBreakpoint(): int
  {
    return 500;
  }

  /**
   * This function takes intersected array and returns optimal
   * query SCAN plan which will be executed against DyDB
   * @param array $data - final intersected array of transaction counts
   * @return array [txTypeNamepart => [ QUERY_ITERATION => [int total, int ledgerindex_first, int ledgerindex_last] ] ]
   *  ledgerindex_last = 'id' from local db table ledgerindexes
   */
  public function calculateScanPlan(array $data): array
  {
   
   // dd($data);
    # Eject zero edges
    # - removes items with zero (0) 'found' param left and right, until cursor reaches filled item

    $newData = [];
    foreach($data as $txTypeNamepart => $l) {
      $l_fwd = $l;
      foreach($l_fwd as $ledgerindexID => $counts) {
        if(!$counts['found']) unset($l_fwd[$ledgerindexID]);
        else break; //stop inner loop
      }

      $l_rew = \array_reverse($l_fwd,true);
      unset($l_fwd);
      foreach($l_rew as $ledgerindexID => $counts) {
        if(!$counts['found']) unset($l_rew[$ledgerindexID]);
        else break; //stop inner loop
      }
      $newData[$txTypeNamepart] = \array_reverse($l_rew,true);
      unset($l_rew);
    }
    $data = $newData;
    unset($newData);
    unset($counts);
    unset($ledgerindexID);
    unset($txTypeNamepart);
    unset($l);
    unset($l_fwd);
    
    # Calculate batch ranges of SCAN query which will not span more than 1000 items (max 1kb items from db)
    # - DyDB QUERY/SCAN operation will paginate after 1MB retrieved data, this should avoid this pagination
    # - QUERY/SCAN is sorted by SK (sort key, eg ledger_index.transaction_index), if there is very large number of 
    #   results and zero found results, it is safe to split to next query and skip group of ledger indexes
    #   Tradeoff is second query to DyDb, and benefit is no heavy/slow SCAN operation execution.

    $breakpoint = self::getPaginatorBreakpoint();
    $breakpoint = 10;

    ##
    $_calc = [];
    foreach($data as $txTypeNamepart => $v) {
      $_calc_c = 0;
      $_calc_page = 1;
      foreach($v as $ledgerIndexID => $counts) {
        $_calc[$txTypeNamepart][$ledgerIndexID] = ['found' => $counts['found'], 'c' => $_calc_c, 'page' => $_calc_page];
        if($this->calculateScanPlan_calcPageShift($_calc_c,$breakpoint)) {
          $_calc_c = 0;
          $_calc_page++;
        }
        $_calc[$txTypeNamepart][$ledgerIndexID]['page'] = $_calc_page;
        $_calc_c += $counts['found'];
      }
    }
    unset($_calc_c);
    unset($_calc_offset);
    unset($counts);
    unset($ledgerIndexID);
    unset($txTypeNamepart);

    $maxes = [];
    $lastpage = 1;
    foreach($_calc as $txTypeNamepart => $v) {
      foreach($v as $ledgerIndexID => $foundCPage) {
        $lastpage = \max($foundCPage['page'],$lastpage);
        $maxes[$ledgerIndexID][] = $lastpage;
      }
    }


    unset($_calc);
    ##
/*
    return [];
   
    $maxes = [];
    foreach($data as $txTypeNamepart => $v) {
      foreach($v as $ledgerIndexID => $counts) {
        $maxes[$ledgerIndexID][] = $counts['found'];
      }
    }
*/
    $ledgerIndexIdPages = [];
    foreach($maxes as $ledgerIndexID => $v) {
      $ledgerIndexIdPages[$ledgerIndexID] = \max($maxes[$ledgerIndexID]);
    }

  //  dd($maxes, $ledgerIndexIdPages);

    $tracker = [];
    foreach($ledgerIndexIdPages as $ledgerIndexID => $page) {
      foreach($data as $txTypeNamepart => $li_totals) {

        if(!isset($li_totals[$ledgerIndexID]))
          continue; //continue on inner loop

        if(!isset($tracker[$page][$txTypeNamepart]['stats']))
          $tracker[$page][$txTypeNamepart]['stats'] = ['total_rows' => 0, 'e' => 'eq' ];

        if(!isset($tracker[$page][$txTypeNamepart]['data']))
          $tracker[$page][$txTypeNamepart]['data'] = ['total' => 0, 'llist' => []];

        $tracker[$page][$txTypeNamepart]['data']['total'] += $li_totals[$ledgerIndexID]['total'];
        $tracker[$page][$txTypeNamepart]['stats']['total_rows']  += $li_totals[$ledgerIndexID]['total'];
        $tracker[$page][$txTypeNamepart]['data']['llist'][] = $ledgerIndexID;
        $tracker[$page][$txTypeNamepart]['stats']['e'] = self::calcSearchEqualizer($tracker[$page][$txTypeNamepart]['stats']['e'],$li_totals[$ledgerIndexID]['e']);

        $tracker[$page][$txTypeNamepart]['data']['ledgerindex_first'] = $tracker[$page][$txTypeNamepart]['data']['llist'][0];
        $tracker[$page][$txTypeNamepart]['data']['ledgerindex_last'] = $tracker[$page][$txTypeNamepart]['data']['llist'][count($tracker[$page][$txTypeNamepart]['data']['llist'])-1];
      }
    }

    //return $tracker;
    dd($tracker,$ledgerIndexIdPages);

  }

  private function _generateSearchIndentifier(array $params): string
  {
    $indentity = $this->address.':';
    unset($params['page']);

    \ksort($params);
    if(isset($params['types'])) {
      $_txTypes = $params['types'];
      unset($params['types']);
      \ksort($_txTypes);
    }

    foreach($params as $k => $v) {
      $indentity .= $k.'='.$v.':';
    }
    if(isset($_txTypes)) {
      foreach($_txTypes as $k => $v) {
        $indentity .= $k.'='.$v.':';
      }
    }
    $hash = \hash('sha512', $indentity);
    return \substr($hash,0,64);
  }

  /**
   * This search identifier. This string identifies all definitive search parameters for this search.
   * NOT used as path for search export.
   * 
   * @return string SHA-512Half
   */
  public function getSearchDefinitiveIdentifier(): string
  {
    return $this->_generateSearchIndentifier($this->params).'_'.$this->param('page');
  }

  /**
   * This search identifier. This string identifies all non-definitive search parameters for this search.
   * Used as path for search export.
   * 
   * @return string SHA-512Half
   */
  public function getSearchIdentifier(): string
  {
    return $this->_generateSearchIndentifier($this->buildNonDefinitiveParams($this->params));
  }

  /**
   * Returns array of count statistics and results by type.
   * This is used as public api output.
   * @return array [stats, data]
   */
  public function result(): array
  {
    if(!$this->isExecuted)
      throw new \Exception('Search::result() called before execute()');

    $r = $this->result_counts;
    $r['identifier'] = $this->getSearchIdentifier();
    $r['definitiveidentifier'] = $this->getSearchDefinitiveIdentifier();
    $r['data'] = $this->result;
    return $r;
  }

  private function param($name)
  {
    return isset($this->params[$name]) ? $this->params[$name]:null;
  }

  /**
   * Used to determine which equilizer is prominent when analyzing returned resultset counts.
   * Return eq or lte depending of parameters
   * @param string $existingE eq|lte
   * @param string $newE eq|lte
   * @return string eq|lte
   */
  public static function calcSearchEqualizer(string $existingE, string $newE): string
  {
    if($existingE == 'lte')
      return 'lte';
      
    return $newE;
  }
}