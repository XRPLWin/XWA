<?php

namespace App\XRPLParsers;

interface XRPLParserInterface
{
  public function __construct(\stdClass $tx);
  public function toDArray(): array;
}