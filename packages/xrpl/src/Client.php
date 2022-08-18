<?php declare(strict_types=1);

namespace XRPLWin\XRPL;


class Client
{
  const VERSION = '0.0.1';
  
  private readonly \GuzzleHttp\Client $httpClient;
  private readonly string $endpointReportingUri;
  private readonly string $endpointFullhistoryUri;

  private array $config_default = [
    'endpoint_reporting_uri' => 'http://s1.ripple.com:51234',
    'endpoint_fullhistory_uri' => 'https://xrplcluster.com'
  ];
  private readonly array $config;

  /**
  * XRPL Client constructor.
  * @param array $config
  */
  public function __construct(array $config)
  {
    $this->httpClient = new \GuzzleHttp\Client();

    $config = array_merge($this->config_default,$config);

    //Check config
    //TODO

    $this->config = $config;
    
    $this->endpointReportingUri = $this->config['endpoint_reporting_uri'];
    $this->endpointFullhistoryUri = $this->config['endpoint_fullhistory_uri'];
  }

  /**
   * Returns instance of AbstractMethod
   * @return \XRPLWin\XRPL\Api\AbstractMethod
   */
  public function api(string $method): mixed
  {
    $class = '\\XRPLWin\\XRPL\\Api\\Methods\\'.self::snakeToCase($method);
    $method = new $class($this);
    return $method;
  }


  public function getHttpClient(): \GuzzleHttp\Client
  {
    return $this->httpClient;
  }

  /**
   * Get configuration
   * @return array
   */
  public function getConfig(): array
  {
    return $this->config;
  }

  public function getHeaders(): array
  {
    return [
      'Content-Type' => 'application/json'
    ];
  }

  public static function snakeToCase(string $str): string
  {
    return \str_replace('_', '', ucwords($str, '_'));
  }
}
