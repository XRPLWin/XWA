<?php declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;

/**
 * @see https://github.com/XRPLF/xrpl.js/blob/main/packages/xrpl/test/utils/getBalanceChanges.ts
 */
class XRPLParserUtilBalanceChangesTest extends TestCase
{
  public function test_xrp_create_account()
  {
    $tx = file_get_contents(__DIR__.'/../stubs/fixtures/utils/paymentXrpCreateAccount.json');
    $tx = \json_decode($tx);

    $parser = new \App\XRPLParsers\Utils\BalanceChanges($tx->metadata);
    $result = $parser->result();

    $expected = [
      [
        'account' => 'rLDYrujdKUfVx28T9vRDAbyJ7G2WVXKo4K',
        'balances' => [['value' => '100', 'currency' => 'XRP' ]]
      ],
      [
        'account' => 'rKmBGxocj9Abgy25J51Mk1iqFzW9aVF9Tc',
        'balances' => [['value' => '-100.012', 'currency' => 'XRP' ]]
      ]
    ];
    $this->assertEquals($expected,$result);
  }

  public function test_usd_payment_to_account_with_no_usd()
  {
    $tx = file_get_contents(__DIR__.'/../stubs/fixtures/utils/paymentTokenDestinationNoBalance.json');
    $tx = \json_decode($tx);

    $parser = new \App\XRPLParsers\Utils\BalanceChanges($tx->metadata);
    $result = $parser->result();

    $expected = [
      [
        'account' => 'rKmBGxocj9Abgy25J51Mk1iqFzW9aVF9Tc',
        'balances' => [
          [
            'value' => '-0.01',
            'currency' => 'USD',
            'issuer' => 'rMwjYedjc7qqtKYVLiAccJSmCwih4LnE2q'
          ],
          [
            'value' => '-0.012',
            'currency' => 'XRP',
          ]
        ]
      ],
      [
        'account' => 'rMwjYedjc7qqtKYVLiAccJSmCwih4LnE2q',
        'balances' => [
          [
            'value' => '0.01',
            'currency' => 'USD',
            'issuer' => 'rKmBGxocj9Abgy25J51Mk1iqFzW9aVF9Tc'
          ],
          [
            'value' => '-0.01',
            'currency' => 'USD',
            'issuer' => 'rLDYrujdKUfVx28T9vRDAbyJ7G2WVXKo4K'
          ]
        ]
      ],
      [
        'account' => 'rLDYrujdKUfVx28T9vRDAbyJ7G2WVXKo4K',
        'balances' => [
          [
            'value' => '0.01',
            'currency' => 'USD',
            'issuer' => 'rMwjYedjc7qqtKYVLiAccJSmCwih4LnE2q'
          ]
        ]
      ]
    ];
    $this->assertEquals($expected,$result);
  }

  public function test_payment_of_all_usd_in_source_account()
  {
    $tx = file_get_contents(__DIR__.'/../stubs/fixtures/utils/paymentTokenSpendFullBalance.json');
    $tx = \json_decode($tx);

    $parser = new \App\XRPLParsers\Utils\BalanceChanges($tx->metadata);
    $result = $parser->result();

    $expected = [
      [
        'account' => 'rKmBGxocj9Abgy25J51Mk1iqFzW9aVF9Tc',
        'balances' => [
          [
            'value' => '0.2',
            'currency' => 'USD',
            'issuer' => 'rMwjYedjc7qqtKYVLiAccJSmCwih4LnE2q'
          ]
        ]
      ],
      [
        'account' => 'rMwjYedjc7qqtKYVLiAccJSmCwih4LnE2q',
        'balances' => [
          [
            'value' => '-0.2',
            'currency' => 'USD',
            'issuer' => 'rKmBGxocj9Abgy25J51Mk1iqFzW9aVF9Tc'
          ],
          [
            'value' => '0.2',
            'currency' => 'USD',
            'issuer' => 'rLDYrujdKUfVx28T9vRDAbyJ7G2WVXKo4K'
          ]
        ]
      ],
      [
        'account' => 'rLDYrujdKUfVx28T9vRDAbyJ7G2WVXKo4K',
        'balances' => [
          [
            'value' => '-0.2',
            'currency' => 'USD',
            'issuer' => 'rMwjYedjc7qqtKYVLiAccJSmCwih4LnE2q'
          ],
          [
            'value' => '-0.012',
            'currency' => 'XRP'
          ]
        ]
      ]
    ];
    $this->assertEquals($expected,$result);
  }

  public function test_usd_payment_to_account_with_usd()
  {
    $tx = file_get_contents(__DIR__.'/../stubs/fixtures/utils/paymentToken.json');
    $tx = \json_decode($tx);

    $parser = new \App\XRPLParsers\Utils\BalanceChanges($tx->metadata);
    $result = $parser->result();

    $expected = [
      [
        'account' => 'rKmBGxocj9Abgy25J51Mk1iqFzW9aVF9Tc',
        'balances' => [
          [
            'value' => '-0.01',
            'currency' => 'USD',
            'issuer' => 'rMwjYedjc7qqtKYVLiAccJSmCwih4LnE2q'
          ],
          [
            'value' => '-0.012',
            'currency' => 'XRP'
          ]
        ]
      ],
      [
        'account' => 'rMwjYedjc7qqtKYVLiAccJSmCwih4LnE2q',
        'balances' => [
          [
            'value' => '0.01',
            'currency' => 'USD',
            'issuer' => 'rKmBGxocj9Abgy25J51Mk1iqFzW9aVF9Tc'
          ],
          [
            'value' => '-0.01',
            'currency' => 'USD',
            'issuer' => 'rLDYrujdKUfVx28T9vRDAbyJ7G2WVXKo4K'
          ]
        ]
      ],
      [
        'account' => 'rLDYrujdKUfVx28T9vRDAbyJ7G2WVXKo4K',
        'balances' => [
          [
            'value' => '0.01',
            'currency' => 'USD',
            'issuer' => 'rMwjYedjc7qqtKYVLiAccJSmCwih4LnE2q'
          ]
        ]
      ]
    ];
    $this->assertEquals($expected,$result);
  }

