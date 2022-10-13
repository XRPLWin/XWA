<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Utilities\Search;
use App\Utilities\Scanplan\Parser as ScanplanParser;

/**
 * This test assumes $breakpoint is set to 500
 * $breakpoint = Search::getPaginatorBreakpoint();
 */
class ScanplanParserTest extends TestCase
{

  protected function setUp(): void
	{
		parent::setUp();
		$this->createApplication();
    //$this->migrateDatabaseLocal();
	}

  public function test_scanplan_case_4_two_pages(): void
	{
    $grid = [];

    //Page 1
    $grid['1000.0001|null|null_Payment' ] = [400,200]; //400
    $grid['1002.0001|null|null_Payment' ] = [200,200]; //600 - break
    
    $grid['1002.0001|null|null_Trustset'] = [1000,1000]; //80
    $grid['1002.0002|null|null_Trustset'] = [850,840];   //80

    //Page 2
    $grid['1004.0001|null|null_Payment']  = [49,49];   //49
    $grid['1005.0001|null|null_Payment']  = [49,49];   //98
    
    $intersected = $this->convertGridToIntersected($grid);
    //dd($intersected);
    $scanplan = new ScanplanParser($intersected);
    $scanplan = $scanplan->parse();
    //dd($scanplan);
    $this->assertEquals([
      1 => [ //page 1
        'Payment' => [
          'total' => 600,
          'found' => 400,
          'e' => 'eq',
          'ledgerindex_first' => 161219700000,
          'ledgerindex_last' => 161818769999,
          'ledgerindex_last_id' => '1002.0001',
        ],
        'Trustset' => [
          'total' => 80,
          'found' => 80,
          'e' => 'eq',
          'ledgerindex_first' => 161617930000,
          'ledgerindex_last' => 161818769999,
          'ledgerindex_last_id' => '1002.0001',
        ],
      ],
      2 => [ //page 2
        'Payment' => [
          'total' => 98,
          'found' => 98,
          'e' => 'eq',
          'ledgerindex_first' => 162018960000,
          'ledgerindex_last' => 162417149999,
          'ledgerindex_last_id' => '1005.0001',
        ]
      ]
    ], $scanplan);
  }


  public function test_numeric_ledgerindexes_decimal_sorting()
  {
    $a = [
      '1827' => 1,
      '1827.10' => 1,
      '1827.111' => 1,
      '1827.2' => 1,
      '1827.22' => 1,
      '10' => 1,
      '11' => 1,
      '1827.11000' => 1,
      '111' => 1,
      '2' => 1,
      '22' => 1,
    ];

    \ksort($a,SORT_NUMERIC);

    $this->assertEquals([
      '2' => 1,
      '10' => 1,
      '11' => 1,
      '22' => 1,
      '111' => 1,
      '1827' => 1,
      '1827.10' => 1,
      '1827.11000' => 1,
      '1827.111' => 1,
      '1827.2' => 1,
      '1827.22' => 1,
    ], $a);
  }

  public function test_scanplan_empty_yields_empty_array(): void
	{
    $scanplan = new ScanplanParser([]);
    $scanplan = $scanplan->parse();
    $this->assertEquals([],$scanplan);
  }

  public function test_scanplan_case_1_single_page(): void
	{
    $grid = [];

    $grid['3000.0001|null|null_Payment']  = [2,1];
    $grid['3002.0001|null|null_Payment']  = [2,2];
    $grid['3003.0001|null|null_Payment']  = [49,49];
    $grid['3004.0001|null|null_Payment']  = [49,49];
    $intersected = $this->convertGridToIntersected($grid);
    //dd($intersected);
    $scanplan = new ScanplanParser($intersected);
    $scanplan = $scanplan->parse();
    //dd($scanplan);

    $this->assertEquals([
      1 => [
        'Payment' => [
          'total' => 102,
          'found' => 101,
          'e' => 'eq',
          'ledgerindex_first' => 622999900000,
          'ledgerindex_last' => 624123059999,
          'ledgerindex_last_id' => '3004.0001',
        ],
      ]
    ], $scanplan);

  }

