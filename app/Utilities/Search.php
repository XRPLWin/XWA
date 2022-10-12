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
use App\Utilities\Scanplan\Parser as ScanplanParser;

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
    
    $mapper->addCondition('txTypes',$txTypes);

    //Direction (in|out)
    $param_dir = $this->param('dir');
    
    if($param_dir && ($param_dir == 'in' || $param_dir == 'out'))
      $mapper->addCondition('dir',$param_dir);

    unset($param_dir);

    //Token (ISSUER+CURRENCY)
    $param_token = $this->param('token');
    if($param_token) {
      if($param_token === 'XRP')
        $mapper->addCondition('token','XRP');
      else {
        $param_token_ex = \explode('+',$param_token);
        if(count($param_token_ex) == 1) $param_token_ex = \explode(' ',$param_token);
        if(count($param_token_ex) == 2) {
          if(isValidXRPAddressFormat($param_token_ex[0])) {
            $mapper->addCondition('token',$param_token);
          }
        }
        unset($param_token_ex);
      }
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
    //dd($intersected);
    /**
     * Caculate optimal SCAN plan
     */
    $scanplan = new ScanplanParser($intersected);
    $scanplan = $scanplan->parse();
    
    //$scanplan = $this->calculateScanPlan($intersected);
    
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
      'page' => 0,
      'total_pages' => 0,
      //'total_e' => 'eq', //non-definitive equilizer (if eq then total_filtered = total)
    ];
    //dd($scanplan);

    /*

    http://analyzer.xrplwin.test/v1/account/search/rEb8TK3gBgk5auZkwc6sHnwrGVJH8DuaLh?from=2017-12-01&to=2017-12-07&types[0]=Payment&page=2
    http://xlanalyzer-ui.test/account/rEb8TK3gBgk5auZkwc6sHnwrGVJH8DuaLh/search?types%5B%5D=Payment&from=2016-01-01&to=2018-02-28&st=&dt=&cp=&token=&dir=
    http://analyzer.xrplwin.test/v1/account/search/rsmYqAFi4hQtTY6k6S3KPJZh7axhUwxT31?from=2021-12-01&to=2021-12-31

    /v1/account/search/rEb8TK3gBgk5auZkwc6sHnwrGVJH8DuaLh?from=2018-01-01&to=2018-01-31&dir=in&page=1&cp=rJb5KsHsDHF1YS5B5DU6QCkH5NsPaKQTcy


    */
    
    $pages_count = count($scanplan);
    //dd($scanplan);
    if($pages_count == 0) {
      return ['counts' => $resultCounts, 'data' => collect([])];
    }
    //dd($pages_count,$scanplan);
    if(!isset($scanplan[$page]))
      throw new \Exception('Page out of range');

    //$scanplan_page = ScanplanParser::parseOnePage($scanplan[$page]);
    //dd($scanplan_page);
    //dd($conditions);

    //dd($scanplan[$page]);
    foreach($scanplan[$page] as $txTypeNamepart => $scanplanTypeData) {
      //dd( $scanplanTypeData,9);
      //$nonDefinitiveResults[$txTypeNamepart] = collect([]);
      $resultCounts['total_scanned'] += $scanplanTypeData['total'];
      
      //$resultCounts['total_e'] = self::calcSearchEqualizer($resultCounts['total_e'],$scanplanTypeData['stats']['e']);
      
      //$ledgerindex_first_range = Ledgerindex::getLedgerindexData($scanplanTypeData['data']['ledgerindex_first']);
      //$ledgerindex_last_range = Ledgerindex::getLedgerindexData($scanplanTypeData['ledgerindex_last_id']);
      //dd($ledgerindex_last_range,$scanplanTypeData);
      /** @var \App\Models\DTransaction */
      $DTransactionModelName = '\\App\\Models\\DTransaction'.$txTypeNamepart;
      $query = $DTransactionModelName::where('PK', $this->address.'-'.$DTransactionModelName::TYPE);

      //apply non-definitive conditions to $query
      $query = $mapper->applyQueryConditions($query);
      //dd(($scanplanTypeData['ledgerindex_first']/10000));
      //if($ledgerindex_last_range[1] == -1)
      if($scanplanTypeData['ledgerindex_last'] === -1)
        $query = $query->where('SK','>=',($scanplanTypeData['ledgerindex_first']/10000));
      else
        $query = $query->where('SK','between',[($scanplanTypeData['ledgerindex_first']/10000),($scanplanTypeData['ledgerindex_last']/10000)]); //DynamoDB BETWEEN is inclusive
      
      dump($query->toDynamoDbQuery());exit;
      $results = $query->all();
      //dd($results,$scanplan);

     /* $j = 1;
      foreach($results as $r )
      {
        echo $j.' '.($r->SK).'<br>';
        $j++;
      }
      exit;*/
      
      //TODO next page to dynamodb!
      //dd($results,$results->lastKey(),$query->afterKey($results->lastKey())->limit(2)->all());


      //$nonDefinitiveResults[$txTypeNamepart] =  $nonDefinitiveResults[$txTypeNamepart]->merge($results);
      $nonDefinitiveResults = $nonDefinitiveResults->merge($results);
      //dump($scanplanTypeData);
      //dump('count: '.$results->count());
      //Add total counts
      //foreach($definitiveResults as $v){
      //  $resultCounts['total'] += $v->count();
      //}
    }
    
    //dd('stop');
    //dd($results->last());
    //sort by SK
    $nonDefinitiveResults = $nonDefinitiveResults->sortByDesc('SK');
    $resultCounts['page'] = $page;
    $resultCounts['total_pages'] = $pages_count;
    return ['counts' => $resultCounts, 'data' => $nonDefinitiveResults];
  }

  /**
   * Will cache result of existance check of a file on S3.
   * Will cache existance flag only if exist.
   */
  private function checkIfCacheFileExists(string $searchIdentifier, int $page, string $filepath): bool
  {
    $cachekey = 'searchcacheexist:'.$searchIdentifier.'_'.config('xwa.paginator_breakpoint').'_'.$page;
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
      $searchcacheexistKey = 'searchcacheexist:'.$searchIdentifier.'_'.config('xwa.paginator_breakpoint').'_'.$page;
      // remove file flag from cache
      Cache::forget($searchcacheexistKey);
    }
  }

  /**
   * Generates filepath from searchIdentifier
   */
  private function searchIdentifierToFilepath(string $searchIdentifier, $page = 1)
  {
    return config('xwa.searchcachedir').'/'.\strtolower(\substr($searchIdentifier,0,3)).'/'.$searchIdentifier.'_'.config('xwa.paginator_breakpoint').'_'.$page;
  }

  public function execute(): self
  {
    $page = $this->param('page');
    
    try{
      $data = $this->_execute_real($page);
    } catch (\Throwable $e) {
      if(config('app.debug')) {
        $this->errors[] = $e->getMessage().' on line '.$e->getLine().' on line '.$e->getLine(). ' in file '.$e->getFile();
        \Log::debug($e);
      }
      else
        $this->errors[] = $e->getMessage();
      return $this;
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
   * Sample: /v1/account/search/rhotcWYdfn6qxhVMbPKGDF3XCKqwXar5J4?from=2022-05-01&to=2022-05-28&st=1&cp=r3mmzMZxRQaiuLRsKDATciyegSgZod88uT
   */
  /*public function execute_disk(): self
  {
    $page = $this->param('page');
    $to = Carbon::createFromFormat('Y-m-d', $this->param('to'));
    $LedgerIndexLastForDay = Ledgerindex::getLedgerIndexLastForDay($to);
    if($LedgerIndexLastForDay == -1) {
      
      // this search is incomplete and should not be cached to disk
      try{
        $data = $this->_execute_real($page);
      } catch (\Throwable $e) {
        if(config('app.debug')) {
          $this->errors[] = $e->getMessage().' on line '.$e->getLine().' on line '.$e->getLine(). ' in file '.$e->getFile();
          \Log::debug($e);
        }
        else
          $this->errors[] = $e->getMessage();
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
          if(config('app.debug')) {
            $this->errors[] = $e->getMessage().' on line '.$e->getLine().' on line '.$e->getLine(). ' in file '.$e->getFile();
            \Log::debug($e);
          }
          else
            $this->errors[] = $e->getMessage();
          return $this;
        }
        # Save this $data to disk
        Storage::disk(config('xwa.searchcachedisk'))->put($filepath,\serialize($data));
      }
      else {

          
        //function aaconvert($size)
        //{
        //      $unit=array('b','kb','mb','gb','tb','pb');
        //      return @round($size/pow(1024,($i=floor(log($size,1024)))),2).' '.$unit[$i];
        //}
        
        # Retrieve from disk
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
            if(config('app.debug')) {
              $this->errors[] = $e->getMessage().' on line '.$e->getLine().' on line '.$e->getLine(). ' in file '.$e->getFile();
              \Log::debug($e);
            }
            else
              $this->errors[] = $e->getMessage();
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
  }*/

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

  /**
   * @deprecated
   */
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
    return config('xwa.paginator_breakpoint');
  }

  /**
   * Takes 123.03 and converts to 123103000
   * Another example is 35484942.028 to 35484942102800
   * @return array ['value' => STRING, 'count' => INT ]
   */
  /*private function calculateScanPlan_Explode_DecToInt_data(string $bp): array
  {
    $bp = \explode('-',$bp);
    $ex = \explode('.', $bp[0]);
    if(!isset($ex[1])) {
      $ex[1] = '0';
    }
    $ex[1] = \str_pad( '1'.$ex[1],6,'0',STR_PAD_RIGHT);
    return ['value' => $ex[0].$ex[1], 'count' => (int)$bp[1]];
  }*/

  /**
   * Explodes large ledger indexes to smaller chunks via breakpoints.
   * @deprecated 
   */
  /*private function calculateScanPlan_Explode(array $data): array
  {
    $new = [];
    foreach($data as $txTypeNamepart => $l) {
      $new[$txTypeNamepart] = [];
      foreach($l as $ledgerindexID => $counts) {
        // add primary row
        $total_count = $counts['total'];
        $new[$txTypeNamepart][$ledgerindexID] = [
          'total' => null,
          'found' => null,
          'e' => $counts['e']
        ];
        if($counts['breakpoints'] !== '')
        {
          //add additional rows
          $breakpoints = \explode('|', $counts['breakpoints']);
          $lastcount = null;
          foreach($breakpoints as $bp) {
            $bpdectoint_data = $this->calculateScanPlan_Explode_DecToInt_data($bp);
            
            if($new[$txTypeNamepart][$ledgerindexID]['total'] === null)
              $new[$txTypeNamepart][$ledgerindexID]['total'] = $new[$txTypeNamepart][$ledgerindexID]['found'] = $bpdectoint_data['count'];
           // dd($bpdectoint_data['count']);
            $new[$txTypeNamepart][(string)($ledgerindexID.'.'.$bpdectoint_data['value'])] = [
              'total' => $lastcount, 'found' => $lastcount, 'e' => 'eq'
            ];

            $lastcount = $bpdectoint_data['count'];
          }
          //$ledgerindexIDSecond = $ledgerindexID;
          //$new[$txTypeNamepart][];
        }

        if($new[$txTypeNamepart][$ledgerindexID]['total'] === null) {
          $new[$txTypeNamepart][$ledgerindexID]['total'] = $counts['total'];
          $new[$txTypeNamepart][$ledgerindexID]['found'] = $counts['found'];
        }
          
      }
    }
    \ksort($new,SORT_NUMERIC);
    //dd($new);
    return $new;
  }*/

  /**
   * This function takes intersected array and returns optimal
   * query SCAN plan which will be executed against DyDB
   * Large ledger days are split to smaller chunks which adds to number of pages,
   * limit of those smaller chunks are extracted from DyDB paged count on ledger day (cached).
   * @param array $data - final intersected array of transaction counts
   * @return array [PAGE => [ txTypeNamepart => [ array stats, array data ] ] ]
   */
  public function calculateScanPlan(array $data): array
  {
    dd($data);
    # Explode inner pages
    //$data = $this->calculateScanPlan_Explode($data);

    //dd($data);
    # Eject zero edges
    # - removes items with zero (0) 'found' param left and right, until cursor reaches filled item
    $newData = [];
    foreach($data as $txTypeNamepart => $l) {
      $l_fwd = $l;
      foreach($l_fwd as $ledgerindexID => $counts) {
        if($counts['found'] === 0) unset($l_fwd[$ledgerindexID]);
        else break; //stop inner loop
      }
      $l_rew = \array_reverse($l_fwd,true);
      unset($l_fwd);
      foreach($l_rew as $ledgerindexID => $counts) {
        if($counts['found'] === 0) unset($l_rew[$ledgerindexID]);
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

    $breakpoint = self::getPaginatorBreakpoint();
    
    $_calc = [];
    $_flat_ledgerindexesIds = [];
    foreach($data as $txTypeNamepart => $v) {
      $_calc_c = 0;
      $_calc_page = 1;
      foreach($v as $ledgerIndexID => $counts) {
        
        ###
        # <LedgerIndex->id>.000X<FIRST STR_PAD_LEFT(0x15)><LAST STR_PAD_LEFT(0x15)>
        //$ledgerIndexIDCompound = \explode('.',$ledgerIndexID)[0] .
        //  \str_pad(  ((string)($counts['first'] *1000) ?? '0')    , 15,'0',STR_PAD_LEFT) . 
        //  \str_pad(  ((string)($counts['next']  *1000)  ?? '0')   , 15,'0',STR_PAD_LEFT)
        //;
        //dd($ledgerIndexIDCompound,((string)($counts['next']*1000)),$counts['next']);
        //$_flat_ledgerindexesIds[$ledgerIndexIDCompound] = [$counts['first'],$counts['next']];
        //$_calc[$txTypeNamepart][$ledgerIndexIDCompound] = ['found' => $counts['found'], 'c' => $_calc_c, 'page' => $_calc_page, 'first' => $counts['first'], 'next' => $counts['next']];
        ### ELSE
        $_flat_ledgerindexesIds[$ledgerIndexID] = [$counts['first'],$counts['next']];
        $_calc[$txTypeNamepart][$ledgerIndexID] = ['found' => $counts['found'], 'c' => $_calc_c, 'page' => $_calc_page, 'first' => $counts['first'], 'next' => $counts['next']];
        ###
        if($this->calculateScanPlan_calcPageShift($_calc_c,$breakpoint)) {
          $_calc_c = 0;
          $_calc_page++;
        }
        $_calc[$txTypeNamepart][$ledgerIndexID]['page'] = $_calc_page;
        $_calc_c += $counts['found'];
      }
    }
    //dump($_flat_ledgerindexesIds);
    \ksort($_flat_ledgerindexesIds,SORT_NUMERIC);
    
    //dd($_flat_ledgerindexesIds,$_calc,$_calc_c);
    unset($_calc_c);
    unset($_calc_offset);
    unset($counts);
    unset($ledgerIndexID);
    unset($txTypeNamepart);
    $maxes = [];
    $lastpage = 1;
    //dd($_flat_ledgerindexesIds,$_calc);
    foreach($_flat_ledgerindexesIds as $li => $foo)
    {
      foreach($_calc as $txTypeNamepart => $v) {
        if(isset($v[$li])) {
          $lastpage = \max($v[$li]['page'],$lastpage);
          $maxes[$li][$txTypeNamepart] = $lastpage;
        }
      }
    }
    //dd($maxes);
    unset($li);
    unset($foo);
    unset($lastpage);
    unset($_calc);
    unset($_flat_ledgerindexesIds);

    $ledgerIndexIdPages = [];
    foreach($maxes as $ledgerIndexID => $v) {
      $ledgerIndexIdPages[$ledgerIndexID] = \max($maxes[$ledgerIndexID]);
    }

    $tracker = [];
    //dd($ledgerIndexIdPages,$data);

    //looping $data but by ledgerindexes low to high
    foreach($ledgerIndexIdPages as $ledgerIndexID => $page) { //[ 1234.001 => 1, 1235.001 => 2, 1236.001 = 3, 1236.002 => 4] 
      /*$tracker[$page]['_GLOBAL'] = [
        'txtypescompleted' => [],
        'min_ledgerindex' => null,
        'max_ledgerindex' => null
      ];*/
      foreach($data as $txTypeNamepart => $li_totals) { //for each Payment Activation Trustset ....
        
        if(!isset($li_totals[$ledgerIndexID]))
          continue; //continue inner loop

        //$tracker[$page]['_GLOBAL']['txtypescompleted'][$txTypeNamepart] = false; //false = complete
        //jedan po tipu, u payment imamo jedno okidanje, u activation jedno, u trustset jedno - po PAGEu!
        
        //dd($li_totals[$ledgerIndexID]);
        if(!isset($tracker[$page][$txTypeNamepart])) {
          $tracker[$page][$txTypeNamepart] = [
            'total_rows' => 0,
            'found' => 0, //todo rename to found
            'e' => 'eq',
            //'first' => $li_totals[$ledgerIndexID]['first'],
            //'next' => $li_totals[$ledgerIndexID]['next'],
            'llist' => [],
            //'test' => [],
          ];
        } else {
          //throw new \Exception('test test test');
        }
          
        //dd($tracker);
          
        $tracker[$page][$txTypeNamepart]['found'] += $li_totals[$ledgerIndexID]['found'];
        $tracker[$page][$txTypeNamepart]['total_rows']  += $li_totals[$ledgerIndexID]['total'];
        $tracker[$page][$txTypeNamepart]['llist'][] = [$ledgerIndexID => ['first' => $li_totals[$ledgerIndexID]['first'], 'next' => $li_totals[$ledgerIndexID]['next']]];
        //$tracker[$page][$txTypeNamepart]['test'][] = [$li_totals[$ledgerIndexID]['first'],$li_totals[$ledgerIndexID]['next']];
        $tracker[$page][$txTypeNamepart]['e'] = self::calcSearchEqualizer($tracker[$page][$txTypeNamepart]['e'],$li_totals[$ledgerIndexID]['e']);

        //first is set, save as global min limit
        /*if($li_totals[$ledgerIndexID]['first'] !== null) {
          if($tracker[$page]['_GLOBAL']['min_ledgerindex'] > $li_totals[$ledgerIndexID]['first'] || $tracker[$page]['_GLOBAL']['min_ledgerindex'] === null) {
            $tracker[$page]['_GLOBAL']['min_ledgerindex'] = $li_totals[$ledgerIndexID]['first'];
            
          }
            
        }*/
        //next is set, save as global max limit
        /*if($li_totals[$ledgerIndexID]['next'] !== null) {
          if($tracker[$page]['_GLOBAL']['max_ledgerindex'] > $li_totals[$ledgerIndexID]['next'] || $tracker[$page]['_GLOBAL']['max_ledgerindex'] === null) {
            $tracker[$page]['_GLOBAL']['max_ledgerindex'] = $li_totals[$ledgerIndexID]['next'];
            
          }
            
        }*/

        //$tracker[$page]['_GLOBAL']['txtypescompleted'][$txTypeNamepart] = true; //true - incomplete
        


        $tracker[$page][$txTypeNamepart]['ledgerindex_first'] = $tracker[$page][$txTypeNamepart]['llist'][0];
        $tracker[$page][$txTypeNamepart]['ledgerindex_last'] = $tracker[$page][$txTypeNamepart]['llist'][count($tracker[$page][$txTypeNamepart]['llist'])-1];

        //$tracker[$page][$txTypeNamepart]['breakpoints'] = $li_totals[$ledgerIndexID]['breakpoints'];
      }
    }


    # Last phase: exploding inner pages
    dd($tracker);
    foreach($tracker as $page) {

    }







































    ############################# OLD
    dd($tracker);
    //new:
    $result = [];
    foreach($tracker as $page => $parts)
    {
      $result[$page] = $this->calculateScanPlan_InnerExplodePages($page,$parts);
    }
    dd($result, $tracker, $data);
    return $result;
  }

  /**
   * We got one page. See if exploding is needed depending on others.
   */
  public function calculateScanPlan_InnerExplodePages(int $page, array $data)
  {
    
    $page = 1;
    $do = true;
    $curr_li = 0;
    $curr_next = 0;
    $tracker = [];
    while($do) {
      foreach($data as $txTypeNamepart => $stats) {
        foreach($stats['llist'] as $kk => $lis) {
          foreach($lis as $ledgerindex => $firstnext) {
            if($curr_li < $ledgerindex )
              $curr_li = $ledgerindex;
          }
        }
      }
      $page++;
      $do = false;
    }
    dd($data);

    #####################
    $plan = [];
    $p = 1; //page 1
    foreach($data as $txTypeNamepart => $stats) {
      foreach($stats['llist'] as $kk => $lis) {
        foreach($lis as $ledgerindex => $firstnext)
        {
          if(!isset($plan[$ledgerindex]['min'])) $plan[$ledgerindex]= ['min' => 999999999999999999999999, 'first' => null,'next' => null, 'page' => $page];

          if($firstnext['next'] !== 0) {
            if($plan[$ledgerindex]['min'] > $firstnext['next']) {
              $plan[$ledgerindex]['min'] = $firstnext['next'];
              $plan[$ledgerindex]['first'] = $firstnext['first'];
              $plan[$ledgerindex]['next'] = $firstnext['next'];
            }
          }
            
        }
       
      }
    }
    dd($data,$plan);




    return $data;
    //if($page == 2) dd($data);
    # 1 add weight
    
    foreach($data as $txTypeNamepart => $stats) {
      $data[$txTypeNamepart]['llist2'] = [];
      foreach($stats['llist'] as $kk => $lis) {
        foreach($lis as $k => $v) {
          $first = $v['first'] ?? 0;
          $next = $v['next'] ?? $first;
          $weight = $next - $first;
          if($weight == 0) $weight = 999999999999999999999999999;
          $data[$txTypeNamepart]['llist'][$kk][$k]['weight'] = $weight; //if 0 then no limit
          unset($first);
          unset($next);
          unset($weight);
        }
      }
    }
    $granularLlist = $granularLlistMap = [];
    foreach($data as $txTypeNamepart => $stats) {
      foreach($stats['llist'] as $kk => $lis) {
        foreach($lis as $k => $v) {
          //if($v['weight'] !== 0)
            $granularLlist[$k][] = $v['weight'];
        
            $granularLlistMap[$v['weight']] = [$k => $v];
        }
      }
    }
    //dump($data, $granularLlistMap,$granularLlist);

    foreach($data as $txTypeNamepart => $stats) {
      foreach($stats['llist'] as $kk => $lis) {
        foreach($lis as $k => $v) {
          $data[$txTypeNamepart]['llist2'][] = $granularLlistMap[\min($granularLlist[$k])];
          //dd($v,$granularLlist,$k,\min($granularLlist[$k]),$granularLlistMap[\min($granularLlist[$k])]);
        }
      }
    }
    //dd($data,$granularLlist);
    return $data;
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
   * 
   * @deprecated
   */
  public static function calcSearchEqualizer(string $existingE, string $newE): string
  {
    if($existingE == 'lte')
      return 'lte';
      
    return $newE;
  }
}