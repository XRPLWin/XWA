<?php declare(strict_types=1);

namespace XRPLWin\XRPLOrderbookReader;
use XRPLWin\XRPL\Client as XRPLWinClient;


class LiquidityCheck
{
  const ERROR_REQUESTED_LIQUIDITY_NOT_AVAILABLE = 'REQUESTED_LIQUIDITY_NOT_AVAILABLE';
  const ERROR_REVERSE_LIQUIDITY_NOT_AVAILABLE = 'REVERSE_LIQUIDITY_NOT_AVAILABLE';
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
  private array $book;
  private array $bookReverse;
  private bool $bookExecuted = false;
  private bool $bookReverseExecuted = false;
  private array $errors = [];
  
  public function __construct(array $trade, array $options, XRPLWinClient $client)
  {
    $this->client = $client;
    $this->trade = $trade;
    
    //Check $trade array
    if(count($this->trade) != 4)
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
    $this->fetchBook();
    $this->fetchBook(true);

    //dd($this->book,$this->bookReverse);

    $books1 = LiquidityParser::parse($this->book, $this->trade['from'], $this->trade['to'], $this->trade['amount']);
    $books2 = LiquidityParser::parse($this->bookReverse, $this->trade['to'], $this->trade['from'], $this->trade['amount']);
    $errors = $this->detectErrors($books1,$books2);
    dd($errors);
    $finalBookLine = $this->book[0];
    dd($rate,$rateReversed, $this);
    
  }

  /**
   * Clears results and resets instance.
   * @return self
   */
  public function reset()
  {
    $this->book = [];
    $this->bookReverse = [];
    $this->bookExecuted = false;
    $this->bookReverseExecuted = false;
    return $this;
  }

  /**
   * Queries XRPL and gets results of book_offers
   * Note that book_offers does not have pagination built in.
   * Fills $this->book or $this->bookReverse (if $reverse = true)
   * @throws \XRPLWin\XRPL\Exceptions\XWException
   * @return void
   */
  private function fetchBook($reverse = false)
  {
    if($this->trade['from'] === $this->trade['to'])
      return;

    //prevent re-querying
    if(!$reverse && $this->bookExecuted) 
      return;
    elseif($this->bookReverseExecuted)
      return;

    if(!$reverse) {
      $from = $this->trade['from'];
      $to = $this->trade['to'];
    } else {
      $from = $this->trade['to'];
      $to = $this->trade['from'];
    }
    

    /** @var \XRPLWin\XRPL\Methods\BookOffers */
    $orderbook = $this->client->api('book_offers')->params([
      'taker_gets' => $to,
      'taker_pays' => $from,
      'limit' => $this->trade['limit'] //200
    ]);

    try {
      $orderbook->send();
    } catch (\XRPLWin\XRPL\Exceptions\XWException $e) {
        // Handle errors
        throw $e;
    }

    if(!$orderbook->isSuccess()) {
      //XRPL response is returned but field result.status did not return 'success'

      if(isset($orderbook->result()->result->error_message))
        throw new \Exception($orderbook->result()->result->error_message);
      else
        throw new \Exception(\json_encode($orderbook->result()));
      return;
    }

    if(!$reverse) {
      $this->book = $orderbook->finalResult(); //array response from ledger
      $this->bookExecuted = true;
    } else {
      $this->bookReverse = $orderbook->finalResult(); //array response from ledger
      $this->bookReverseExecuted = true;
    }
  }

  private function detectErrors(array $books, array $booksReversed): array
  {
    $errors = [];
    if(!count($books)) {
      $errors[] = self::ERROR_REQUESTED_LIQUIDITY_NOT_AVAILABLE;
      return $errors;
    }
      

    if(!count($booksReversed)) {
      $errors[] = self::ERROR_REVERSE_LIQUIDITY_NOT_AVAILABLE;
      return $errors;
    }

    $amount = $this->trade['amount'];
    //TODO
    dd($amount);

    return $errors;
      
  }
}
