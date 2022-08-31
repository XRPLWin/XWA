<?php

namespace App\Utilities;
#use XRPLWin\XRPL\Client;
#use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;

use function Symfony\Component\String\b;

class Search
{
  private readonly array $result;
  private bool $isExecuted = false;

  public function buildFromRequest(Request $request): self
  {
    foreach($request->input() as $k => $v)
    {
      //
    }
    return $this;
  }

  public function execute(): self
  {


    $this->result = [];
    $this->isExecuted = true;
    return $this;
  }

  public function result(): array
  {
    if(!$this->isExecuted)
      throw new \Exception('Search::result() called before execute()');

    return $this->result;
  }
}