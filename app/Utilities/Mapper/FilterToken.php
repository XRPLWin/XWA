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

    $param = explode('+', $param);
    if(count($param) != 2 )
      throw new \Exception('Invalid token parameter');
    return ['issuer' => $param[0], 'currency' => $param[1]]; 
  }

  /**
   * Returns array with count information for this filter.
   * @return array
   */
  public function reduce(): array
  {
    $FirstFewLetters = self::parseToNonDefinitiveParam($this->conditions['token']);
    $r = [];
    //dd($this->foundLedgerIndexesIds);
    foreach($this->txTypes as $txTypeNamepart) {
      $r[$txTypeNamepart] = [];
      if(isset($this->foundLedgerIndexesIds[$txTypeNamepart])) {
        foreach($this->foundLedgerIndexesIds[$txTypeNamepart] as $ledgerindex => $countTotalReduced) {
          $r[$txTypeNamepart][$ledgerindex] = ['total' => $countTotalReduced['total'], 'found' => 0, 'e' => 'eq'];
          if($countTotalReduced['total'] == 0 || $countTotalReduced['found'] == 0) continue; //no transactions here, skip
          
          $count = $this->fetchCount($ledgerindex, $txTypeNamepart, $FirstFewLetters);
          if($count > 0) { //has transactions
            $r[$txTypeNamepart][$ledgerindex] = ['total' => $countTotalReduced['total'], 'found' => $count, 'e' => self::calcEqualizer($countTotalReduced['e'], 'lte')];
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

  private function fetchCount(int $ledgerindex, string $txTypeNamepart, string $FirstFewLetters): int
  {
    $DModelName = '\\App\\Models\\DTransaction'.$txTypeNamepart;
    $cond = $this->conditionName($FirstFewLetters);
    $cache_key = 'mpr'.$this->address.'_'.$cond.'_'.$ledgerindex.'_'.$DModelName::TYPE;

    $r = Cache::get($cache_key);
    //$r = null;
    if($r === null) {
      $map = Map::select('count_num')
        ->where('address', $this->address)
        ->where('ledgerindex_id',$ledgerindex)
        ->where('txtype',$DModelName::TYPE)
        ->where('condition',$cond)
        ->first();
      //$map = null;
      if(!$map)
      {
        //no records found, query DyDB for this day, for this type and save
        //$li = Ledgerindex::select('ledger_index_first','ledger_index_last')->where('id',$ledgerindex)->first();
        $li = Ledgerindex::getLedgerindexData($ledgerindex);

        if(!$li) {
          //clear cache then then/instead exception?
          throw new \Exception('Unable to fetch Ledgerindex of ID (previously cached): '.$ledgerindex);
          //return 0; //something went wrong
        }
        $DModelTxCount = $DModelName::where('PK',$this->address.'-'.$DModelName::TYPE);
   
        if($li[1] == -1)
          $DModelTxCount = $DModelTxCount->where('SK','>=',$li[0]);
        else
          $DModelTxCount = $DModelTxCount->where('SK','between',[$li[0],$li[1] + 0.9999]);
        $issuerAndToken = self::extractIssuerAndToken($FirstFewLetters);
        if($issuerAndToken['issuer'] == 'XRP' && $issuerAndToken['currency'] == 'XRP') {
          //i and c must not exist
          $DModelTxCount = $DModelTxCount->whereNull('i')->whereNull('c'); //check value presence (in attribute always does not exists if out)
        } else {
          $DModelTxCount = $DModelTxCount->where('i', '=',$issuerAndToken['issuer'])->where('c', '=', $issuerAndToken['currency']);
        }
          
          $DModelTxCount = $DModelTxCount
            //->toDynamoDbQuery()
            ->count();
        
        $map = new Map;
        $map->address = $this->address;
        $map->ledgerindex_id = $ledgerindex;
        $map->txtype = $DModelName::TYPE;
        $map->condition = $cond;
        $map->count_num = $DModelTxCount;
        $map->created_at = now();
        $map->save();
      }
  
      $r = $map->count_num;
      Cache::put( $cache_key, $r, 2629743); //2629743 seconds = 1 month
    }
    
    return $r;
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