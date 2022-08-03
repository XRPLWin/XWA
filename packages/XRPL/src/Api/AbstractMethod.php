<?php declare(strict_types=1);

namespace XRPLWin\XRPL\Api;

use XRPLWin\XRPL\Client;

abstract class AbstractMethod
{
  protected Client $client;
  protected array $params = [];
  protected string $method;
  protected string $endpoint;
  protected string $endpoint_config_key = 'endpoint_reporting_uri';
  protected array $result = [];

  public function __construct(Client $client)
  {
    $this->client = $client;
    $this->endpoint($this->client->getConfig()[$this->endpoint_config_key]);
  }

  public function endpoint(string $uri): self
  {
    $this->endpoint = $uri;
    return $this;
  }

  public function getEndpoint(): string
  {
    return $this->endpoint;
  }

  /**
  * Set input parameters for request.
  * @see https://xrpl.org/public-api-methods.html
  * @param array $params
  * @return self
  */
  public function params(array $params = []): self
  {
    $this->params = $params;
    return $this;
  }

  /**
  * Executes request against Ledger node / enpoint uri.
  * @return self
  */
  public function execute()
  {
    $p = [];
    $p['method'] = $this->method;
    if(!empty($this->params)) {
      $p['params'] = [];
      $p['params'][] = $this->params;
    }

    $response = $this->client
      ->getHttpClient()
      ->request('POST', $this->endpoint, [
        'body' => json_encode($p),
        'headers' => $this->client->getHeaders()
      ]);

    $this->result = \json_decode((string)$response->getBody(),true);
    return $this;
  }

  public function isSuccess()
  {

  }
}
