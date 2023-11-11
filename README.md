# XRPLWinAnalyzer

XRP Ledger Analyzer (WORK IN PROGRESS)

## Requirements

- PHP 8.1.x, 8.2.x
- swoole (PECL)
- Nginx
- Supervisor (too keep swoole workers up)
- Varnish (optional)

## Installation

Install [composer](https://getcomposer.org/download/) to composer.phar

```
php composer.phar install --no-dev
cp .env.example .env
php artisan key:generate
# set .env variables now
```

Permissions
```
chown -R root:daemon .
find storage/ -type d -exec chmod 770 {} \;
find storage/ -type f -exec chmod 760 {} \;
```

Prepare reaload.sh
```
cp reload.sh.sample reload.sh
chmod +x reload.sh
# edit reload.sh to change nginx cache folder if needed
```

### Database (MySQL)

Character set: `utf8mb4`  
Collation: `utf8mb4_bin`

## Supervisor

### Swoole worker
Customize depending of your needs, 12 workers per 1 CPU tested optimal.  
CD to xwa project dir.
```
cp ./documentation/supervisor/octane.ini /etc/supervisor/conf.d/octane.conf
# change name, log filename and port to match nginx vhost
```
Note: edit `octane.conf` and make sure path to artisan is correct, you would want to also change log name.

### Account sync queue workers (only if you using sync_type=account)
Currently 3 jobs supported.  
Copy `/documentation/supervisor/queue.ini` to `/etc/supervisor/conf.d/queue.conf`


### Enabling and restarting supervisor
```
# Tell supervisor to bring the changes into effect
sudo supervisorctl update

# Restart supervisor service
sudo supervisorctl restart all
```

### Nginx vhost
Now while we have swoole workers running locally on port 8000 we need to expose them to public using nginx.  
CD to xwa project dir.
```
cp ./documentation/nginx/xwa_swoole.conf /opt/nginx/conf/vhosts/xwa_swoole.conf
# edit to match swoole port and nginx cache directory
```

### Full restart

```
./reload.sh
```

### Task scheduler
Edit crontab: `export VISUAL=nano && crontab -e`

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

## Helpers

```
# Switch to superuser:
sudo su -
```
