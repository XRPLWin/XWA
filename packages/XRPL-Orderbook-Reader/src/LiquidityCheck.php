<?php declare(strict_types=1);

namespace XRPLWin\XRPLOrderbookReader;
use XRPLWin\XRPL\Client as XRPLWinClient;


class LiquidityCheck
{
  /**
   * @var array $trade
   * [
   *    'from' => ['currency' => 'USD', 'issuer' (optional if XRP) => 'rhub8VRN55s94qWKDv6jmDy1pUykJzF3wq'],
   *    'to'   => ['currency' => 'EUR', 'issuer' (optional) => 'rhub8VRN55s94qWKDv6jmDy1pUykJzF3wq'],
   *    'amount' => 500
   * ]
   */
  private array $trade;
  /**
   * @var array $options
   * [
   *    'maxSpreadPercentage' => 4,
   *    'maxSlippagePercentage' => 3,
   *    'maxSlippagePercentageReverse' => 3
   * ]
   */
  private array $options;
  protected XRPLWinClient $client;


  public function __construct(array $trade, array $options, XRPLWinClient $client)
  {
    $this->trade = $trade;
    $this->options = $options;
    $this->client = $client;
  }

  public function get()
  {

  }
}
