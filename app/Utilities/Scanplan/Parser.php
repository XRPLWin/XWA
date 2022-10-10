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

  public function parse()
  {
    $r = $this->generateDailySubPages();
    //petljaj ove ledger indexe, unutra su sub-pageovi, mergaj sta se moze mergat i napravi optimalni scanplan
    //dd($r);
    $breakpoint = config('xwa.paginator_breakpoint');

    $paged_data = [];
    $page = 1;
    $count = 0;
    foreach($r as $ledgerIndex => $subPages) {
      foreach($subPages as $subPage => $txs) {
        foreach($txs as $txType => $data) {
          $count += $data['total']; //total
          $paged_data[$page][$txType][] = $data;
        }
        if($this->calcPageShift($count,$breakpoint))
          $page++;
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

        foreach($txtypeSubpages as $subpageCounts) {
          $_total += $subpageCounts['total'];
          $_found += $subpageCounts['found'];
          $_e = self::calcSearchEqualizer($_e, $subpageCounts['e']);
          $_ledger_index_firsts[] = $subpageCounts['first2'];
          $_ledger_index_lasts[] = $subpageCounts['next2'];
          $_ledgerindex_last_ids[] = $subpageCounts['li_id'];
        }
        dump($subpageCounts);
        $scanplan[$page][$txtype] = [
          'total' => $_total,
          'found' => $_found,
          'e' => $_e,
          'ledgerindex_first' => \min($_ledger_index_firsts),
          'ledgerindex_last' => \max($_ledger_index_lasts),
          'ledgerindex_last_id' => \max($_ledgerindex_last_ids)
        ];
      }
    }

    return $scanplan;
  }

  public static function calcSearchEqualizer(string $existingE, string $newE): string
  {
    if($existingE == 'lte')
      return 'lte';
      
    return $newE;
  }


  /**
   * @return array [ txtype => [ 'stats' => [], 'data' => [] ]]
   * test: http://analyzer.xrplwin.test/v1/account/search/rEb8TK3gBgk5auZkwc6sHnwrGVJH8DuaLh?from=2018-01-03&to=2018-01-29&page=1
   * test: http://analyzer.xrplwin.test/v1/account/search/rsmYqAFi4hQtTY6k6S3KPJZh7axhUwxT31?from=2022-01-01&to=2022-01-29
   */
  /*public static function parseOnePage(array $data): array
  {
    //TODO merge common txs into one
    return $data;
  }*/

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
    //dd($ledgerIndexIds,$r);
    return $r;
  }

  private function runOneIndex(array $data)
  {
    $breakpoints = [];
    foreach($this->data as $txType => $list) { //each txtype
      
      foreach($data as $li_subpage) { //1234.001..9

        if(!isset($list[$li_subpage])) continue;
        
        $first = $this->firstLi_toLedgerIndex($li_subpage,$list[$li_subpage]['first']);
        $next = $this->nextLi_toLedgerIndex($li_subpage,$list[$li_subpage]['next']);
        //dump( $next,$li_subpage, '----'.$list[$li_subpage]['next']);
        $breakpoints[$first] = true;
        $breakpoints[$next] = true;
      }
    }
    \ksort($breakpoints, SORT_NUMERIC);
    # Breakpoints done, how much breakpoint that much sub-pages
    //dd($breakpoints);
    # Generate pages between breakpoints:
    $breakpoints_values = [];
    foreach($breakpoints as $b => $foo) {
      $breakpoints_values[] = $b;
    }
    
    $pages = [];
    $page = 0;
    foreach($breakpoints_values as $k => $breakpoint) {
     // dd($k,$breakpoint);
      if($k > 0) {
        $pages[$page] = [$breakpoints_values[$k-1],$breakpoint];
      }
      $page++;
    }


    # Loop pages and collect transaction counts
    $collection = [];
    foreach($pages as $page => $p) {
      //dd($pages);
      //get all transactions for this breakpoints
      //if next is null then include and pass on
      foreach($this->data as $txType => $list) { //each txtype
        //dd($this->data);
        foreach($list as $li_subpage => $d) { //1234.001..9

          if($d['found'] === 0) continue; //skip zero transactions
          //dd($d, $li_subpage);
          $first = $this->firstLi_toLedgerIndex($li_subpage, $d['first']);
          $next = $this->nextLi_toLedgerIndex($li_subpage, $d['next']);
          $valid = false;
          # EQUAL TO FIRST EDGE OR EQUAL TO LAST EDGE
          if((string)$p[0] == $first || (string)$p[1] == $next) {
            $valid = true;
          } else {
            # IS SPANNED ACROS MIDDLE PAGES
            //dump('Page no'.$page.': '.$p[0].' >= '.$first.' && '.$p[1].' <= '.$next.' = ', ($p[0] >= $first && $p[1] <= $next));
            if($p[0] >= $first && $p[1] <= $next) {
              $valid = true;
            }
          }

          if($valid) {
            $d['first2'] = (string)$p[0];
            $d['next2'] = (string)$p[1];
            $d['li_id'] = $li_subpage;
            $collection[$page][$txType] = $d;
          }
          //dd($page,$list,$first,$next);
        }
      }
    }
    //dd($collection,$this->data);
    return $collection;
  }

  /**
   * @param string $li_subpage - "1234.001"
   * @param ?string $first - null or "35475944.029"
   * @return int ledger_index
   */
  private function firstLi_toLedgerIndex(string $li_subpage, ?string $ledger_index): string
  {
    if($ledger_index !== null)
      return $ledger_index;

    if(isset($this->mem_li[$li_subpage])) {
      $li = $this->mem_li[$li_subpage];
    } else {
      $li = Ledgerindex::getLedgerindexData(\explode('.',$li_subpage)[0]);
      $this->mem_li[$li_subpage] = $li;
    }
    return (string)$li[0];
  }

  private function nextLi_toLedgerIndex(string $li_subpage, ?string $ledger_index): string
  {
    if($ledger_index !== null)
      return $ledger_index;

    if(isset($this->mem_li[$li_subpage])) {
      $li = $this->mem_li[$li_subpage];
    } else {
      $li = Ledgerindex::getLedgerindexData(\explode('.',$li_subpage)[0]);
      $this->mem_li[$li_subpage] = $li;
      //$li[1] += 0.9999; //due to inclusive between filtering, last edge of LedgerIndex + 0.9999;
      //dd($li);
    }
    return (string)$li[1];
  }


  /**
   * @return [  "1827.0001" => 1827, ...  ]
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
    return $keys;
  }

 
}