<?php

namespace App\Repository\Base;

interface BatchInterface
{
  public function execute(): int;
}