  public function test_scanplan_case_2_two_tx_types(): void
	{
    $grid = [];

    $grid['1000.0001|null|null_Payment']  = [2,1];
    $grid['1002.0001|null|null_Payment']  = [2,2];
    $grid['1003.0001|null|null_Payment']  = [49,49];
    $grid['1004.0001|null|null_Payment']  = [49,49];
    $grid['2008.0001|null|null_Trustset'] = [80,80];
    $intersected = $this->convertGridToIntersected($grid);
    //dd($intersected);
    $scanplan = new ScanplanParser($intersected);
    $scanplan = $scanplan->parse();
    //dd($scanplan);
    $this->assertEquals([
      1 => [
        'Payment' => [
          'total' => 102,
          'found' => 101,
          'e' => 'eq',
          'ledgerindex_first' => 161219700000,
          'ledgerindex_last' => 162216709999,
          'ledgerindex_last_id' => '1004.0001',
        ],
        'Trustset' => [
          'total' => 80,
          'found' => 80,
          'e' => 'eq',
          'ledgerindex_first' => 397592310000,
          'ledgerindex_last' => 397820549999,
          'ledgerindex_last_id' => '2008.0001',
        ],
      ]
    ], $scanplan);
  }

  public function test_scanplan_case_3_two_pages(): void
	{
    $grid = [];

    //Page 1
    $grid['1000.0001|null|null_Payment' ] = [400,200]; //400
    $grid['1002.0001|null|null_Payment' ] = [200,200]; //600 - break
    $grid['1002.0001|null|null_Trustset'] = [80,80];   //80

    //Page 2
    $grid['1004.0001|null|null_Payment']  = [49,49];   //49
    $grid['1005.0001|null|null_Payment']  = [49,49];   //98
    
    $intersected = $this->convertGridToIntersected($grid);
    //dd($intersected);
    $scanplan = new ScanplanParser($intersected);
    $scanplan = $scanplan->parse();
    //dd($scanplan);
    $this->assertEquals([
      1 => [ //page 1
        'Payment' => [
          'total' => 600,
          'found' => 400,
          'e' => 'eq',
          'ledgerindex_first' => 161219700000,
          'ledgerindex_last' => 161818769999,
          'ledgerindex_last_id' => '1002.0001',
        ],
        'Trustset' => [
          'total' => 80,
          'found' => 80,
          'e' => 'eq',
          'ledgerindex_first' => 161617930000,
          'ledgerindex_last' => 161818769999,
          'ledgerindex_last_id' => '1002.0001',
        ],
      ],
      2 => [ //page 2
        'Payment' => [
          'total' => 98,
          'found' => 98,
          'e' => 'eq',
          'ledgerindex_first' => 162018960000,
          'ledgerindex_last' => 162417149999,
          'ledgerindex_last_id' => '1005.0001',
        ]
      ]
    ], $scanplan);
  }

  
  ### OLD:

  public function test_scanplan_paginator_two_pages(): void
	{
    $address = 'rhotcWYdfn6qxhVMbPKGDF3XCKqwXar5J4';
    $search = new Search($address);

    $grid = [];

    //Should yield sigle page
    $grid['1_Payment']  = 10;   //10 (page 1)
    $grid['2_Payment']  = 10;   //20 (page 1)
    $grid['3_Payment']  = 570;  //590 (page 1)
    $grid['4_Payment']  = 25;   //615 (page 2)

    $grid['1_Trustset'] = 10;   //10 (page 1)
    $grid['2_Trustset'] = 10;   //20 (page 1)


    $seed = $this->convertGridToSeed($grid);
    $result = $search->calculateScanPlan($seed);


    $this->assertEquals(2, count($result)); //number of pages
    $this->assertEquals([1,2,3],$result[1]['Payment']['data']['llist']);
    $this->assertEquals([4],$result[2]['Payment']['data']['llist']);
    $this->assertEquals([1,2],$result[1]['Trustset']['data']['llist']);
  }

