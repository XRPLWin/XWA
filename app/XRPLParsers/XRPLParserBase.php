<?php

namespace App\XRPLParsers;

/**
 * This class takes full Transaction object returned from XRPL and parses it.
 */
class XRPLParserBase implements XRPLParserInterface
{
  protected readonly \stdClass $tx;
  protected array $data = [];

  /**
   * Constructor
   * @param \stdClass $tx
   */
  public function __construct(\stdClass $tx)
  {
    $this->tx = $tx;
    $this->parseCommonFields();
  }

  /**
   * Returns standardized array of relevant data for storing to Dynamo database.
   */
  public function toDArray(): array
  {
    throw new \Exception('Not implemented');
  }

  /**
   * Parses common fields as defined in XRPL documentation.
   * @see https://xrpl.org/transaction-common-fields.html
   * @return void
   */
  protected function parseCommonFields(): void
  {
    //
  }
}