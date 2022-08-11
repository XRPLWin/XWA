<?php

namespace App\XRPLParsers;

interface XRPLParserInterface
{
  public function __construct(\stdClass $tx, string $reference_address);
  public function toDArray(): array;
}