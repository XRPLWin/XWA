# XRPLWinAnalyzer

XRP Ledger Analyzer (WORK IN PROGRESS)

## Installation

Set permissions:
```
find storage/ -type d -exec chmod 770 {} \;
find storage/ -type f -exec chmod 760 {} \;
```

## Swoole

```
pecl install swoole
```

## Supervisor

### Swoole worker
Customize depending of your needs.  
Copy `/documentation/supervisor/octane.ini` to `/etc/supervisor.d/octane.ini`  

### Account sync queue workers
Customize depending of your needs.  
Copy `/documentation/supervisor/queue.ini` to `/etc/supervisor.d/queue.ini`

```
# Inform supervisor to read workers again.
sudo supervisorctl reread

# Tell supervisor to bring the changes into effect
sudo supervisorctl update

# Restart supervisor service
sudo supervisorctl restart all
```

### Permissions

```
chown -R root:daemon .
find storage/ -type d -exec chmod 770 {} \;
find storage/ -type f -exec chmod 760 {} \;
```

### Restarting

```
php artisan octane:reload
```

## Tests
To execute all tests run:

`php artisan test` or `./vendor/bin/phpunit`

To execute only Unit tests run:

`php artisan test --testsuite=Unit`

To execute only Feature tests run:

`php artisan test --testsuite=Feature`

To learn more on how to run Laravel tests read [official documentation](https://laravel.com/docs/10.x/testing).