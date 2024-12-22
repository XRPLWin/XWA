<?php declare(strict_types=1);

namespace App\XRPLParsers\Types;

use App\XRPLParsers\XRPLParserBase;
use XRPLWin\XRPLTxParticipantExtractor\TxParticipantExtractor;
use Brick\Math\BigDecimal;

final class AMMDeposit extends XRPLParserBase
{
  private array $acceptedParsedTypes = ['REGULARKEYSIGNER','SENT','SET','UNKNOWN'];
  /**
   * Parses AMMDeposit type fields and maps them to $this->data
   * @see https://xrpl.org/transaction-types.html
   * @see 77DBA705D3350C1DB51B68A5B2EF4662C142B58F44A3A76345DEAC01EE320918 LPTokenOut only
   * @see BD03987D0440A0C651C37A039426BCBBC37AC5B15DA85DF0B35716F76CA54FE0
   * @return void
   */
  protected function parseTypeFields(): void
  {
    $parsedType = $this->data['txcontext'];
    if(!in_array($parsedType, $this->acceptedParsedTypes))
      throw new \Exception('Unhandled parsedType ['.$parsedType.'] on AMMDeposit with HASH ['.$this->data['hash'].'] and perspective ['.$this->reference_address.']');

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
      throw new \Exception('Unable to find AMM_ACCOUNT in AMMDeposit with HASH ['.$this->data['hash'].'] and perspective ['.$this->reference_address.']');
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

  
    if((!isset($this->data['eventList']['primary']) || !isset($this->data['eventList']['secondary'])) && $this->persist) {
      throw new \Exception('Expecting primary and secondary events on AMMDeposit with HASH ['.$this->data['hash'].'] and perspective ['.$this->reference_address.']');
    }

    # Source and destination tags
    $this->data['DestinationTag'] = isset($this->tx->DestinationTag) ? $this->tx->DestinationTag:null;
    $this->data['SourceTag'] = isset($this->tx->SourceTag) ? $this->tx->SourceTag:null;

    if(!$this->persist) {
      return;
    }

    $BC = $this->data['balanceChangesExclFee'];

    $Amount2Identification = $Amount2Identification_AMM = null;

    if(!isset($this->tx->Amount) && !isset($this->tx->Amount2)) { //in case of LPTokenOut only
      $Amount1Identification      = \is_string($this->tx->Asset) ? 'XRP':$this->tx->Asset->currency.'.'.$this->tx->Asset->issuer;
      $Amount1Identification_AMM  = \is_string($this->tx->Asset) ? 'XRP':$this->tx->Asset->currency.'.'.$AMM_ACCOUNT;
      $Amount2Identification      = \is_string($this->tx->Asset2) ? 'XRP':$this->tx->Asset2->currency.'.'.$this->tx->Asset2->issuer;
      $Amount2Identification_AMM  = \is_string($this->tx->Asset2) ? 'XRP':$this->tx->Asset2->currency.'.'.$AMM_ACCOUNT;
    } else {
      if(isset($this->tx->Amount)) {
        $Amount1Identification      = \is_string($this->tx->Amount) ? 'XRP':$this->tx->Amount->currency.'.'.$this->tx->Amount->issuer;
        $Amount1Identification_AMM  = \is_string($this->tx->Amount) ? 'XRP':$this->tx->Amount->currency.'.'.$AMM_ACCOUNT;
      }
        

      if(isset($this->tx->Amount2)) {
        $Amount2Identification      = \is_string($this->tx->Amount2) ? 'XRP':$this->tx->Amount2->currency.'.'.$this->tx->Amount2->issuer;
        $Amount2Identification_AMM  = \is_string($this->tx->Amount2) ? 'XRP':$this->tx->Amount2->currency.'.'.$AMM_ACCOUNT;
      }
        
    }

    //A0BF3E5999FC90A1BBC118EEC895FE5DF1E65942168E9288CE2F1E846CC3A8EA this is tx where issuer creates own pool, then BC identification will have different issuer (cause IOU to Pool)
    
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
            throw new \Exception('Duplicate amount1 detected in AMMDeposit with HASH ['.$this->data['hash'].'] and perspective ['.$this->reference_address.']');
          $amount1 = $_bc;
        } else if($Amount2Identification !== null && $Amount2Identification == $_bc_Identification || $Amount2Identification_AMM !== null && $Amount2Identification_AMM == $_bc_Identification) {
          //It is amount1
          if($amount2 !== null) 
            throw new \Exception('Duplicate amount2 detected in AMMDeposit with HASH ['.$this->data['hash'].'] and perspective ['.$this->reference_address.']');
          $amount2 = $_bc;
        } else {
          //It is LT
          if($amountLT !== null)
            throw new \Exception('Duplicate LT detected in AMMDeposit with HASH ['.$this->data['hash'].'] and perspective ['.$this->reference_address.']');
            

          //check if its really LT
          if(!\str_starts_with($_bc['currency'],'03'))
            throw new \Exception('Non LT detected in AMMDeposit with HASH ['.$this->data['hash'].'] and perspective ['.$this->reference_address.']');

          $amountLT = $_bc;
        }
      }

      if($amountLT !== false && $amountLT !== null) {
        //see BD03987D0440A0C651C37A039426BCBBC37AC5B15DA85DF0B35716F76CA54FE0
        //Fill $amount1 and $amount2 from transaction
        if(!\is_array($amount1)) {
          if(\is_string($this->tx->Amount)) {
            $Amount_BN = BigDecimal::of($this->tx->Amount)->exactlyDividedBy(1000000);
            $amount1 = [
              'value' => (string)$Amount_BN,
              'currency' => 'XRP',
            ];
            unset($Amount_BN);
          } else {
            $amount1 = [
              'value' => $this->tx->Amount->value,
              'currency' => $this->tx->Amount->currency,
              'counterparty' => $this->tx->Amount->issuer,
            ];
          }
        }

        if(isset($this->tx->Amount2) && !\is_array($amount2)) {
          if(\is_string($this->tx->Amount2)) {
            $Amount2_BN = BigDecimal::of($this->tx->Amount2)->exactlyDividedBy(1000000);
            $amount2 = [
              'value' => (string)$Amount2_BN,
              'currency' => 'XRP',
            ];
            unset($Amount2_BN);
          } else {
            $amount2 = [
              'value' => $this->tx->Amount2->value,
              'currency' => $this->tx->Amount2->currency,
              'counterparty' => $this->tx->Amount2->issuer,
            ];
          }
        }

      }


      if($amount1 === false || $amount2 === false || $amountLT == false) {
        throw new \Exception('Expecting all 3 currencies for AMM account in AMMDeposit with HASH ['.$this->data['hash'].'] and perspective ['.$this->reference_address.']');
      }

      if($amount1 === null || $amountLT === null) {
        throw new \Exception('Expecting amount1 and amountlt currencies non null for AMM account in AMMDeposit with HASH ['.$this->data['hash'].'] and perspective ['.$this->reference_address.']');
      }

      
      //Set Amount 1
      $this->data['Amount'] = $amount1['value'];
      if($amount1['currency'] !== 'XRP') {
        $this->data['Issuer'] = $amount1['counterparty'];
        $this->data['Currency'] = $amount1['currency'];
      }
      
      //Set Amount 2 (if sent)
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