  public function test_scanplan_paginator_skipping(): void
	{
    $address = 'rhotcWYdfn6qxhVMbPKGDF3XCKqwXar5J4';
    $search = new Search($address);

    $grid = [];

    //Should skip low Payment amounts to next page because large trustset on ledger 3
    $grid['1_Payment']  = 10;   //10 (page 1)
    $grid['2_Payment']  = 10;   //20 (page 1)
    $grid['3_Payment']  = 10;   //30 (page 1)
    $grid['4_Payment']  = 10;   //40 (page 2)
    $grid['5_Payment']  = 10;   //50 (page 2)
    $grid['6_Payment']  = 10;   //60 (page 2)

    $grid['3_Trustset'] = 1000; //1000 (page 1)
    $grid['4_Trustset'] = 10;   //1010 (page 2)


    $seed = $this->convertGridToSeed($grid);
    $result = $search->calculateScanPlan($seed);


    $this->assertEquals(2, count($result)); //number of pages
    $this->assertEquals([1,2,3],$result[1]['Payment']['data']['llist']);
    $this->assertEquals([4,5,6],$result[2]['Payment']['data']['llist']);
    $this->assertEquals([3],$result[1]['Trustset']['data']['llist']);
    $this->assertEquals([4],$result[2]['Trustset']['data']['llist']);
  }

  /**
   * =--111111---
   */
  public function test_scanplan_paginator_1_1(): void
	{
    $address = 'rhotcWYdfn6qxhVMbPKGDF3XCKqwXar5J4';
    $search = new Search($address);

    $grid = [];

    $grid['1_Payment']  = 10;   //(page 1)
    $grid['2_Payment']  = 10;   //(page 1)
    $grid['3_Payment']  = 10;   //(page 1)
    $grid['4_Payment']  = 10;   //(page 1)
    $grid['5_Payment']  = 10;   //(page 1)
    $grid['6_Payment']  = 10;   //(page 1)

    $seed = $this->convertGridToSeed($grid);
    $result = $search->calculateScanPlan($seed);


    $this->assertEquals(1, count($result)); //number of pages
    $this->assertEquals([1,2,3,4,5,6],$result[1]['Payment']['data']['llist']);
  }

  /**
   * =--111111222---
   */
  public function test_scanplan_paginator_1_2(): void
	{
    $address = 'rhotcWYdfn6qxhVMbPKGDF3XCKqwXar5J4';
    $search = new Search($address);

    $grid = [];

    $grid['1_Payment']  = 10;   //(page 1)
    $grid['2_Payment']  = 10;   //(page 1)
    $grid['3_Payment']  = 10;   //(page 1)
    $grid['4_Payment']  = 10;   //(page 1)
    $grid['5_Payment']  = 10;   //(page 1)
    $grid['6_Payment']  = 490;  //(page 1)
    $grid['7_Payment']  = 10;   //(page 2)
    $grid['8_Payment']  = 10;   //(page 2)
    $grid['9_Payment']  = 10;   //(page 2)

    $seed = $this->convertGridToSeed($grid);
    $result = $search->calculateScanPlan($seed);


    $this->assertEquals(2, count($result)); //number of pages
    $this->assertEquals([1,2,3,4,5,6],$result[1]['Payment']['data']['llist']);
    $this->assertEquals([7,8,9],$result[2]['Payment']['data']['llist']);
  }

