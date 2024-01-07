<?php

namespace App\Utilities\Nft;

use Brick\Math\BigDecimal;
use XRPLWin\XRPLNFTTxMutatationParser\NFTTxMutationParser;
use XRPLWin\XRPLTxParticipantExtractor\TxParticipantExtractor;
use XRPLWin\XRPLTxMutatationParser\TxMutationParser;


/**
 * Takes nft transaction of type: NFTokenAcceptOffer or URITokenBuy
 * and return relevant information of nft exchange, seller buyer broker, values etc.
 * Used for Aggregator and NFT Lane.
 */
class NftSaleTx
{
  private readonly \stdClass $tx;

  //NFToken or URIToken in question
  private string $nft;

  private ?string $context;

  //participants
  private ?string $sellerAccount = null;
  private ?string $buyerAccount = null;
  private ?string $brokerAccount = null;
  private ?string $issuerAccount = null;

  //balance change amounts for participants
  private ?string $sellerAmount = null;
  private ?string $buyerAmount = null;
  private ?string $brokerAmount = null;
  private ?string $issuerAmount = null;

  //nft ownership change
  //private string $oldOwner;
  //private string $newOwner;

  public function __construct(\stdClass $transaction, \stdClass $meta)
  {
    if(!($transaction->TransactionType == 'NFTokenAcceptOffer' || $transaction->TransactionType == 'URITokenBuy'))
      throw new \Exception('Invalid transaction type for NftSaleTx');

    if($meta->TransactionResult !== 'tesSUCCESS')
      throw new \Exception('Invalid transaction result for NftSaleTx only accepted tesSUCCESS');

    $this->tx = \unserialize(\serialize($transaction)); //clone it

    if(isset($this->tx->Account) && $this->tx->Account == '') {
      $this->tx->Account = 'rrrrrrrrrrrrrrrrrrrrrhoLvTp'; //in case of UNLReport
    }

    $TxParticipantExtractor = new TxParticipantExtractor(\unserialize(\serialize($this->tx)));
    $participating_accounts = $TxParticipantExtractor->result();
    //$participating_accounts_roles = $TxParticipantExtractor->accounts();




    $nftcurrentowner = null;
    $parsed_inout = ['IN' => null, 'OUT' => null];
    $parsed_unknowns = $parsed_all = [];
    
    foreach($participating_accounts as $pacc) {
      $NFTTxMutationParser = new NFTTxMutationParser($pacc, $transaction);
      $_r = $NFTTxMutationParser->result();

      $this->context = $_r['context'];
      $this->nft = $_r['nft'];

      if(\in_array('SELLER',$_r['ref']['roles']))
        $this->sellerAccount = $_r['ref']['account'];
      if(\in_array('BUYER',$_r['ref']['roles']))
        $this->buyerAccount = $_r['ref']['account'];
      if(\in_array('BROKER',$_r['ref']['roles']))
        $this->brokerAccount = $_r['ref']['account'];
      if(\in_array('ISSUER',$_r['ref']['roles']))
        $this->issuerAccount = $_r['ref']['account'];

      unset($_r);
      unset($NFTTxMutationParser);
    }

    //Amounts
    if($this->sellerAccount !== null) {
      $parser = new TxMutationParser($this->sellerAccount, $transaction);
      $res = $parser->result();
      foreach($res['self']['balanceChangesExclFee'] as $bc) {
        if($bc['currency'] == 'XRP' && count($bc) == 2) {
          //we only record native currency
          $this->sellerAmount = $bc['value'];
        }
      }
      if($this->sellerAmount === null)
        $this->sellerAmount = '0';
    }

    if($this->buyerAccount !== null) {
      $parser = new TxMutationParser($this->buyerAccount, $transaction);
      $res = $parser->result();
      foreach($res['self']['balanceChangesExclFee'] as $bc) {
        if($bc['currency'] == 'XRP' && count($bc) == 2) {
          //we only record native currency
          $this->buyerAmount = $bc['value'];
        }
      }
      if($this->buyerAmount === null)
        $this->buyerAmount = '0';
    }

    if($this->brokerAccount !== null) {
      $parser = new TxMutationParser($this->brokerAccount, $transaction);
      $res = $parser->result();
      foreach($res['self']['balanceChangesExclFee'] as $bc) {
        if($bc['currency'] == 'XRP' && count($bc) == 2) {
          //we only record native currency
          $this->brokerAmount = $bc['value'];
        }
      }
      if($this->brokerAmount === null)
        $this->brokerAmount = '0';
    }

    if($this->issuerAccount !== null) {
      $parser = new TxMutationParser($this->issuerAccount, $transaction);
      $res = $parser->result();
      foreach($res['self']['balanceChangesExclFee'] as $bc) {
        if($bc['currency'] == 'XRP' && count($bc) == 2) {
          //we only record native currency
          $this->issuerAmount = $bc['value'];
        }
      }
      if($this->issuerAmount === null)
        $this->issuerAmount = '0';
    }
  }

  private function amountToDrops(string $amount): string
  {
    $BN = BigDecimal::of($amount)->multipliedBy(1000000);
    return (string)$BN;
  }

  public function getNft(): string
  {
    return $this->nft;
  }

  public function isBrokered(): bool
  {
    return $this->context == 'BROKERED';
  }

  public function getSeller(): ?array
  {
    if($this->sellerAccount === null)
      return null;
    return [$this->sellerAccount,$this->amountToDrops($this->sellerAmount)];
  }

  public function getBuyer(): ?array
  {
    if($this->buyerAccount === null)
      return null;
    return [$this->buyerAccount,$this->amountToDrops($this->buyerAmount)];
  }

  public function getBroker(): ?array
  {
    if($this->brokerAccount === null)
      return null;
    return [$this->brokerAccount,$this->amountToDrops($this->brokerAmount)];
  }

  public function getIssuer(): ?array
  {
    if($this->issuerAccount === null)
      return null;
    return [$this->issuerAccount,$this->amountToDrops($this->issuerAmount)];
  }
}