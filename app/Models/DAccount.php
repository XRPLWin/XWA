<?php

namespace App\Models;

use App\Jobs\QueueArtisanCommand;
use Illuminate\Support\Facades\DB;


/**
 * DynamoDB Transaction Account information.
 */
final class DAccount extends DTransaction
{
  //No TYPE

  public function sync(bool $recursive = true)
  {
    //check if already synced
    $check = DB::connection(config('database.default'))
      ->table('jobs')
      ->where('qtype','account')
      ->where('qtype_data',$this->address)
      ->count();
    
    if($check)
      return;
    
    QueueArtisanCommand::dispatch(
      'xwa:accountsync',
      ['address' => $this->address, '--recursiveaccountqueue' => $recursive ],
      'account',
      $this->address
    )->onQueue('default'); //todo replace default with sync
  }

}
