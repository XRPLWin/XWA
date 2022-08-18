<?php declare(strict_types=1);

namespace XRPLWin\XRPLOrderbookReader;
use XRPLWin\XRPL\Client as XRPLWinClient;


class LiquidityCheck
{
  protected XRPLWinClient $client;

  /**
   * @var array $trade
   * [
   *    'from' => ['currency' => 'USD', 'issuer' (optional if XRP) => 'rhub8VRN55s94qWKDv6jmDy1pUykJzF3wq'],
   *    'to'   => ['currency' => 'EUR', 'issuer' (optional) => 'rhub8VRN55s94qWKDv6jmDy1pUykJzF3wq'],
   *    'amount' => 500,
   *    'limit' => 200
   * ]
   */
  private array $trade;

  private array $options_default = [
    'maxSpreadPercentage' => 4,
    'maxSlippagePercentage' => 3,
    'maxSlippagePercentageReverse' => 3
  ];

  private array $options;
  //private object $result;
  
  public function __construct(array $trade, array $options, XRPLWinClient $client)
  {
    $this->client = $client;
    $this->trade = $trade;
    
    //Check $trade array
    if(count($this->trade) != 3)
      throw new \Exception('Invalid trade parameters');
    if(!isset($this->trade['from']) || !isset($this->trade['to']) || !isset($this->trade['amount']) || !isset($this->trade['limit']))
      throw new \Exception('Invalid trade parameters required parameters are from, to, amount and (int)limit');
    if(!is_array($this->trade['from']) || !is_array($this->trade['to']))
      throw new \Exception('Invalid trade parameters from and to must be array');
    if(!isset($this->trade['from']['currency']) || !isset($this->trade['to']['currency']))
      throw new \Exception('Invalid trade parameters from and to must have currency defined');
    if($this->trade['from']['currency'] != 'XRP' && !isset($this->trade['from']['issuer']))
      throw new \Exception('Invalid trade parameters from.issuer is not defined');
    if($this->trade['to']['currency'] != 'XRP' && !isset($this->trade['to']['issuer']))
      throw new \Exception('Invalid trade parameters to.issuer is not defined');
    if($this->trade['from'] === $this->trade['to'])
    throw new \Exception('Invalid trade parameters they can not be the same');

    \ksort($this->trade['from']);
    \ksort($this->trade['to']);

    $options = array_merge($this->options_default,$options);
    $this->options = $options;

  }

  public function get()
  {
    $this->send();
    
    
    $lp = new LiquidityParser;
    dd($lp);
  }

  /**
   * Fills $this->result
   * @return void
   */
  private function send()
  {
    if($this->trade['from'] === $this->trade['to'])
      return;

    /** @var \XRPLWin\XRPL\Methods\BookOffers */
    $orderbook = $this->client->api('book_offers')->params([
      'taker_gets' => $this->trade['from'],
      'taker_pays' => $this->trade['to'],
      'limit' => $this->trade['limit'] //200
    ]);

    dd($orderbook);
  }

  
}
