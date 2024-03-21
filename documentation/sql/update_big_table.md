## Update column on transactions* tables:

See https://docs.percona.com/percona-server/8.0/data-loading.html#loading-data  
    https://docs.percona.com/percona-server/8.0/limitations.html#not-supported-on-myrocks  
    https://learn.percona.com/hubfs/Manuals/Percona_Server_for_MYSQL/Percona_Server_8.0/PerconaServer-8.1.pdf  

Get schema:

```SQL
SHOW CREATE TABLE ...
```

```SQL
CREATE TABLE `transactions202403` (
  `address` varchar(50) COLLATE utf8mb4_bin NOT NULL COMMENT 'rAddress',
  `l` int unsigned NOT NULL COMMENT 'LedgerIndex',
  `li` smallint unsigned NOT NULL COMMENT 'TransactionIndex',
  `t` datetime NOT NULL COMMENT 'Transaction Timestamp',
  `xwatype` smallint unsigned NOT NULL COMMENT 'XWA Transaction Type',
  `h` varchar(64) COLLATE utf8mb4_bin NOT NULL COMMENT 'Transaction HASH',
  `r` varchar(50) COLLATE utf8mb4_bin NOT NULL COMMENT 'Counterparty',
  `isin` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Direction (in or out)',
  `fee` bigint unsigned DEFAULT NULL COMMENT 'Fee in drops',
  `a` varchar(194) COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Amount',
  `i` varchar(50) COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Issuer',
  `c` varchar(40) COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Currency',
  `a2` varchar(194) COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Amount (secondary)',
  `i2` varchar(50) COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Issuer (secondary)',
  `c2` varchar(40) COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Currency (secondary)',
  -- `a3` varchar(194) COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Amount (tertiary)',
  -- `i3` varchar(50) COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Issuer (tertiary)',
  -- `c3` varchar(40) COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Currency (tertiary)',
  -- `ax` json NOT NULL DEFAULT (json_array()) COMMENT 'List of additional amounts - 4th... (possible in Remit)',
  -- `ix` json NOT NULL DEFAULT (json_array()) COMMENT 'List of additional issuers - 4th... (possible in Remit)',
  -- `cx` json NOT NULL DEFAULT (json_array()) COMMENT 'List of additional currencies - 4th... (possible in Remit)',
  `offers` json NOT NULL COMMENT 'List of offers that are affected in specific transaction in format: rAccount:sequence',
  `nft` varchar(64) COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'NFTokenID',
  -- `nfts` json NOT NULL DEFAULT (json_array()) COMMENT 'List of URITokens (sfURITokenIDs) included in Remit transaction',
  `nftoffers` json NOT NULL COMMENT 'List of NFTOfferIDs that are affected in specific transaction',
  `pc` varchar(64) COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Payment channel',
  `hooks` json NOT NULL COMMENT 'List of executed hook hashes',
  `dt` bigint unsigned DEFAULT NULL COMMENT 'Destination Tag',
  `st` bigint unsigned DEFAULT NULL COMMENT 'Source Tag',
  PRIMARY KEY (`address`,`l`,`li`,`xwatype`,`r`),
  KEY `transactions202403_address_index` (`address`)
) ENGINE=ROCKSDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin
```


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