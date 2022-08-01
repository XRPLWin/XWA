<?php declare(strict_types=1);

namespace XRPLWin\XRPL;


class Client
{

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

    $this->config = array_merge($config,$this->config_default);

    //Check config




    $this->endpointReportingUri = $this->config['endpoint_reporting_uri'];
    $this->endpointFullhistoryUri = $this->config['endpoint_fullhistory_uri'];


  }

  /*public function execute(string $method): void
  {

  }*/


  public function getHttpClient(): \GuzzleHttp\Client
  {
    return $this->httpClient;
  }
}
