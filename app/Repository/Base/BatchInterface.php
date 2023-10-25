<?php

namespace App\Repository\Base;
use App\Models\B;

interface BatchInterface
{
  public function execute(): int;
  public function queueModelChanges(B $model): void;
}