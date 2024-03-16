## Update column on transactions* tables:

Sample:
```SQL
SET session rocksdb_bulk_load=1;
ALTER TABLE transactions201306 MODIFY COLUMN `fee` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'Fee in drops' AFTER `isin`;
```

Depending of table size and server capacity this can take between 1  minute to few hours. Alter table copies to temp table then renames and drops existing.

Update 16 03 2024:  
```SQL
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
ALTER TABLE `xwa`.`transactionsX` 
ADD COLUMN `a3` varchar(194) NULL COMMENT 'Amount (tertiary)' AFTER `c2`,
ADD COLUMN `i3` varchar(50) NULL COMMENT 'Issuer (tertiary)' AFTER `a3`,
ADD COLUMN `c3` varchar(40) NULL COMMENT 'Currency (tertiary)' AFTER `i3`,
ADD COLUMN `ax` json NOT NULL DEFAULT (JSON_ARRAY()) COMMENT 'List of additional amounts - 4th... (possible in Remit)' AFTER `c3`,
ADD COLUMN `ix` json NOT NULL DEFAULT (JSON_ARRAY()) COMMENT 'List of additional issuers - 4th... (possible in Remit)' AFTER `ax`,
ADD COLUMN `cx` json NOT NULL DEFAULT (JSON_ARRAY()) COMMENT 'List of additional currencies - 4th... (possible in Remit)' AFTER `ix`,
ADD COLUMN `nfts` json NOT NULL DEFAULT (JSON_ARRAY()) COMMENT 'List of URITokens (sfURITokenIDs) included in Remit transaction' AFTER `nft`;


```