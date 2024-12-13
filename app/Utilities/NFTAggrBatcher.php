<?php

namespace App\Utilities;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Models\Nftfeed;
use Brick\Math\BigInteger;
use App\Utilities\Nft\NftSaleTx;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use XRPLWin\XRPL\Utilities\BalanceChanges;
#use Illuminate\Support\Collection;
use XRPLWin\XRPLNFTTxMutatationParser\NFTTxMutationParser;

class NFTAggrBatcher
{
  private bool $isStarted = false;

  const TYPE_MINT = 1; //Mints a token
  const TYPE_BURN = 9; //Burns a token

  //Offerings:
  const TYPE_SELLING = 2; //NFTokenCreateOffer with sale flag (user wants to sell own nft)
  const TYPE_BUYING = 3;  //NFTokenCreateOffer with buy flag (user wants to buy some nft)

  //NFT changes hands:
  const TYPE_SALE = 4;    //direct sale (cancels someones NFTokenSellOffer) user sold own nft with action of other
  const TYPE_BUY = 5;     //direct sale (cancels someones NFTokenBuyOffer) user bought someones nft offering
  const TYPE_BROKERED = 6; //brokered sale (two offers cancels out) - marketplace matched them (maybe collected fee)

  //direct sale 006B90BC3D92AD2DD75DB57E096AE7ABE9BF8170C01B861A9E7DAFC11B78516C
  

  public function begin()
  {
    DB::beginTransaction();
    $this->isStarted = true;
  }

  private function getBalanceChanges(\stdClass $tx)
  {
    return new BalanceChanges($tx->metaData,false);
  }

  /**
   * @see C624CD4A8B604C60ABA10D9E64F5E41E9BCB9EA4F958169F51FC05C17C1D0718 (TYPE_BUYING)
   * @see 4F47F78B7E2EB7112B34A441BFF2EFF25B2970EE0DFDAE6B0FD0D2243AA127C5 (TYPE_BUYING)
   * @see CC59D92011B9331E78FB09E2CA3CFE7D8C773116861B6EBE79DB195F1D003B0B (TYPE_SELLING) xrp amount
   * @see 3571BAD536BF215631518D0E6648906CCCB8FA9BF07810FF16E4DF0A41EB7FD4 (TYPE_SELLING) Currency amount
   * DONE
   */
  private function ProcessNFTokenCreateOffer(\stdClass $tx, array $participants, string $ctid, string $timestamp): void
  {
    $model = new Nftfeed;
    $model->ctid = bchexdec($ctid); //store ctid as INT64 (saves space)
    $model->t = $timestamp;

    //$BC = $this->getBalanceChanges($tx);
    //$BCResult = $BC->result(true);

    #VARIABLES
    $nftcurrentowner = null;
    //$broker = null;
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
      //if(\in_array('BROKER',$_r['ref']['roles'])) //extract broker if exists
      //  $broker = $_r['ref']['account'];

      //$nftcontext = $_r['context'];//??
      $nftid = $_r['nft'];
      $parsed_all[$pacc] = $_r;
      if($_r['ref']['direction'] == 'UNKNOWN')
        $parsed_unknowns[$pacc] = $_r;
      else {
        if($parsed_inout[$_r['ref']['direction']] === null)
          $parsed_inout[$_r['ref']['direction']] = $_r;
        else {
          throw new \Exception('NFTAggrBatcher::ProcessNFTokenAcceptOffer: Unhandled nft multi in or out for tx in NFTAggrBatcher '.$tx->hash);
        }
      }
      unset($_r);
      unset($_NFTTxMutationParser);
    }

    $model->nft = $nftid; //subject nft
    # Sell offer, someone sold someone bought, may be brokered
    //$model->source = $parsed_inout['OUT']['ref']['account']; //seller
    if($nftcurrentowner === null)
      throw new \Exception('NFTAggrBatcher::ProcessNFTokenAcceptOffer: Unable to detect nft current owner '.$tx->hash);

    if(isset($tx->Flags) && $tx->Flags == 1) { //its sale (tfSellNFToken enabled)
      $model->source = $nftcurrentowner;
      $model->type = self::TYPE_SELLING;

      if(isset($tx->Destination)) {
        //sale to specific person
        $model->destination = $tx->Destination; //sale for someone of via some broker
      }
      
    } else {
      $model->source = $tx->Account;
      $model->destination = $nftcurrentowner;
      $model->type = self::TYPE_BUYING;
      //if tx destination is different than $nftcurrentowner then tx destination is broker
      if(isset($tx->Destination) && $tx->Destination != $nftcurrentowner) {
        $model->broker = $tx->Destination;
      }
    }

