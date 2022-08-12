<?php

namespace App\XRPLParsers;

class Parser
{
  /**
   * Entry point, send Transaction and reference address and you will get appropriate Type instance.
   * 
   * @param \stdClass $tx
   * @param string $reference_address
   * @throws Exception on unsupported type
   * @return XRPLParserBase
   */
  public static function get(\stdClass $tx, \stdClass $meta, string $reference_address)
  {
    $name = '\\App\\XRPLParsers\\Types\\'.$tx->TransactionType;
    return new $name($tx,$meta,$reference_address);
  }
}