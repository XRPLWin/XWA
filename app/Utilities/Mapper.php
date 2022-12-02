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
  public function generateTConditionSQL()
  {
    return 't BETWEEN "'.$this->conditions['from'].' 00:00:00" AND "'.$this->conditions['to'].' 23:59:59"';
  }

}