    if(isset($tx->Amount)) {
      //sell offer that const something?
      if(\is_string($tx->Amount)) {
        $model->i = null;
        $model->a = (string)BigDecimal::of($tx->Amount)->dividedBy(1000000, RoundingMode::HALF_EVEN);
        $model->c = 'XRP';
      } elseif(isset($tx->Amount->currency)) {
        $model->a = $tx->Amount->value;
        $model->i = $tx->Amount->issuer;
        $model->c = $tx->Amount->currency;
      } else {
        //Multi purpose token?
        throw new \Exception('NFTAggrBatcher::ProcessNFTokenAcceptOffer: Unhandled currency detected '.$tx->hash);
      }
    }

    $model->save();
  }

  /**
   * @see 99EEF2FD5D29ACE0A48A7DFAF7E4C366868683E8DCC72EA18394E7ABCA854A11 (TYPE_MINT)
   * DONE
   */
  private function ProcessNFTokenMint(\stdClass $tx, array $participants, string $ctid, string $timestamp): void
  {
    $model = new Nftfeed;
    $model->ctid = bchexdec($ctid); //store ctid as INT64 (saves space)
    $model->t = $timestamp;
    $model->type = self::TYPE_MINT;

    $minter = null; //minter account
    $nftid = null;
    foreach($participants as $pacc) {
      if($minter !== null && $nftid !== null)
        break; //we have all info
          
      $_NFTTxMutationParser = new NFTTxMutationParser($pacc, $tx);
      $_r = $_NFTTxMutationParser->result();
      if(\in_array('MINTER',$_r['ref']['roles'])) //extract broker if exists
        $minter = $_r['ref']['account'];

      $nftid = $_r['nft'];
      unset($_r);
      unset($_NFTTxMutationParser);
    }
    if($minter === null || $nftid === null) {
      throw new \Exception('NFTAggrBatcher::ProcessNFTokenMint: Cant extract both minter and nft '.$tx->hash);
      //return;
    }
    $model->nft = $nftid;
    $model->source = $minter;
    $model->save();
    
  }

  /**
   * @see 006B90BC3D92AD2DD75DB57E096AE7ABE9BF8170C01B861A9E7DAFC11B78516C (TYPE_SALE)
   * @see 9320AF4EC22FB7EE75B9FBB2E87BA2F5E494D9F02B87C5C22738233541219A83 (TYPE_BROKERED)
   * @see 8309CDAEAB871270C34B5174293663D9BEA45E1EBEEFEF619010C6561C7399C1 (no balance - free transfer)
   * DONE
   */
  private function ProcessNFTokenAcceptOffer(\stdClass $tx, array $participants, string $ctid, string $timestamp): void
  {
    $model = new Nftfeed;
    $model->ctid = bchexdec($ctid); //store ctid as INT64 (saves space)
    $model->t = $timestamp;

    $BC = $this->getBalanceChanges($tx);
    $BCResult = $BC->result(true);

    #VARIABLES
    //$nftcurrentowner = null;
    $broker = null;
    $parsed_inout = ['IN' => null, 'OUT' => null];
    $parsed_unknowns = $parsed_all = [];
    $nftid = null;
    #VARIABLES END
    foreach($participants as $pacc) {
          
      $_NFTTxMutationParser = new NFTTxMutationParser($pacc, $tx);
      //dd($_NFTTxMutationParser);
      $_r = $_NFTTxMutationParser->result();

      //if(\in_array('OWNER',$_r['ref']['roles']))
      //  $nftcurrentowner = $_r['ref']['account'];
      if(\in_array('BROKER',$_r['ref']['roles'])) //extract broker if exists
        $broker = $_r['ref']['account'];

      //$nftcontext = $_r['context'];//??
      $nftid = $_r['nft'];
      $parsed_all[$pacc] = $_r;
      if($_r['ref']['direction'] == 'UNKNOWN')
        $parsed_unknowns[$pacc] = $_r;
      else {
        if($parsed_inout[$_r['ref']['direction']] === null)
          $parsed_inout[$_r['ref']['direction']] = $_r;
        else {
          throw new \Exception('NFTAggrBatcher::ProcessNFTokenAcceptOffer: Unhandled nft multi in or out for tx in NFTAggrBatcher '.$tx->hash);
        }
      }
      unset($_r);
      unset($_NFTTxMutationParser);
    }

    $model->nft = $nftid; //subject nft

    # Sell offer, someone sold someone bought, may be brokered
    $model->source = $parsed_inout['OUT']['ref']['account']; //seller

    //if(!isset($BCResult[$model->source]['balances'][0])) {
    //  dd($tx->hash,$model->source,$BCResult);
    //}

    if(isset($BCResult[$model->source])) {
      //Payed Transfer
      if(count($BCResult[$model->source]['balances'][0]) == 2) {
        $model->i = null;
        $model->a = $BCResult[$model->source]['balances'][0]['value'];
        $model->c = 'XRP';
        //2D20169D6743655E794D318239C7297FF0A75E2BF3A793C93B6EB05C4245D565
        //If $model->source is Fee Payer then deduce fee from $model->a
        if($model->source == $tx->Account) {
          $a = BigDecimal::of($model->a)->plus(($tx->Fee/1000000));
          $model->a = (string)$a;
        }
      } else {
        $model->i = $BCResult[$model->source]['balances'][0]['counterparty'];
        $model->a = $BCResult[$model->source]['balances'][0]['value'];
        $model->c = $BCResult[$model->source]['balances'][0]['currency'];
      }
    } else {
      //Free Transfer
    }
    

    $model->destination = $parsed_inout['IN']['ref']['account'];
    if($broker) {
      $model->type = self::TYPE_BROKERED;
      $model->broker = $broker;
      //get broker fee
      if(count($BCResult[$model->broker]['balances'][0]) == 2) {
        $model->bi = null;
        $model->ba = $BCResult[$model->broker]['balances'][0]['value'];
        $model->bc = 'XRP';
        //B9A84D39250C92BD4DF0D4942580CB9E07CC37E48595149D55328A769110F52B
        //If $model->broker is Fee Payer then deduce fee from $model->a
        if($model->broker == $tx->Account) {
          $ba = BigDecimal::of($model->ba)->plus(($tx->Fee/1000000));
          $model->ba = (string)$ba;
        }
      } else {
        $model->bi = $BCResult[$model->broker]['balances'][0]['counterparty'];
        $model->ba = $BCResult[$model->broker]['balances'][0]['value'];
        $model->bc = $BCResult[$model->broker]['balances'][0]['currency'];
      }
    } else {
      if(isset($tx->NFTokenSellOffer)) {
        $model->type = self::TYPE_SALE;
      } else {
        $model->type = self::TYPE_BUY;
      }
    }
    //From balance changes get sale amount and broker amount
    $model->save();
  }

  private function ProcessNFTokenBurn(\stdClass $tx, array $participants, string $ctid, string $timestamp): void
  {
    $nftid = null;
    foreach($participants as $pacc) {
      $_NFTTxMutationParser = new NFTTxMutationParser($pacc, $tx);
      $_r = $_NFTTxMutationParser->result();
      $nftid = $_r['nft'];
      if($nftid)
        break;
    }
    if($nftid) { 

      //Nftfeed::where('nft',$nftid)->limit(100)->delete(); //cleanup old notifications

      $model = new Nftfeed;
      $model->ctid = bchexdec($ctid); //store ctid as INT64 (saves space)
      $model->t = $timestamp;
      $model->nft = $nftid;
      $model->source = $tx->Account;
      $model->type = self::TYPE_BURN;
      $model->save();
    }
  }

  private function ProcessURITokenMint(\stdClass $tx, array $participants, string $ctid, string $timestamp): void
  {
    //TODO
  }

  private function ProcessRemit(\stdClass $tx, array $participants, string $ctid, string $timestamp): void
  {
    //TODO
  }

  public function addTx(\stdClass $tx, array $participants): void
  {
    $t = ripple_epoch_to_carbon((int)$tx->date);
    
    if(!$t->gt(now()->addDays(-config('xwa.nftfeed_max_days'))))
      return; //tx is too old

    $isSuccess = $tx->metaData->TransactionResult == 'tesSUCCESS';
    if(!$isSuccess)
      return; //not sucessfull tx

    $txtype = $tx->TransactionType;
    
    if(!\in_array($txtype,[
      //XRPL
      'NFTokenMint', //to show mint                         - ok
      'NFTokenBurn', //to delete nfts from this table ? hm
      'NFTokenAcceptOffer', //to show sales                 - ok
      'NFTokenCreateOffer', //to show offerings             - ok
      //XAHAU
      'URITokenMint', //to show uri mint
      'Remit' //to show uri mint...
    ])) return; //not nft type

    $ctid = encodeCTID($tx->ledger_index,$tx->metaData->TransactionIndex,config('xrpl.'.config('xrpl.net').'.networkid'));
    $t = $t->format('Y-m-d H:i:s.uP');

    $method = 'Process'.$txtype;
    $this->$method($tx, $participants, $ctid, $t);
  }

  public function execute()
  {
    if(!$this->isStarted)
      throw new \Exception('NFTAggrBatcher::begin() must be called after construct');

    DB::commit();
  }
}