<?php

namespace App\Utilities;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Models\RecentAggr;
use Brick\Math\BigInteger;
use App\Utilities\Nft\NftSaleTx;
use Illuminate\Support\Collection;
use XRPLWin\XRPLNFTTxMutatationParser\NFTTxMutationParser;

class RecentAggrBatcher
{
  private array $collections = [];
  //private array $queue = [];
  private bool $isStarted = false;

  public function begin()
  {
    DB::beginTransaction();
    $this->isStarted = true;
  }

  private function loadDay(Carbon $day)
  {
    $ymd = $day->format('Y-m-d');
    if(isset($this->collections[$ymd]))
      return;
    $collection = RecentAggr::select('subject','identifier','day','value_uint64','context')
    ->whereDate('day',$day)
    ->get();

    //convert collection to keyed array:
    $this->collections[$ymd] = [];
    foreach($collection as $m){
      $this->collections[$ymd][$m->uniqueIdentifier()] = $m;
    }
    unset($collection);
    /*$this->collections[$ymd] = RecentAggr::select('subject','identifier','day','value_uint64','context')
      ->whereDate('day',$day)
      ->get();*/
    echo PHP_EOL.'Loaded day '.$ymd.' '.count($this->collections[$ymd]).' rows'.PHP_EOL;
  }

  private function getCollection(Carbon $day)
  {
    $this->loadDay($day);
    return $this->collections[$day->format('Y-m-d')];
  }

  private function insert(array $models, string $subject, string $identifier, Carbon $day, int $value, string $context)
  {
    $m = new RecentAggr;
    $m->subject = $subject;
    $m->identifier = $identifier;
    $m->day = $day->startOfDay();
    $m->value_uint64 = (string)$value;
    $m->context = $context;
    $this->collections[$day->format('Y-m-d')][$m->uniqueIdentifier()] = $m;
    //$models->push($m);
  }

  private function setTo(array $models, string $subject, string $identifier, Carbon $day, int $value, string $context)
  {
    $identifier = $subject.'_'.$identifier;
    $m = isset($models[$identifier]) ? $models[$identifier]:null;

    if($m !== null) {
      $m->value_uint64 = (string)$value;
      $m->context = $context;
    } else {
      $this->insert(
        $models,
        $subject,
        $identifier,
        $day,
        $value,
        $context
      );
    }

    //Old below:
    /*if($m = $models->where('subject',$subject)->where('identifier',$identifier)->first()) {
      $m->value_uint64 = (string)$value;
      $m->context = $context;
    } else {
      $this->insert(
        $models,
        $subject,
        $identifier,
        $day,
        $value,
        $context
      );
      //$m = new RecentAggr;
      //$m->subject = $subject;
      //$m->identifier = $identifier;
      //$m->value_uint64 = (string)$value;
      //$m->day = $day->startOfDay();
      //$m->context = $context;
      //$models->push($m);
    }*/
  }

  private function incrementInt(array $models, string $subject, string $identifier, Carbon $day, int $value, string $context = '')
  {
    $identifier = $subject.'_'.$identifier;
    $m = isset($models[$identifier]) ? $models[$identifier]:null;

    if($m !== null) {
      $m->value_uint64 = (string)BigInteger::of($m->value_uint64)->plus($value);
      $m->context = $context;
    } else {
      $m = new RecentAggr;
      $m->subject = $subject;
      $m->identifier = $identifier;
      $m->value_uint64 = (string)$value;
      $m->day = $day->startOfDay();
      $m->context = $context;
      $this->collections[$day->format('Y-m-d')][$m->uniqueIdentifier()] = $m;
    }

    //echo 'INC start'.PHP_EOL;
    /*if($m = $models->where('subject',$subject)->where('identifier',$identifier)->first()) {
      $m->value_uint64 = (string)BigInteger::of($m->value_uint64)->plus($value);
      $m->context = $context;
    } else {
      $m = new RecentAggr;
      $m->subject = $subject;
      $m->identifier = $identifier;
      $m->value_uint64 = (string)$value;
      $m->day = $day->startOfDay();
      $m->context = $context;
      $models->push($m);
    }*/
    //echo 'INC END'.PHP_EOL;
  }

