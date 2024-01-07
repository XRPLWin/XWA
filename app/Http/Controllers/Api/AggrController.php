<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
#use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\RecentAggr;

class AggrController extends Controller
{
  /**
   * Recent aggregation data
   * Table: arecent_aggrs
   * Cache: 5 min
   */
  public function recent()
  {
    $ttl = 300;
    $httpttl = 180;

    $now = now();
    $r = [
      'TxTypeCounts' => [],
      'TxSuccessCount' => 0,
      'TxFailedCount' => 0,
      'AccountsCreated' => 0,
      'AccountsDeleted' => 0,
      'TopPayments' => [],
      'FeeSum' => 0,
      'TopFeeTx' => ['hash' => null, 'fee' => 0],
      'TrustlineAddCounts' => [],
      'NFTMintedCount' => 0,
      'NFTMintedByAccountCounts' => [],
      'NFTSalesBySeller' => [],
      'NFTSalesByBroker' => [],
      'TopNFTSales' => [],
      //Xahau specific:
      'XahGenesisMintedSum' => 0,
      'XahGenesisMintedAccountsSum' => [],
      //Hook specific:
      //TODO query from hook_transactions table
    ];

    $aggrs = RecentAggr::select('subject','identifier','value_uint64','context')
      ->whereDate('day',$now)
      ->orderBy('value_uint64','desc')
      ->get();

    # TxTypeCounts
    foreach($aggrs->where('subject','TxCount') as $v) {
      $r['TxTypeCounts'][$v->identifier] = (int)$v->value_uint64;
    }

    # TxFailedCount and TxSuccessCount
    foreach($aggrs->where('subject','TxResult') as $v) {
      if($v->identifier == 'FAILED') $r['TxFailedCount'] = (int)$v->value_uint64;
      if($v->identifier == 'SUCCESS') $r['TxSuccessCount'] = (int)$v->value_uint64;
    }

    # AccountsCreated
    if($AccCreates = $aggrs->where('subject','AccCreates')->first()) {
      $r['AccountsCreated'] = (int)$AccCreates->value_uint64;
      unset($AccCreates);
    }

    # AccountsDeleted
    if($AccDeletes = $aggrs->where('subject','AccDeletes')->first()) {
      $r['AccountsDeleted'] = (int)$AccDeletes->value_uint64;
      unset($AccDeletes);
    }

    # TopPayments
    foreach($aggrs->where('subject','TopPayment') as $v) {
      $r['TopPayments'][] = ['account' => $v->identifier, 'hash' => $v->context, 'value' => $v->value_uint64];
    }

    # FeeSum
    if($FeeSum = $aggrs->where('subject','FeeSum')->first()) {
      $r['FeeSum'] = $FeeSum->value_uint64;
      unset($FeeSum);
    }

    # TopFeeTx
    if($TopFee = $aggrs->where('subject','TopFee')->first()) {
      $r['TopFeeTx'] = ['hash' => $TopFee->identifier, 'fee' => $TopFee->value_uint64];
      unset($TopFee);
    }

    # TrustlineAddCounts (stop after 50)
    $i = 0;
    foreach($aggrs->where('subject','TLAdds') as $v) {
      $i++;
      if($i > 50) break;
      $ex = \explode(':',$v->identifier);
      $r['TrustlineAddCounts'][] = ['issuer' => $ex[0], 'currency' => $ex[1], 'count' => (int)$v->value_uint64];
    }

    # NFTMintedCount
    if($NFTMints = $aggrs->where('subject','NFTMints')->first()) {
      $r['NFTMintedCount'] = (int)$NFTMints->value_uint64;
      unset($NFTMints);
    }

    # NFTMintedByAccountCounts
    foreach($aggrs->where('subject','NFTMintsBy') as $v) {
      $r['NFTMintedByAccountCounts'][] = ['account' => $v->identifier, 'count' => (int)$v->value_uint64];
    }

    # NFTSalesBySeller
    foreach($aggrs->where('subject','NFTSellerC') as $v) {
      $r['NFTSalesBySeller'][] = ['account' => $v->identifier, 'count' => (int)$v->value_uint64];
    }

    # NFTSalesByBroker
    foreach($aggrs->where('subject','NFTBrokerC') as $v) {
      $r['NFTSalesByBroker'][] = ['account' => $v->identifier, 'count' => (int)$v->value_uint64];
    }

    # TopNFTSales
    foreach($aggrs->where('subject','TopNFTSale') as $v) {
      $r['TopNFTSales'][] = ['nft' => $v->identifier, 'hash' => $v->context, 'value' => $v->value_uint64];
    }

    # XahGenesisMintedSum
    if($GenMinted = $aggrs->where('subject','GenMinted')->first()) {
      $r['XahGenesisMintedSum'] = $GenMinted->value_uint64;
      unset($GenMinted);
    }

    # XahGenesisMintedAccountsSum
    foreach($aggrs->where('subject','GenMintedA') as $v) {
      $r['XahGenesisMintedAccountsSum'][] = ['account' => $v->identifier, 'value' => $v->value_uint64];
    }

    return response()->json([
      'date' => $now->format('Y-m-d'),
      'updated' => $now,
      'data' => $r
    ])
      ->header('Cache-Control','public, s-max-age='.$ttl.', max_age='.$httpttl)
      ->header('Expires', gmdate('D, d M Y H:i:s \G\M\T', time() + $httpttl))
    ;
  }
}