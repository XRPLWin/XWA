<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\XRPLParsers\Parser;
use App\XRPLParsers\XRPLParserInterface;
use App\XRPLParsers\Types\Payment;

class XRPLParserPaymentTest extends TestCase
{
    public function test_parser_init(): XRPLParserInterface
    {
        $tx_payment_json = file_get_contents(__DIR__.'/../stubs/api/transaction_methods/tx/payment1.json');
        $tx_payment = \json_decode($tx_payment_json);
        $parser = Parser::get($tx_payment->result,$tx_payment->result->meta,'rfqhRdNGy8NFedhbHi64bC3Tcb74XTEowA');

        $this->assertInstanceOf('\\App\\XRPLParsers\\Types\\Payment',$parser);
        return $parser;
    }

    /**
     * @depends test_parser_init
     */
    public function test_common_fields_are_preset(Payment $parser): Payment
    {
        $data = $parser->getData();
        $this->assertArrayHasKey('Fee',$data);
        $this->assertArrayHasKey('In',$data);
        $this->assertArrayHasKey('TransactionIndex',$data);
        $this->assertEquals($data['TransactionIndex'],$parser->getMeta()->TransactionIndex);
        return $parser;
    }

    /**
     * @depends test_common_fields_are_preset
     */
    public function test_payment_specific_fields_are_preset(Payment $parser): Payment
    {
        $data = $parser->getData();
        $this->assertArrayHasKey('Counterparty',$data);
        $this->assertArrayHasKey('DestinationTag',$data);
        $this->assertArrayHasKey('SourceTag',$data);

        $this->assertArrayHasKey('Amount',$data);
        $this->assertArrayHasKey('Issuer',$data);
        $this->assertArrayHasKey('Currency',$data);

        if(is_object($parser->getTx()->Amount)) {
            $this->assertNotNull($data['Issuer']);
            $this->assertNotNull($data['Currency']);
        } else {
            $this->assertNull($data['Issuer']);
            $this->assertNull($data['Currency']);
        }
        
        return $parser;
    }

}
