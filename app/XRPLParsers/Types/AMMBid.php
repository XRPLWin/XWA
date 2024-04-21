<?php declare(strict_types=1);

namespace App\XRPLParsers\Types;

use App\XRPLParsers\XRPLParserBase;
use XRPLWin\XRPLTxParticipantExtractor\TxParticipantExtractor;
use Brick\Math\BigDecimal;

final class AMMBid extends XRPLParserBase
{
  private array $acceptedParsedTypes = ['REGULARKEYSIGNER','SENT','SET','UNKNOWN'];
  /**
   * Parses AMMBid type fields and maps them to $this->data
   * @see https://xrpl.org/transaction-types.html
   * @see A3A278DEEF3C5E06126526F948F31436BDDD96E61779E2E7A55090270017BD89 with r3sS4di5fzXEzq2XgeVNDV3Ys4wzwDVXpU - BidMax without primary event
   *  - this is probably case when biding for reduced fee but fee is not set
   * @return void
   */
  protected function parseTypeFields(): void
  {
    $parsedType = $this->data['txcontext'];
    if(!in_array($parsedType, $this->acceptedParsedTypes))
      throw new \Exception('Unhandled parsedType ['.$parsedType.'] on AMMBid with HASH ['.$this->data['hash'].'] and perspective ['.$this->reference_address.']');

    //Default:
    $this->data['Counterparty'] = $this->tx->Account;
    
    $participantsParser = new TxParticipantExtractor($this->tx);
    $participants = $participantsParser->accounts();
    $AMM_ACCOUNT = false;
    //Find AMM_ACCOUNT:
    foreach($participants as $p_acc => $p_roles) {
      if(\in_array('AMM_ACCOUNT',$p_roles)) {
        $AMM_ACCOUNT = $p_acc;
        break;
      }
    }
    if($AMM_ACCOUNT === false) {
      throw new \Exception('Unable to find AMM_ACCOUNT in AMMBid with HASH ['.$this->data['hash'].'] and perspective ['.$this->reference_address.']');
    }
    # Counterparty
    // If initiator, counterparty is generated amm account
    // If AMM account, counterpary is initiator
    // Everything else discarded
    if($this->reference_address == $this->tx->Account) {
      //Counterparty is AMM Account
      $this->data['Counterparty'] = $AMM_ACCOUNT;
      $this->data['In'] = false;
    } else if ($this->reference_address == $AMM_ACCOUNT) {
      $this->data['Counterparty'] = $this->tx->Account;
      $this->data['In'] = true;
    } else {
      $this->data['In'] = false;
      $this->persist = false;
    }

    # Source and destination tags
    $this->data['DestinationTag'] = isset($this->tx->DestinationTag) ? $this->tx->DestinationTag:null;
    $this->data['SourceTag'] = isset($this->tx->SourceTag) ? $this->tx->SourceTag:null;


    if(!isset($this->data['eventList']['primary']) && $this->persist) {
      
      if(count($this->data['balanceChangesExclFee']) == 0) {
        //in this case there was no fee and this transaction has no effect (except paying for fee)
        if($this->parsedData['self']['feePayer']) {
          $this->data['In'] = false;
          $this->persist = true;
        } else {
          //No balance changes whatsoever - do not persist
          $this->persist = false;
        }
        return;
      }
      throw new \Exception('Expecting primary event on AMMBid with HASH ['.$this->data['hash'].'] and perspective ['.$this->reference_address.']');
    }


    $BC = $this->data['balanceChangesExclFee'];
    $BC = \array_values($BC);

    if(count($BC) && !$this->persist) {
      //this is not initiator nor amm account this is bid winner or accont who got LP tokens
      $this->data['In'] = true;
      $this->persist = true;
    }
    
    if(!$this->persist) {
      return;
    }

    $amountLT = null; //required
    if($this->reference_address != $AMM_ACCOUNT) {

      if(count($BC) != 1) {
        throw new \Exception('Expecting 1 balance change (excl fee) on AMMBid with HASH ['.$this->data['hash'].'] and perspective ['.$this->reference_address.']');
      }
      $amountLT = $BC[0];

      if(!\str_starts_with($amountLT['currency'],'03')) {
        throw new \Exception('Unexpected non-LP balance change for AMM account on AMMBid with HASH ['.$this->data['hash'].'] and perspective ['.$this->reference_address.']');
      }
      
      //Set Amount 3
      $this->data['Amount3'] = $amountLT['value'];
      //$this->data['Issuer3'] = $amountLT['counterparty'];
      $this->data['Issuer3'] = $AMM_ACCOUNT;//LP token issuer is always AMM account
      $this->data['Currency3'] = $amountLT['currency'];

    } elseif($this->reference_address == $AMM_ACCOUNT) { //60A2E1053DA19AE34BA0435962610D7672DE09A71A5BD5E169878063C923FB74

      //AMM acoount has IN LP tokens and some out LP tokens, we store diff to Amount3/Issuer3/Currency3
      $LPSUM = BigDecimal::of(0);
      $LPSUM_exists = false;
      foreach($BC as $b) {
        if(count($b) != 3) {
          throw new \Exception('Unexpected XRP balance change for AMM account on AMMBid with HASH ['.$this->data['hash'].'] and perspective ['.$this->reference_address.']');
        }
        if(!\str_starts_with($b['currency'],'03')) {
          throw new \Exception('Unexpected non-LP balance change for AMM account on AMMBid with HASH ['.$this->data['hash'].'] and perspective ['.$this->reference_address.']');
        }
        $LPSUM_exists = true;
        $LPSUM = $LPSUM->plus($b['value']);
        $this->data['Currency3'] = $b['currency'];
      }
      if(!$LPSUM_exists) {
        throw new \Exception('Unexisting LP balance change for AMM account on AMMBid with HASH ['.$this->data['hash'].'] and perspective ['.$this->reference_address.']');
      }
      //Set Amount 3
      $this->data['Amount3'] = (string)$LPSUM;
      $this->data['Issuer3'] = $AMM_ACCOUNT;//LP token issuer is always AMM account
    }
  }


