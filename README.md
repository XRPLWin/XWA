# XRPLWinAnalyzer

XRP Ledger Analyzer (WORK IN PROGRESS)

## Installation

Install [composer](https://getcomposer.org/download/) to composer.phar

```SHELL
php composer.phar install --no-dev
```

Set permissions:
```
cp .env.example .env
php artisan key:generate
# set .env variables now
```

### Database (MySQL)

Character set: `utf8mb4`  
Collation: `utf8mb4_bin`

## Swoole

```
pecl install swoole
```

## Supervisor

### Swoole worker
Customize depending of your needs, 12 workers per 1 CPU tested optimal.  
Copy `/documentation/supervisor/octane.ini` to `/etc/supervisor.d/octane.ini`  
CD to xwa project dir.
```
cp ./documentation/supervisor/octane.ini /etc/supervisor/conf.d/octane.conf
```
Note: edit `octane.conf` and make sure path to artisan is correct, you would want to also change log name.

### Account sync queue workers (only if you using sync_type=account)
Currently 3 jobs supported.  
Copy `/documentation/supervisor/queue.ini` to `/etc/supervisor.d/queue.conf`


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
or use .sh script (copy reload.sh.sample to reload.sh) and make it executable `chmod +x reload.sh`
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