  /**
   * =--111111222344---
   */
  public function test_scanplan_paginator_1_3(): void
	{
    $address = 'rhotcWYdfn6qxhVMbPKGDF3XCKqwXar5J4';
    $search = new Search($address);

    $grid = [];

    $grid['1_Payment']  = 10;   //(page 1)
    $grid['2_Payment']  = 10;   //(page 1)
    $grid['3_Payment']  = 10;   //(page 1)
    $grid['4_Payment']  = 10;   //(page 1)
    $grid['5_Payment']  = 10;   //(page 1)
    $grid['6_Payment']  = 490;  //(page 1)
    $grid['7_Payment']  = 10;   //(page 2)
    $grid['8_Payment']  = 10;   //(page 2)
    $grid['9_Payment']  = 1000; //(page 2)
    $grid['10_Payment'] = 500;  //(page 3)
    $grid['11_Payment'] = 499;  //(page 4)
    $grid['12_Payment'] = 1;    //(page 4)

    $seed = $this->convertGridToSeed($grid);
    $result = $search->calculateScanPlan($seed);


    $this->assertEquals(4, count($result)); //number of pages
    $this->assertEquals([1,2,3,4,5,6],$result[1]['Payment']['data']['llist']);
    $this->assertEquals([7,8,9],$result[2]['Payment']['data']['llist']);
    $this->assertEquals([10],$result[3]['Payment']['data']['llist']);
    $this->assertEquals([11,12],$result[4]['Payment']['data']['llist']);
  }

  /**
   * ---111111222---
   * ---111111222---
   * =--111111222---
   */
  public function test_scanplan_paginator_2_2(): void
	{
    $address = 'rhotcWYdfn6qxhVMbPKGDF3XCKqwXar5J4';
    $search = new Search($address);

    $grid = [];

    $grid['1_Payment']  = 10;   //(page 1)
    $grid['2_Payment']  = 10;   //(page 1)
    $grid['3_Payment']  = 10;   //(page 1)
    $grid['4_Payment']  = 10;   //(page 1)
    $grid['5_Payment']  = 10;   //(page 1)
    $grid['6_Payment']  = 490;  //(page 1)
    $grid['7_Payment']  = 10;   //(page 2)
    $grid['8_Payment']  = 10;   //(page 2)
    $grid['9_Payment']  = 10;   //(page 2)

    $grid['1_Trustset']  = 10;   //(page 1)
    $grid['2_Trustset']  = 10;   //(page 1)
    $grid['3_Trustset']  = 10;   //(page 1)
    $grid['4_Trustset']  = 10;   //(page 1)
    $grid['5_Trustset']  = 10;   //(page 1)
    $grid['6_Trustset']  = 490;  //(page 1)
    $grid['7_Trustset']  = 10;   //(page 2)
    $grid['8_Trustset']  = 10;   //(page 2)
    $grid['9_Trustset']  = 10;   //(page 2)

    $seed = $this->convertGridToSeed($grid);
    $result = $search->calculateScanPlan($seed);


    $this->assertEquals(2, count($result)); //number of pages
    $this->assertEquals([1,2,3,4,5,6],$result[1]['Payment']['data']['llist']);
    $this->assertEquals([7,8,9],$result[2]['Payment']['data']['llist']);
    $this->assertEquals([1,2,3,4,5,6],$result[1]['Trustset']['data']['llist']);
    $this->assertEquals([7,8,9],$result[2]['Trustset']['data']['llist']);
  }