  public function test_set_trust_limit_to_0_with_balance_remaining()
  {
    $tx = file_get_contents(__DIR__.'/../stubs/fixtures/utils/trustlineSetLimitZero.json');
    $tx = \json_decode($tx);

    $parser = new \App\XRPLParsers\Utils\BalanceChanges($tx->metadata);
    $result = $parser->result();

    $expected = [
      [
        'account' => 'rLDYrujdKUfVx28T9vRDAbyJ7G2WVXKo4K',
        'balances' => [
          [
            'value' => '-0.012',
            'currency' => 'XRP'
          ]
        ]
      ]
    ];
    $this->assertEquals($expected,$result);
  }

  public function test_create_trustline()
  {
    $tx = file_get_contents(__DIR__.'/../stubs/fixtures/utils/trustlineCreate.json');
    $tx = \json_decode($tx);

    $parser = new \App\XRPLParsers\Utils\BalanceChanges($tx->metadata);
    $result = $parser->result();

    $expected = [
      [
        'account' => 'rLDYrujdKUfVx28T9vRDAbyJ7G2WVXKo4K',
        'balances' => [
          [
            'value' => '10',
            'currency' => 'USD',
            'issuer' => 'rMwjYedjc7qqtKYVLiAccJSmCwih4LnE2q',
          ],
          [
            'value' => '-0.012',
            'currency' => 'XRP',
          ]
        ]
      ],
      [
        'account' => 'rMwjYedjc7qqtKYVLiAccJSmCwih4LnE2q',
        'balances' => [
          [
            'value' => '-10',
            'currency' => 'USD',
            'issuer' => 'rLDYrujdKUfVx28T9vRDAbyJ7G2WVXKo4K',
          ]
        ]
      ]
    ];
    $this->assertEquals($expected,$result);
  }

  public function test_set_trustline()
  {
    $tx = file_get_contents(__DIR__.'/../stubs/fixtures/utils/trustlineSetLimit.json');
    $tx = \json_decode($tx);

    $parser = new \App\XRPLParsers\Utils\BalanceChanges($tx->metadata);
    $result = $parser->result();

    $expected = [
      [
        'account' => 'rLDYrujdKUfVx28T9vRDAbyJ7G2WVXKo4K',
        'balances' => [
          [
            'value' => '-0.012',
            'currency' => 'XRP',
          ]
        ]
      ]
    ];
    $this->assertEquals($expected,$result);
  }

  public function test_set_trustline_2()
  {
    $tx = file_get_contents(__DIR__.'/../stubs/fixtures/utils/trustlineSetLimit2.json');
    $tx = \json_decode($tx);

    $parser = new \App\XRPLParsers\Utils\BalanceChanges($tx->metadata);
    $result = $parser->result();

    $expected = [
      [
        'account' => 'rsApBGKJmMfExxZBrGnzxEXyq7TMhMRg4e',
        'balances' => [
          [
            'value' => '-0.00001',
            'currency' => 'XRP',
          ]
        ]
      ]
    ];
    $this->assertEquals($expected,$result);
  }

  public function test_delete_trustline()
  {
    $tx = file_get_contents(__DIR__.'/../stubs/fixtures/utils/trustlineDelete.json');
    $tx = \json_decode($tx);

    $parser = new \App\XRPLParsers\Utils\BalanceChanges($tx->metadata);
    $result = $parser->result();

    $expected = [
      [
        'account' => 'rKmBGxocj9Abgy25J51Mk1iqFzW9aVF9Tc',
        'balances' => [
          [
            'value' => '0.02',
            'currency' => 'USD',
            'issuer' => 'rMwjYedjc7qqtKYVLiAccJSmCwih4LnE2q'
          ]
        ],
      ],
      [
        'account' => 'rMwjYedjc7qqtKYVLiAccJSmCwih4LnE2q',
        'balances' => [
          [
            'value' => '-0.02',
            'currency' => 'USD',
            'issuer' => 'rKmBGxocj9Abgy25J51Mk1iqFzW9aVF9Tc'
          ],
          [
            'value' => '0.02',
            'currency' => 'USD',
            'issuer' => 'rLDYrujdKUfVx28T9vRDAbyJ7G2WVXKo4K'
          ]
        ]
      ],
      [
        'account' => 'rLDYrujdKUfVx28T9vRDAbyJ7G2WVXKo4K',
        'balances' => [
          [
            'value' => '-0.02',
            'currency' => 'USD',
            'issuer' => 'rMwjYedjc7qqtKYVLiAccJSmCwih4LnE2q'
          ],
          [
            'value' => '-0.012',
            'currency' => 'XRP',
          ]
        ]
      ]
    ];

    $this->assertEquals($expected,$result);
  }
}