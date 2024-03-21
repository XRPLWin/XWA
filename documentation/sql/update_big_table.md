## Update column on transactions* tables:

See https://docs.percona.com/percona-server/8.0/data-loading.html#loading-data  
    https://docs.percona.com/percona-server/8.0/limitations.html#not-supported-on-myrocks  
    https://learn.percona.com/hubfs/Manuals/Percona_Server_for_MYSQL/Percona_Server_8.0/PerconaServer-8.1.pdf  


Sample:
```SQL
SET session sql_log_bin=0;
-- SET session rocksdb_bulk_load_allow_unsorted=1;
SET session rocksdb_bulk_load=1;
ALTER TABLE transactions201306 MODIFY COLUMN `fee` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'Fee in drops' AFTER `isin`;
SET session rocksdb_bulk_load=0;
-- SET session rocksdb_bulk_load_allow_unsorted=0;
```

Depending of table size and server capacity this can take between 1 minute to few hours. Alter table copies to temp table then renames and drops existing.  
20m rows: 1200sec; 74m rows = 5300sec

Update 16 03 2024:  
```SQL
SET session sql_log_bin=0;
-- SET session rocksdb_bulk_load_allow_unsorted=1;
SET session rocksdb_bulk_load=1;
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
ALTER TABLE `transactionsX` 
ADD COLUMN `a3` varchar(194) NULL COMMENT 'Amount (tertiary)' AFTER `c2`,
ADD COLUMN `i3` varchar(50) NULL COMMENT 'Issuer (tertiary)' AFTER `a3`,
ADD COLUMN `c3` varchar(40) NULL COMMENT 'Currency (tertiary)' AFTER `i3`,
ADD COLUMN `ax` json NOT NULL DEFAULT (JSON_ARRAY()) COMMENT 'List of additional amounts - 4th... (possible in Remit)' AFTER `c3`,
ADD COLUMN `ix` json NOT NULL DEFAULT (JSON_ARRAY()) COMMENT 'List of additional issuers - 4th... (possible in Remit)' AFTER `ax`,
ADD COLUMN `cx` json NOT NULL DEFAULT (JSON_ARRAY()) COMMENT 'List of additional currencies - 4th... (possible in Remit)' AFTER `ix`,
ADD COLUMN `nfts` json NOT NULL DEFAULT (JSON_ARRAY()) COMMENT 'List of URITokens (sfURITokenIDs) included in Remit transaction' AFTER `nft`;
SET FOREIGN_KEY_CHECKS = 1;
SET session rocksdb_bulk_load=0;
-- SET session rocksdb_bulk_load_allow_unsorted=0;
```