<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class XwaPullrichlist extends Command
{
  /**
   * The name and signature of the console command.
   *
   * @var string
   */
  protected $signature = 'xwa:pullrichlist';

  /**
   * The console command description.
   *
   * @var string
   */
  protected $description = 'Fetches binary data from last (started) ledger_index using ledger_data command. This is long running command, it should be ran once per day.';

  /**
   * WS client instance
   */
  protected \WebSocket\Client $client;

  /**
   * Execute the console command.
   */
  public function handle()
  {
    exit; //in todo
    $ws_uris = config('xrpl.'.config('xrpl.net').'.server_wss_syncer');
    $ws_pick = rand(0,count($ws_uris)-1);
    $ws_uri = $ws_uris[$ws_pick];

    $context = stream_context_create();
    stream_context_set_option($context, 'ssl', 'verify_peer', false);
    stream_context_set_option($context, 'ssl', 'verify_peer_name', false);

    $this->client = new \WebSocket\Client($ws_uri,[
      'context' => $context,
      'filter' => ['text'],// ['text', 'binary', 'ping'],
      'headers' => [ // Additional headers, used to specify subprotocol
        'User-Agent' => 'XRPLWin XWA (v'.config('xwa.version').') richlistpull',
        //'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:129.0) Gecko/20100101 Firefox/129.0',
      ],
      'persistent' => true,
      'timeout' => 30 //30
    ]);

  }
}
