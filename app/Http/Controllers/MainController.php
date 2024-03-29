<?php

namespace App\Http\Controllers;

use App\Models\B;
use Illuminate\Http\Request;
use App\Models\BAccount;
use Brick\Math\BigDecimal;
use XRPLWin\XRPL\Client;
use XRPLWin\XRPLOrderbookReader\LiquidityCheck;

class MainController extends Controller
{
    public function txtest(Request $request)
    {
      $form = '<style>body{font:12px Menlo, Monaco, Consolas, monospace}</style>
      <h1>Transaction parse tester</h1><form action="" method="GET">
        <input type="text" name="tx" placeholder="tx" value="'.$request->input('tx').'" style="width:500px">
        <input type="text" name="acc" placeholder="perspective" value="'.$request->input('acc').'" style="width:300px">
        <input type="submit">
      </form>';

      echo $form;

      if(!$request->input('tx') || !$request->input('acc')) return '';


      $client = app(\XRPLWin\XRPL\Client::class);

      $tx = $client->api('tx')
        ->params([
          'transaction' => $request->input('tx')
        ]);
      $tx = $tx->send()->finalResult();
      
      $parser = \App\XRPLParsers\Parser::get($tx, $tx->meta, $request->input('acc'));

      $parsedData = $parser->toBArray();
      dump($parser);
      echo '$parser->toBArray() - parsedData:';
      dump($parsedData);

      $TransactionClassName = '\\App\\Models\\BTransaction'.$parser->getTransactionTypeClass();
      try {
        $model = new $TransactionClassName($parsedData);
        $model->address = $request->input('acc');
        $model->xwatype = $TransactionClassName::TYPE;
        
      } catch (\Throwable $e) {
        $model = null;
      }

      if($model) {
        echo $TransactionClassName.'->toArray():';
        dump($model->toArray());
        echo $TransactionClassName.'->toFinalArray():';
        dump($model->toFinalArray());
      } else {
        echo $TransactionClassName.'->toArray():<div style="color:red;margin:20px 0">MODEL DOES NOT EXISTS</div>';
      }
      
      echo '<a target="_blank" href="https://playground.xrpl.win/play/xrpl-transaction-mutation-parser?hash='.$request->input('tx').'&ref='.$request->input('acc').'">View in Playground (new window)</a>';
    }

