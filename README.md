# XRPLWinAnalyzer

XRP Ledger Analyzer (WORK IN PROGRESS)

# Installation

Set permissions:
```
find storage/ -type d -exec chmod 770 {} \;
find storage/ -type f -exec chmod 760 {} \;
```

## Tests
To execute all tests run:

`php artisan test` or `./vendor/bin/phpunit`

To execute only Unit tests run:

`php artisan test --testsuite=Unit`

To execute only Feature tests run:

`php artisan test --testsuite=Feature`

To learn more on how to run Laravel tests read [official documentation](https://laravel.com/docs/9.x/testing).