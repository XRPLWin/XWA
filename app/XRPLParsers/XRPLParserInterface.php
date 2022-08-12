<?php

namespace App\XRPLParsers;

interface XRPLParserInterface
{
  public function __construct(\stdClass $tx, \stdClass $meta, string $reference_address);
  public function toDArray(): array;
}