<?php

namespace App\Utilities\Scanplan;
use App\Models\Ledgerindex;

class Parser
{
  private readonly array $data;
  private array $mem_li = [];

  public function __construct(array $intersectedLedgerIndexes)
  {
    $this->data = $intersectedLedgerIndexes;
  }

  /*private function reducePages(array $r)
  {
    //nothing to reduce on zero or one page
    if(count($r) < 2)
      return $r;

    $r = \array_values($r);
  
    $reduced = \array_reduce($r, function($carry,$item) {
      
      if(count($item) > 1) {
        //can not be merged - have multi pages
        $carry[] = $item;
        return $carry;
      }

      $item_eligible = true;
      foreach($item[1] as $itemTxType => $itemCounts) {
        if($itemCounts['first'] !== null) {
          $item_eligible = false;
        }
      }
      unset($itemTxType);
      unset($itemCounts);

      if(!$item_eligible) {
        //can not be merged - one of them or all have partial first edge
        $carry[] = $item;
        return $carry;
      }
      unset($item_eligible);


      # Get previous item
      $prev = \end($carry);
      \reset($carry);
      if(!$prev) {
        $carry[] = $item;
        return $carry;
      }
      
      if(count($prev) > 1) {
        //can not be merged - prev have multi pages
        $carry[] = $item;
        return $carry;
      }

      $merged = [];
      $is_valid = true;
      # Merge $item with $prev to $merged
      foreach($item[1] as $itemTxType => $itemCounts) {
        # Merge individual tx type
        if(isset($prev[1][$itemTxType])) {
         
          # Merge two counts
          $prevCounts = $prev[1][$itemTxType];
          if(($prevCounts['last2']+1) === $itemCounts['first2']) {
            $merged[$itemTxType] = [
              'total' => ($prevCounts['total'] + $itemCounts['total']),
              'found' => ($prevCounts['found'] + $itemCounts['found']),
              'e' => \App\Utilities\Scanplan\Parser::calcSearchEqualizer($prevCounts['e'], $itemCounts['e']),
              'first' => $prevCounts['first'],
              'last' => $itemCounts['last'],
              'first2' => $prevCounts['first2'],
              'last2' => $itemCounts['last2'],
              'li_id' => $itemCounts['li_id']
            ];
          } else {
            //could not stitch together
            $is_valid = false;
          }
        } else {
          $merged[$itemTxType] = $itemCounts;
         
        }
      } //endforeach
      
      if($is_valid) {
        $index = count($carry)-1;

        foreach($merged as $mk => $mv) {
          $carry[$index][1][$mk] = $mv;
        }
      } else {
        $carry[] = $item;
      }
      return $carry;
    },[]);
    return $reduced;
  }*/

  public function parse()
  {
    $r = $this->generateDailySubPages();
    $breakpoint = config('xwa.paginator_breakpoint');
    $paged_data = [];
    $page = 1;
    $count = 0;
    
    foreach($r as $subPages) {
      foreach($subPages as $txs) {
        foreach($txs as $txType => $data) {
          $count += $data['total']; //total
          $paged_data[$page][$txType][] = $data;
        }
        if($this->calcPageShift($count,$breakpoint)) {
          $page++;
          $count = 0;
        }
      }
    }
    //Generate scanplan
    $scanplan = [];
    foreach($paged_data as $page => $txtypes) {
      foreach($txtypes as  $txtype => $txtypeSubpages) {
        //set initial values
        $_total = 0;
        $_found = 0;
        $_e = 'eq';
        $_ledger_index_firsts = $_ledger_index_lasts = $_ledgerindex_last_ids = [];
        $_is_current = false; //flag to check if last2 is -1

        foreach($txtypeSubpages as $subpageCounts) {
          $_total += $subpageCounts['total'];
          $_found += $subpageCounts['found'];
          $_e = self::calcSearchEqualizer($_e, $subpageCounts['e']);
          $_ledger_index_firsts[] = $subpageCounts['first2'];
          if($subpageCounts['last2'] === -1)
            $_is_current = true;
          $_ledger_index_lasts[] = $subpageCounts['last2'];
          $_ledgerindex_last_ids[] = $subpageCounts['li_id']; //not needed
        }

        $scanplan[$page][$txtype] = [
          'total' => $_total,
          'found' => $_found,
          'e' => $_e,
          'ledgerindex_first' => \min($_ledger_index_firsts),
          'ledgerindex_last' => $_is_current ? -1 : \max($_ledger_index_lasts),
          'ledgerindex_last_id' => \max($_ledgerindex_last_ids) //not needed
        ];
      }
    }
    //$scanplan = $this->reducePages($scanplan);
    return $scanplan;
  }