    public function test()
    {
      //$a = \BigQuery::client();
      //dd($a);
      exit;
      $i = new BAccount([
        'address' => 'testtesttesttest',
        'l' => 123,
        'activatedBy' => 'bbbbbbbbbbb',
        'isdeleted' => false,
      ]);
      $i->save();
      dd($i);
      $a = BAccount::find('r38UeRHhNLnprf1CjJ3ts4y1TuGCSSY3hL');
      //dd($a);
      $a->activatedBy = 'asdb32';
      $a->save();
      dd($a);
      //$dataset = \BigQuery::createDataset('myNewDataSet');
      exit;
      $client = app(\XRPLWin\XRPL\Client::class);


      $account_tx = $client->api('account_tx')
        ->params([
          'account' => 'rEsCYHcMpr5M4Knd9iPWF8oUaHkMEZ1r3a',
          'ledger_index' => 'current',
          'ledger_index_min' => 41275088, //Ledger index this account is scanned to.
          'ledger_index_max' => 41275088,
          'binary' => false,
          'forward' => true,
          'limit' => 20, //400
        ]);
      $account_tx->send();
      $txs = $account_tx->finalResult();
      
      foreach($txs as $transaction) {
        //dd($txs);
          if($transaction->tx->TransactionType == 'Payment') {
              $parser = \App\XRPLParsers\Parser::get($transaction->tx, $transaction->meta, 'rEsCYHcMpr5M4Knd9iPWF8oUaHkMEZ1r3a');
              $parsedData = $parser->toDArray();
              dd($parsedData);
          }
          
      }

      exit;

      $client = app(\XRPLWin\XRPL\Client::class);


      $account_tx = $client->api('account_tx')
        ->params([
          'account' => 'rrpNnNLKrartuEqfJGpqyDwPj1AFPg9vn1',
          'ledger_index' => 'current',
          'ledger_index_min' => 41275087, //Ledger index this account is scanned to.
          'ledger_index_max' => 41275087,
          'binary' => false,
          'forward' => true,
          'limit' => 2, //400
        ]);
      $account_tx->send();
      $txs = $account_tx->finalResult();
      
      foreach($txs as $transaction) {
          if($transaction->tx->TransactionType == 'Payment') {
              $parser = \App\XRPLParsers\Parser::get($transaction->tx, $transaction->meta, 'rrpNnNLKrartuEqfJGpqyDwPj1AFPg9vn1');
              $parsedData = $parser->toDArray();
              dd($parsedData);
          }
          
      }

      exit;
        $client = app(\XRPLWin\XRPL\Client::class);


        $account_tx = $client->api('account_tx')
          ->params([
            'account' => 'rhXrLZcXDF1WcULu7xSottKinbDvYG4cFQ',
            'ledger_index' => 'current',
            'ledger_index_min' => 74139050, //Ledger index this account is scanned to.
            'ledger_index_max' => 74139061,
            'binary' => false,
            'forward' => true,
            'limit' => 2, //400
          ]);
        $account_tx->send();
        $txs = $account_tx->finalResult();
        
        foreach($txs as $transaction) {
            if($transaction->tx->TransactionType == 'Payment') {
                $parser = \App\XRPLParsers\Parser::get($transaction->tx, $transaction->meta, 'rhXrLZcXDF1WcULu7xSottKinbDvYG4cFQ');
                $parsedData = $parser->toDArray();
                dd($parsedData);
            }
            
        }
        
    


        exit;
        $client = app(\XRPLWin\XRPL\Client::class);


        $account_tx = $client->api('account_tx')
          ->params([
            'account' => 'rhXrLZcXDF1WcULu7xSottKinbDvYG4cFQ',
            'ledger_index' => 'current',
            'ledger_index_min' => 74139050, //Ledger index this account is scanned to.
            'ledger_index_max' => 74139061,
            'binary' => false,
            'forward' => true,
            'limit' => 20, //400
          ]);

          $account_tx->send();
          $txs = $account_tx->finalResult();

          foreach($txs as $tx) {
            if($tx->tx->hash == 'CD96DDD677021D5CD29755459E060CE11C4707D796B3F95C4E44CB9ED5C9900D')
            //if($tx->tx->hash == 'E0382D408F1BD7835E86336B43EBD43C7543779BDECD406B0BC00BA7CB86CE13')
            {

                $parser = new \App\XRPLParsers\Utils\BalanceChanges($tx->meta);
                $result = $parser->result();
                dd($result);

                exit;
            }
          }


        //dd($txs);

        exit;
        $DTransactionModelName = '\\App\\Models\\DTransactionPayment';
        $DTransactionModel = $DTransactionModelName::createContextInstance('rsmYqAFi4hQtTY6k6S3KPJZh7axhUwxT31');
        //$test = new $DTransactionModelName;
        //dd($DTransactionModel);
        //$query = $DTransactionModelName::accountContext('rsmYqAFi4hQtTY6k6S3KPJZh7axhUwxT31')
        $r = $DTransactionModel->where('PK', 'rsmYqAFi4hQtTY6k6S3KPJZh7axhUwxT31-'.$DTransactionModelName::TYPE)
            ->where('SK', '>', 0)
            ->take(1)
            ;
        $res = null;
        $res = $r->get(['t'])->first(); //['PK','SK','t']
        dd($r,$res);
        exit;
        $model = new \App\Models\DTransactionPayment;


        //$r = $model::find(['PK' => 'rsmYqAFi4hQtTY6k6S3KPJZh7axhUwxT31-1','SK' => 0]);
        $r = $model->where('PK',  'rsmYqAFi4hQtTY6k6S3KPJZh7axhUwxT31-1')->where('SK', '>', 0)->take(1)->get(['SK']);
        dd($r,'rsmYqAFi4hQtTY6k6S3KPJZh7axhUwxT31-1');
    //dd($r->toDynamoDbQuery());
    // $r = $r->find($this->PK.'-'.$DTransactionModelName::TYPE);
        exit;
        /*$model = new \App\Models\DTransactionPayment;

        $raw = $model->toDynamoDbQuery(['count(*)']);
      
        $r = $model->scan($raw);
        dd($raw,$r);*/


        $test = new \App\Models\DTransactionPayment;
        $test = $test
            ->where('PK','rEb8TK3gBgk5auZkwc6sHnwrGVJH8DuaLh-1')
            ->where('SK', 'between', [35521645,36198903.033])
            ->where('r','begins_with','rDC')
            ;
        $r = $test->get();
        dd($r,$test);






        return;
    
        
        $count1 = new \App\Models\DTransactionPayment;
        $count1 = $count1->where('PK','rEb8TK3gBgk5auZkwc6sHnwrGVJH8DuaLh-1')->where('SK', 'between', [35470456,35496119.9999])->where('r','123');
        $count1Result = $count1->pagedCount();

        $count2 = $count1->setExclusiveStartKey($count1Result->lastKey);
        $count2Result = $count2->pagedCount();

        $count3 = $count1->setExclusiveStartKey($count2Result->lastKey);
        $count3Result = $count3->pagedCount();

        dd($count1Result,$count2Result,$count3Result);
        //$test = $count->prepare();
        //dd($test->get(['count(*)']));
        //dd($count->toDynamoDbQuery(),$count->toDynamoDbQuery()->query);
        //$last = $count->last();
        $test = $count->all(['count(*)']);
        dd($test);
        $nextPage = $count->after($test->last())->limit(2)->all();

        dd($test,$nextPage);
        dd('--',$count->count(),$count->get(['count(*)']),$count->toDynamoDbQuery());
















        return;
        $client = app(Client::class);

        $lc = new LiquidityCheck([
            //'to' => ['currency' => 'XRP'],
            'from' => ['currency' => '534F4C4F00000000000000000000000000000000', 'issuer' => 'rsoLo2S1kiGeCcn6hCUXVrCpGMWLrRrLZz'],
            //'from' => ['currency' => 'MTA', 'issuer' => 'r95bSz69js5MrCoMdhejdGHvHyPRXumLTm'],
            'to' => ['currency' => 'XRP'],
            'amount' => 0.1
        ],[
            'maxBookLines' => 500,

        ],$client);

        $liquidity =  $lc->get();

        return response()->json($liquidity);
    }
}
