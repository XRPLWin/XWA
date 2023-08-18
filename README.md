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
Customize depending of your needs, 12 workers per 1 CPU tested optimal.  
Copy `/documentation/supervisor/octane.ini` to `/etc/supervisor.d/octane.ini`  

### Account sync queue workers
Currently 3 jobs supported.  
Copy `/documentation/supervisor/queue.ini` to `/etc/supervisor.d/queue.ini`

```
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

### Restarting workers

```
php artisan octane:reload
```

### Full restart

```
php artisan cache:clear
rm -rf /var/nginxcache/*
systemctl restart supervisord nginx
```

### Task scheduler

```
* * * * * /opt/php/bin/php /opt/nginx/htdocs/xwa/artisan schedule:run >> /dev/null 2>&1
```

## Tests
To execute all tests run:

`php artisan test` or `./vendor/bin/phpunit`

To execute only Unit tests run:

`php artisan test --testsuite=Unit`

To execute only Feature tests run:

`php artisan test --testsuite=Feature`

To learn more on how to run Laravel tests read [official documentation](https://laravel.com/docs/10.x/testing).