  public static function calcSearchEqualizer(string $existingE, string $newE): string
  {
    if($existingE == 'lte')
      return 'lte';
      
    return $newE;
  }

  private function calcPageShift(int $count, int $breakpoint): int
  {
    return ($count >= $breakpoint) ? 1:0;
  }

  /**
   * @return array
   * [
   *   <LedgerIndex->id> => [ sub-page => [ TxType => [ total,found,e,first,next,first2,next2 ] ] ] ...
   * ]
   */
  private function generateDailySubPages()
  {
    $ledgerIndexIds = $this->getAllLedgerIndexesIDs();
    $r = [];
    foreach($ledgerIndexIds as $ledgerIndexID => $ledgerIndexSubpages) {
      //$ledgedIndexSubpages: array of maximum number of successive pages for this specific LedgerIndexID - [ '1827.0001', '1827.0002', '1827.0003', ... ]
      $r[$ledgerIndexID] = $this->runOneIndex($ledgerIndexSubpages);
    }
    return $r;
  }

  public function runOneIndex(array $data)
  {
    $breakpoints = [];
    foreach($this->data as $txType => $list) { //each txtype
      foreach($data as $li_subpage) { //1234.001..9
        if(!isset($list[$li_subpage])) continue;

        $first = $this->firstLi_toLedgerIndex($li_subpage,$list[$li_subpage]['first'] ? ($list[$li_subpage]['first']):null);
        $breakpoints[$first] = true;
        $last = $this->nextLi_toLedgerIndex($li_subpage,$list[$li_subpage]['last']); //start of next "first"
        
        if($last === -1)
          $breakpoints[$last] = true; //current ledger (-1)
        else {
          # Sanity check
          if($first > $last)
            throw new \Exception('First greather than last - ledgerindex ranges not correct');
          $breakpoints[$last+1] = true;
        }
      }
    }
    \ksort($breakpoints, SORT_NUMERIC);
    # Breakpoints done, how much breakpoint that much sub-pages
    
    # Generate pages between breakpoints:
    $breakpoints_values = [];
    foreach($breakpoints as $b => $foo) {
      $breakpoints_values[] = $b;
    }
    
    //Move -1 to end - this is current ledger_index and it belongs at end of the list
    if(isset($breakpoints_values[0]) && $breakpoints_values[0] === -1) {
      unset($breakpoints_values[0]);
      $breakpoints_values[] = -1;
      $breakpoints_values = array_values($breakpoints_values);
    }
    
    $pages = [];
    $page = 0;
    foreach($breakpoints_values as $k => $breakpoint) {
      if($k > 0) {
        if($breakpoint !== -1)
          $breakpoint--;
        $pages[$page] = [$breakpoints_values[$k-1],$breakpoint];
      }
      $page++;
    }
    
    # Loop pages and collect transaction counts
    $collection = [];
    foreach($pages as $page => $p) {
      //get all transactions for this breakpoints
      //if next is null then include and pass on
      foreach($this->data as $txType => $list) { //each txtype
        $_d = null;
        foreach($list as $li_subpage => $d) { //1234.001..9
          
          if($d['found'] === 0) continue; //skip zero transactions

          $first = $this->firstLi_toLedgerIndex($li_subpage, $d['first']);
          $last = $this->nextLi_toLedgerIndex($li_subpage, $d['last']);
          
          $valid = false;
          $e = $d['e'];
          # EQUAL TO FIRST EDGE OR EQUAL TO LAST EDGE
          if($p[0] === $first || $p[1] === $last) {
            //if(count($data) == 2) dump('Page no'.$page.': '.$p[0].' == '.$first.' || '.$p[1].' == '.$last.' = '. (int)($p[0] == $first && $p[1] == $last));
            $valid = true;
            if($p[0] !== $first || $p[1] !== $last) {
              $e = 'lte';
            }
              
          } else {
            # IS SPANNED ACROS MIDDLE PAGES
            //if(count($data) == 2) dump('Page no'.$page.': '.$p[0].' >= '.$first.' && '.$p[1].' <= '.$last.' = '. (int)($p[0] >= $first && $p[1] <= $last));
            if($p[0] >= $first && ($last === -1 || $p[1] <= $last)) {
              $valid = true;
              $e = 'lte';
            }
          }
          if($valid) {

            if($_d === null) {
              //first iteration, create object
              $_d = [
                'total' => $d['total'],
                'found' => $d['found'],
                'e' => $d['e'],
                'first' => $d['first'],
                'last' => null,
                'first2' => $first,
                'last2' => null,
              ];
            } else {
              $_d['total'] += $d['total'];
              $_d['found'] += $d['found'];
            }

            
            $_d['last'] = $d['last'];
            $_d['last2'] = $last; //last iteration will fill this finalized
            $_d['e'] = self::calcSearchEqualizer($_d['e'], $e);
            $_d['li_id'] = $li_subpage; //last iteration will fill this finalized

            //$d['first2'] = $p[0];
            //$d['last2']  = $p[1];
            //$d['li_id']  = $li_subpage;
          }
        }
        if($_d !== null) {
          $collection[$page]['_edges'] = $p;
          $collection[$page][$txType] = $_d;
        }
          
      }
    }

    unset($page);
    # STEP 2
    # For type counters that can overflow to next sub-pages - equilize their 
    # first2, last2 edges by using "_edges" limits.

    foreach($collection as $page => $pagedata) {
      $edges = $pagedata['_edges'];
      unset($pagedata['_edges']);

      foreach($pagedata as $txtype => $counts)
      {
        # fix first2
        if($counts['first2'] < $edges[0])
          $collection[$page][$txtype]['first2'] = $edges[0];

        # fix last2
        if($counts['last2'] > $edges[1])
          $collection[$page][$txtype]['last2'] = $edges[1];

        unset($collection[$page][$txtype]['first']);
        unset($collection[$page][$txtype]['last']);
      }
      unset($collection[$page]['_edges']);
    }
    return $collection;
  }

