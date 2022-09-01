<?php

namespace App\Utilities;

use App\Models\Ledgerindex;
#use XRPLWin\XRPL\Client;
#use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;
use Carbon\Carbon;

class Search
{
  private string $address;
  private readonly array $result;
  private readonly array $params;
  private bool $isExecuted = false;
  private array $txTypes = [
    // App\Models\DTransaction<VALUE_BELOW>
    'Payment', 'Trustset'
  ];

  public function __construct(string $address)
  {
    $this->address = $address;
  }

  public function buildFromArray(array $data): self
  {
   
    ///($data);
    foreach($data as $k => $v)
    {
      //
    }
    $this->params = $data;
    return $this;
  }

  public function buildFromRequest(Request $request): self
  {
    return $this->buildFromArray($request->only(['from','to']));
  }

  public function execute(): self
  {
    $this->result = [];
    # 1. Get time range from-to
    $from = Carbon::createFromFormat('Y-m-d', $this->param('from'));
    $to = Carbon::createFromFormat('Y-m-d', $this->param('to'));
    $fromLedger = Ledgerindex::select('ledger_index_last')->where('day',$from)->first();
    if(!$fromLedger) abort(404);
    $toLedger = Ledgerindex::select('ledger_index_last')->where('day',$to)->first();
    if(!$toLedger) abort(404);
    //dd($fromLedger,$toLedger);

    # 2. Types to query
    $txTypes = $this->txTypes; //thiese all all types
    //Todo get types from param



    # 2. Get all DTransactions within this range for all types
    $txs = [];
    foreach($txTypes as $txType)
    {
      //dump([$fromLedger->ledger_index_last,$toLedger->ledger_index_last]);
      $mname = '\\App\\Models\\DTransaction'.$txType;
      $txs['T'.$mname::TYPE] = $mname::where('PK',$this->address.'-'.$mname::TYPE)
        ->where('SK','between',[$fromLedger->ledger_index_last,$toLedger->ledger_index_last])
        ->get();
       //dd( $txs['Type'.$mname::TYPE]);
    }
    dd($txs);
    dd($from,$to);









    $this->isExecuted = true;
    return $this;
  }


  ###
  

  public function result(): array
  {
    if(!$this->isExecuted)
      throw new \Exception('Search::result() called before execute()');

    return $this->result;
  }

  private function param($name)
  {
    return $this->params[$name];
  }
}