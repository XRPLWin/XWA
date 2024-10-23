<?php

namespace App\Utilities\Bigquery;
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
  //public function generateTConditionSQL(?QueryBuilder $queryBuilder = null): string|QueryBuilder
  //{
  //  return 't BETWEEN "'.$this->conditions['from'].' 00:00:00" AND "'.$this->conditions['to'].' 23:59:59"';
  //}

  /**
   * Returns part of composed SQL for BigQuery
   * @return string
   */
  public function generateConditionsSQL(?QueryBuilder $queryBuilder = null): string|QueryBuilder
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

    # (optional) r - Counterparty (array)
    if(isset($this->conditions['cp'])) {
      if(count($this->conditions['cp']) == 1)
        $SQL .= ' AND r = """'.$this->conditions['cp'][0].'"""';
      else {
        $SQL .= ' AND (r = """'.\implode('""" OR r = """',$this->conditions['cp']).'""")';
      }
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

    # (optional) Offer - in list of offers contained in single row
    if(isset($this->conditions['offer'])) {
      $SQL .= ' AND EXISTS(SELECT 1 FROM UNNEST(offers) AS o WHERE o="""'.$this->conditions['offer'].'""")';
    }

    if(isset($this->conditions['nft'])) {
      $SQL .= ' AND nft = """'.$this->conditions['nft'].'"""';
    }

    # (optional) NFTOffer - in list of offers contained in single row
    if(isset($this->conditions['nftoffer'])) {
      $SQL .= ' AND EXISTS(SELECT 1 FROM UNNEST(nftoffers) AS x WHERE x="""'.$this->conditions['nftoffer'].'""")';
    }


    # (optional) Offer - in list of offers contained in single row
    if(isset($this->conditions['hook'])) {
      $SQL .= ' AND EXISTS(SELECT 1 FROM UNNEST(hooks) AS hok WHERE hok="""'.$this->conditions['hook'].'""")';
    }

    //TODO hooks and pc

    return $SQL;
  }
}