  /**
   * @param string $li_subpage - "1234.001"
   * @param ?int $ledger_index - null or "354759440290"
   * @return int ledger_index
   */
  private function firstLi_toLedgerIndex(string $li_subpage, ?int $ledger_index): int
  {
    if($ledger_index !== null)
      return $ledger_index;

    if(isset($this->mem_li[$li_subpage])) {
      $li = $this->mem_li[$li_subpage];
    } else {
      $li = Ledgerindex::getLedgerindexData(\explode('.',$li_subpage)[0]);
      $this->mem_li[$li_subpage] = $li;
    }
    return $li[0];
  }

  /**
   * @param string $li_subpage - "1234.001"
   * @param ?int $ledger_index - null or "354759440290"
   * @return int ledger_index - can be -1 for current ledger index
   */
  private function nextLi_toLedgerIndex(string $li_subpage, ?int $ledger_index): int
  {
    if($ledger_index !== null)
      return $ledger_index;

    if(isset($this->mem_li[$li_subpage])) {
      $li = $this->mem_li[$li_subpage];
    } else {
      $li = Ledgerindex::getLedgerindexData(\explode('.',$li_subpage)[0]);
      $this->mem_li[$li_subpage] = $li;
    }
    return $li[1];
  }

  /**
   * @return [  1827 => [ "1827.0001", "1827.0002", ... ], ...  ]
   */
  private function getAllLedgerIndexesIDs()
  {
    $keys = [];
    
    foreach($this->data as $t => $list)
    {
      foreach($list as $k => $v){
        $keys[(int)$k][] = $k;
      }
    }

    foreach($keys as $k => $v) {
      $keys[$k] = \array_unique($keys[$k]);
    }

    \ksort($keys, SORT_NUMERIC);

    return $keys;
  }

 
}