  /**
   * ---111111111---
   * -------12------
   * =--111112222---
   */
  public function test_scanplan_paginator_2_3(): void
	{
    $address = 'rhotcWYdfn6qxhVMbPKGDF3XCKqwXar5J4';
    $search = new Search($address);

    $grid = [];

    $grid['1_Payment']  = 10;   //(page 1)
    $grid['2_Payment']  = 10;   //(page 1)
    $grid['3_Payment']  = 10;   //(page 1)
    $grid['4_Payment']  = 10;   //(page 1)
    $grid['5_Payment']  = 10;   //(page 1) - Trustset breakpoint
    $grid['6_Payment']  = 10;   //(page 1) 2
    $grid['7_Payment']  = 10;   //(page 1) 2
    $grid['8_Payment']  = 10;   //(page 1) 2
    $grid['9_Payment']  = 10;   //(page 1) 2

    $grid['5_Trustset']  = 900;   //(page 1)
    $grid['6_Trustset']  = 900;   //(page 2)


    $seed = $this->convertGridToSeed($grid);
    $result = $search->calculateScanPlan($seed);


    $this->assertEquals(2, count($result)); //number of pages
    $this->assertEquals([1,2,3,4,5],$result[1]['Payment']['data']['llist']);
    $this->assertEquals([6,7,8,9],$result[2]['Payment']['data']['llist']);
    $this->assertEquals([5],$result[1]['Trustset']['data']['llist']);
    $this->assertEquals([6],$result[2]['Trustset']['data']['llist']);
  }

 
/**
   * ---011111----11110---
   * ----------12---------
   * =---11111-12-2222----
   */
  public function test_scanplan_paginator_2_4(): void
	{
    $address = 'rhotcWYdfn6qxhVMbPKGDF3XCKqwXar5J4';
    $search = new Search($address);

    $grid = [];

    $grid['1_Payment']  = 0;   //(ejected)
    $grid['2_Payment']  = 10;  //(page 1)
    $grid['3_Payment']  = 10;  //(page 1)
    $grid['4_Payment']  = 0;   //(page 1)
    $grid['5_Payment']  = 10;  //(page 1)
    $grid['10_Payment']  = 10; //(page 1)
    $grid['11_Payment']  = 10; //(page 1)
    $grid['12_Payment']  = 10; //(page 1)
    $grid['13_Payment']  = 10; //(page 1)
    $grid['14_Payment']  = 10; //(page 1)
    $grid['15_Payment']  = 0;  //(ejected)

    $grid['7_Trustset']  = 900; //(page 1)
    $grid['8_Trustset']  = 900; //(page 2)
    $grid['9_Trustset']  = 0;   //(ejected)


    $seed = $this->convertGridToSeed($grid);
    $result = $search->calculateScanPlan($seed);

    $this->assertEquals(2, count($result)); //number of pages
    $this->assertEquals([2,3,4,5],$result[1]['Payment']['data']['llist']);
    $this->assertEquals([10,11,12,13,14],$result[2]['Payment']['data']['llist']);
    $this->assertEquals(10,$result[2]['Payment']['data']['ledgerindex_first']);
    $this->assertEquals(14,$result[2]['Payment']['data']['ledgerindex_last']);

    $this->assertEquals([7],$result[1]['Trustset']['data']['llist']);
    $this->assertEquals(7, $result[1]['Trustset']['data']['ledgerindex_first']);
    $this->assertEquals(7, $result[1]['Trustset']['data']['ledgerindex_last']);

    $this->assertEquals([8],$result[2]['Trustset']['data']['llist']);
    $this->assertEquals(8, $result[2]['Trustset']['data']['ledgerindex_first']);
    $this->assertEquals(8, $result[2]['Trustset']['data']['ledgerindex_last']);

  }

  /**
   * @param array $grid
   * [
   *  '<LedgerIndexID.<subpage>|<li_first>|<li_next>_<TxType>' =>  [<total>,<found>],
   *  '3000.0001|null|null_Payment' =>  [2,2],
   * ]
   * @return array
   */
  private function convertGridToIntersected(array $grid): array
  {
    $seed = [];

    foreach($grid as $k => $v)
    {
      $k_ex = \explode('_',$k);
      $k_li_first_next = \explode('|',$k_ex[0]);
      $seed[$k_ex[1]][(string)$k_li_first_next[0]]['total'] = $v[0];
      $seed[$k_ex[1]][(string)$k_li_first_next[0]]['found'] = $v[1];
      $seed[$k_ex[1]][(string)$k_li_first_next[0]]['e'] = 'eq';
      $seed[$k_ex[1]][(string)$k_li_first_next[0]]['first'] = $k_li_first_next[1] == 'null' ? null:$k_li_first_next[1];
      $seed[$k_ex[1]][(string)$k_li_first_next[0]]['last'] = $k_li_first_next[2] == 'null' ? null:$k_li_first_next[2];
    }
    return $seed;
  }
}