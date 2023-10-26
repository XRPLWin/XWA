<?php

namespace App\Utilities\Base;
use Carbon\Carbon;
use App\Models\BAccount;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Search conditions mapper
 */
abstract class Mapper
{
  protected array $conditions = [
    //from
    //to
    //...
  ];
  protected readonly string $address;
  protected int $page = 1;

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

  public function getConditions(): array
  {
    return $this->conditions;
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
   * @return ?array null if invalid or [Carbon $from, Carbon $to] 
   */
  public function parseDateRanges(): ?array
  {
    if(!isset($this->conditions['from']) || !isset($this->conditions['to']))
      return null;

    if($this->conditions['from'] === $this->conditions['to']) {
      $from = Carbon::createFromFormat('Y-m-d', $this->conditions['from']);
      return [$from, clone $from];
    }

    $from = Carbon::createFromFormat('Y-m-d', $this->conditions['from']);
    //check if days between dates do not exceed 31 days
    if($from->diffInDays($this->conditions['to']) > 31)
      return null;

    //'from' has to be before 'to'
    if(!$from->isBefore($this->conditions['to']))
      return null;

    //from and to needs to be current date or past
    if($from->isFuture())
      return null;

    $to = Carbon::createFromFormat('Y-m-d', $this->conditions['to']);
    if($to->isFuture())
      return null;

    return [$from,$to];
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
    
    //TODO? check if $acct->lt (ledger time) is synced to requested from/to conditions
  }

  /**
   * @return string t > x AND t < y (this is inclusive)
   */
  //public function generateTConditionSQL(?QueryBuilder $queryBuilder = null): string|QueryBuilder
  //{
  //  throw new \Exception('Not implemented');
  //}

  /**
   * Returns part of SQL or appends QueryBuilder, depending of driver
   */
  public function generateConditionsSQL(?QueryBuilder $queryBuilder = null): string|QueryBuilder
  {
    throw new \Exception('Not implemented');
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