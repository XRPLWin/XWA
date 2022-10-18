<?php
/**
 * Main search class
 *
 * @category   Search Engine
 * @package    XRPLWinAnalyzer
 * @author     Zvjezdan Grguric <zgrgric@xrplwin.com>
 */

namespace App\Utilities;

use App\Models\DAccount;
use App\Utilities\Scanplan\Parser as ScanplanParser;
use App\Utilities\AccountLoader;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;


class Search
{
  private string $address;
  private readonly Collection $result;
  private readonly array $result_counts;
  private readonly array $params;
  private bool $isExecuted = false;
  private array $errors = [];
  private int $last_error_code = 0; //0 - no error
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

  /**
   * Only non-definitive filters go here.
   * @return array
   */
  private function buildNonDefinitiveParams(array $params): array
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

  /**
   * If any errors collected this will return true.
   * @return bool
   */
  public function hasErrors(): bool
  {
    if(count($this->errors))
      return true;
    return false;
  }

  /**
   * Last error's error code.
   * @return int
   */
  public function getErrorCode(): int
  {
    return $this->last_error_code;
  }

  /**
   * Collected error messages
   * @return array
   */
  public function getErrors(): array
  {
    return $this->errors;
  }

  public function execute(): self
  {
    
    $acct = AccountLoader::get($this->address);
   
    if(!$acct) {
      $this->errors[] = 'Account not synced yet';
      $this->last_error_code = 3;
      return $this;
    }
    
    $page = $this->param('page');

    try {
      $data = $this->_execute_real($page, $acct);
    } catch (\Throwable $e) {
      if(config('app.debug')) {
        $this->errors[] = $e->getMessage().' on line '.$e->getLine().' on line '.$e->getLine(). ' in file '.$e->getFile();
        Log::debug($e);
      }
      else
        $this->errors[] = $e->getMessage();

      $this->last_error_code = 2;
      return $this;
    }

    $definitiveResults = $this->applyDefinitiveFilters($data['data']);
    $definitiveResults = $definitiveResults->values();
    $this->result = $definitiveResults;

    $result_counts = [
      'filtered' => $definitiveResults->count(),
      'scanned' => $data['counts']['total_scanned'],
      'page' => $data['counts']['page'],
      'pages' => $data['counts']['total_pages'],
    ];

    # Sanity check
    if($result_counts['filtered'] > $result_counts['scanned']) {
      $this->errors[] = 'Sanity check failed, filtered results count more than scanned results count: '.$result_counts['filtered'].' > '.$result_counts['scanned'];
      if(config('app.debug'))
        Log::debug($this->getErrors());
      
      $this->last_error_code = 1;
      return $this;
    }

    if($result_counts['pages'] > $result_counts['page']) {
      $result_counts['next'] = true;
    }
 
    $this->result_counts = $result_counts;

    $this->isExecuted = true;
    return $this;
  }

