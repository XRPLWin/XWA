<?php
/**
 * Main search class
 *
 * @category   Search Engine
 * @package    XRPLWinAnalyzer
 * @author     Zvjezdan Grguric <zgrgric@xrplwin.com>
 */

namespace App\Utilities;

use App\Models\BAccount;
use App\Models\BTransaction;
use App\Utilities\AccountLoader;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;


class Search
{
  private string $address;
  private readonly array $params;
  private array $parametersWhitelist = ['from','to','dir','cp','dt','st','token','types','page'];
  private bool $isExecuted = false;
  private array $errors = [];
  private int $last_error_code = 0; //0 - no error
  private readonly array $txTypes;
  private readonly array $result;
  private readonly array $result_counts;


  public function __construct(string $address)
  {
    $this->txTypes = config('xwa.transaction_types');
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
      if(config('app.env') == 'production') {
        if(config('app.debug')) {
          $this->errors[] = $e->getMessage().' on line '.$e->getLine().' on line '.$e->getLine(). ' in file '.$e->getFile();
          Log::debug($e);
        }
        else
          $this->errors[] = $e->getMessage();
  
        $this->last_error_code = 2;
        return $this;
      }

      //Throw erros on local (dev) environments
      throw $e;
      
    }

    //calculate how much pages total
    $num_pages = 1;
    $limit = config('xwa.limit_per_page');

    if($data['total'] > $limit) {
      $num_pages = (int)\ceil($data['total'] / $limit);
    }

