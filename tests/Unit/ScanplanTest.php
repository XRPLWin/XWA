<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Utilities\Search;

/**
 * This test assumes $breakpoint is set to 500
 * $breakpoint = Search::getPaginatorBreakpoint();
 */
class ScanplanTest extends TestCase
{
  protected function setUp(): void
	{
		parent::setUp();
		$this->createApplication();
	}

  public function test_calculate_scan_plan_inner_explode_pages()
  {
    $address = 'rhotcWYdfn6qxhVMbPKGDF3XCKqwXar5J4';
    $search = new Search($address);

    $testdata = [
      'Payment' => ['llist' => [ 
        ['1854.0001' => ['first' => null, 'next' => 1000.055]], //if part then only one can exist
      ]],
      'Trustset' => ['llist' => [ 
        ['1854.0001' => ['first' => null, 'next' => null]],
        ['1855.0001' => ['first' => null, 'next' => null]],
      ]],
      'Third' => ['llist' => [ 
        ['1854.0001' => ['first' => null, 'next' => 800.055]], //if part then only one can exist
      ]],


    ];

    
    $response = $search->calculateScanPlan_InnerExplodePages(1,$testdata);
    dd('TEST',$response);
  }

  public function test_numeric_ledgerindexes_decimal_sorting()
  {
    $a = [
      '1827' => 1,
      '1827.10' => 1,
      '1827.11000' => 1,
      '1827.111' => 1,
      '1827.2' => 1,
      '1827.22' => 1,
      '10' => 1,
      '11' => 1,
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

  public function test_numeric_ledgerindexes_largedecimal_sorting()
  {
    $a = [
      '2990.0001000000000000000358920530622000' => 1,
      '1843.0001000000000000000358920530610000' => 1,
      '1843.0001000000000000000358920530611000' => 1,
      '1843.0001000000000000000358920530611100' => 1,
      '1843.0001000000000000000358920530620000' => 1,
      '1843.0001000000000000000358920530622000' => 1,
      '2810.0001000000000000000358920530622000' => 1,
      '2811.0001000000000000000358920530622000' => 1,
      '2900.0001000000000000000358920530622000' => 1,
      
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

  public function test_scanplan_paginator_single_page(): void
	{
    $address = 'rhotcWYdfn6qxhVMbPKGDF3XCKqwXar5J4';
    $search = new Search($address);

    $grid = [];

    //Should yield sigle page
    $grid['3000.0001|null|null_Payment']  = 2;
    $grid['3002.0001|null|null_Payment']  = 2;
    $grid['3003.0001|null|null_Payment']  = 49;
    $grid['3004.0001|null|null_Payment']  = 49;

    /*
    $grid['3000.0001|null|2000.05_Payment']  = 2;
    $grid['3000.0002|2000.05|2100.05_Payment']  = 2;
    $grid['3000.0003|2100.05|null_Payment']  = 2;
    $grid['3002.0001|null|null_Payment']  = 2;
    $grid['3003.0001|null|null_Payment']  = 49;
    $grid['3004.0001|null|null_Payment']  = 49;
    */

    $grid['4008.0001|null|null_Trustset'] = 49;

    $seed = $this->convertGridToSeed($grid);
    
    $result = $search->calculateScanPlan($seed);
    
    $this->assertEquals(1, count($result)); //number of pages = 1
    
    $this->assertEquals(102,$result[1]['Payment']['total_rows']);
    
    $this->assertEquals(102,$result[1]['Payment']['found']);
    $this->assertEquals([
        '3000.0001' => [null,null],
        '3002.0001' => [null,null],
        '3003.0001' => [null,null],
        '3004.0001' => [null,null]
      ],
      $result[1]['Payment']['llist']
    );
    $this->assertEquals('3000.0001',$result[1]['Payment']['ledgerindex_first']);
    $this->assertEquals('3004.0001',$result[1]['Payment']['ledgerindex_last']);
    
    $this->assertEquals(49,$result[1]['Trustset']['total_rows']);
    $this->assertEquals(49,$result[1]['Trustset']['found']);
    $this->assertEquals(['4008.0001' => [null,null]],$result[1]['Trustset']['llist']);
    $this->assertEquals('4008.0001',$result[1]['Trustset']['ledgerindex_first']);
    $this->assertEquals('4008.0001',$result[1]['Trustset']['ledgerindex_last']);
    dd($result);
  }

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

  private function convertGridToSeed(array $grid)
  {
    $seed = [];

    foreach($grid as $k => $v)
    {
      $k_ex = \explode('_',$k);
      $k_li_first_next = \explode('|',$k_ex[0]);
      $seed[$k_ex[1]][(string)$k_li_first_next[0]]['total'] = $v;
      $seed[$k_ex[1]][(string)$k_li_first_next[0]]['found'] = $v;
      $seed[$k_ex[1]][(string)$k_li_first_next[0]]['e'] = 'eq';
      $seed[$k_ex[1]][(string)$k_li_first_next[0]]['first'] = $k_li_first_next[1] == 'null' ? null:$k_li_first_next[1];
      $seed[$k_ex[1]][(string)$k_li_first_next[0]]['next'] = $k_li_first_next[2] == 'null' ? null:$k_li_first_next[2];
    }
    return $seed;
  }
}