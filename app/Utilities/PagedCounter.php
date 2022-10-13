<?php

namespace App\Utilities;
use App\Override\DynamoDb\DynamoDbQueryBuilder;

class PagedCounter
{
    /**
     * Takes Query builder and do paged count in loop.
     * @return int
     */
    public static function count(DynamoDbQueryBuilder $queryBuilder): int
    {
      $do = true;
      $count = 0;
      while($do) {
        $c = $queryBuilder->pagedCount();
        if(!$c->lastKey)
          $do = false;
        $count += $c->count;
      }
      return $count;
    }

    /**
     * Takes Query builder and do paged count in loop.
     * Also retuns lastKey breakpoints.
     * @param DynamoDbQueryBuilder $queryBuilder
     * @param ?array $pk_map ['PK','S'] Will map value of Partition key to breakpoint
     * @param ?array $sk_map ['SK','N'] Will map value of Sort key to breakpoint
     */
    /*public static function countWithBreakpoints(DynamoDbQueryBuilder $queryBuilder, ?array $pk_map = null, ?array $sk_map = null): array
    {
      $do = true;
      $count = 0;
      $breakpoints = [];
      while($do) {
        $c = $queryBuilder->pagedCount();
        if(!$c->lastKey)
          $do = false;
        else {
          $bp = [];
          if($pk_map) $bp[] = [ $c->lastKey[$pk_map[0]][$pk_map[1]] => $c->count];
          if($sk_map) $bp[] = [ $c->lastKey[$sk_map[0]][$sk_map[1]] => $c->count];
          $breakpoints[] = $bp;
          unset($bp);
        }
        $count += $c->count;
      }
      return ['count' => $count, 'breakpoints' => $breakpoints];
    }*/

    /**
     * Specific function for counting dynamodb XRPL Transactions.
     * @deprecated
     */
    /*public static function countAndReturnBreakpointsForTransacitons(DynamoDbQueryBuilder $queryBuilder): array
    {
      $r = self::countWithBreakpoints($queryBuilder,null,['SK','N']);
      $breakpoints = '';
      foreach($r['breakpoints'] as $bp) {
        $key = \key($bp[0]);
        $breakpoints .= $key.'-'.$bp[0][$key].'|';
        //$breakpoints .= \key($bp[0]).'|';
      }
      $breakpoints = \rtrim($breakpoints,'|');

      return ['count' => $r['count'], 'breakpoints' => $breakpoints];
    }*/
}