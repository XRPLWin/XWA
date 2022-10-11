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
    
    $breakpoint = config('xwa.paginator_breakpoint');
    //dd($r,$breakpoint);
    $paged_data = [];
    $page = 1;
    $count = 0;
    foreach($r as $subPages) {
      foreach($subPages as $txs) {
        foreach($txs as $txType => $data) {
          $count += $data['total']; //total
          $paged_data[$page][$txType][] = $data;
        }
        if($this->calcPageShift($count,$breakpoint))
          $page++;
      }
    }
    //dd($paged_data);
   
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
          $_ledger_index_lasts[] = $subpageCounts['last2'];
          $_ledgerindex_last_ids[] = $subpageCounts['li_id'];
        }
        //dd($txtypeSubpages,$_ledger_index_firsts,$_ledger_index_lasts,$_ledgerindex_last_ids);
        $scanplan[$page][$txtype] = [
          'total' => $_total,
          'found' => $_found,
          'e' => $_e,
          'ledgerindex_first' => \min($_ledger_index_firsts),
          'ledgerindex_last' => \max($_ledger_index_lasts),
          'ledgerindex_last_id' => \max($_ledgerindex_last_ids)
        ];
        //if($txtype == 'Trustset') dd( $scanplan[$page][$txtype],$txtypeSubpages);
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
        //dd($list[$li_subpage]);
        $first = $this->firstLi_toLedgerIndex($li_subpage,$list[$li_subpage]['first'] ? ($list[$li_subpage]['first']):null); 
        $last = $this->nextLi_toLedgerIndex($li_subpage,$list[$li_subpage]['last']) + 1; //start of next "first"
        //dump( $first.' - '.$last);
        $breakpoints[$first] = true;
        $breakpoints[$last] = true;
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
      //dd($k,$breakpoint,$breakpoints_values);
      if($k > 0) {
        $pages[$page] = [$breakpoints_values[$k-1],($breakpoint - 1)];
      }
      $page++;
    }
    //dump($breakpoints_values,$pages);

    # Loop pages and collect transaction counts
    $collection = [];
    foreach($pages as $page => $p) {
      //dd($pages);
      //get all transactions for this breakpoints
      //if next is null then include and pass on
      foreach($this->data as $txType => $list) { //each txtype
        
        foreach($list as $li_subpage => $d) { //1234.001..9
          
          if($d['found'] === 0) continue; //skip zero transactions
          //dd($d, $li_subpage);
          
          $first = $this->firstLi_toLedgerIndex($li_subpage, $d['first']);
          $last = $this->nextLi_toLedgerIndex($li_subpage, $d['last']);
          
          $valid = false;
          $e = $d['e'];
          # EQUAL TO FIRST EDGE OR EQUAL TO LAST EDGE
          //dd($li_subpage,$d,$first,$last,$p);
          if($p[0] === $first || $p[1] === $last) {
            $valid = true;
          } else {
            # IS SPANNED ACROS MIDDLE PAGES
            //dump('Page no'.$page.': '.$p[0].' >= '.$first.' && '.$p[1].' <= '.$last.' = ', ($p[0] >= $first && $p[1] <= $last));
            if($p[0] >= $first && $p[1] <= $last) {
              $valid = true;
              $e = 'lte';
            }
          }

          if($valid) {
            $d['first2'] = $p[0];
            $d['last2']  = $p[1];
            $d['li_id']  = $li_subpage;
            $d['e'] = self::calcSearchEqualizer($d['e'], $e);
            $collection[$page][$txType] = $d;
          }
          //dd($page,$list,$first,$last);
        }
      }
    }
    //dd($collection,$this->data);
    return $collection;
  }

  /**
   * @param string $li_subpage - "1234.001"
   * @param ?string $first - null or "354759440290"
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