    $this->result = $data['data'];
    $this->result_counts = ['page' => $data['page'], 'pages' => $num_pages, 'more' => $data['hasmorepages'], 'total' => $data['total']];
    $this->isExecuted = true;
    return $this;
  }

  /**
   * @throws \Exception
   * @return array
   */
  private function _execute_real(int $page = 1, BAccount $acct): array
  {
    $mapper = new Mapper();
    $mapper->setAddress($this->address);
    $mapper->setPage($page);

    $mapper
    ->addCondition('from',$this->param('from'))
    ->addCondition('to',$this->param('to'));

    $dateRanges = $mapper->parseDateRanges();
    if($dateRanges === null)
      throw new \Exception('From and to params spans more than allowed 31 days and *from* has to be before *to*. Dates must not be in future');

    # Check if $acct is synced to "To"
    if(!$acct->isSynced(10,$dateRanges[1]))
      throw new \Exception('Account not synced to this date yet');
    //$lt = clone $acct->lt;
    //$lt->addMinutes(10); //10 min leeway time (eg sync can be 10 min stale)
    //if($dateRanges[1]->greaterThan($lt))
    //  throw new \Exception('Account not synced to this date yet');

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
      //to use all types leave empty
      //$txTypes = $this->txTypes;
    }
    unset($types);
    
    $mapper->addCondition('txTypes',$txTypes);

    # Check if current requested start date is equal or larger than first available txtype
    $firstTxInfo = $acct->getFirstTransactionAllInfo();

    if($firstTxInfo['first'] === null) {
      throw new \Exception('No synced transactions found');
    }
    $c1 = $dateRanges[1]->setTimeFromTimeString('10:00:00');
    $c2 = Carbon::createFromFormat('U',$firstTxInfo['first'])->setTimeFromTimeString('10:00:00');
    
    if($c1->lessThan($c2))
      throw new \Exception('No synced transactions found to requested date');

    if(!$typesIsAll) {
      //only specific types are requested
      $_txtypesrangeisvalid = false;
      $c2 = $c1;
      foreach($mapper->getCondition('txTypes') as $k => $v) {
        if($firstTxInfo['first_per_types'][$k] !== null) {
          $c2 = Carbon::createFromFormat('U',$firstTxInfo['first_per_types'][$k])->setTimeFromTimeString('10:00:00');
          if($c1->greaterThanOrEqualTo($c2))
            $_txtypesrangeisvalid = true; //found one
        }
      }
      if(!$_txtypesrangeisvalid) {
        //throw new \Exception('No synced specific transactions found to requested date');
        //return sucessfull response
        return [
          /*'counts' => [
            'total_filtered' => 0,
            'total_scanned' => 0,
            'page' => 0,
            'total_pages' => 0
          ],*/
          'page' => 0,
          'total' => 0,
          'hasmorepages' => false,
          'data' => []
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

    # Check all requirements for query build
    $mapper->checkRequirements($acct);

    //Now the fun part.
  
    # Build query for BQ
    $limit = $mapper->getLimit();
    //$limit = 1;
    //$start = microtime(true);
    $SQL = 'SELECT '.\implode(',',\array_keys(BTransaction::BQCASTS)).' FROM `'.config('bigquery.project_id').'.xwa.transactions` WHERE ';
    //$SQL = 'SELECT COUNT(*) as c FROM `'.config('bigquery.project_id').'.xwa.transactions` WHERE ';
    
    # Add all conditions
    $SQL .= $mapper->generateConditionsSQL();
    # Limit and offset, always get +1 result to see if there are more pages
    $SQL .= ' ORDER BY t ASC LIMIT '.($limit+1).' OFFSET '.$mapper->getOffset();
   
    //dd($SQL);
    //dump(' LIMIT '.($limit+1).' OFFSET '.$mapper->getOffset());

    //https://github.com/googleapis/google-cloud-php-bigquery/blob/main/tests/Snippet/CopyJobConfigurationTest.php
    ///v1/account/search/rDCgaaSBAWYfsxUYhCk1n26Na7x8PQGmkq?from=2014-08-15&to=2017-08-15&types[0]=Payment
    ///v1/account/search/rDCgaaSBAWYfsxUYhCk1n26Na7x8PQGmkq?from=2016-09-06&to=2016-09-06&types[0]=Payment&dir=in
    //https://cloud.google.com/blog/products/bigquery/life-of-a-bigquery-streaming-insert
    $query = \BigQuery::query($SQL)->useQueryCache($dateRanges[1]->isToday() ? false:true); //we do not use cache on queries that envelop today
    
    # Run query and wait for results
    $results = \BigQuery::runQuery($query); //run query
   
    /*$backoff = new \Google\Cloud\Core\ExponentialBackoff(8);
    $backoff->execute(function () use ($results) {
        $results->reload();
        
        if (!$results->isComplete()) {
            throw new \Exception();
        }
    });*/

    if (!$results->isComplete()) {
      throw new \Exception('Query did not complete within the allotted time');
    }
    //dd($results);
    // All results are loaded at this point
    //echo  microtime(true)- $start;

    //dd('stop');


    # Loop raw results and create models
    $i = 1;
    $hasMorePages = false;
    $collection = [];
    foreach($results->rows(['returnRawResults' => false]) as $row) {
      if($i > $limit) {
        $hasMorePages = true;
        break;
      }
      $collection[] = $this->mutateRowToModel($row);
      $i++;
    }
    
    
    if($hasMorePages) {
      $count = $this->_runCount($mapper,$dateRanges);
    } else {
      $count = $i;
    }
    return ['page' => $page, 'hasmorepages' => $hasMorePages, 'total' => $count, 'data' => $collection];
  }

  private function _runCount(Mapper $mapper, array $dateRanges): int
  {

    $cache_key = 'searchcount:'.$this->_generateSearchIndentifier($mapper);
    $count = Cache::get($cache_key);
    
    if($count === null) {

      # Count Start

      $SQL = 'SELECT COUNT(*) as c FROM `'.config('bigquery.project_id').'.xwa.transactions` WHERE ';
      # Add all conditions
      $SQL .= $mapper->generateConditionsSQL();
      $query = \BigQuery::query($SQL)->useQueryCache($dateRanges[1]->isToday() ? false:true); //we do not use cache on queries that envelop today
      # Run query and wait for results
      $results = \BigQuery::runQuery($query); //run query
  
      /*$backoff = new \Google\Cloud\Core\ExponentialBackoff(8);
      $backoff->execute(function () use ($results) {
          $results->reload();
          if (!$results->isComplete()) {
              throw new \Exception();
          }
      });*/
  
      if (!$results->isComplete()) {
        throw new \Exception('Count Query did not complete within the allotted time');
      }
      $count = null;
      foreach($results->rows() as $v) {
        $count = $v['c'];
        break;
      }
      if($count === null)
        throw new \Exception('Count Query did not returned expected single row');

      # Count End
      Cache::put($cache_key, $count, 86400); //86400 seconds = 24 hours
    }
    return $count;
  }

  /**
   * This search identifier. This string identifies all search parameter combination for this search.
   * @return string SHA-512Half
   */
  private function _generateSearchIndentifier(Mapper $mapper): string
  {
    $params = $mapper->getConditions();

    $indentity = $this->address.':';
    unset($params['page']);

    \ksort($params);
    if(isset($params['txTypes'])) {
      $_txTypes = $params['txTypes'];
      unset($params['txTypes']);
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


  private function param($name)
  {
    return isset($this->params[$name]) ? $this->params[$name]:null;
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

  /**
   * @param array $row - BigQuery Row Result
   * @return BTransaction
   */
  private function mutateRowToModel(array $row): BTransaction
  {
    if(!isset($this->txTypes[$row['xwatype']]))
      throw new \Exception('Unsupported xwatype ['.$row['xwatype'].'] returned from BQ');
    $modelName = '\\App\\Models\\BTransaction'.$this->txTypes[$row['xwatype']];
    return $modelName::hydrate([ $row ])->first();
  }

  /**
   * Returns array of count statistics and results by type.
   * This is used as public api output.
   * @return array [page => INT, mode => BOOL, data => ARRAY]
   */
  public function result(): array
  {
    if(!$this->isExecuted)
      throw new \Exception('Search::result() called before execute()');

    $r = $this->result_counts;
    $r['data'] = [];
    foreach($this->result as $v) {
      //cast BTransaction model to Final array
      $r['data'][] = $v->toFinalArray();
    }
    return $r;
  }
}