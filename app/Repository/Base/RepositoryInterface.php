<?php

namespace App\Repository\Base;

interface RepositoryInterface
{
  public static function insert(array $values): bool;
  public static function update(string $table, string $conditions, array $modelandfields): ?bool;
}