<?php

namespace App\Utilities;
use App\Models\Map;
use App\Models\Ledgerindex;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\Cache;
use App\Repository\TransactionsRepository;
use App\Models\BAccount;

/**
 * 
 */
class Mapper
{
  private array $conditions = [
    //from
    //to
    //...
  ];
  private readonly string $address;
  private int $page = 1;

  public function setAddress(string $address): self
  {
    $this->address = $address;
    return $this;
  }

  public function setPage(int $page): self
  {
    $this->page = $page;
    return $this;
  }

  public function getLimit(): int
  {
    return (int)config('xwa.limit_per_page');
  }

  public function getOffset(): int
  {
    if($this->page <= 1)
      return 0;
    return $this->getLimit() * ($this->page - 1);
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
   * Checks if all parameters are present
   * @throws \Exception
   * @return void
   */
  public function checkRequirements(BAccount $acct): void
  {
    if( !isset($this->conditions['from']) || !isset($this->conditions['to']) || !isset($this->conditions['txTypes']) )
      throw new \Exception('From To and txTypes conditions are not set');
    
    //Todo check if $acct->lt (ledger time) is synced to requested from/to conditions

  }

  /**
   * @return string t > x AND t < y (this is inclusive)
   */
  public function generateTConditionSQL(): string
  {
    return 't BETWEEN "'.$this->conditions['from'].' 00:00:00" AND "'.$this->conditions['to'].' 23:59:59"';
  }

  public function generateConditionsSQL(): string
  {
    # (required) address and time range: t > x AND t < y (this is inclusive)
    $SQL = 'address = """'.$this->address.'""" AND t BETWEEN "'.$this->conditions['from'].' 00:00:00" AND "'.$this->conditions['to'].' 23:59:59"';
    //unset($conditions['from']);
    //unset($conditions['to']);

    # (optional) xwatype - Transaction types
    if(count($this->conditions['txTypes']) > 0) {
      $SQL .= ' AND xwatype IN ('.\implode(',',\array_keys($this->conditions['txTypes'])).')';
    }

    # (optional) isin - Direction
    if(isset($this->conditions['dir'])) {
      if($this->conditions['dir'] == 'in')
        $SQL .= ' AND isin = true';
      else
        $SQL .= ' AND isin = false';
    }

    # (optional) r - Counterparty
    if(isset($this->conditions['cp'])) {
      $SQL .= ' AND r = """'.$this->conditions['cp'].'"""';
    }

    # (optional) st - Source tag
    if(isset($this->conditions['st'])) {
      $SQL .= ' AND st = '.$this->conditions['st'];
    }

    # (optional) dt - Destination tag
    if(isset($this->conditions['dt'])) {
      $SQL .= ' AND dt = '.$this->conditions['dt'];
    }

    # (optional) Token (ISSUER+CURRENCY or XRP)
    if(isset($this->conditions['token'])) {
      $issuerAndToken = self::extractIssuerAndToken($this->conditions['token']);
      if($issuerAndToken['issuer'] == 'XRP' && $issuerAndToken['currency'] == 'XRP') {
        $SQL .= ' AND ( (a IS NOT NULL AND i IS NULL AND c IS NULL) OR (a2 IS NOT NULL AND i2 IS NULL AND c2 IS NULL) )';
      } else {
        $SQL .= ' AND ( (a IS NOT NULL AND i = """'.$issuerAndToken['issuer'].'""" AND c = """'.$issuerAndToken['currency'].'""") OR (a2 IS NOT NULL AND i2 = """'.$issuerAndToken['issuer'].'""" AND c2 = """'.$issuerAndToken['currency'].'""") )';
      }
    }

    //dd($SQL);
    //dd($this);






    //dd($this->conditions);
    return $SQL;
  }

  public static function extractIssuerAndToken(string $param): array
  {
    if($param == 'XRP')
      return ['issuer' => 'XRP', 'currency' => 'XRP']; 

    $param_ex = explode('+', $param);
    if(count($param_ex) == 1) $param_ex = \explode(' ',$param);
    if(count($param_ex) != 2 )
      throw new \Exception('Invalid token parameter');
    return ['issuer' => $param_ex[0], 'currency' => $param_ex[1]]; 
  }

}