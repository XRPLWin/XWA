<?php

namespace App\Utilities;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Models\RecentAggr;
use Brick\Math\BigInteger;
use App\Utilities\Nft\NftSaleTx;
use Illuminate\Support\Collection;

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

    $this->collections[$ymd] = RecentAggr::select('subject','identifier','day','value_uint64','context')
      ->whereDate('day',$day)
      ->get();
  }

  private function getCollection(Carbon $day)
  {
    $this->loadDay($day);
    return $this->collections[$day->format('Y-m-d')];
  }

  private function setTo(Collection $models, string $subject, string $identifier, Carbon $day, int $value, string $context)
  {
    if($m = $models->where('subject',$subject)->where('identifier',$identifier)->first()) {
      $m->value_uint64 = (string)$value;
    } else {
      $m = new RecentAggr;
      $m->subject = $subject;
      $m->identifier = $identifier;
      $m->value_uint64 = (string)$value;
      $m->day = $day->startOfDay();
      $m->context = $context;
      $models->push($m);
    }
  }


  private function incrementInt(Collection $models, string $subject, string $identifier, Carbon $day, int $value, string $context = '')
  {
    if($m = $models->where('subject',$subject)->where('identifier',$identifier)->first()) {
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
    }
  }

  public function addTx(\stdClass $tx)
  {
    $t = ripple_epoch_to_carbon((int)$tx->date);
    if(!$t->isToday()) return;

    $models = $this->getCollection($t);

    $type = $tx->TransactionType;
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
      if(\is_string($tx->Amount)) {
        if($FoundTopPayment = $models->where('subject','TopPayment')->where('identifier',$tx->Account)->first()) {
          $value_uint64 = BigInteger::of($FoundTopPayment->value_uint64);
          $new_value_uint64 = BigInteger::of($tx->Amount);
          if($new_value_uint64->isGreaterThan($value_uint64)) {
            $FoundTopPayment->value_uint64 = (string)$new_value_uint64;
            $FoundTopPayment->context = $tx->hash;
          }
        } else {
          $this->setTo($models,'TopPayment',$tx->Account,$t,$tx->Amount,$tx->hash);
        }
        //echo 'A'.rand(1,9).PHP_EOL;
      }
    }

    //Highest fee payed (1) (skip account deletes)
    if($type != 'AccountDelete') {
      if($TopFee = $models->where('subject','TopFee')->first()) {
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

    //Issuer trustline adds
    if($type == 'TrustSet' && $isSuccess) {
      if($tx->LimitAmount->value != '0')
        $this->incrementInt($models,'TLAdds',$tx->LimitAmount->issuer.':'.$tx->LimitAmount->currency,$t,1);
    }

    //NFT tokens minted
    if(($type == 'NFTokenMint' || $type == 'URITokenMint') && $isSuccess) {
      $this->incrementInt($models,'NFTMints','',$t,1);
      $this->incrementInt($models,'NFTMintsBy',$tx->Account,$t,1);
    }

    //NFT SALES
    if(($type == 'NFTokenAcceptOffer' || $type == 'URITokenBuy') && $isSuccess) {

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

        if($FoundTopNFTSale = $models->where('subject','TopNFTSale')->where('identifier',$NFTSale->getNft())->first()) {
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

  /**
   * Saving aggregations only for today and yesterday, everything else is discarded.
   * @deprecated
   */
  private function OLD_processRecentggr(\stdClass $tx): void
  {
    $t = ripple_epoch_to_carbon((int)$tx->date);
    //if(!($t->isToday() || $t->isYesterday())) return;
    if(!$t->isToday()) return;

    $type = $tx->TransactionType;
    $isSuccess = $tx->metaData->TransactionResult == 'tesSUCCESS';
    
    //Tx usage (counts):
    RecentAggr::incrementInt('TxCount',$type,$t,1);
    //Tx result:
    if($tx->metaData->TransactionResult != 'tesSUCCESS')
      RecentAggr::incrementInt('TxResult','FAILED',$t,1);
    else
      RecentAggr::incrementInt('TxResult','SUCCESS',$t,1);

    //Tx usage by Initiator account (most initiated txs) (disabled: too much rows)
    //if(isset($tx->Account) && $tx->Account) {
    //  RecentAggr::incrementInt('TxAccount',$tx->Account,$t,1);
    //}

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
        RecentAggr::incrementInt('AccCreates','',$t,$_num_created_accounts);
      unset($_num_created_accounts);
    }

    //Deleted accounts
    if($type == 'AccountDelete' && $isSuccess) {
      RecentAggr::incrementInt('AccDeletes','',$t,1);
    }

    //Most significant payments in native currency (10)
    if($type == 'Payment' && $isSuccess) {
      if(\is_string($tx->Amount)) {
        $TopPayments = RecentAggr::select('subject','identifier','day','value_uint64')
          ->where('subject','TopPayment')
          ->whereDate('day',$t)
          ->orderBy('value_uint64','desc')
          ->get();
        if($FoundTopPayment = $TopPayments->where('identifier',$tx->Account)->first()) {
          $value_uint64 = BigInteger::of($FoundTopPayment->value_uint64);
          $new_value_uint64 = BigInteger::of($tx->Amount);
          if($new_value_uint64->isGreaterThan($value_uint64)) {
            $FoundTopPayment->value_uint64 = $tx->Amount;
            $FoundTopPayment->context = $tx->hash;
            $FoundTopPayment->save();
          }
        } else {
          RecentAggr::setTo('TopPayment',$tx->Account,$t,$tx->Amount,$tx->hash);
        }
        unset($TopPayments);

        //delete if there is more than 10 results
        $TopPayments = RecentAggr::select('subject','identifier','day')
          ->where('subject','TopPayment')
          ->whereDate('day',$t)
          ->orderBy('value_uint64','desc')
          ->get();
        $i = 0;
        foreach($TopPayments as $_tp) {
          $i++;
          if($i>10) {
            $_tp->delete();
          }
        }
      }
    }

    //Highest fee payed (1) (skip account deletes)
    if($type != 'AccountDelete') {
      $TopFee = RecentAggr::select('subject','identifier','day','value_uint64')
        ->where('subject','TopFee')
        ->whereDate('day',$t)
        ->first();
      if(!$TopFee)
        RecentAggr::setTo('TopFee',$tx->hash,$t,$tx->Fee,'');
      else {
        $TopFee->changePrimaryKey(['subject','day']);
        $value_uint64 = BigInteger::of($TopFee->value_uint64);
        $new_value_uint64 = BigInteger::of($tx->Fee);
        if($new_value_uint64->isGreaterThan($value_uint64)) {
          //$this->info((string)$value_uint64.' '.(string)$new_value_uint64);
          $TopFee->value_uint64 = (string)$new_value_uint64;
          $TopFee->identifier = $tx->hash;
          $TopFee->save();
        }
      }
    }

    //Sum fee for day - eg burned native currency
    RecentAggr::incrementInt('FeeSum','',$t,$tx->Fee);

    //Issuer trustline adds
    if($type == 'TrustSet' && $isSuccess) {
      if($tx->LimitAmount->value != '0')
        RecentAggr::incrementInt('TLAdds',$tx->LimitAmount->issuer.':'.$tx->LimitAmount->currency,$t,1);
    }

    //NFT tokens minted
    if(($type == 'NFTokenMint' || $type == 'URITokenMint') && $isSuccess) {
      RecentAggr::incrementInt('NFTMints','',$t,1);
      RecentAggr::incrementInt('NFTMintsBy',$tx->Account,$t,1);
    }

    //NFT SALES
    if(($type == 'NFTokenAcceptOffer' || $type == 'URITokenBuy') && $isSuccess) {

      $NFTSale = new NftSaleTx($tx,$tx->metaData);
      if($NFTSaleSeller = $NFTSale->getSeller()) {

        //Top sellers (by count of sales)
        RecentAggr::incrementInt('NFTSellerC',$NFTSaleSeller[0],$t,1);

        //Top brokers (by count of sales)
        if($NFTSaleBroker = $NFTSale->getBroker()) {
          RecentAggr::incrementInt('NFTBrokerC',$NFTSaleBroker[0],$t,1);
        }

        //Top NFT sale (10) in native currency only
        $saleAmount = $NFTSaleSeller[1]; //drops
        $TopNFTSales = RecentAggr::select('subject','identifier','day','value_uint64','context')
            ->where('subject','TopNFTSale')
            ->whereDate('day',$t)
            ->orderBy('value_uint64','desc')
            ->get();

        if($FoundTopNFTSale = $TopNFTSales->where('identifier',$NFTSale->getNft())->first()) {
          $value_uint64 = BigInteger::of($FoundTopNFTSale->value_uint64);
          $new_value_uint64 = BigInteger::of($saleAmount);
          if($new_value_uint64->isGreaterThan($value_uint64)) {
            $FoundTopNFTSale->value_uint64 = $saleAmount;
            $FoundTopNFTSale->context = $tx->hash;
            $FoundTopNFTSale->save();
          }
        } else {
          RecentAggr::setTo('TopNFTSale',$NFTSale->getNft(),$t,$saleAmount,$tx->hash);
        }
        unset($TopNFTSales);

        //delete if there is more than 10 results
        $TopNFTSales = RecentAggr::select('subject','identifier','day')
          ->where('subject','TopNFTSale')
          ->whereDate('day',$t)
          ->orderBy('value_uint64','desc')
          ->get();
        $i = 0;
        foreach($TopNFTSales as $_tp) {
          $i++;
          if($i>10) {
            $_tp->delete();
          }
        }
      }
    }
    
    //Xahau Import (already have it)
    //if($type == 'Import' && $isSuccess) {}

    //Xahau GenesisMint
    if($type == 'GenesisMint' && $isSuccess) {
      $total_minted_sum = 0;
      foreach($tx->GenesisMints as $gm) {
        RecentAggr::incrementInt('GenMintedA',$gm->GenesisMint->Destination,$t,$gm->GenesisMint->Amount);
        $total_minted_sum = $total_minted_sum + $gm->GenesisMint->Amount;
      }
      RecentAggr::incrementInt('GenMinted','',$t,$total_minted_sum);
    }
    

    //Payments to and from exchanges
    //TODO (unreliable)
  }

  public function execute()
  {
    if(!$this->isStarted)
      throw new \Exception('begin() must be called after construct');
    //save to db
    //dd($this->collections);
    foreach($this->collections as $collection) {
      
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
        echo 'Saving model: '.$m->subject.' '.$m->identifier.PHP_EOL;
        $m->save();
      }
    }

    //Cleanup
    //TODO


    DB::commit();
  }
}