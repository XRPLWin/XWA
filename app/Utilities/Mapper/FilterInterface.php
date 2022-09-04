<?php

namespace App\Utilities\Mapper;

interface FilterInterface {
    public function __construct(string $address, array $conditions, array $foundLedgerIndexesIds);
    public function reduce(): array;
}