  /**
   * Returns standardized array of relevant data for storing to Dynamo database.
   * key => value one dimensional array which correlates to column => value in DyDb.
   * @return array
   */
  public function toBArray(): array
  {
    $r = [
      't' => ripple_epoch_to_carbon((int)$this->data['Date'])->format('Y-m-d H:i:s.uP'),
      'l' => $this->data['LedgerIndex'],
      'li' => $this->data['TransactionIndex'],
      'isin' => $this->data['In'],
      'r' => (string)$this->data['Counterparty'],
      'h' => (string)$this->data['hash'],
      'ax' => [],
      'ix' => [],
      'cx' => [],
      'nfts' => [],
      'offers' => [],
      'nftoffers' => [],
      'hooks' => $this->data['hooks'],
    ];

    if(\array_key_exists('Amount', $this->data))
      $r['a'] = $this->data['Amount'];
    if(\array_key_exists('Amount2', $this->data))
      $r['a2'] = $this->data['Amount2'];
    if(\array_key_exists('Amount3', $this->data))
      $r['a3'] = $this->data['Amount3'];

    if(\array_key_exists('Issuer', $this->data))
      $r['i'] = $this->data['Issuer'];
    if(\array_key_exists('Issuer2', $this->data))
      $r['i2'] = $this->data['Issuer2'];
    if(\array_key_exists('Issuer3', $this->data))
      $r['i3'] = $this->data['Issuer3'];

    if(\array_key_exists('Currency', $this->data))
      $r['c'] = $this->data['Currency'];
    if(\array_key_exists('Currency2', $this->data))
      $r['c2'] = $this->data['Currency2'];
    if(\array_key_exists('Currency3', $this->data))
      $r['c3'] = $this->data['Currency3'];


    if(\array_key_exists('Fee', $this->data))
      $r['fee'] = $this->data['Fee'];

    /**
     * dt - destination tag, stored as string
     */
    if($this->data['DestinationTag'] !== null)
      $r['dt'] = (string)$this->data['DestinationTag'];
    
    /**
     * st - source tag, stored as string
     */
    if($this->data['SourceTag'] !== null)
      $r['st'] = (string)$this->data['SourceTag'];

    return $r;
  }



}