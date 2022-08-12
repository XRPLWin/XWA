<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\XRPLParsers\Parser;
use App\XRPLParsers\XRPLParserInterface;
use App\XRPLParsers\Types\Payment;

class XRPLParserActionTest extends TestCase
{
    public function test_parser_init_tx_correctly(): XRPLParserInterface
    {
        $tx_payment_json = file_get_contents(__DIR__.'/../stubs/api/transaction_methods/tx/payment1.json');
        $tx_payment = \json_decode($tx_payment_json);
        
        $parser = Parser::get($tx_payment->result,$tx_payment->result->meta,'rfqhRdNGy8NFedhbHi64bC3Tcb74XTEowA');

        #check interface
        $this->assertInstanceOf('\\App\\XRPLParsers\\XRPLParserInterface',$parser);

        #check extended base
        $this->assertInstanceOf('\\App\\XRPLParsers\\XRPLParserBase',$parser);

        #check final class
        $this->assertInstanceOf('\\App\\XRPLParsers\\Types\\Payment',$parser);

        $account_tx_payment_json = file_get_contents(__DIR__.'/../stubs/api/account_methods/account_tx/payments1.json');
        $account_tx_payment = \json_decode($account_tx_payment_json);

        $parser2 = Parser::get($account_tx_payment->result->transactions[0]->tx,$account_tx_payment->result->transactions[0]->meta,'rfqhRdNGy8NFedhbHi64bC3Tcb74XTEowA');
        
        #check interface
        $this->assertInstanceOf('\\App\\XRPLParsers\\XRPLParserInterface',$parser2);

        #check extended base
        $this->assertInstanceOf('\\App\\XRPLParsers\\XRPLParserBase',$parser2);

        #check final class
        $this->assertInstanceOf('\\App\\XRPLParsers\\Types\\Payment',$parser2);


        #fidelity check
        $this->assertEquals($parser->getMeta(),$parser2->getMeta());
        $this->assertEquals($parser->SK(),$parser2->SK());

        #activations parsing
        $parser->detectActivations();
        $parser2->detectActivations();
        $this->assertEquals($parser->getActivatedBy(),$parser2->getActivatedBy());

        #switch direction
        $parser3 = Parser::get($account_tx_payment->result->transactions[0]->tx,$account_tx_payment->result->transactions[0]->meta,'rEW8BjpMyFZfGMjqbykbhpnr4KEb2qr6PC');
        
        #activations parsing
        $parser3->detectActivations();
        $this->assertEquals('rfqhRdNGy8NFedhbHi64bC3Tcb74XTEowA',$parser3->getActivated());

        return $parser;
    }

    /**
     * Checks if SK (sort key) is created correctly.
     * 
     * @depends test_parser_init_tx_correctly
     */
    public function test_parser_sk_is_correct(Payment $parser): Payment
    {
        $this->assertEquals($parser->SK(), $parser->getTx()->ledger_index.'.'.$parser->getTransactionIndex());

        return $parser;
    }

}
