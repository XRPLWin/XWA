<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;

abstract class TestCase extends BaseTestCase
{
	/**
	 * Creates the application.
	 *
	 * @return \Illuminate\Foundation\Application
	 */
	public function createApplication()
	{
		$app = require __DIR__.'/../bootstrap/app.php';

		$app->make(Kernel::class)->bootstrap();

		$this->clearCache();

		return $app;
	}

	/**
	 * Clears Laravel Cache.
	 */
	protected function clearCache()
	{
		$commands = [
			//'clear-compiled',
			'config:clear',
			'cache:clear',
			//'view:clear',
			
			//'route:clear'
		];
		foreach ($commands as $command) {
			Artisan::call($command);
		}
	}

	/**
	 * Migrate tables to local database (in memory sqlite)
	 */
	public function migrateDatabaseLocal()
	{
		$commands = [
			'migrate --path=/database/migrations/2022_07_28_000000_create_failed_jobs_table.php',
			'migrate --path=/database/migrations/2022_07_28_create_jobs_table.php',
			'migrate --path=/database/migrations/2022_08_29_095147_create_ledgerindexes_table.php',
			'migrate --path=/database/migrations/2022_09_01_124240_create_maps_table.php',
		];
		foreach ($commands as $command) {
			Artisan::call($command);
		}
	}

}
