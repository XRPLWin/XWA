<?php declare(strict_types=1);

namespace Tests\Redis;

#use PHPUnit\Framework\TestCase;
use Tests\TestCase;
use Illuminate\Support\Facades\Cache;

class RedisTest extends TestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		$this->createApplication();
	}

	public function test_redis_cache_keys_are_case_sensitive(): void
	{
		$cache_key1 = 'rAccount1ABCtest';
		$cache_key2 = 'rAccount1abctest';
		$value1 = 'value for account with capital letters';
		$value2 = 'value for account without capital letters';

		//reset
		Cache::forget($cache_key1);
		Cache::forget($cache_key2);

		Cache::put( $cache_key1, $value1, 1000);
		Cache::put( $cache_key2, $value2, 1000);

		$val1 = Cache::get($cache_key1);
		$val2 = Cache::get($cache_key2);

		$this->assertNotEquals($val1,$val2);
		$this->assertEquals($value1,$val1);
		$this->assertEquals($value2,$val2);
	}
}