  /**
   * @throws \Exception
   * @return array ['counts' => $resultCounts, 'data' => $nonDefinitiveResults]
   */
  private function _execute_real(int $page = 1, DAccount $acct)
  {
    
    $mapper = new Mapper();
    $mapper->setAddress($this->address);

    $mapper
    ->addCondition('from',$this->param('from'))
    ->addCondition('to',$this->param('to'));

    if(!$mapper->dateRangeIsValid())
      throw new \Exception('From and to params spans more than allowed 31 days and *from* has to be before *to*. Dates must not be in future');
    
    //$txTypes = $this->txTypes; //these are all types
    $types = $this->param('types');
    $typesIsAll = true;
    $txTypes = [];
    if($types) {
      $typesIsAll = false;
      $txTypesFlipped = \array_flip($this->txTypes);
      foreach($types as $type) {
        if(isset($txTypesFlipped[$type])) {
          $txTypes[$txTypesFlipped[$type]] = $type;
        }
      }
      unset($txTypesFlipped);
    } else {
      //use all types
      $txTypes = $this->txTypes;
    }
    unset($types);
    
    $mapper->addCondition('txTypes',$txTypes);
    
    # Now we have requested types
    # Check if current requested start date is equal or larger than first available txtype
    $firstTxInfo = $acct->getFirstTransactionAllInfo();
    if($firstTxInfo['first'] === null) {
      throw new \Exception('No synced transactions found');
    }
    $c1 = Carbon::createFromFormat('Y-m-d H:i:s',$mapper->getCondition('to').' 10:00:00');
    $c2 = Carbon::createFromFormat('Y-m-d H:i:s',$firstTxInfo['first'].' 10:00:00');
    if($c1->lessThan($c2))
      throw new \Exception('No synced transactions found to requested date');


    if(!$typesIsAll) {
      //only specific types are requested
      $_txtypesrangeisvalid = false;
      $c2 = Carbon::createFromFormat('Y-m-d H:i:s',$mapper->getCondition('to').' 10:00:00');
      foreach($mapper->getCondition('txTypes') as $k => $v) {
        if($firstTxInfo['first_per_types'][$k] && $firstTxInfo['first_per_types'][$k]['date']) {
          $c2 = Carbon::createFromFormat('Y-m-d H:i:s',$firstTxInfo['first_per_types'][$k]['date'].' 10:00:00');
          if($c1->greaterThanOrEqualTo($c2))
            $_txtypesrangeisvalid = true; //found one
        }
      }
      if(!$_txtypesrangeisvalid) {
        //throw new \Exception('No synced specific transactions found to requested date');
        //return sucessfull response
        return [
          'counts' => [
            'total_filtered' => 0,
            'total_scanned' => 0,
            'page' => 0,
            'total_pages' => 0
          ],
          'data' => collect([])
        ];
      } 
    }
    unset($c1);
    unset($c2);
    
    //Direction (in|out)
    $param_dir = $this->param('dir');
    
    if($param_dir && ($param_dir == 'in' || $param_dir == 'out'))
      $mapper->addCondition('dir',$param_dir);

    unset($param_dir);

    //Token (ISSUER+CURRENCY or XRP)
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
    
    /**
     * Caculate optimal SCAN plan
     */
    $scanplan = new ScanplanParser($intersected);
    $scanplan = $scanplan->parse();
    
    /**
     * Query the DyDB using $scanplan
     */
    $nonDefinitiveResults = collect([]);

    //Count full array template with default zero values
    $resultCounts = [
      'total_filtered' => 0,  //definitive total results
      'total_scanned' => 0,   //non-definitive total results (informational only)
      'page' => 0,            //current viewed page
      'total_pages' => 0,     //total pages in this scope
    ];

    $pages_count = count($scanplan);
    if($pages_count == 0) {
      return ['counts' => $resultCounts, 'data' => collect([])];
    }

    if(!isset($scanplan[$page]))
      throw new \Exception('Page out of range');

    foreach($scanplan[$page] as $txTypeNamepart => $scanplanTypeData) {
      $resultCounts['total_scanned'] += $scanplanTypeData['total'];

      /** @var \App\Models\DTransaction */
      $DTransactionModelName = '\\App\\Models\\DTransaction'.$txTypeNamepart;
      $query = $DTransactionModelName::createContextInstance($this->address)->where('PK', $this->address.'-'.$DTransactionModelName::TYPE);

      //apply non-definitive conditions to $query
      $query = $mapper->applyQueryConditions($query);

      if($scanplanTypeData['ledgerindex_last'] === -1)
        $query = $query->where('SK','>=',($scanplanTypeData['ledgerindex_first']/10000));
      else
        $query = $query->where('SK','between',[($scanplanTypeData['ledgerindex_first']/10000),($scanplanTypeData['ledgerindex_last']/10000)]); //DynamoDB BETWEEN is inclusive
      
      //dd($query->toDynamoDbQuery());

      $results = $query->all();
      $nonDefinitiveResults = $nonDefinitiveResults->merge($results);
    }

    //sort by SK
   
    $nonDefinitiveResults = $nonDefinitiveResults->sortBy('SK');
    $resultCounts['page'] = $page;
    $resultCounts['total_pages'] = $pages_count;
    return ['counts' => $resultCounts, 'data' => $nonDefinitiveResults];
  }

  /**
   * Filters items via $this->params (precise)
   * @return Collection - filtered collection
   */
  private function applyDefinitiveFilters(Collection $results): Collection
  {
    $filter_dir   = $this->param('dir');
    $filter_st    = $this->param('st');
    $filter_dt    = $this->param('dt');
    $filter_token = $this->param('token');
    $filter_cp    = $this->param('cp');

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
    return collect($r);
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
}