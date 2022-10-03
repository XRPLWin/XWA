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

  public function test_scanplan_paginator_single_page(): void
	{
    $address = 'rhotcWYdfn6qxhVMbPKGDF3XCKqwXar5J4';
    $search = new Search($address);

    $grid = [];

    //Should yield sigle page
    $grid['3000_Payment']  = 2;
    $grid['3002_Payment']  = 2;
    $grid['3003_Payment']  = 49;
    $grid['3004_Payment']  = 49;

    $grid['4008_Trustset'] = 49;

    $seed = $this->convertGridToSeed($grid);

    $result = $search->calculateScanPlan($seed);

    $this->assertEquals(1, count($result)); //number of pages = 1

    $this->assertEquals(102,$result[1]['Payment']['stats']['total_rows']);
    $this->assertEquals(102,$result[1]['Payment']['data']['total']);
    $this->assertEquals([3000,3002,3003,3004],$result[1]['Payment']['data']['llist']);
    $this->assertEquals(3000,$result[1]['Payment']['data']['ledgerindex_first']);
    $this->assertEquals(3004,$result[1]['Payment']['data']['ledgerindex_last']);

    $this->assertEquals(49,$result[1]['Trustset']['stats']['total_rows']);
    $this->assertEquals(49,$result[1]['Trustset']['data']['total']);
    $this->assertEquals([4008],$result[1]['Trustset']['data']['llist']);
    $this->assertEquals(4008,$result[1]['Trustset']['data']['ledgerindex_first']);
    $this->assertEquals(4008,$result[1]['Trustset']['data']['ledgerindex_last']);
  }

  private function convertGridToSeed(array $grid)
  {
    $seed = [];

    foreach($grid as $k => $v)
    {
      $k_ex = \explode('_',$k);
      $seed[$k_ex[1]][(int)$k_ex[0]]['total'] = $v;
      $seed[$k_ex[1]][(int)$k_ex[0]]['found'] = $v;
      $seed[$k_ex[1]][(int)$k_ex[0]]['e'] = 'eq';
    }
    return $seed;
  }

  public function test_scanplan_paginator_multi_page(): void
	{
    $address = 'rhotcWYdfn6qxhVMbPKGDF3XCKqwXar5J4';
    $search = new Search($address);

    $grid = [];

    //Should yield sigle page
    $grid['1_Payment']  = 10;
    $grid['2_Payment']  = 10;

    $grid['1_Trustset'] = 10;
    $grid['2_Trustset'] = 10;


    $seed = $this->convertGridToSeed($grid);
    $result = $search->calculateScanPlan($seed);

    $this->assertEquals(1, count($result)); //number of pages = 1
    $this->assertEquals([1,2],$result[1]['Payment']['data']['llist']);
    $this->assertEquals([1,2],$result[1]['Trustset']['data']['llist']);
  }
}