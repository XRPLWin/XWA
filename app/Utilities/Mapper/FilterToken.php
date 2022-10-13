<?php

namespace App\Utilities\Mapper;

use Illuminate\Support\Facades\Cache;
use App\Models\Map;
use App\Models\Ledgerindex;

class FilterToken extends FilterBase {
    
  private readonly string $address;
  private readonly array $foundLedgerIndexesIds;
  private readonly array $conditions;
  private array $allowedTxTypes = ['Payment','Trustset'];
  private readonly array $txTypes;

  public function __construct(string $address, array $conditions, array $foundLedgerIndexesIds)
  {
    $this->address = $address;
    $this->conditions = $conditions;
    $this->foundLedgerIndexesIds = $foundLedgerIndexesIds;

    $txTypes = [];
    foreach($this->conditions['txTypes'] as $txType) {
      if(in_array($txType,$this->allowedTxTypes))
        $txTypes[] = $txType;
    }
    $this->txTypes = $txTypes;
  }

  /**
   * rAccount.. = Ac
   * @return string
   */
  public static function parseToNonDefinitiveParam(string $param): string
  {
    return $param;
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

  /**
   * Returns array with count information for this filter.
   * @return array
   */
  public function reduce(): array
  {
    $FirstFewLetters = self::parseToNonDefinitiveParam($this->conditions['token']);
    $r = [];
    foreach($this->txTypes as $txTypeNamepart) {
      $r[$txTypeNamepart] = [];
      if(isset($this->foundLedgerIndexesIds[$txTypeNamepart])) {
        foreach($this->foundLedgerIndexesIds[$txTypeNamepart] as $ledgerindex => $countTotalReduced) {
          $r[$txTypeNamepart][$ledgerindex] = [
            'total' => $countTotalReduced['total'],
            'found' => 0,
            'e' => 'eq',
            'first' => $countTotalReduced['first'],
            'last' => $countTotalReduced['last']
          ];
          if($countTotalReduced['total'] == 0 || $countTotalReduced['found'] == 0) continue; //no transactions here, skip
    
          $ledgerindexEx = $this->explodeLedgerindex($ledgerindex);
          $count = $this->fetchCount($ledgerindexEx[0], $ledgerindexEx[1], $txTypeNamepart, $FirstFewLetters, $countTotalReduced['first'], $countTotalReduced['last']);
          if($count > 0) { //has transactions
            $r[$txTypeNamepart][$ledgerindex] = [
              'total' => $countTotalReduced['total'],
              'found' => $count,
              'e' => self::calcEqualizer($countTotalReduced['e'], 'lte'),
              'first' => $countTotalReduced['first'],
              'last' => $countTotalReduced['last']
            ];
          }
          unset($count);
        }
      }
    }
    return $r;
  }

  private function conditionName(string $FirstFewLetters)
  {
    return 'token_'.\sha1($FirstFewLetters);
  }

  /**
   * @param int $ledgerindex - local internal LedgerIndex->id
   * @param int $subpage - subpage within LedgerIndex
   * @param string $txTypeNamepart
   * @param string $FirstFewLetters - part of filter to do non-definitive filtering on
   * @param ?int $first - SK*10000 (inclusive)
   * @param ?int $last - SK*10000 (inclusive)
   */
  private function fetchCount(int $ledgerindex, int $subpage, string $txTypeNamepart, string $FirstFewLetters, ?int $first, ?int $last): int
  {
    $DModelName = '\\App\\Models\\DTransaction'.$txTypeNamepart;
    $cond = $this->conditionName($FirstFewLetters);
    $cache_key = 'mpr'.$this->address.'_'.$cond.'_'.$ledgerindex.'_'.$subpage.'_'.$DModelName::TYPE;
    $r = Cache::get($cache_key);
    
    if($r === null) {
      $map = Map::select('count_num')
        ->where('address', $this->address)
        ->where('ledgerindex_id',$ledgerindex)
        ->where('page',$subpage)
        ->where('txtype',$DModelName::TYPE)
        ->where('condition',$cond)
        ->first();
      
      if(!$map)
      {
        $model = $DModelName::where('PK',$this->address.'-'.$DModelName::TYPE);
        if($first == null || $last == null) {
          //Need to get edges
          $li = Ledgerindex::getLedgerindexData($ledgerindex);
          if(!$li) {
            //clear cache then then/instead exception?
            throw new \Exception('Unable to fetch Ledgerindex of ID (previously cached): '.$ledgerindex);
          }
          $first = $first ?? $li[0];
          $last = $last ?? $li[1]; 
        }
        if($last === -1)
          $model = $model->where('SK','>=',($first/10000));
        else
          $model = $model->where('SK','between',[($first/10000),($last/10000)]); //DynamoDB BETWEEN is inclusive
        
        $model = $this->applyQueryCondition($model, $FirstFewLetters);
        //dd($model);
        $count = \App\Utilities\PagedCounter::count($model);

        $map = new Map;
        $map->address = $this->address;
        $map->ledgerindex_id = $ledgerindex;
        $map->txtype = $DModelName::TYPE;
        $map->condition = $cond;
        $map->count_num = $count;
        $map->page = $subpage;
        $map->created_at = \date('Y-m-d H:i:s');
        $map->save();
      }
  
      $r = $map->count_num;
      Cache::put( $cache_key, $r, 2629743); //2629743 seconds = 1 month
    }
    
    return $r;
  }

  /**
   * Adds WHERE conditions to query builder if any.
   * @return \BaoPham\DynamoDb\DynamoDbQueryBuilder
   */
  public function applyQueryCondition(\BaoPham\DynamoDb\DynamoDbQueryBuilder $query, ...$params)
  {
    $issuerAndToken = self::extractIssuerAndToken($params[0]);
    if($issuerAndToken['issuer'] == 'XRP' && $issuerAndToken['currency'] == 'XRP') {
      //i and c must not exist
      $query = $query->whereNull('i')->whereNull('c'); //check value presence (in attribute always does not exists if out)
    } else {
      $query = $query->where('i', '=',$issuerAndToken['issuer'])->where('c', '=', $issuerAndToken['currency']);
    }
    return $query;
  }

  /**
   * Check if DyDB item has $value in its data.
   * Checked if combination of fields 'i' and 'c' matches.
   * @return bool
   */
  public static function itemHasFilter(\App\Models\DTransaction $item, string|int|float|bool $value): bool
  {
    if($value == 'XRP') {
      if(!isset($item->i) && !isset($item->c))
        return true;
      return false;
    }

    if(isset($item->i) && isset($item->c)) {
      $issuerAndToken = self::extractIssuerAndToken($value);
      if((string)$item->i == (string)$issuerAndToken['issuer'] && (string)$item->c == (string)$issuerAndToken['currency'])
        return true;
    }
    return false;
  }
  

}