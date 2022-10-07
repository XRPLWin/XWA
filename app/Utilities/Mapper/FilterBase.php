<?php

namespace App\Utilities\Mapper;

use App\Utilities\Mapper\FilterInterface;

abstract class FilterBase implements FilterInterface {

  /**
   * Return eq or lte depending of parameters
   * @param string $existingE eq|lte
   * @param string $newE eq|lte
   * @return string eq|lte
   */
  public static function calcEqualizer(string $existingE, string $newE): string
  {
    if($existingE == 'lte')
      return 'lte';
      
    return $newE;
  }

  /**
   * Check if DyDB item has $value in its data.
   * Checked field depends on filter.
   * @return bool
   */
  public static function itemHasFilter(\App\Models\DTransaction $item, string|int|float|bool $value): bool
  {
    return true;
  }

  /**
   * If not applicable return $param
   * If applicable eg. counterparty of "rAccount123" returns "Acc"
   * eg. source and destination tags returns 12345 -> 12, 34567 -> 34 ...
   * Depends on filter.
   * @return string
   */
  public static function parseToNonDefinitiveParam(string $param): string
  {
    return $param;
  }

  /**
   * Adds WHERE conditions to query builder if any.
   * @return \BaoPham\DynamoDb\DynamoDbQueryBuilder
   */
  public function applyQueryCondition(\BaoPham\DynamoDb\DynamoDbQueryBuilder $query, ...$params)
  {
    throw new \Exception('Not implemented');
    //return $query;
  }

  /**
   * @param string $ledgerindexwithpage - 1827.0020
   * @return array [ (int)1827, (int)20]
   */
  protected function explodeLedgerindex(string $ledgerindexwithpage): array
  {
    $ex = \explode('.',$ledgerindexwithpage);
    $ledgerindex = (int)$ex[0];
    $subpage = (int)(\ltrim($ex[1]));
    return [$ledgerindex,$subpage];
  }
}
