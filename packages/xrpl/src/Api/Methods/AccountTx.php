<?php declare(strict_types=1);

namespace XRPLWin\XRPL\Api\Methods;

use XRPLWin\XRPL\Api\AbstractMethod;
use XRPLWin\XRPL\Exceptions\NotSentException;
use XRPLWin\XRPL\Exceptions\XRPL\NotSuccessException;

class AccountTx extends AbstractMethod
{
  protected string $method = 'account_tx';
  protected string $endpoint_config_key = 'endpoint_fullhistory_uri';

  /**
   * Returns current ledger index.
   * @return array
   * @throws NotExecutedException
   */
  public function finalResult(): array
  {
    if(!$this->executed)
      throw new NotSentException('Please send request first');

    if(!$this->isSuccess())
      throw new NotSuccessException('Request did not return success result: '.\json_encode($this->result));

    return $this->result()->result->transactions;
  }
}
