<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Account;
use Brick\Math\BigDecimal;
use XRPLWin\XRPL\Client;
use XRPLWin\XRPLOrderbookReader\LiquidityCheck;

class MainController extends Controller
{
    public function test()
    {
        exit;

        $client = app(\XRPLWin\XRPL\Client::class);


        $account_tx = $client->api('account_tx')
          ->params([
            'account' => 'rhXrLZcXDF1WcULu7xSottKinbDvYG4cFQ',
            'ledger_index' => 'current',
            'ledger_index_min' => 74139050, //Ledger index this account is scanned to.
            'ledger_index_max' => 74139050,
            'binary' => false,
            'forward' => true,
            'limit' => 20, //400
          ]);

          $account_tx->send();
          $txs = $account_tx->finalResult();

          foreach($txs as $tx) {
            if($tx->tx->hash == 'E0382D408F1BD7835E86336B43EBD43C7543779BDECD406B0BC00BA7CB86CE13')
            {

                $parser = new \App\XRPLParsers\Utils\BalanceChanges($tx->meta);
                $result = $parser->result();
                dd($parser,$result);

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
