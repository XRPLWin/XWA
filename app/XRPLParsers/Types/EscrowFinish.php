<?php declare(strict_types=1);

namespace App\XRPLParsers\Types;

use App\XRPLParsers\XRPLParserBase;
use XRPLWin\XRPLTxParticipantExtractor\TxParticipantExtractor;

final class EscrowFinish extends XRPLParserBase
{
  private array $acceptedParsedTypes = ['SET','SENT','REGULARKEYSIGNER','UNKNOWN'];

  /**
   * Parses EscrowFinish type fields and maps them to $this->data
   * @see https://xrpl.org/transaction-types.html
   * @see 317081AF188CDD4DBE55C418F41A90EC3B959CDB3B76105E0CBE6B7A0F56C5F7 - rf1BiGeXwwQoi8Z2ueFYTEXSwuJYfV2Jpn - initiator, also unrelated to escrow but paid fee
   * @see 317081AF188CDD4DBE55C418F41A90EC3B959CDB3B76105E0CBE6B7A0F56C5F7 - rKDvgGUsNPZxsgmoemfrgXPS2Not4co2op - reciever (xrp destination)
   * @see A265173EA0C13AA4E61DFD28EBBAB61084611E04E14E93EAA09FAAE9A4A9C7F9 - XAHAU TESTNET - IOU (if XLS34 is enabled)
   * @return void
   */
  protected function parseTypeFields(): void
  {
    $parsedType = $this->data['txcontext'];
    if(!in_array($parsedType, $this->acceptedParsedTypes))
      throw new \Exception('Unhandled parsedType ['.$parsedType.'] on EscrowFinish with HASH ['.$this->data['hash'].'] and perspective ['.$this->reference_address.']');

    if($parsedType == 'REGULARKEYSIGNER') {
      $this->persist = false;
    }

    # EscrowFinish initiator (default value)
    $this->data['Counterparty'] = $this->tx->Account;
    $this->data['In'] = false;

    $participantsParser = new TxParticipantExtractor($this->tx);
    $participants = $participantsParser->accounts();

    if(isset($participants[$this->reference_address])) {
      if(\in_array('ESCROW_DESTINATION',$participants[$this->reference_address])) {
        $this->data['In'] = true;
        //reference account is escrow destination, counterparty is then ESCROW_ACCOUNT
        foreach($participants as $p => $roles) {
          if(in_array('ESCROW_ACCOUNT',$roles))
            $this->data['Counterparty'] = $p;
        }
      } else if(\in_array('ESCROW_ACCOUNT',$participants[$this->reference_address])) {
        //reference account is escrow source, counterparty is then ESCROW_DESTINATION
        foreach($participants as $p => $roles) {
          if(in_array('ESCROW_DESTINATION',$roles))
            $this->data['Counterparty'] = $p;
        }
      } else {
        $this->persist = false;
      }
    } else {
      $this->persist = false;
    }

    if($this->persist == false) {
      if(\array_key_exists('Fee', $this->data)) {
        //spent fee, persist it - outside account finished this escrow, paid fee, counterparty is escrow destination:
        $this->persist = true;
        foreach($participants as $p => $roles) {
          if(in_array('ESCROW_DESTINATION',$roles))
            $this->data['Counterparty'] = $p;
        }
      } 
    }
    
    # Balance changes from eventList (primary/secondary, both, one, or none)
    if(isset($this->data['eventList']['primary'])) {
      $this->data['Amount'] = $this->data['eventList']['primary']['value'];
      //if XLS34 enabled:
      if($this->data['eventList']['primary']['currency'] !== 'XRP') {
        $this->data['Issuer'] = $this->data['eventList']['primary']['counterparty'];
        $this->data['Currency'] = $this->data['eventList']['primary']['currency'];
      }
      //:XLS34 enabled end
    }
    /*if(isset($this->data['eventList']['primary'])) {
      $this->data['Amount'] = $this->data['eventList']['primary']['value'];
      if($this->data['eventList']['primary']['currency'] !== 'XRP') {
        throw new \Exception('Unhandled non XRP value on EscrowFinish with HASH ['.$this->data['hash'].'] and perspective ['.$this->reference_address.']');
      }
    }*/
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

    //if XLS34 enabled:
    if(\array_key_exists('Issuer', $this->data))
      $r['i'] = $this->data['Issuer'];

    if(\array_key_exists('Currency', $this->data))
      $r['c'] = $this->data['Currency'];
    //:XLS34 enabled end

    if(\array_key_exists('Fee', $this->data))
      $r['fee'] = $this->data['Fee'];

    return $r;
  }
}