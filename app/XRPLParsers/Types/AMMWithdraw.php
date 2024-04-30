<?php declare(strict_types=1);

namespace App\XRPLParsers\Types;

use App\XRPLParsers\XRPLParserBase;
use XRPLWin\XRPLTxParticipantExtractor\TxParticipantExtractor;

final class AMMWithdraw extends XRPLParserBase
{
  private array $acceptedParsedTypes = ['REGULARKEYSIGNER','SENT','SET','UNKNOWN'];
  /**
   * Parses AMMWithdraw type fields and maps them to $this->data
   * @see https://xrpl.org/transaction-types.html
   * @see 35F5EE63A28B4FAC5C106B147D8A89719ED7C1B3C4528BFEA3A42C0CC03361DE - 0 withdraw - unable to find amm account
   * @return void
   */
  protected function parseTypeFields(): void
  {
    $parsedType = $this->data['txcontext'];
    if(!in_array($parsedType, $this->acceptedParsedTypes))
      throw new \Exception('Unhandled parsedType ['.$parsedType.'] on AMMWithdraw with HASH ['.$this->data['hash'].'] and perspective ['.$this->reference_address.']');

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
      throw new \Exception('Unable to find AMM_ACCOUNT in AMMWithdraw with HASH ['.$this->data['hash'].'] and perspective ['.$this->reference_address.']');
    }
    # Counterparty
    // If initiator, counterparty is generated amm account
    // If AMM account, counterpary is initiator
    // Everything else discarded
    if($this->reference_address == $this->tx->Account) {
      //Counterparty is AMM Account
      $this->data['Counterparty'] = $AMM_ACCOUNT;
      $this->data['In'] = true;
    } else if ($this->reference_address == $AMM_ACCOUNT) {
      $this->data['Counterparty'] = $this->tx->Account;
      $this->data['In'] = false;
    } else {
      $this->data['In'] = false;
      $this->persist = false;
    }

    if((!isset($this->data['eventList']['primary']) && !isset($this->data['eventList']['secondary'])) && $this->persist) {
      //see 5081780FC987CDC875B01C9B16AA71FF4CD585B11E0FF03F6554C6C5046B4E17 (0xrp withdraw)
      // - finally nothing witdrawed and LP token returned
      throw new \Exception('Expecting at least primary and/or secondary events on AMMWithdraw with HASH ['.$this->data['hash'].'] and perspective ['.$this->reference_address.']');
    }

    # Source and destination tags
    $this->data['DestinationTag'] = isset($this->tx->DestinationTag) ? $this->tx->DestinationTag:null;
    $this->data['SourceTag'] = isset($this->tx->SourceTag) ? $this->tx->SourceTag:null;

    if(!$this->persist) {
      return;
    }

    $BC = $this->data['balanceChangesExclFee'];
    $amount1 = null;  //required
    $amount2 = null;  //optional (optional in single asset witdraw)
    $amountLT = null; //required
    if($this->reference_address == $this->tx->Account || $this->reference_address == $AMM_ACCOUNT) {
      foreach($BC as $_bc) {
        if(\str_starts_with($_bc['currency'],'03')) {
          //LT
          $amountLT = $_bc;
        } else {
          //Its Amount 1 or Amount 2
          if($amount1 === null) {
            $amount1 = $_bc;
          } else {
            $amount2 = $_bc;
          }
        }
      }
      //echo '<hr>';
      //dd($BC,$amount1,$amount2,$amountLT);

      if($amount1 === false || $amountLT == false) {
        throw new \Exception('Expecting up to two amounts and LT for AMM account in AMMWithdraw with HASH ['.$this->data['hash'].'] and perspective ['.$this->reference_address.']');
      }

      //Set Amount 1
      if($amount1 !== null) {
        $this->data['Amount'] = $amount1['value'];
        if($amount1['currency'] !== 'XRP') {
          $this->data['Issuer'] = $amount1['counterparty'];
          $this->data['Currency'] = $amount1['currency'];
        }
      }
      

      //Set Amount 2
      if($amount2 !== null) {
        $this->data['Amount2'] = $amount2['value'];
        if($amount2['currency'] !== 'XRP') {
          $this->data['Issuer2'] = $amount2['counterparty'];
          $this->data['Currency2'] = $amount2['currency'];
        }
      }
      
      //Set Amount 3
      $this->data['Amount3'] = $amountLT['value'];
      //$this->data['Issuer3'] = $amountLT['counterparty'];
      $this->data['Issuer3'] = $AMM_ACCOUNT;//LP token issuer is always AMM account
      $this->data['Currency3'] = $amountLT['currency'];
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