<?php

namespace App\Utilities\Sql;
use App\Utilities\Base\Mapper as BaseMapper;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Search conditions mapper
 */
class Mapper extends BaseMapper
{
  /**
   * @return string t > x AND t < y (this is inclusive)
   */
  //public function generateTConditionSQL(?QueryBuilder $querybuilder = null): string|QueryBuilder
  //{
  //  if($querybuilder === null)
  //    throw new \Exception('Query builder is required for generateTConditionSQL() SQL Mapper');
  //
  //  return $querybuilder->whereBetween('t',[$this->conditions['from'].' 00:00:00',$this->conditions['to'].' 23:59:59']);
  //  //dd($querybuilder);
  //  //return 't BETWEEN "'.$this->conditions['from'].' 00:00:00" AND "'.$this->conditions['to'].' 23:59:59"';
  //}

  /**
   * Appends conditions to existing QueryBuilder
   * @return QueryBuilder
   */
  public function generateConditionsSQL(?QueryBuilder $queryBuilder = null): string|QueryBuilder
  {
    if($queryBuilder === null)
      throw new \Exception('Query builder is required for generateConditionsSQL() SQL Mapper');

    $queryBuilder->where('address',$this->address)
      # (required) address and time range: t > x AND t < y (this is inclusive)
      ->whereBetween('t',[$this->conditions['from'].' 00:00:00',$this->conditions['to'].' 23:59:59']);

    # (optional) xwatype - Transaction types
    if(count($this->conditions['txTypes']) > 0) {
      $queryBuilder->whereIn('xwatype',\array_keys($this->conditions['txTypes']));
    }

    # (optional) isin - Direction
    if(isset($this->conditions['dir'])) {
      if($this->conditions['dir'] == 'in')
        $queryBuilder->where('isin',true);
      else
        $queryBuilder->where('isin',false);
    }

    # (optional) r - Counterparty (array)
    if(isset($this->conditions['cp'])) {

      if(count($this->conditions['cp']) == 1)
        $queryBuilder->where('r',$this->conditions['cp'][0]);
      else {
        $_temp_cp = $this->conditions['cp'];
        $queryBuilder->where(function($q) use ($_temp_cp) {
          foreach($_temp_cp as $cp) {
            $q->orWhere('r',$cp);
          }
        });
        unset($_temp_cp);
       // $SQL .= ' AND (r = """'.\implode('""" OR r = """',$this->conditions['cp']).'""")';
      }
    }

    # (optional) st - Source tag
    if(isset($this->conditions['st'])) {
      $queryBuilder->where('st',$this->conditions['st']);
    }

    # (optional) dt - Destination tag
    if(isset($this->conditions['dt'])) {
      $queryBuilder->where('dt',$this->conditions['dt']);
    }
    //https://xlanalyzer.test/v1/account/search/rWinEUKtN3BmYdDoGU6HZ7tTG54BeCAiz?from=2023-08-01&to=2023-08-30&token=rfXwi3SqywQ2gSsvHgfdVsyZdTVM15BG7Z+65646974696F6E73000000000000000000000000&types=Activation,Payment&cp=rwietsevLFg8XSmG3bEZzFein1g8RBqWDZ,rYhfynZDrde1uSvvQAYctApg6DnVE5HKm
    //https://xlanalyzer.test/v1/account/search/rWinEUKtN3BmYdDoGU6HZ7tTG54BeCAiz?from=2022-02-01&to=2022-02-30&token=rfXwi3SqywQ2gSsvHgfdVsyZdTVM15BG7Z+65646974696F6E73000000000000000000000000
    # (optional) Token (ISSUER+CURRENCY or XRP)
    if(isset($this->conditions['token'])) {
      $issuerAndToken = self::extractIssuerAndToken($this->conditions['token']);
      //dd($issuerAndToken);
      if($issuerAndToken['issuer'] == 'XRP' && $issuerAndToken['currency'] == 'XRP') {
        $queryBuilder->where(function($q) {
          $q->orWhere(function($q2){
            $q2->whereNotNull('a')
              ->whereNull('i')
              ->whereNull('c');
          });
          $q->orWhere(function($q2){
            $q2->whereNotNull('a2')
              ->whereNull('i2')
              ->whereNull('c2');
          });
          $q->orWhere(function($q3){
            $q3->whereNotNull('a3')
              ->whereNull('i3')
              ->whereNull('c3');
          });
        });
        //$SQL .= ' AND ( (a IS NOT NULL AND i IS NULL AND c IS NULL) OR (a2 IS NOT NULL AND i2 IS NULL AND c2 IS NULL) )';
      } else {
        $queryBuilder->where(function($q) use ($issuerAndToken) {
          $q->orWhere(function($q2) use ($issuerAndToken) {
            $q2->whereNotNull('a')
              ->where('i',$issuerAndToken['issuer'])
              ->where('c',$issuerAndToken['currency']);
          });
          $q->orWhere(function($q2) use ($issuerAndToken) {
            $q2->whereNotNull('a2')
              ->where('i2',$issuerAndToken['issuer'])
              ->where('c2',$issuerAndToken['currency']);
          });
          $q->orWhere(function($q3) use ($issuerAndToken) {
            $q3->whereNotNull('a3')
              ->where('i3',$issuerAndToken['issuer'])
              ->where('c3',$issuerAndToken['currency']);
          });
        });
        //$SQL .= ' AND ( (a IS NOT NULL AND i = """'.$issuerAndToken['issuer'].'""" AND c = """'.$issuerAndToken['currency'].'""") OR (a2 IS NOT NULL AND i2 = """'.$issuerAndToken['issuer'].'""" AND c2 = """'.$issuerAndToken['currency'].'""") )';
      }
      unset($issuerAndToken);
    }

    # (optional) Offer - in list of offers contained in single row
    if(isset($this->conditions['offer'])) {
      $queryBuilder->whereJsonContains('offers',$this->conditions['offer']);
    }

    if(isset($this->conditions['nft'])) {
      $queryBuilder->where('nft',$this->conditions['nft']);
    }

    # (optional) NFTOffer - in list of offers contained in single row
    if(isset($this->conditions['nftoffer'])) {
      $queryBuilder->whereJsonContains('nftoffers',$this->conditions['nftoffer']);
    }

    # (optional) Hook - in list of hooks contained in single row
    if(isset($this->conditions['hook'])) {
      $queryBuilder->whereJsonContains('hooks',$this->conditions['hook']);
    }

    //TODO hooks and pc

    return $queryBuilder;
  }

}