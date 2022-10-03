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

  /*public function test_scanplan_paginator_one_page(): void
	{
    $address = 'rhotcWYdfn6qxhVMbPKGDF3XCKqwXar5J4';
    $search = new Search($address);

    $seed = [
      'Payment' => [
        3000 => [
          'total' => 2,
          'found' => 2,
          'e' => 'eq',
        ],
        3002 => [
          'total' => 2,
          'found' => 2,
          'e' => 'eq',
        ],
        3003 => [
          'total' => 50,
          'found' => 49,
          'e' => 'eq',
        ],
        3004 => [
          'total' => 50,
          'found' => 49,
          'e' => 'eq',
        ]
        ],
        'Trustset' => [
          4008 => [
            'total' => 50,
            'found' => 49,
            'e' => 'eq',
          ]
        ]
    ];

    $result = $search->calculateScanPlan($seed);

    $this->assertEquals(1, count($result)); //number of pages = 1

    $this->assertEquals(104,$result[1]['Payment']['stats']['total_rows']);
    $this->assertEquals(104,$result[1]['Payment']['data']['total']);
    $this->assertEquals([3000,3002,3003,3004],$result[1]['Payment']['data']['llist']);
    $this->assertEquals(3000,$result[1]['Payment']['data']['ledgerindex_first']);
    $this->assertEquals(3004,$result[1]['Payment']['data']['ledgerindex_last']);

    $this->assertEquals(50,$result[1]['Trustset']['stats']['total_rows']);
    $this->assertEquals(50,$result[1]['Trustset']['data']['total']);
    $this->assertEquals([4008],$result[1]['Trustset']['data']['llist']);
    $this->assertEquals(4008,$result[1]['Trustset']['data']['ledgerindex_first']);
    $this->assertEquals(4008,$result[1]['Trustset']['data']['ledgerindex_last']);
  }*/

  public function test_scanplan_paginator_multi_page(): void
	{
    $address = 'rhotcWYdfn6qxhVMbPKGDF3XCKqwXar5J4';
    $search = new Search($address);

    $seed = [
      'Payment' => [
        3000 => [
          'total' => 254,
          'found' => 254,
          'e' => 'eq',
        ],
        3002 => [
          'total' => 300,
          'found' => 300,
          'e' => 'eq',
        ],
        3003 => [
          'total' => 50,
          'found' => 49,
          'e' => 'eq',
        ],
        3004 => [
          'total' => 80,
          'found' => 80,
          'e' => 'eq',
        ]
      ],
      'Trustset' => [
        3000 => [
          'total' => 50,
          'found' => 49,
          'e' => 'eq',
        ],
        4008 => [
          'total' => 50,
          'found' => 49,
          'e' => 'eq',
        ]
      ]
    ];

    $result = $search->calculateScanPlan($seed);

    

    $this->assertEquals(2, count($result)); //number of pages = 1
    dd($result);
    /*$this->assertEquals(104,$result[1]['Payment']['stats']['total_rows']);
    $this->assertEquals(104,$result[1]['Payment']['data']['total']);
    $this->assertEquals([3000,3002,3003,3004],$result[1]['Payment']['data']['llist']);
    $this->assertEquals(3000,$result[1]['Payment']['data']['ledgerindex_first']);
    $this->assertEquals(3004,$result[1]['Payment']['data']['ledgerindex_last']);

    $this->assertEquals(50,$result[1]['Trustset']['stats']['total_rows']);
    $this->assertEquals(50,$result[1]['Trustset']['data']['total']);
    $this->assertEquals([4008],$result[1]['Trustset']['data']['llist']);
    $this->assertEquals(4008,$result[1]['Trustset']['data']['ledgerindex_first']);
    $this->assertEquals(4008,$result[1]['Trustset']['data']['ledgerindex_last']);*/
  }
}