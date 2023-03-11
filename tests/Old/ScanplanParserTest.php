<?php declare(strict_types=1);

namespace Tests\Old;

use Tests\TestCase;
use App\Utilities\Search;
use App\Utilities\Scanplan\Parser as ScanplanParser;
use App\Models\Ledgerindex;

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
    $this->migrateDatabaseLocal();

    //seed ledger indexes (easy to read)
    $seed = [
      1 =>  [  10000,  19999, '2020-01-01' ],
      2 =>  [  20000,  29999, '2020-01-02' ],
      3 =>  [  30000,  39999, '2020-01-03' ],
      4 =>  [  40000,  49999, '2020-01-04' ],
      5 =>  [  50000,  59999, '2020-01-05' ],
      6 =>  [  60000,  69999, '2020-01-06' ],
      7 =>  [  70000,  79999, '2020-01-07' ],
      8 =>  [  80000,  89999, '2020-01-08' ],
      9 =>  [  90000,  99999, '2020-01-09' ],
      10 => [ 100000, 109999, '2020-01-10' ],
      11 => [ 110000, 119999, '2020-01-11' ],
      12 => [ 120000, 129999, '2020-01-12' ]
    ];

    foreach($seed as $id => $v) {
      $model = new Ledgerindex;
      $model->id = $id;
      $model->ledger_index_first = $v[0];
      $model->ledger_index_last = $v[1];
      $model->day = $v[2];
      $model->save();
      unset($model);
    }

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

    $grid['1.0001|null|null_Payment']  = [2,1];
    $grid['3.0001|null|null_Payment']  = [2,2];
    $grid['4.0001|null|null_Payment']  = [49,49];
    $grid['5.0001|null|null_Payment']  = [49,49];
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
          'ledgerindex_first' => 10000,
          'ledgerindex_last' =>  59999,
          'ledgerindex_last_id' => '5.0001',
        ],
      ]
    ], $scanplan);

  }

  public function test_scanplan_case_2_two_tx_types_in_one_page(): void
	{
    $grid = [];

    $grid['1.0001|null|null_Payment']  = [2,1];
    $grid['3.0001|null|null_Payment']  = [2,2];
    $grid['4.0001|null|null_Payment']  = [49,49];
    $grid['5.0001|null|null_Payment']  = [49,49];

    $grid['8.0001|null|null_Trustset'] = [80,80];
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
          'ledgerindex_first' => 10000,
          'ledgerindex_last' =>  59999,
          'ledgerindex_last_id' => '5.0001',
        ],
        'Trustset' => [
          'total' => 80,
          'found' => 80,
          'e' => 'eq',
          'ledgerindex_first' => 80000,
          'ledgerindex_last' =>  89999,
          'ledgerindex_last_id' => '8.0001',
        ],
      ]
    ], $scanplan);
  }

  public function test_scanplan_case_3_two_pages(): void
	{
    $grid = [];

    //Page 1
    $grid['1.0001|null|null_Payment' ] = [400,200]; //400
    $grid['2.0001|null|null_Payment' ] = [200,200]; //600 - breakpoint trigger
    $grid['2.0001|null|null_Trustset'] = [80,80];   //80

    //Page 2
    $grid['4.0001|null|null_Payment']  = [49,49];   //49
    $grid['5.0001|null|null_Payment']  = [49,49];   //98
    
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
          'ledgerindex_first' => 10000,
          'ledgerindex_last' => 29999,
          'ledgerindex_last_id' => '2.0001',
        ],
        'Trustset' => [
          'total' => 80,
          'found' => 80,
          'e' => 'eq',
          'ledgerindex_first' => 20000,
          'ledgerindex_last' => 29999,
          'ledgerindex_last_id' => '2.0001',
        ],
      ],
      2 => [ //page 2
        'Payment' => [
          'total' => 98,
          'found' => 98,
          'e' => 'eq',
          'ledgerindex_first' => 40000,
          'ledgerindex_last' => 59999,
          'ledgerindex_last_id' => '5.0001',
        ]
      ]
    ], $scanplan);
  }

  public function test_scanplan_case_4_two_pages_two_tx_types_with_inner_break(): void
	{
    $grid = [];

    $grid['1.0001|null|null_Payment'] = [400,200];      // from 10000 to 19999
    $grid['2.0001|null|null_Payment'] = [200,200];      // from 20000 to 29999
    $grid['2.0001|null|21000_Trustset'] = [1000,1000];  // from 20000 to 21000
    $grid['2.0002|21001|null_Trustset'] = [850,840];    // from 21001 to 29999
    $grid['4.0001|null|null_Payment']  = [49,49];       // from 40000 to 49999
    $grid['5.0001|null|null_Payment']  = [49,49];
    
    $intersected = $this->convertGridToIntersected($grid);
    //dd($intersected);
    //dd(var_export($intersected));
    $scanplan = new ScanplanParser($intersected);
    $scanplan = $scanplan->parse();
    //dd($scanplan);
    $this->assertEquals([
      1 => [ //page 1
        'Payment' => [
          'total' => 600,
          'found' => 400,
          'e' => 'lte',
          'ledgerindex_first' => 10000,
          'ledgerindex_last' => 21000,
          'ledgerindex_last_id' => '2.0001',
        ],
        'Trustset' => [
          'total' => 1000,
          'found' => 1000,
          'e' => 'eq',
          'ledgerindex_first' => 20000,
          'ledgerindex_last' => 21000,
          'ledgerindex_last_id' => '2.0001',
        ],
      ],
      2 => [ //page 2
        'Payment' => [
          'total' => 200,
          'found' => 200,
          'e' => 'lte',
          'ledgerindex_first' => 21001,
          'ledgerindex_last' => 29999,
          'ledgerindex_last_id' => '2.0001',
        ],
        'Trustset' => [
          'total' => 850,
          'found' => 840,
          'e' => 'eq',
          'ledgerindex_first' => 21001,
          'ledgerindex_last' => 29999,
          'ledgerindex_last_id' => '2.0002',
        ],
      ],
      3 => [ //page 3
        'Payment' => [
          'total' => 98,
          'found' => 98,
          'e' => 'eq',
          'ledgerindex_first' => 40000,
          'ledgerindex_last' => 59999,
          'ledgerindex_last_id' => '5.0001',
        ],
      ]
    ], $scanplan);
  }

  /**
   * =--111111---
   */
  public function test_scanplan_simple_paginator_1_1(): void
	{
    $grid = [];

    $grid['1.0001|null|null_Payment'] = [10,10];
    $grid['2.0001|null|null_Payment'] = [10,10];
    $grid['4.0001|null|null_Payment'] = [10,10];
    $grid['5.0001|null|null_Payment'] = [10,9];
    $grid['6.0001|null|null_Payment'] = [10,8];

    $intersected = $this->convertGridToIntersected($grid);
    //dd($intersected);
    //dd(var_export($intersected));
    $scanplan = new ScanplanParser($intersected);
    $scanplan = $scanplan->parse();
    //dd($scanplan);

    $this->assertEquals([
      1 => [
        'Payment' => [
          'total' => 50,
          'found' => 47,
          'e' => 'eq',
          'ledgerindex_first' => 10000,
          'ledgerindex_last' => 69999,
          'ledgerindex_last_id' => '6.0001',
        ],
      ]
    ], $scanplan);
  }

  /**
   * =--1111122333---
   */
  public function test_scanplan_simple_paginator_1_2(): void
	{

    $grid = [];

    $grid['1.0001|null|null_Payment'] = [10,10];
    $grid['2.0001|null|null_Payment'] = [10,10];
    $grid['4.0001|null|null_Payment'] = [10,10];
    $grid['5.0001|null|null_Payment'] = [10,10];
    $grid['6.0001|null|null_Payment'] = [1250,1]; //breakpoint trigger
    $grid['7.0001|null|73000_Payment'] = [10,10];
    $grid['7.0002|73001|74000_Payment'] = [9000,1]; //breakpoint trigger
    $grid['7.0004|74001|null_Payment'] = [100,100];
    $grid['8.0001|null|null_Payment'] = [10,10];
    $grid['9.0001|null|null_Payment'] = [10,10];

    $intersected = $this->convertGridToIntersected($grid);
    //dd($intersected);
    //dd(var_export($intersected));
    $scanplan = new ScanplanParser($intersected);
    $scanplan = $scanplan->parse();
    //dd($scanplan);

    $this->assertEquals([
      1 => [
        'Payment' => [
          'total' => 1290,
          'found' => 41,
          'e' => 'eq',
          'ledgerindex_first' => 10000,
          'ledgerindex_last' => 69999,
          'ledgerindex_last_id' => '6.0001',
        ],
      ],
      2 => [
        'Payment' => [
          'total' => 9010,
          'found' => 11,
          'e' => 'eq',
          'ledgerindex_first' => 70000,
          'ledgerindex_last' => 74000,
          'ledgerindex_last_id' => '7.0002',
        ],
      ],
      3 => [
        'Payment' => [
          'total' => 120,
          'found' => 120,
          'e' => 'eq',
          'ledgerindex_first' => 74001,
          'ledgerindex_last' => 99999,
          'ledgerindex_last_id' => '9.0001',
        ],
      ]
    ], $scanplan);

  }

  

  /**
   * ---1111222---
   * ---1111222---
   * =--1111222---
   */
  public function test_scanplan_simple_paginator_2_2(): void
	{
    $grid = [];

    $grid['1.0001|null|null_Payment'] = [10,10];
    $grid['1.0001|null|null_Trustset'] = [10,10];
    $grid['1.0001|null|null_Other'] = [10,10];

    $grid['2.0001|null|null_Payment'] = [10,10];
    $grid['2.0001|null|null_Trustset'] = [10,10];
    $grid['2.0001|null|null_Other'] = [10,10];

    $grid['3.0001|null|null_Payment'] = [10,10];
    $grid['3.0001|null|null_Trustset'] = [10,10];
    $grid['3.0001|null|null_Other'] = [10,10];

    $grid['4.0001|null|null_Payment'] = [500,200];
    $grid['4.0001|null|null_Trustset'] = [700,700];
    $grid['4.0001|null|null_Other'] = [900,685];
    //breakpoint
    $grid['5.0001|null|null_Payment'] = [10,10];
    $grid['5.0001|null|null_Trustset'] = [10,10];
    $grid['5.0001|null|null_Other'] = [10,10];

    $intersected = $this->convertGridToIntersected($grid);
    //dd($intersected);
    //dd(var_export($intersected));
    $scanplan = new ScanplanParser($intersected);
    $scanplan = $scanplan->parse();
    //dd($scanplan);

    $this->assertEquals([
      1 => [
        'Payment' => [
          'total' => 530,
          'found' => 230,
          'e' => 'eq',
          'ledgerindex_first' => 10000,
          'ledgerindex_last' => 49999,
          'ledgerindex_last_id' => '4.0001',
        ],
        'Trustset' => [
          'total' => 730,
          'found' => 730,
          'e' => 'eq',
          'ledgerindex_first' => 10000,
          'ledgerindex_last' => 49999,
          'ledgerindex_last_id' => '4.0001',
        ],
        'Other' => [
          'total' => 930,
          'found' => 715,
          'e' => 'eq',
          'ledgerindex_first' => 10000,
          'ledgerindex_last' => 49999,
          'ledgerindex_last_id' => '4.0001',
        ],
      ],
      2 => [
        'Payment' => [
          'total' => 10,
          'found' => 10,
          'e' => 'eq',
          'ledgerindex_first' => 50000,
          'ledgerindex_last' => 59999,
          'ledgerindex_last_id' => '5.0001',
        ],
        'Trustset' => [
          'total' => 10,
          'found' => 10,
          'e' => 'eq',
          'ledgerindex_first' => 50000,
          'ledgerindex_last' => 59999,
          'ledgerindex_last_id' => '5.0001',
        ],
        'Other' => [
          'total' => 10,
          'found' => 10,
          'e' => 'eq',
          'ledgerindex_first' => 50000,
          'ledgerindex_last' => 59999,
          'ledgerindex_last_id' => '5.0001',
        ],
      ]
    ], $scanplan);

  }

  /**
   * ---111111111---
   * -------12------
   * =--111112222---
   */
  public function test_scanplan_simple_paginator_2_3(): void
	{
    $grid = [];

    $grid['1.0001|null|null_Payment'] = [10,10];
    $grid['2.0001|null|null_Payment'] = [10,10];
    $grid['3.0001|null|null_Payment'] = [10,10];
    $grid['4.0001|null|null_Payment'] = [10,10];
    $grid['5.0001|null|null_Trustset'] = [900,900];
    $grid['6.0001|null|null_Payment'] = [10,10];
    $grid['7.0001|null|null_Payment'] = [10,10];
    $grid['8.0001|null|null_Payment'] = [10,10];
    $grid['9.0001|null|null_Payment'] = [10,10];

    $intersected = $this->convertGridToIntersected($grid);
    //dd($intersected);
    //dd(var_export($intersected));
    $scanplan = new ScanplanParser($intersected);
    $scanplan = $scanplan->parse();
    //dd($scanplan);

    $this->assertEquals([
      1 => [
        'Payment' => [
          'total' => 40,
          'found' => 40,
          'e' => 'eq',
          'ledgerindex_first' => 10000,
          'ledgerindex_last' => 49999,
          'ledgerindex_last_id' => '4.0001',
        ],
        'Trustset' => [
          'total' => 900,
          'found' => 900,
          'e' => 'eq',
          'ledgerindex_first' => 50000,
          'ledgerindex_last' => 59999,
          'ledgerindex_last_id' => '5.0001',
        ],
      ],
      2 => [
        'Payment' => [
          'total' => 40,
          'found' => 40,
          'e' => 'eq',
          'ledgerindex_first' => 60000,
          'ledgerindex_last' => 99999,
          'ledgerindex_last_id' => '9.0001',
        ],
      ]
    ], $scanplan);

  }

 
  /**
   * ---011111----11110---
   * ----------12---------
   * =---11111-12-2222----
   */
  public function test_scanplan_simple_paginator_2_4(): void
	{
    $grid = [];

    $grid['1.0001|null|null_Payment'] = [10,0];      //(ejected)
    $grid['2.0001|null|null_Payment'] = [10,10];
    $grid['3.0001|null|null_Payment'] = [10,10];

    $grid['5.0001|null|null_Trustset'] = [900,900]; //breakpoint trigger
    $grid['6.0001|null|null_Trustset'] = [10,10];   //page 2

    $grid['8.0001|null|null_Payment'] = [10,10];
    $grid['9.0001|null|null_Payment'] = [10,10];
    $grid['10.0001|null|null_Payment'] = [10,10];
    $grid['11.0001|null|null_Payment'] = [2,0];     //(ejected)

    $intersected = $this->convertGridToIntersected($grid);
    //dd($intersected);
    $scanplan = new ScanplanParser($intersected);
    $scanplan = $scanplan->parse();

    $this->assertEquals([
      1 => [
        'Payment' => [
          'total' => 20,
          'found' => 20,
          'e' => 'eq',
          'ledgerindex_first' => 20000,
          'ledgerindex_last' => 39999,
          'ledgerindex_last_id' => '3.0001',
        ],
        'Trustset' => [
          'total' => 900,
          'found' => 900,
          'e' => 'eq',
          'ledgerindex_first' => 50000,
          'ledgerindex_last' => 59999,
          'ledgerindex_last_id' => '5.0001',
        ],
      ],
      2 => [
        'Trustset' => [
          'total' => 10,
          'found' => 10,
          'e' => 'eq',
          'ledgerindex_first' => 60000,
          'ledgerindex_last' => 69999,
          'ledgerindex_last_id' => '6.0001',
        ],
        'Payment' => [
          'total' => 30,
          'found' => 30,
          'e' => 'eq',
          'ledgerindex_first' => 80000,
          'ledgerindex_last' => 109999,
          'ledgerindex_last_id' => '10.0001',
        ],
      ]
    ], $scanplan);
  }

  /**
   * Visualized:
   * x--12345678901--x
   * P--11-----2-2----
   * O1---1-----2-----
   * O2---0-------2---
   * T------12--------
   * =--111-12-2222--=
   */
  public function test_scanplan_simple_paginator_2_5(): void
	{
    $grid = [];

    $grid['1.0001|null|null_Payment'] = [10,1];
    $grid['2.0001|null|null_Payment'] = [10,10];
    $grid['3.0001|null|null_Other1'] = [10,10];
    $grid['3.0001|null|null_Other2'] = [10,0];      // (ejected)

    $grid['5.0001|null|null_Trustset'] = [900,900]; //breakpoint trigger
    $grid['6.0001|null|null_Trustset'] = [10,10];   //page 2

    $grid['8.0001|null|null_Payment'] = [10,10];
    $grid['9.0001|null|null_Other1'] = [10,10];
    $grid['10.0001|null|null_Payment'] = [10,10];
    $grid['11.0001|null|null_Other2'] = [2,2];

    $intersected = $this->convertGridToIntersected($grid);
    //dd($intersected);
    $scanplan = new ScanplanParser($intersected);
    $scanplan = $scanplan->parse();
    //dd($scanplan);

    $this->assertEquals([
      1 => [
        'Payment' => [
          'total' => 20,
          'found' => 11,
          'e' => 'eq',
          'ledgerindex_first' => 10000,
          'ledgerindex_last' => 29999,
          'ledgerindex_last_id' => '2.0001',
        ],
        'Other1' => [
          'total' => 10,
          'found' => 10,
          'e' => 'eq',
          'ledgerindex_first' => 30000,
          'ledgerindex_last' => 39999,
          'ledgerindex_last_id' => '3.0001',
        ],
        'Trustset' => [
          'total' => 900,
          'found' => 900,
          'e' => 'eq',
          'ledgerindex_first' => 50000,
          'ledgerindex_last' => 59999,
          'ledgerindex_last_id' => '5.0001',
        ],
      ],
      2 => [
        'Trustset' => [
          'total' => 10,
          'found' => 10,
          'e' => 'eq',
          'ledgerindex_first' => 60000,
          'ledgerindex_last' => 69999,
          'ledgerindex_last_id' => '6.0001',
        ],
        'Payment' => [
          'total' => 20,
          'found' => 20,
          'e' => 'eq',
          'ledgerindex_first' => 80000,
          'ledgerindex_last' => 109999,
          'ledgerindex_last_id' => '10.0001',
        ],
        'Other1' => [
          'total' => 10,
          'found' => 10,
          'e' => 'eq',
          'ledgerindex_first' => 90000,
          'ledgerindex_last' => 99999,
          'ledgerindex_last_id' => '9.0001',
        ],
        'Other2' => [
          'total' => 2,
          'found' => 2,
          'e' => 'eq',
          'ledgerindex_first' => 110000,
          'ledgerindex_last' => 119999,
          'ledgerindex_last_id' => '11.0001',
        ],
      ]
    ], $scanplan);
  }

  /**
   * This sample demonstrates internal 3.x pagination with very small XWA_SCAN_LIMIT.
   * Internal paginator limit is 10, external is 500
   * (not enough results to paginate externally)
   */
  public function test_scanplan_inner_paginator_1(): void
	{
    $grid = [];

    $grid['1.0001|null|null_Payment'] = [10,1];
    $grid['2.0001|null|null_Payment'] = [10,10];

    $grid['3.0001|null|31000_Payment'] = [10,10];
    $grid['3.0002|31001|35000_Payment'] = [10,10];
    $grid['3.0003|35001|38000_Payment'] = [10,10];
    $grid['3.0004|38001|null_Payment'] = [10,10];

    $grid['10.0001|null|null_Payment'] = [10,10];

    $intersected = $this->convertGridToIntersected($grid);
    //dd($intersected);
    $scanplan = new ScanplanParser($intersected);
    $scanplan = $scanplan->parse();
    //dd($scanplan);

    $this->assertEquals([
      1 => [
        'Payment' => [
          'total' => 70,
          'found' => 61,
          'e' => 'eq',
          'ledgerindex_first' => 10000,
          'ledgerindex_last' => 109999,
          'ledgerindex_last_id' => '10.0001',
        ]
      ]
    ],$scanplan);

  }

  /**
   * Visualized:
   * 30000┌───┐          ┌───┐
   * 31000├───┤          ├───┤ --p1
   *      │   │          │   │
   *      │   │    32000 ├───┤ --p2
   *      │   │          │   │
   *      │   │          │   │
   * 35000├───┤          │   │ --p3
   *      │   │    36000 ├───┤ --p4
   *      │   │          │   │
   * 38000├───┤          │ 0 │ --p5
   *      │   │          │   │
   * 40000└───┘          └───┘
   */
  public function test_scanplan_inner_paginator_2(): void
	{
    $grid = [];

    $grid['1.0001|null|null_Payment'] = [10,1];
    $grid['2.0001|null|null_Payment'] = [10,10];

    $grid['3.0001|null|31000_Payment'] = [1000,1000];  // breakpoint trigger
    $grid['3.0002|31001|35000_Payment'] = [1000,1000]; // breakpoint trigger
    $grid['3.0003|35001|38000_Payment'] = [1000,1000]; // breakpoint trigger
    $grid['3.0004|38001|null_Payment'] = [60,60];

    $grid['3.0001|null|31000_Activation'] = [1000,1000];  // breakpoint trigger
    $grid['3.0002|31001|32000_Activation'] = [1000,1000]; // breakpoint trigger
    $grid['3.0003|32001|36000_Activation'] = [1000,1000]; // breakpoint trigger
    $grid['3.0004|36001|null_Activation'] = [20,0];


    $grid['10.0001|null|null_Payment'] = [10,10];

    $intersected = $this->convertGridToIntersected($grid);
    //dd($intersected);
    $scanplan = new ScanplanParser($intersected);
    $scanplan = $scanplan->parse();
    //dd($scanplan);

    $this->assertEquals([
      1 => [
        'Payment' => [
          'total' => 1020,
          'found' => 1011,
          'e' => 'eq',
          'ledgerindex_first' => 10000,
          'ledgerindex_last' => 31000,
          'ledgerindex_last_id' => '3.0001',
        ],
        'Activation' => [
          'total' => 1000,
          'found' => 1000,
          'e' => 'eq',
          'ledgerindex_first' => 30000,
          'ledgerindex_last' => 31000,
          'ledgerindex_last_id' => '3.0001',
        ]
      ],
      2 => [
        'Payment' => [
          "total" => 1000,
          "found" => 1000,
          "e" => "lte",
          "ledgerindex_first" => 31001,
          "ledgerindex_last" => 32000,
          "ledgerindex_last_id" => "3.0002",
        ],
        'Activation' => [
          "total" => 1000,
          "found" => 1000,
          "e" => "eq",
          "ledgerindex_first" => 31001,
          "ledgerindex_last" => 32000,
          "ledgerindex_last_id" => "3.0002",
        ]
      ],
      3 => [
        'Payment' => [
          "total" => 1000,
          "found" => 1000,
          "e" => "lte",
          "ledgerindex_first" => 32001,
          "ledgerindex_last" => 35000,
          "ledgerindex_last_id" => "3.0002",
        ],
        'Activation' => [
          "total" => 1000,
          "found" => 1000,
          "e" => "lte",
          "ledgerindex_first" => 32001,
          "ledgerindex_last" => 35000,
          "ledgerindex_last_id" => "3.0003",
        ]
      ],
      4 => [
        'Payment' => [
          "total" => 1000,
          "found" => 1000,
          "e" => "lte",
          "ledgerindex_first" => 35001,
          "ledgerindex_last" => 36000,
          "ledgerindex_last_id" => "3.0003",
        ],
        'Activation' => [
          "total" => 1000,
          "found" => 1000,
          "e" => "lte",
          "ledgerindex_first" => 35001,
          "ledgerindex_last" => 36000,
          "ledgerindex_last_id" => "3.0003",
        ]
      ],
      5 => [
        'Payment' => [
          "total" => 1000,
          "found" => 1000,
          "e" => "lte",
          "ledgerindex_first" => 36001,
          "ledgerindex_last" => 38000,
          "ledgerindex_last_id" => "3.0003",
        ],
      ],
      6 => [
        'Payment' => [
          "total" => 70,
          "found" => 70,
          "e" => "eq",
          "ledgerindex_first" => 38001,
          "ledgerindex_last" => 109999,
          "ledgerindex_last_id" => "10.0001",
        ]
      ]
    ],$scanplan);

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
      $seed[$k_ex[1]][(string)$k_li_first_next[0]]['first'] = $k_li_first_next[1] == 'null' ? null:(int)$k_li_first_next[1];
      $seed[$k_ex[1]][(string)$k_li_first_next[0]]['last'] = $k_li_first_next[2] == 'null' ? null:(int)$k_li_first_next[2];
    }
    return $seed;
  }
}