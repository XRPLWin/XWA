<?php

namespace App\Utilities\Mapper;

interface FilterInterface {
    public function __construct(string $address, array $conditions, array $foundLedgerIndexesIds);
    public function reduce(): array;
    public static function calcEqualizer(string $existingE, string $newE): string;
    public static function itemHasFilter(\App\Models\DTransaction $item, string|int|float|bool $value): bool;
    public static function parseToNonDefinitiveParam(string $param): string;
}