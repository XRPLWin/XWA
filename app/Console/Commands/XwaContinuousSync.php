<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

#use XRPLWin\XRPL\WSSClient;
use WSSC\WebSocketClient;
use WSSC\Components\ClientConfig;

class XwaContinuousSync extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'xwa:continuoussync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync transactions in ledgers, one by one.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
      /*
      $recvMsg = '{"user_id" : 123}';
      $client = new WebSocketClient($this->url, new ClientConfig());
      try {
          $client->send($recvMsg);
      } catch (BadOpcodeException $e) {
          echo 'Couldn`t sent: ' . $e->getMessage();
      }
      $recv = $client->receive();
      $this->assertEquals($recv, $recvMsg);
      */
      $ws_uri = 'wss://xrplcluster.com';
      //$ws_uri = 'wss://s.altnet.rippletest.net';



      if(true) {
        $client = new \WebSocket\Client($ws_uri);

        $client->text('{"id" : 1, "command" : "ping"}');
        $this->line($client->receive());
        $client->text('{"id" : 2, "command" : "ping"}');
        $this->line($client->receive());
        $client->text('{"id" : 9, "command" : "ping"}');
        $this->line($client->receive());
        $client->text('{"id" : 1, "command" : "ping"}');
        $this->line($client->receive());
        $client->text('{"id" : 2, "command" : "ping"}');
        $this->line($client->receive());
        $client->text('{"id" : 9, "command" : "ping"}');
        $this->line($client->receive());
        $client->text('{"id" : 1, "command" : "ping"}');
        $this->line($client->receive());
        $client->text('{"id" : 2, "command" : "ping"}');
        $this->line($client->receive());
        $client->text('{"id" : 9, "command" : "ping"}');
        $this->line($client->receive());
  
  
        //$client->text('{"id" : 1, "command" : "ping"}');
        //echo $client->receive();
        $client->close();
        return;
  
  
      }

    




      $config = new ClientConfig();
      $config->setTimeout(15);
      $config->setHeaders([
        //'X-Consumer-Client' => 'XRPLWin XWA',
        //'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:109.0) Gecko/20100101 Firefox/118.0',
        //'Accept' => '*/*',
        //Accept-Language: en-GB,en;q=0.5
        //Accept-Encoding: gzip, deflate, br
        //'Sec-WebSocket-Version' => '13',
        //'Origin' => 'https://xrpl.org',
        //'Sec-WebSocket-Extensions' => 'permessage-deflate',
        //'Sec-WebSocket-Key' => 'VsSUlNHhNn5GkoFNycPRpQ==',
        /*Connection: keep-alive, Upgrade
        Sec-Fetch-Dest: empty
        Sec-Fetch-Mode: websocket
        Sec-Fetch-Site: cross-site
        Pragma: no-cache
        Cache-Control: no-cache
        Upgrade: websocket*/
      ]);
      $config->setContextOptions(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
      //dd($ws_uri);
      $client = new WebSocketClient($ws_uri, $config);
      $client->send('{"id" : 1, "command" : "ping"}');
      $this->line($client->receive());
      $client->send('{"id" : 2, "command" : "ping"}');
      $this->line($client->receive());
      $client->send('{"id" : 9, "command" : "ping"}');
      $this->line($client->receive());
      $client->send('{"id" : 1, "command" : "ping"}');
      $this->line($client->receive());
      $client->send('{"id" : 2, "command" : "ping"}');
      $this->line($client->receive());
      $client->send('{"id" : 9, "command" : "ping"}');
      $this->line($client->receive());
      $client->send('{"id" : 1, "command" : "ping"}');
      $this->line($client->receive());
      $client->send('{"id" : 2, "command" : "ping"}');
      $this->line($client->receive());
      $client->send('{"id" : 9, "command" : "ping"}');
      $this->line($client->receive());
      
      return Command::SUCCESS;
    }
}
