<?php declare(strict_types=1);

namespace Tests\Unit;

#use PHPUnit\Framework\TestCase;
use Tests\TestCase;

class HelpersTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createApplication();
    }
  
    public function test_config_static_function_loads_correctly()
    {
        $data = config_static('xrpl');
        $this->assertIsArray($data);
        $this->assertArrayHasKey('address_ignore',$data);

        $data2 = config_static('xrpl.address_ignore');
        $this->assertIsArray($data2);
        $this->assertArrayNotHasKey('address_ignore',$data2);

        $unknown_path = config_static('xrpl.some.unknown.path');
        $this->assertNull($unknown_path);

        $unknown_file = config_static('someunknownconfigfile');
        $this->assertNull($unknown_file);

        $unknown_file2 = config_static('someunknownconfigfile.with.some.depth');
        $this->assertNull($unknown_file2);
    }

    public function test_ripple_epoch_to_epoch()
    {
        $this->assertEquals(946686034,ripple_epoch_to_epoch(1234));
        $this->assertEquals(946684800,ripple_epoch_to_epoch(0));
    }

    public function test_ripple_epoch_to_carbon()
    {
        $data = ripple_epoch_to_carbon(3243666);
        $this->assertInstanceOf(\Carbon\Carbon::class,$data);
        $this->assertEquals(949928466,$data->timestamp);
    }

    public function test_xrpl_has_flag()
    {
        $this->assertFalse(xrpl_has_flag(2147483648, 1));
        # This flag 2228224 should have PartialPayment flag 131072
        $this->assertTrue(xrpl_has_flag(2228224, 131072));
    }

    public function test_wallet_to_short()
    {
        $wallet = 'rsAbXB4zabcdesAdeffgh3D24ABdD';
        $this->assertEquals('rsAb....ABdD',wallet_to_short($wallet));
        $this->assertEquals('rsAb---ABdD',wallet_to_short($wallet,'---'));

        $this->expectException(\TypeError::class);
        wallet_to_short(null);
    }

    public function test_drops_to_xrp()
    {
        $this->assertEquals(0,drops_to_xrp(0));
        $this->assertEquals(0.001,drops_to_xrp(1000));
        $this->assertEquals(123.456789,drops_to_xrp(123456789));
        $this->assertEquals(5123,drops_to_xrp(5123000000));
    }

    public function test_xrp_currency_to_symbol()
    {
        $this->assertEquals('ShibaNFT',xrp_currency_to_symbol('53686962614E4654000000000000000000000000'));
        $this->assertEquals('CSC',xrp_currency_to_symbol('CSC'));
        $this->assertEquals('XRP',xrp_currency_to_symbol('XRP'));
        $this->assertEquals('SOMELONGERSTRING',xrp_currency_to_symbol('SOMELONGERSTRING'));

        //todo add nft example
    }

    public function test_xw_number_format()
    {
        $this->assertEquals('123.45',xw_number_format('123.45'));
        $this->assertEquals('123',xw_number_format('123.00'));
        $this->assertEquals('123',xw_number_format('123.000000'));
        $this->assertEquals('123.0000001',xw_number_format('123.0000001'));
        $this->assertEquals('123',xw_number_format('123'));

        $this->assertEquals('123.45',xw_number_format(123.45));
        $this->assertEquals('123',xw_number_format(123.00));
        $this->assertEquals('123',xw_number_format(123.000000));
        $this->assertEquals('123.0000001',xw_number_format(123.0000001));
        $this->assertEquals('123',xw_number_format(123));
    }

    public function test_format_with_suffix()
    {
        $this->assertEquals(1,format_with_suffix(1));
        $this->assertEquals(50,format_with_suffix(50));
        $this->assertEquals(500,format_with_suffix(500));
        $this->assertEquals('5k',format_with_suffix(5000));
        $this->assertEquals('5.1k',format_with_suffix(5125));
        $this->assertEquals('5.9k',format_with_suffix(5895));
        $this->assertEquals('589k',format_with_suffix(589005));
        $this->assertEquals('589.9m',format_with_suffix(589855005));
        $this->assertEquals('58.9B',format_with_suffix(58900855005));
        $this->assertEquals('5T',format_with_suffix(5008900855005));
        $this->assertEquals('5 quad',format_with_suffix(5008999000855005));
        $this->assertEquals('50.2 quint',format_with_suffix(50208999000859995005));

        $this->assertEquals('0',format_with_suffix(1.23e-6));
        $this->assertEquals('0',format_with_suffix(1.23E-6));
        $this->assertEquals('1.2m',format_with_suffix(1.23e6));
        $this->assertEquals('1.2m',format_with_suffix(1.23E6));
        $this->assertEquals('5.853686962616E+87',format_with_suffix(5853686962616e75));
    }

    function test_getbaseurlfromurl()
    {
        $this->assertEquals('https://xrplwin.com',getbaseurlfromurl('https://xrplwin.com/test.html'));
        $this->assertEquals('http://xrplwin.com', getbaseurlfromurl('http://xrplwin.com/test.html'));
        $this->assertEquals('https://xrplwin.com',getbaseurlfromurl('https://xrplwin.com/somepath/test.html?test=123&a=b'));
        $this->assertEquals('https://xrplwin.com',getbaseurlfromurl('https://xrplwin.com/somepath/test.html?test=123&a=b#test'));
        $this->assertEquals('https://xrplwin.com',getbaseurlfromurl('https://xrplwin.com'));
        $this->assertEquals('https://xrplwin.com',getbaseurlfromurl('https://xrplwin.com/'));
        $this->assertEquals('https://xrplwin.com',getbaseurlfromurl('https://xrplwin.com/?test=123&a=b#test'));
        $this->assertEquals('https://xrplwin.com',getbaseurlfromurl('https://xrplwin.com?test=123&a=b#test'));
    }

    function test_getbasedomainfromurl()
    {
        $this->assertEquals('xrplwin.com',getbasedomainfromurl('https://xrplwin.com/test.html'));
        $this->assertEquals('xrplwin.com',getbasedomainfromurl('http://xrplwin.com/test.html'));
        $this->assertEquals('xrplwin.com',getbasedomainfromurl('https://xrplwin.com/somepath/test.html?test=123&a=b'));
        $this->assertEquals('xrplwin.com',getbasedomainfromurl('https://xrplwin.com/somepath/test.html?test=123&a=b#test'));
        $this->assertEquals('xrplwin.com',getbasedomainfromurl('https://xrplwin.com'));
        $this->assertEquals('xrplwin.com',getbasedomainfromurl('https://xrplwin.com/'));
        $this->assertEquals('xrplwin.com',getbasedomainfromurl('https://xrplwin.com/?test=123&a=b#test'));
        $this->assertEquals('xrplwin.com',getbasedomainfromurl('https://xrplwin.com?test=123&a=b#test'));
    }
}
