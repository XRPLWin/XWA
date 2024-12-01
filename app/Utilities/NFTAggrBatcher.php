<?php

namespace App\Utilities;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Models\Nftfeed;
use Brick\Math\BigInteger;
use App\Utilities\Nft\NftSaleTx;
use XRPLWin\XRPL\Utilities\BalanceChanges;
#use Illuminate\Support\Collection;
use XRPLWin\XRPLNFTTxMutatationParser\NFTTxMutationParser;

class NFTAggrBatcher
{
  private bool $isStarted = false;
  private ?BalanceChanges $mem_balanceChanges = null;

  public function begin()
  {
    DB::beginTransaction();
    $this->isStarted = true;
  }

  private function getBalanceChanges(\stdClass $tx)
  {
    if($this->mem_balanceChanges !== null)
      return $this->mem_balanceChanges;
    $this->mem_balanceChanges = new BalanceChanges($tx->metaData,false);
    return $this->mem_balanceChanges;
  }

  public function addTx(\stdClass $tx, array $participants)
  {
    $t = ripple_epoch_to_carbon((int)$tx->date);
    
    if(!$t->gt(now()->addDays(-config('xwa.nftfeed_max_days'))))
      return; //tx is too old

    $isSuccess = $tx->metaData->TransactionResult == 'tesSUCCESS';
    if(!$isSuccess)
      return; //not sucessfull tx

    $txtype = $tx->TransactionType;
    
    if(!\in_array($txtype,[
      'NFTokenMint', //to show mint
      'NFTokenBurn', //to delete nfts from this table ? hm
      'NFTokenAcceptOffer', //to show sales
      'NFTokenCreateOffer', //to show offerings
      'URITokenMint', //to show uri mint
      'Remit' //to show uri mint...
    ])) return; //not nft type

    $ctid = encodeCTID($tx->ledger_index,$tx->metaData->TransactionIndex,config('xrpl.'.config('xrpl.net').'.networkid'));
    $t = $t->format('Y-m-d H:i:s.uP');


    #VARIABLES
    $nftcontext = $nftcurrentowner = $broker = null;
    $parsed_inout = ['IN' => null, 'OUT' => null];
    $parsed_unknowns = $parsed_all = [];
    $nftid = null;
    #VARIABLES END

    foreach($participants as $pacc) {
          
      $_NFTTxMutationParser = new NFTTxMutationParser($pacc, $tx);
      //dd($_NFTTxMutationParser);
      $_r = $_NFTTxMutationParser->result();

      if(\in_array('OWNER',$_r['ref']['roles']))
        $nftcurrentowner = $_r['ref']['account'];
      if(\in_array('BROKER',$_r['ref']['roles'])) //extract broker if exists
        $broker = $_r['ref']['account'];

      $nftcontext = $_r['context'];
      $nftid = $_r['nft'];
      $parsed_all[$pacc] = $_r;
      if($_r['ref']['direction'] == 'UNKNOWN')
        $parsed_unknowns[$pacc] = $_r;
      else {
        if($parsed_inout[$_r['ref']['direction']] === null)
          $parsed_inout[$_r['ref']['direction']] = $_r;
        else {
          throw new \Exception('NFTAggrBatcher: Unhandled nft multi in or out for tx in NFTAggrBatcher '.$tx->hash);
        }
      }
      unset($_r);
      unset($_NFTTxMutationParser);
    }
    //dd($parsed_inout);
    if(!$nftid)
      throw new \Exception('NFTAggrBatcher: Unable to extract NFTID for tx '.$tx->hash);

    $BC = $this->getBalanceChanges($tx);
    $BCResult = $BC->result(true);
    $model = new Nftfeed;
    $model->ctid = bchexdec($ctid);
    $model->t = $t;
    //LOGIC

    //php artisan xwa:continuoussyncproc 80004981 80004981
    if($txtype == 'Remit') {
      //get nfts from remit if there is none skip this tx
      throw new \Exception('NFTAggrBatcher: unhandled Remit');
    } else {
      $model->nft = $nftid; //subject nft

      if($txtype == 'NFTokenAcceptOffer') {
        # Sell offer, someone sold someone bought, may be brokered
        $model->source      = $parsed_inout['OUT']['ref']['account']; //seller

        if(count($BCResult[$model->source]['balances'][0]) == 2) {
          $model->i = null;
          $model->a = $BCResult[$model->source]['balances'][0]['value'];
          $model->c = 'XRP';
        } else {
          $model->i = $BCResult[$model->source]['balances'][0]['counterparty'];
          $model->a = $BCResult[$model->source]['balances'][0]['value'];
          $model->c = $BCResult[$model->source]['balances'][0]['currency'];
        }

        $model->destination = $parsed_inout['IN']['ref']['account'];
        if($broker) {
          $model->broker = $broker;
          //get broker fee
          if(count($BCResult[$model->broker]['balances'][0]) == 2) {
            $model->bi = null;
            $model->ba = $BCResult[$model->broker]['balances'][0]['value'];
            $model->bc = 'XRP';
          } else {
            $model->bi = $BCResult[$model->broker]['balances'][0]['counterparty'];
            $model->ba = $BCResult[$model->broker]['balances'][0]['value'];
            $model->bc = $BCResult[$model->broker]['balances'][0]['currency'];
          }
        }
        //From balance changes get sale amount and broker amount
        $model->save();
      } else if($txtype == 'NFTokenCreateOffer') {
        //Someone is selling nft 196E07C93EB1D75CA0740787ECB152BEB5FF6FBFA881E51E79AE1D044DBE3382 (todo add amount to explorer)
        dd('OVDJE STAO');
      } elseif($txtype == 'NFTokenMint') {
        throw new \Exception('TODO NFTokenMint');
        dd($nftParserResult,$model);
      } else {
        throw new \Exception('NFTAggrBatcher: unhandled '.$txtype);
      }

    }
  }

  public function execute()
  {
    if(!$this->isStarted)
      throw new \Exception('NFTAggrBatcher::begin() must be called after construct');

    DB::commit();
  }
}