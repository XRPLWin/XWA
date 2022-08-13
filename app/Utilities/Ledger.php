<?php

namespace App\Utilities;
use XRPLWin\XRPL\Client;
use Illuminate\Support\Facades\Cache;

class Ledger
{
    /**
     * Gets current ledger, cached for 5 seconds.
     * @return int
     */
    public static function current(): int
    {
        $ledger_index = Cache::get('ledger_current');
        if($ledger_index === null) {
            $ledger_index = app(Client::class)->api('ledger_current')->send()->finalResult();
            Cache::put('ledger_current', $ledger_index, 5); //5 seconds
        }
        return $ledger_index;
    }
}