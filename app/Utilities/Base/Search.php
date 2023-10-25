<?php
/**
 * Main search class
 *
 * @category   Search Engine
 * @package    XRPLWinAnalyzer
 * @author     Zvjezdan Grguric <zgrguric@xrplwin.com>
 */
namespace App\Utilities\Base;

use App\Models\BAccount;
use App\Models\BTransaction;
use App\Utilities\AccountLoader;
use App\Utilities\Base\Mapper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
#use Illuminate\Support\Facades\Cache;
#use Carbon\Carbon;


abstract class Search
{
  protected string $address;
  protected array $parametersWhitelist = ['from','to','dir','cp','dt','st','token','offer','nft','nftoffer','types','page']; //todo add hook and pc
  protected bool $isExecuted = false;
  protected array $errors = [];
  protected int $last_error_code = 0; //0 - no error
  
  protected readonly array $params;
  protected readonly array $txTypes;
  protected readonly array $result;
  protected readonly array $result_counts;


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

    if(isset($data['types'])) {
      $data['types'] = \explode(',',$data['types']);
    }
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
    $this->result_counts = ['page' => $data['page'], 'pages' => $num_pages, 'more' => $data['hasmorepages'], 'total' => $data['total'], 'info' => $data['info']];
    $this->isExecuted = true;
    return $this;
  }

  /**
   * This search identifier. This string identifies all search parameter combination for this search.
   * @return string SHA-512Half
   */
  protected function _generateSearchIndentifier(Mapper $mapper): string
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


  protected function param($name)
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
   * @param array $row - DB Row Result
   * @return BTransaction
   */
  protected function mutateRowToModel(array $row): BTransaction
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

  /**
   * @throws \Exception
   * @return array
   */
  protected function _execute_real(int $page = 1, BAccount $acct): array
  {
    throw new \Exception('Not implemented');
  }

  /**
   * @throws \Exception
   * @return int
   */
  protected function _runCount(Mapper $mapper, array $dateRanges): int
  {
    throw new \Exception('Not implemented');
  }
}