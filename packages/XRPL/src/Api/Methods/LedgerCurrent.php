<?php declare(strict_types=1);

namespace XRPLWin\XRPL\Api\Methods;

use XRPLWin\XRPL\Api\AbstractMethod;

class LedgerCurrent extends AbstractMethod
{
  protected string $method = 'ledger_current';
  protected string $endpoint_config_key = 'endpoint_reporting_uri';

}
