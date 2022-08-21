<?php declare(strict_types=1);

namespace XRPLWin\XRPLOrderbookReader;
use XRPLWin\XRPL\Client as XRPLWinClient;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;


class LiquidityCheck
{
  const ERROR_REQUESTED_LIQUIDITY_NOT_AVAILABLE = 'REQUESTED_LIQUIDITY_NOT_AVAILABLE';
  const ERROR_REVERSE_LIQUIDITY_NOT_AVAILABLE = 'REVERSE_LIQUIDITY_NOT_AVAILABLE';
  const ERROR_MAX_SPREAD_EXCEEDED = 'MAX_SPREAD_EXCEEDED';
  const ERROR_MAX_SLIPPAGE_EXCEEDED = 'MAX_SLIPPAGE_EXCEEDED';
  const ERROR_MAX_REVERSE_SLIPPAGE_EXCEEDED = 'MAX_REVERSE_SLIPPAGE_EXCEEDED';

  const PRECISION = 20;
  /**
   * PRECISION_CAPPED:
   * Due to divisions, calculated values must be rounded to PRECISION, 
   * sum of fields _I_Spend_Capped and _I_Get_Capped can be very close approximation of 
   * TradeAmount. To create correct comparison of TradeAmount === _I_Spend_Capped or _I_Get_Capped,
   * those fields are rounded to ROUNDING_MODE of PRECISION_CAPPED precision.
   */
  const PRECISION_CAPPED = 10;
  const ROUNDING_MODE = RoundingMode::HALF_UP;

  protected XRPLWinClient $client;

  /**
   * @var array $trade
   * [
   *    'from' => ['currency' => 'USD', 'issuer' (optional if XRP) => 'rhub8VRN55s94qWKDv6jmDy1pUykJzF3wq'],
   *    'to'   => ['currency' => 'EUR', 'issuer' (optional if XRP) => 'rhub8VRN55s94qWKDv6jmDy1pUykJzF3wq'],
   *    'amount' => 500,
   *    'limit' => 200
   * ]
   */
  private array $trade;

  private array $options_default = [
    'rates' => 'to',
    'maxSpreadPercentage' => 4, //4
    'maxSlippagePercentage' => 3, //3
    'maxSlippagePercentageReverse' => 3 //3
  ];

  private array $options;
  private array $book;
  private array $bookReverse;
  private bool $bookExecuted = false;
  private bool $bookReverseExecuted = false;
  
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
    if($options['rates'] != 'from' && $options['rates'] != 'to')
      throw new \Exception('Options rates can be from or to only');
    $this->options = $options;
  }

  /**
   * Fetches orderbook and reverse orderbook then calculates exchange rate, checks for errors.
   * @return array [rate,safe,errors]
   */
  public function get(): array
  {
    $this->fetchBook();
    $this->fetchBook(true);

    $book1 = LiquidityParser::parse($this->book,        $this->trade['from'], $this->trade['to'], $this->trade['amount'], $this->options['rates']);
    $book2 = LiquidityParser::parse($this->bookReverse, $this->trade['from'], $this->trade['to'], $this->trade['amount'], ($this->options['rates'] == 'to' ? 'from':'to')); 
    $errors = $this->detectErrors($book1,$book2);
    $finalBookLine = (count($book1)) ? \end($book1) : null;

    if($finalBookLine === null)
      $rate = 0;
    else
      $rate = ($finalBookLine['_CumulativeRate_Cap']) ? $finalBookLine['_CumulativeRate_Cap'] : $finalBookLine['_CumulativeRate'];

    return [
      'rate' => (string)$rate,
      'safe' => (count($errors) == 0),
      'errors' => $errors,
      //'books' => [$book1,$book2],
    ];
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
    else if($this->bookReverseExecuted)
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

  /**
   * Detects errors
   * @param array $book
   * @param array $bookReversed
   * @return array of errors
   */
  private function detectErrors(array $book, array $bookReversed): array
  {
    # Check for orders existance
    $errors = [];
    if(!count($book)) {
      $errors[] = self::ERROR_REQUESTED_LIQUIDITY_NOT_AVAILABLE;
      return $errors;
    }
    if(!count($bookReversed)) {
      $errors[] = self::ERROR_REVERSE_LIQUIDITY_NOT_AVAILABLE;
      return $errors;
    }

    # Prepeare parameters
    $amount = $this->trade['amount'];

    $bookAmount = \end($book)['_I_Spend_Capped'];
    $bookReversedAmount = \end($bookReversed)['_I_Get_Capped'];
   
    $firstBookLine = $book[0];
    $finalBookLine = \end($book);

    /** @var \Brick\Math\BigDecimal */
    $startRate = ($firstBookLine['_CumulativeRate_Cap']) ? $firstBookLine['_CumulativeRate_Cap'] : $firstBookLine['_CumulativeRate'];
    /** @var \Brick\Math\BigDecimal */
    $finalRate = ($finalBookLine['_CumulativeRate_Cap']) ? $finalBookLine['_CumulativeRate_Cap'] : $finalBookLine['_CumulativeRate'];

    $firstBookLineReverse = $bookReversed[0];
    $finalBookLineReverse = \end($bookReversed);

    /** @var \Brick\Math\BigDecimal */
    $startRateReverse = ($firstBookLineReverse['_CumulativeRate_Cap']) ? $firstBookLineReverse['_CumulativeRate_Cap'] : $firstBookLineReverse['_CumulativeRate'];
    /** @var \Brick\Math\BigDecimal */
    $finalRateReverse = ($finalBookLineReverse['_CumulativeRate_Cap']) ? $finalBookLineReverse['_CumulativeRate_Cap'] : $finalBookLineReverse['_CumulativeRate'];
   
    # Check for errors
    if(!$bookAmount->toScale(self::PRECISION_CAPPED,self::ROUNDING_MODE)->isEqualTo($amount)) {
      $errors[] = self::ERROR_REQUESTED_LIQUIDITY_NOT_AVAILABLE;
      return $errors;
    }
    if(!$bookReversedAmount->toScale(self::PRECISION_CAPPED,self::ROUNDING_MODE)->isEqualTo($amount)) {
      $errors[] = self::ERROR_REVERSE_LIQUIDITY_NOT_AVAILABLE;
      return $errors;
    }

    if($this->options['maxSpreadPercentage']) {
      $spread = BigDecimal::one()->minus(  $startRate->dividedBy($startRateReverse,self::PRECISION,self::ROUNDING_MODE) )->multipliedBy(100)->abs();

      //todo: log

      if($spread->isGreaterThan($this->options['maxSpreadPercentage']))
        $errors[] = self::ERROR_MAX_SPREAD_EXCEEDED;
    }

    if($this->options['maxSlippagePercentage']) {
      $slippage = BigDecimal::one()->minus(  $startRate->dividedBy($finalRate,self::PRECISION,self::ROUNDING_MODE) )->multipliedBy(100)->abs();

      //todo: log

      if($slippage->isGreaterThan($this->options['maxSlippagePercentage']))
        $errors[] = self::ERROR_MAX_SLIPPAGE_EXCEEDED;
    }

    if($this->options['maxSlippagePercentageReverse']) {
      $slippage = BigDecimal::one()->minus(  $startRateReverse->dividedBy($finalRateReverse,self::PRECISION,self::ROUNDING_MODE) )->multipliedBy(100)->abs();

      //todo: log

      if($slippage->isGreaterThan($this->options['maxSlippagePercentageReverse']))
        $errors[] = self::ERROR_MAX_REVERSE_SLIPPAGE_EXCEEDED;
    }
    return $errors;
  }
}
