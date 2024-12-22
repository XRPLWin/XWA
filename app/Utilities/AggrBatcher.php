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
use App\Models\Aggr\Aggrtxtype;
use App\Models\Aggr\Aggrtotal;
use App\Models\Aggr\Aggrtxresult;
use App\Models\Aggr\Aggrtempledgerinterval;
use XRPL_PHP\Core\CoreUtilities as XRPLPHPUtilities;

/**
 * used in command: XwaStartAggrSyncer -> XwaContinousAggrSyncProc
 */
class AggrBatcher
{
  private readonly int $ledger_index;
  private readonly int $thisLedgerIntervalSeconds;
  private readonly array $thisLedgerIntervalPrevCurr; //[rippletime,rippletime]
  private int $txs_count = 0;
  private int $fee_total_drops = 0;
  private int $txs_failed_count = 0;
  private int $txs_rksigned_count = 0;
  private int $txs_multisigned_count = 0;
  private int $txs_withmemo_count = 0;
  private int $memo_bytes = 0;
  
  //private int $avg_ledger_interval_ms = 0;
  private ?int $max_ledger_interval_s = null;
  private ?int $min_ledger_interval_s = null;

  private array $transaction_types = [];
  private array $transaction_results = [];

  
  
  /**
   * This instance processes single ledger all transactions of single ledger.
   */
  public function __construct(int $ledger_index, int $thisLedgerIntervalSeconds, array $thisLedgerIntervalPrevCurr)
  {
    $this->ledger_index = $ledger_index;
    $this->thisLedgerIntervalSeconds = $thisLedgerIntervalSeconds;
    $this->thisLedgerIntervalPrevCurr = $thisLedgerIntervalPrevCurr;

  }

  public function addTx(\stdClass $tx): void
  {
    $this->txs_count++;
    $this->fee_total_drops += (int)$tx->Fee; //Drops

    # Failed txs
    if($tx->metaData->TransactionResult != 'tesSUCCESS') {
      $this->txs_failed_count++;
    }

    # Signed using regular key
    if(isset($tx->SigningPubKey) && \is_string($tx->SigningPubKey) && $tx->SigningPubKey !== '') {
      //echo $tx->hash.PHP_EOL;
      if(XRPLPHPUtilities::deriveAddress($tx->SigningPubKey) != $tx->Account) {
        //echo XRPLPHPUtilities::deriveAddress($tx->SigningPubKey) .' != '.$tx->Account;
        $this->txs_rksigned_count++;
      }
    }

    # Multisigned txs
    if(isset($tx->Signers)) {
      $this->txs_multisigned_count++;
    }

    # Txs with memos
    if(isset($tx->Memos)) {
      $this->txs_withmemo_count++;

      //Count memo bytes
      foreach($tx->Memos as $memo) {
        if(isset($memo->Memo->MemoData)) $this->memo_bytes += \strlen($memo->Memo->MemoData);
        if(isset($memo->Memo->MemoFormat)) $this->memo_bytes += \strlen($memo->Memo->MemoFormat);
        if(isset($memo->Memo->MemoType)) $this->memo_bytes += \strlen($memo->Memo->MemoType);
      }
    }

    # Transaction Types
    if(!isset($this->transaction_types[$tx->TransactionType])) $this->transaction_types[$tx->TransactionType] = 0;
    $this->transaction_types[$tx->TransactionType]++;

    # Transaction Results

    
    if(!isset($this->transaction_results[$tx->metaData->TransactionResult])) $this->transaction_results[$tx->metaData->TransactionResult] = 0;
    $this->transaction_results[$tx->metaData->TransactionResult]++;

    //dd($tx);
  }