  public function addTx(\stdClass $tx)
  {
    $t = ripple_epoch_to_carbon((int)$tx->date);
    if(!$t->isToday()) return;
    
    $models = $this->getCollection($t);

    $type = $tx->TransactionType;
    //dump($type);
    //return;
    $isSuccess = $tx->metaData->TransactionResult == 'tesSUCCESS';

    //Tx usage (counts):
    $this->incrementInt($models,'TxCount',$type,$t,1);
    
    if($tx->metaData->TransactionResult != 'tesSUCCESS')
      $this->incrementInt($models,'TxResult','FAILED',$t,1);
    else
      $this->incrementInt($models,'TxResult','SUCCESS',$t,1);

    //Activated accounts (check created node in metadata)
    if($isSuccess) {
      $_num_created_accounts = 0;
      if(isset($tx->metaData->AffectedNodes)) {
        foreach($tx->metaData->AffectedNodes as $AffectedNode) {
          if(isset($AffectedNode->CreatedNode)) {
            if(isset($AffectedNode->CreatedNode->LedgerEntryType) && $AffectedNode->CreatedNode->LedgerEntryType == 'AccountRoot') {
              $_num_created_accounts++;
            }
          }
        }
      }
      if($_num_created_accounts > 0)
        $this->incrementInt($models,'AccCreates','',$t,$_num_created_accounts);
      unset($_num_created_accounts);
    }

    //Deleted accounts
    if($type == 'AccountDelete' && $isSuccess) {
      $this->incrementInt($models,'AccDeletes','',$t,1);
    }

    //Most significant payments in native currency (10)
    if($type == 'Payment' && $isSuccess) {
      $Amount = isset($tx->metaData->delivered_amount) ? $tx->metaData->delivered_amount : $tx->Amount;
      if(\is_string($Amount)) {
        $new_value_uint64 = BigInteger::of($Amount);
        if($new_value_uint64->isGreaterThan(100000000)) { //do not record below 100 XRP transfers (performance reasons)
          $FoundTopPayment = isset($models['TopPayment_'.$tx->Account])?$models['TopPayment_'.$tx->Account]:null;
          if($FoundTopPayment) {
            $value_uint64 = BigInteger::of($FoundTopPayment->value_uint64);
            
            if($new_value_uint64->isGreaterThan($value_uint64)) {
              $FoundTopPayment->value_uint64 = (string)$new_value_uint64;
              $FoundTopPayment->context = $tx->hash;
            }
          } else {
            $this->insert($models,'TopPayment',$tx->Account,$t,$Amount,$tx->hash);
          }
          //echo 'DO '.rand(1,9999).PHP_EOL;
        }
      }
    }

    //Highest fee payed (1) (skip account deletes)
    if($type != 'AccountDelete') {
      $TopFee = isset($models['TopFee_'])?$models['TopFee_']:null;
      if($TopFee) {
        $value_uint64 = BigInteger::of($TopFee->value_uint64);
        $new_value_uint64 = BigInteger::of($tx->Fee);
        if($new_value_uint64->isGreaterThan($value_uint64)) {
          $TopFee->value_uint64 = (string)$new_value_uint64;
          $TopFee->context = $tx->hash;
        }
      } else {
        $this->setTo($models,'TopFee','',$t,$tx->Fee,$tx->hash);
      }
    }

    //Sum fee for day - eg burned native currency
    $this->incrementInt($models,'FeeSum','',$t,$tx->Fee);

    //Issuer trustline adds (skips trustline modifications)
    if($type == 'TrustSet' && $isSuccess) {
      if($tx->LimitAmount->value != '0') {
        //if ripple state object was created then this is new trustline addition:
        if(isset($tx->metaData->AffectedNodes)) {
          foreach($tx->metaData->AffectedNodes as $AffectedNode) {
            if(isset($AffectedNode->CreatedNode)) {
              if(isset($AffectedNode->CreatedNode->LedgerEntryType) && $AffectedNode->CreatedNode->LedgerEntryType == 'RippleState') {
                $this->incrementInt($models,'TLAdds',$tx->LimitAmount->issuer.':'.$tx->LimitAmount->currency,$t,1);
                break;
              }
            }
          }
        }
      } else {
        $this->incrementInt($models,'TLRemoves',$tx->LimitAmount->issuer.':'.$tx->LimitAmount->currency,$t,1);
      }
    }

    //NFT tokens minted
    if(($type == 'NFTokenMint' || $type == 'URITokenMint' || $type == 'Remit') && $isSuccess) {
      $_num_mints = 1;
      if($type == 'Remit') {
        //can be zero or one mint
        $_num_mints = 0;
        
        $nftParser = new NFTTxMutationParser($tx->Account,$tx);
        $nftParserResult = $nftParser->result();
        if($nftParserResult['nft'] !== null)
          $_num_mints = 1;
        unset($nftParser);
        unset($nftParserResult);
      }

      if($_num_mints > 0) {
        $this->incrementInt($models,'NFTMints','',$t,$_num_mints);
        $this->incrementInt($models,'NFTMintsBy',$tx->Account,$t,$_num_mints);
      }

      unset($_num_mints);
      
    }

    //NFT tokens burned
    if(($type == 'NFTokenBurn' || $type == 'URITokenBurn') && $isSuccess) {
      $this->incrementInt($models,'NFTBurns','',$t,1);
    }

    //NFT SALES (disabled cause its too slow)
    if(($type == 'NFTokenAcceptOffer' || $type == 'URITokenBuy') && $isSuccess) {
      //This was too slow on xahau with hight load of 500 uritokenbuys per ledger:
      $NFTSale = new NftSaleTx($tx,$tx->metaData);
      if($NFTSaleSeller = $NFTSale->getSeller()) {

        //Top sellers (by count of sales)
        $this->incrementInt($models,'NFTSellerC',$NFTSaleSeller[0],$t,1);

        //Top brokers (by count of sales)
        if($NFTSaleBroker = $NFTSale->getBroker()) {
          $this->incrementInt($models,'NFTBrokerC',$NFTSaleBroker[0],$t,1);
        }

        //Top NFT sale (10) in native currency only
        $saleAmount = $NFTSaleSeller[1]; //drops
        $_id = 'TopNFTSale_'.$NFTSale->getNft();
        $FoundTopNFTSale = isset($models[$_id])?$models[$_id]:null;
        unset($_id);
        if($FoundTopNFTSale) {
          $value_uint64 = BigInteger::of($FoundTopNFTSale->value_uint64);
          $new_value_uint64 = BigInteger::of($saleAmount);
          if($new_value_uint64->isGreaterThan($value_uint64)) {
            $FoundTopNFTSale->value_uint64 = (string)$new_value_uint64;
            $FoundTopNFTSale->context = $tx->hash;
            $FoundTopNFTSale->save();
          }
        } else {
          $this->setTo($models,'TopNFTSale',$NFTSale->getNft(),$t,$saleAmount,$tx->hash);
        }

      }
    }

    //Xahau GenesisMint
    if($type == 'GenesisMint' && $isSuccess) {
      $total_minted_sum = 0;
      foreach($tx->GenesisMints as $gm) {
        $this->incrementInt($models,'GenMintedA',$gm->GenesisMint->Destination,$t,$gm->GenesisMint->Amount);
        $total_minted_sum = $total_minted_sum + $gm->GenesisMint->Amount;
      }
      $this->incrementInt($models,'GenMinted','',$t,$total_minted_sum);
    }
  }

  public function execute()
  {
    if(!$this->isStarted)
      throw new \Exception('begin() must be called after construct');
    //save to db
    //dd($this->collections);
    foreach($this->collections as $collectionArray) {

      $collection = collect($collectionArray);
      //SORT BY VALUE
      $collection = $collection->sortByDesc('value_uint64',SORT_NUMERIC);

      //Save top 10 of TopPayment
      $i = 0;
      foreach($collection->where('subject','TopPayment') as $m) {
        $i++;
        if($i>10) {
          if($m->exists)
            $m->delete(); //db delete
        } else {
          $m->save();
        }
      }

      //Save top 10 of TopNFTSale
      $i = 0;
      foreach($collection->where('subject','TopNFTSale') as $m) {
        $i++;
        if($i>10) {
          if($m->exists)
            $m->delete(); //db delete
        } else {
          $m->save();
        }
      }

      foreach($collection as $m) {
        if($m->subject == 'TopPayment') continue;
        if($m->subject == 'TopNFTSale') continue;
        //echo 'Saving model: '.$m->subject.' '.$m->identifier.PHP_EOL;
        $m->save();
      }
    }

    DB::commit();
  }
}