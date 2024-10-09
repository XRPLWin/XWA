<?php

namespace App\Utilities;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Models\Nftfeed;
use Brick\Math\BigInteger;
use App\Utilities\Nft\NftSaleTx;
#use Illuminate\Support\Collection;
use XRPLWin\XRPLNFTTxMutatationParser\NFTTxMutationParser;

class NFTAggrBatcher
{
  private bool $isStarted = false;

  public function begin()
  {
    DB::beginTransaction();
    $this->isStarted = true;
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
      'Remit' //mixed TODO
    ])) return; //not nft type

    $ctid = encodeCTID($tx->ledger_index,$tx->metaData->TransactionIndex,config('xrpl.'.config('xrpl.net').'.networkid'));
    $t = $t->format('Y-m-d H:i:s.uP');











    #VARIABLES
    $nftcontext = $nftcurrentowner = null;
    $parsed_inout = ['IN' => null, 'OUT' => null];
    $parsed_unknowns = $parsed_all = [];
    $nftid = null;
    #VARIABLES END

    foreach($participants as $pacc) {
          
      $_NFTTxMutationParser = new NFTTxMutationParser($pacc, $tx);
      $_r = $_NFTTxMutationParser->result();

      if(\in_array('OWNER',$_r['ref']['roles']))
        $nftcurrentowner = $_r['ref']['account'];
      
      $nftcontext = $_r['context'];
      $nftid = $_r['nft'];
      $parsed_all[$pacc] = $_r;
      if($_r['ref']['direction'] == 'UNKNOWN')
        $parsed_unknowns[$pacc] = $_r;
      else {
        if($parsed_inout[$_r['ref']['direction']] === null)
          $parsed_inout[$_r['ref']['direction']] = $_r;
        else {
          throw new \Exception('Unhandled nft multi in or out for tx in NFTAggrBatcher '.$tx->hash);
        }
      }
      unset($_r);
      unset($_NFTTxMutationParser);
    }
    dd($parsed_inout);


    //LOGIC

    //php artisan xwa:continuoussyncproc 80004981 80004981
    if($txtype == 'Remit') {
      //get nfts from remit if there is none skip this tx
      throw new \Exception('NFTAggrBatcher unhandled Remit');
    } else {
      $model->nft = $nftid; //subject nft

      if($txtype == 'NFTokenAcceptOffer') {
        if($nftParserResult['context'] == 'SELL') {
          //initiator bought nft
          $model->destination = $tx->Account;
          $model->source = ''; //todo extract buyer
          dd($participants);
        }
        $model->source = $tx->Account;
        $model->destination = $nftParserResult;
        
        dd($tx->hash,$nftParserResult,$model);
      } elseif($txtype == 'NFTokenMint') {
        dd($nftParserResult,$model);
      } else {
        throw new \Exception('NFTAggrBatcher unhandled '.$txtype);
      }






    }
    


























    $nftParser = new NFTTxMutationParser($tx->Account,$tx);
    $nftParserResult = $nftParser->result();

    $model = new Nftfeed;
    $model->ctid = bchexdec($ctid);
    $model->t = $t;

    //php artisan xwa:continuoussyncproc 80004981 80004981
    if($txtype == 'Remit') {
      //get nfts from remit if there is none skip this tx
      throw new \Exception('NFTAggrBatcher unhandled Remit');
    } else {
      $model->nft = $nftParserResult['nft']; //subject nft

      if($txtype == 'NFTokenAcceptOffer') {
        if($nftParserResult['context'] == 'SELL') {
          //initiator bought nft
          $model->destination = $tx->Account;
          $model->source = ''; //todo extract buyer
          dd($participants);
        }
        $model->source = $tx->Account;
        $model->destination = $nftParserResult;
        
        dd($tx->hash,$nftParserResult,$model);
      } elseif($txtype == 'NFTokenMint') {
        dd($nftParserResult,$model);
      } else {
        throw new \Exception('NFTAggrBatcher unhandled '.$txtype);
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