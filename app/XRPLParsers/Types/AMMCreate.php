<?php declare(strict_types=1);

namespace App\XRPLParsers\Types;

use App\XRPLParsers\XRPLParserBase;
use XRPLWin\XRPLTxParticipantExtractor\TxParticipantExtractor;

final class AMMCreate extends XRPLParserBase
{
  private array $acceptedParsedTypes = ['REGULARKEYSIGNER','SENT','SET','UNKNOWN'];
  /**
   * Parses AMMCreate type fields and maps them to $this->data
   * @see https://xrpl.org/transaction-types.html
   * @see A7B7F529AF0CF2483515B966B52D6F73F446C7C0D1A7B898EC77F1011FEA89D8 with raN9y9TaBT3GAbSM2nV22RXKN9enbssssB - this should be handled for other participants
   * @return void
   */
  protected function parseTypeFields(): void
  {
    $parsedType = $this->data['txcontext'];
    if(!in_array($parsedType, $this->acceptedParsedTypes))
      throw new \Exception('Unhandled parsedType ['.$parsedType.'] on AMMCreate with HASH ['.$this->data['hash'].'] and perspective ['.$this->reference_address.']');

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
      throw new \Exception('Unable to find AMM_ACCOUNT in AMMCreate with HASH ['.$this->data['hash'].'] and perspective ['.$this->reference_address.']');
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

      //if(count($this->data['balanceChangesExclFee']))
      //  $this->persist = true;
      
    }

  
    if((!isset($this->data['eventList']['primary']) || !isset($this->data['eventList']['secondary'])) && $this->persist) {
      throw new \Exception('Expecting primary and secondary events on AMMCreate with HASH ['.$this->data['hash'].'] and perspective ['.$this->reference_address.']');
    }

    # Source and destination tags
    $this->data['DestinationTag'] = isset($this->tx->DestinationTag) ? $this->tx->DestinationTag:null;
    $this->data['SourceTag'] = isset($this->tx->SourceTag) ? $this->tx->SourceTag:null;

    if(!$this->persist) {
      return;
    }

    $BC = $this->data['balanceChangesExclFee'];

    $Amount1Identification = \is_string($this->tx->Amount) ? 'XRP':$this->tx->Amount->currency.'.'.$this->tx->Amount->issuer;
    $Amount2Identification = \is_string($this->tx->Amount2) ? 'XRP':$this->tx->Amount2->currency.'.'.$this->tx->Amount2->issuer;

    //C17FFD27444BF5FF981539723989880C4E5B045B21EDD957DE1DC7C4A9D10144 this is tx where issuer creates own pool, then BC identification will have different issuer (cause IOU to Pool)
    $Amount1Identification_AMM = \is_string($this->tx->Amount) ? 'XRP':$this->tx->Amount->currency.'.'.$AMM_ACCOUNT;
    $Amount2Identification_AMM = \is_string($this->tx->Amount2) ? 'XRP':$this->tx->Amount2->currency.'.'.$AMM_ACCOUNT;
    
    $amount1 = null;
    $amount2 = null;
    $amountLT = null;
    //3 balance changes for AMM account or sender
    if($this->reference_address == $this->tx->Account || $this->reference_address == $AMM_ACCOUNT) {
      //Amm creator (sender)
      foreach($BC as $_bc) {
        $_bc_Identification = $_bc['currency'];
        if(count($_bc) == 3)
          $_bc_Identification .= '.'.$_bc['counterparty'];
        //Check if is amount1
        if($Amount1Identification == $_bc_Identification || $Amount1Identification_AMM == $_bc_Identification) {
          //It is amount1
          if($amount1 !== null) 
            throw new \Exception('Duplicate amount1 detected in AMMCreate with HASH ['.$this->data['hash'].'] and perspective ['.$this->reference_address.']');
          $amount1 = $_bc;
        } else if($Amount2Identification == $_bc_Identification || $Amount2Identification_AMM == $_bc_Identification) {

          //It is amount2
          if($amount2 !== null) 
            throw new \Exception('Duplicate amount2 detected in AMMCreate with HASH ['.$this->data['hash'].'] and perspective ['.$this->reference_address.']');
          $amount2 = $_bc;
        } else {
          //It is LT
          if($amountLT !== null)
            throw new \Exception('Duplicate LT detected in AMMCreate with HASH ['.$this->data['hash'].'] and perspective ['.$this->reference_address.']');

          //check if its really LT
          if(!\str_starts_with($_bc['currency'],'03'))
            throw new \Exception('Non LT detected in AMMDeposit with HASH ['.$this->data['hash'].'] and perspective ['.$this->reference_address.']');
          
          $amountLT = $_bc;
        }
      }

      if($amount1 === false || $amount2 === false || $amountLT == false) {
        throw new \Exception('Expecting all 3 currencies for AMM account in AMMCreate with HASH ['.$this->data['hash'].'] and perspective ['.$this->reference_address.']');
      }

      //Set Amount 1
      if($amount1 !== null) { //can be null - see: A7B7F529AF0CF2483515B966B52D6F73F446C7C0D1A7B898EC77F1011FEA89D8
        $this->data['Amount'] = $amount1['value'];
        if($amount1['currency'] !== 'XRP') {
          $this->data['Issuer'] = $amount1['counterparty'];
          $this->data['Currency'] = $amount1['currency'];
        }
      }

      //Set Amount 2
      $this->data['Amount2'] = $amount2['value'];
      if($amount2['currency'] !== 'XRP') {
        $this->data['Issuer2'] = $amount2['counterparty'];
        $this->data['Currency2'] = $amount2['currency'];
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