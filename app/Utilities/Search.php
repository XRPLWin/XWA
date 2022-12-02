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
use App\Repository\TransactionsRepository;
use App\Utilities\Scanplan\Parser as ScanplanParser;
use App\Utilities\AccountLoader;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;


class Search
{
  private string $address;
  private readonly array $params;
  private array $parametersWhitelist = ['from','to','dir','cp','dt','st','token','types','page'];

  private bool $isExecuted = false;
  private array $errors = [];
  private int $last_error_code = 0; //0 - no error

  private array $txTypes = [
    // App\Models\DTransaction<VALUE_BELOW>::TYPE => App\Models\DTransaction<VALUE_BELOW>
    1 => 'Payment',
    2 => 'Activation',
    3 => 'Trustset',
    4 => 'AccountDelete',
    5 => 'Payment_BalanceChange',
    6 => 'Payment_Exchange'
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

  public function execute(): self
  {
    $acct = AccountLoader::get($this->address);

    if(!$acct) {
      $this->errors[] = 'Account not synced yet';
      $this->last_error_code = 3;
      return $this;
    }

    $page = $this->param('page');
    
    //try {
      $data = $this->_execute_real($page, $acct);
    /*} catch (\Throwable $e) {
      if(config('app.debug')) {
        $this->errors[] = $e->getMessage().' on line '.$e->getLine().' on line '.$e->getLine(). ' in file '.$e->getFile();
        Log::debug($e);
      }
      else
        $this->errors[] = $e->getMessage();

      $this->last_error_code = 2;
      
    }*/
    dd($data);
    return $this;
  }

  /**
   * @throws \Exception
   * @return ?
   */
  private function _execute_real(int $page = 1, BAccount $acct)
  {
    $mapper = new Mapper();
    $mapper->setAddress($this->address);
    $mapper->setPage($page);

    $mapper
    ->addCondition('from',$this->param('from'))
    ->addCondition('to',$this->param('to'));

    //if(!$mapper->dateRangeIsValid())
    //  throw new \Exception('From and to params spans more than allowed 31 days and *from* has to be before *to*. Dates must not be in future');

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

    # Check if current requested start date is equal or larger than first available txtype
    $firstTxInfo = $acct->getFirstTransactionAllInfo();

    if($firstTxInfo['first'] === null) {
      throw new \Exception('No synced transactions found');
    }
    $c1 = Carbon::createFromFormat('Y-m-d H:i:s',$mapper->getCondition('to').' 10:00:00');
    $c2 = Carbon::createFromFormat('U',$firstTxInfo['first'])->setTimeFromTimeString('10:00:00');
    
    if($c1->lessThan($c2))
      throw new \Exception('No synced transactions found to requested date');

    if(!$typesIsAll) {
      //only specific types are requested
      $_txtypesrangeisvalid = false;
      $c2 = Carbon::createFromFormat('Y-m-d H:i:s',$mapper->getCondition('to').' 10:00:00');
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

    # Check all requirements for query build
    $mapper->checkRequirements($acct);

    //Now the fun part.

    # Build query for BQ
    $limit = $mapper->getLimit();
    
    $SQL = 'SELECT '.\implode(',',\array_keys(BTransaction::BQCASTS)).' FROM `'.config('bigquery.project_id').'.xwa.transactions` WHERE ';
    //$SQL = 'SELECT COUNT(*) as c FROM `'.config('bigquery.project_id').'.xwa.transactions` WHERE ';
    $SQL .= $mapper->generateTConditionSQL().' AND address = """'.$this->address.'"""';
    //todo add other conditions

    # Limit and offset, always get +1 result to see if there are more pages
    //$SQL .= ' LIMIT '.($limit+1).' OFFSET '.$mapper->getOffset();
    //dd($SQL);

    //https://github.com/googleapis/google-cloud-php-bigquery/blob/main/tests/Snippet/CopyJobConfigurationTest.php
    ///v1/account/search/rDCgaaSBAWYfsxUYhCk1n26Na7x8PQGmkq?from=2014-08-15&to=2017-08-15&types[0]=Payment
    ///v1/account/search/rDCgaaSBAWYfsxUYhCk1n26Na7x8PQGmkq?from=2016-09-06&to=2016-09-06&types[0]=Payment&dir=in
    $bq = app('bigquery');
    $query = $bq->query($SQL)->useQueryCache(true);

    # Run query and wait for results
    $results = $bq->runQuery($query); //run query

    $backoff = new \Google\Cloud\Core\ExponentialBackoff(8);
    $backoff->execute(function () use ($results) {
        $results->reload();

        if (!$results->isComplete()) {
            throw new \Exception();
        }
    });

    if (!$results->isComplete()) {
      throw new \Exception('Query did not complete within the allotted time');
    }

    // All results are loaded at this point

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

      echo $i.') '.$row['h'].'<br>';
      //if($i == 12) dd($job->rows() );
      $i++;
    }
    exit;
    
    $results = TransactionsRepository::query($SQL);


    dd($results,123);
    dd($mapper);

  }


  private function param($name)
  {
    return isset($this->params[$name]) ? $this->params[$name]:null;
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
    $model = new $modelName($row);
    dd(json_encode($model->toFinalArray()));
    dd($this->txTypes,$row,$this->txTypes[$row['xwatype']]);
    dd($row);
  }
}