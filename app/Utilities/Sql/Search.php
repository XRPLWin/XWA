<?php
/**
 * Main search class
 *
 * @category   Search Engine
 * @package    XRPLWinAnalyzer
 * @author     Zvjezdan Grguric <zgrguric@xrplwin.com>
 */
namespace App\Utilities\Sql;

use App\Models\BAccount;
use App\Models\BTransaction;
use App\Utilities\Base\Mapper as BaseMapper;
use App\Utilities\Sql\Mapper;
use Illuminate\Support\Facades\DB;
#use App\Utilities\AccountLoader;

#use Illuminate\Http\Request;
#use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;


class Search extends \App\Utilities\Base\Search
{
  /*private string $address;
  private readonly array $params;
  private array $parametersWhitelist = ['from','to','dir','cp','dt','st','token','offer','nft','nftoffer','types','page']; //todo add hook and pc
  private bool $isExecuted = false;
  private array $errors = [];
  private int $last_error_code = 0; //0 - no error
  private readonly array $txTypes;
  private readonly array $result;
  private readonly array $result_counts;*/

  /**
   * @throws \Exception
   * @return array
   */
  protected function _execute_real(int $page = 1, BAccount $acct): array
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
    if(!$acct->isSynced(20,$dateRanges[1]))
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
    //$firstTxInfo = $acct->getFirstTransactionAllInfo();
    $firstTxInfo = [
      'first' => $acct->getFirstTransactionTime(),
      'first_per_types' => [],
    ];

    if($firstTxInfo['first'] === null) {
      /*return [
        'page' => 0,
        'total' => 0,
        'hasmorepages' => false,
        'info' => 'No synced transactions found',
        'data' => []
      ];*/
      throw new \Exception('No synced transactions found');
    }
   
    $c1 = $dateRanges[1]->setTimeFromTimeString('10:00:00');
    $c2 = Carbon::createFromFormat('U',$firstTxInfo['first'])->setTimeFromTimeString('10:00:00');
    
    if($c1->lessThan($c2)) {
      return [
        'page' => 0,
        'total' => 0,
        'hasmorepages' => false,
        'info' => 'No transactions found to requested date',
        'data' => []
      ];
      //throw new \Exception('No transactions found to requested date');
    }
      
    
    /*if(!$typesIsAll) {
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
        return [
          'page' => 0,
          'total' => 0,
          'hasmorepages' => false,
          'info' => 'No specific transactions found to requested date',
          'data' => []
        ];
      } 
    }*/
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

    //Offer
    $param_offer = $this->param('offer');
    if($param_offer) {
      $mapper->addCondition('offer',$param_offer);
    }
    
    //if($param_offer) {
    //  //has to be 64 characters length
    //  if(\strlen($param_offer) == 64) //TODO upper limit, probably no more than 70chars
    //      $mapper->addCondition('offer',$param_offer);
    //}

    unset($param_offer);

    //Hook
    $param_hook = $this->param('hook');
    if($param_hook) {
      $mapper->addCondition('hook',$param_hook);
    }
    unset($param_hook);

    //NFT
    $param_nft = $this->param('nft');
    if($param_nft) {
      //has to be 64 characters length
      if(\strlen($param_nft) == 64)
        $mapper->addCondition('nft',$param_nft);
    }
    unset($param_nft);

    //NFTOffer
    $param_nftoffer = $this->param('nftoffer');
    if($param_nftoffer) {
      //has to be 64 characters length
      if(\strlen($param_nftoffer) == 64)
        $mapper->addCondition('nftoffer',$param_nftoffer);
    }
    unset($param_nftoffer);


    //Counterparty (rAddress1,rAddress2,...)
    $param_cp = $this->param('cp');
    if($param_cp) {
      $param_cp_arr = [];
      //$param_cp_ex =  \explode(',',$param_cp);
      foreach(\explode(',',$param_cp) as $param_cp_exv) {
        if(!$param_cp_exv) continue;
        if(isValidXRPAddressFormat($param_cp_exv)) {
          $param_cp_arr[] = $param_cp_exv;
        }
      }
      $mapper->addCondition('cp',$param_cp_arr);
      unset($param_cp_arr);
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

    # Build query for SQL
    $limit = $mapper->getLimit();
    $SQL = DB::table(transactions_db_name($dateRanges[0]->format('Ym')));
    $SQL = $mapper->generateConditionsSQL($SQL);

    # Limit and offset, always get +1 result to see if there are more pages
    //$SQL->orderBy('t','ASC')->limit(($limit+1))->offset($mapper->getOffset());
    $SQL->orderBy('l','ASC')->limit(($limit+1))->offset($mapper->getOffset());
    
    // Test start
    //DB::enableQueryLog(); // Enable query log
    //$testresult = $SQL->get();
    //dump(DB::getQueryLog()); // Show results of log
    //dd($SQL, $testresult);
    // Test end

    $results = $SQL->get();
    //dd($results);

    # Loop raw results and create models
    $i = 1;
    $hasMorePages = false;
    $collection = [];
    foreach($results as $row) {
      if($i > $limit) {
        $hasMorePages = true;
        break;
      }
      $collection[] = $this->mutateRowToModel((array)$row);
      $i++;
    }
    
    if($hasMorePages || $page > 1) {
      $count = $this->_runCount($mapper,$dateRanges);
    } else {
      $count = $i-1;
    }
    return ['page' => $page, 'hasmorepages' => $hasMorePages, 'total' => $count, 'info' => '', 'data' => $collection];
  }

  /**
   * @throws \Exception
   * @return int
   */
  protected function _runCount(BaseMapper $mapper, array $dateRanges): int
  {
    $cache_key = 'searchcount:'.$this->_generateSearchIndentifier($mapper);
    //$count = Cache::get($cache_key); //ne valja kad je trenutan dan, iskljuci todo
    $count = null;
    if($count === null) {

      # Count Start
      $SQL = DB::table(transactions_db_name($dateRanges[0]->format('Ym')));
      $SQL = $mapper->generateConditionsSQL($SQL);
      $count = $SQL->count();
      # Count End
      Cache::put($cache_key, $count, 86400); //86400 seconds = 24 hours
    }
    return $count;
  }

}