  /**
   * Run queries to store changes to db
   */
  public function execute(): void
  {
    //1. If this is new ledger close previous
    $prevYMD = ripple_epoch_to_carbon($this->thisLedgerIntervalPrevCurr[0])->format('Y-m-d');
    $currYMD = ripple_epoch_to_carbon($this->thisLedgerIntervalPrevCurr[1])->format('Y-m-d');
    if($prevYMD != $currYMD) {
      $this->execute_closeDay($prevYMD);
    }

    $this->execute_openDayIfNotOpen($currYMD);

    //not here 2. Get latest total data
    //$totalsData = Aggrtotal::select('id','min_ledger_interval_ms','max_ledger_interval_ms')->where('day',$currYMD)->first();

    

    //2. Transaction Types
    foreach($this->transaction_types as $txType => $txTypeCount) {
      $this->execute_createAggrtxtypeIfDoesNotExist($currYMD, $txType);
      Aggrtxtype::where('day',$currYMD)->where('txtype',$txType)->increment('total',$txTypeCount);
    }
    unset($txType);unset($txTypeCount);

    //3. Transaction Results
    foreach($this->transaction_results as $txResult => $txResultCount) {
      $this->execute_createAggrtxresultIfDoesNotExist($currYMD, $txResult);
      Aggrtxresult::where('day',$currYMD)->where('txresult',$txResult)->increment('total',$txResultCount);
    }
    unset($txResult);unset($txResultCount);

    //4. Add interval (current, min, max)
    $this->execute_insertLedgerIntervalSeconds($currYMD);
    $totalInfo = $this->getTotalIntervalInfo($currYMD);

    if($this->thisLedgerIntervalSeconds > $totalInfo['max_ledger_interval_s']) {
      //needs to update max value
      $this->max_ledger_interval_s = $this->thisLedgerIntervalSeconds;
    }
    if($totalInfo['min_ledger_interval_s'] == 0) { //first time, fill it
      $this->min_ledger_interval_s = $this->thisLedgerIntervalSeconds;
    } elseif($this->thisLedgerIntervalSeconds < $totalInfo['min_ledger_interval_s']) {
      $this->min_ledger_interval_s = $this->thisLedgerIntervalSeconds;
    }
    //dd($this);
    unset($totalInfo);


    //5. Store totals & intervals (if change needed)
    $extra = [];
    if($this->max_ledger_interval_s !== null) $extra['max_ledger_interval_s'] = $this->max_ledger_interval_s;
    if($this->min_ledger_interval_s !== null) $extra['min_ledger_interval_s'] = $this->min_ledger_interval_s;
    Aggrtotal::where('day',$currYMD)->incrementEach([
      'txs_count' => $this->txs_count,
      'fee_total_drops' => $this->fee_total_drops,
      'txs_failed_count' => $this->txs_failed_count,
      'txs_rksigned_count' => $this->txs_rksigned_count,
      'txs_multisigned_count' => $this->txs_multisigned_count,
      'txs_withmemo_count' => $this->txs_withmemo_count,
      'memo_bytes' => $this->memo_bytes,
      'ledger_count' => 1,
    ],$extra);
    unset($extra);

    //dd($this);
  }

  /**
   * @return array [min_ledger_interval_s,max_ledger_interval_s]
   */
  private function getTotalIntervalInfo(string $YMD): array
  {
    $model = Aggrtotal::select('min_ledger_interval_s','max_ledger_interval_s')->where('day',$YMD)->first();
    if($model == null) {
      $this->execute_openDayIfNotOpen($YMD);
      return [
        'min_ledger_interval_s' => 0,
        'max_ledger_interval_s' => 0
      ];
    }
    return $model->toArray();
  }

  private function execute_insertLedgerIntervalSeconds(string $YMD)
  {
    $model = new Aggrtempledgerinterval;
    $model->day = $YMD;
    $model->ledger_index = $this->ledger_index;
    $model->val = $this->thisLedgerIntervalSeconds;
    $model->save();
  }

  /**
   * Todo cache check
   */
  private function execute_createAggrtxtypeIfDoesNotExist(string $YMD, string $txType): void
  {
    $check = DB::table('aggrtxtypes')->where('day',$YMD)->where('txtype',$txType)->count();
    if(!$check) {
      $model = new Aggrtxtype;
      $model->day = $YMD;
      $model->txtype = $txType;
      $model->total = 0;
      $model->save();
    }
  }

  private function execute_createAggrtxresultIfDoesNotExist(string $YMD, string $txResult): void
  {
    $check = DB::table('aggrtxresults')->where('day',$YMD)->where('txresult',$txResult)->count();
    if(!$check) {
      $model = new Aggrtxresult;
      $model->day = $YMD;
      $model->txresult = $txResult;
      $model->total = 0;
      $model->save();
    }
  }

  /**
   * Todo cache check
   */
  private function execute_openDayIfNotOpen(string $YMD)
  {
    $check = DB::table('aggrtotals')->where('day',$YMD)->count();
    if(!$check) {
      $model = new Aggrtotal;
      $model->day = $YMD;
      $model->save();
    }
  }

  private function execute_closeDay(string $YMD)
  {
    //close day $YMD
  }
}