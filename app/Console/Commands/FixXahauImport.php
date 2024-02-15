<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use XRPLWin\XRPL\Client;
use App\XRPLParsers\Parser;

class FixXahauImport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:fix-xahau-import {Ym}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Temporary command line tool to fix a2 field on Import transactions.';

    protected string $Ym;

    /**
     * Take all txs of type Import (36) if there it is isin=true and a is filled and a2 is null then re-fetch and update a2
     */
    public function handle()
    {
      $this->Ym = $this->argument('Ym');
      $this->info('table: '.transactions_db_name($this->Ym));

      $results = DB::table(transactions_db_name($this->Ym))
          ->select('address','l','li','h','a')
          ->where('xwatype',36)
          ->where('isin',true)
          ->whereNull('a2')
          ->whereNotNull('a')
          ->orderBy('l','asc')
          ->limit(5000)
          ->get();
    
      $this->info('Found  '.$results->count().' rows (max 5000)');
      
      foreach($results as $k => $row) {
        $this->info('Row '.($k+1));
        $this->fixRow($row);
      }
    }

    private function fixRow(\stdClass $row)
    {
      $tx = $this->fetchTx($row->h);

      try {
        /** @var \App\XRPLParsers\XRPLParserBase */
        $parser = Parser::get($tx->result, $tx->result->meta, $row->address);
      } catch (\Throwable $e) {
        throw $e;
      }

      $parsedData = $parser->toBArray();
      if($parser->getPersist() === false)
        return;
      DB::table(transactions_db_name($this->Ym))
        ->where('address',$row->address)
        ->where('xwatype',36)
        ->where('h',$row->h)
        ->where('isin',true)
        ->whereNull('a2')
        ->whereNotNull('a')
        ->limit(1)
        ->update([
          'a2' => $parsedData['a2']
        ]);
      $this->info($row->h.' updated with a2 = '.$parsedData['a2']);
    }

    private function fetchTx(string $hash)
    {
      $client = app(Client::class);
      $tx = $client->api('tx')
        ->params([
          'transaction' => $hash,
          'binary' => false
        ]);
      try {
        $tx->send();
      } catch (\XRPLWin\XRPL\Exceptions\XWException $e) {
        throw $e;
      }

      return $tx->result();
    }
}
