## Update column on transactions* tables:

Sample:
```SQL
SET session rocksdb_bulk_load=1;
ALTER TABLE transactions201306 MODIFY COLUMN `fee` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'Fee in drops' AFTER `isin`;
```

Depending of table size and server capacity this can take between 1  minute to few hours. Alter table copies to temp table then renames and drops existing.

