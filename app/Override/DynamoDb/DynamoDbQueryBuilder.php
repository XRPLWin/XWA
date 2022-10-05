<?php

namespace App\Override\DynamoDb;

use BaoPham\DynamoDb\DynamoDbQueryBuilder as DynamoDbQueryBuilderSource;
use Illuminate\Support\Arr;

class DynamoDbQueryBuilder extends DynamoDbQueryBuilderSource
{
    public function pagedCount()
    {
        $limit = isset($this->limit) ? $this->limit : static::MAX_LIMIT;

        $raw = $this->toDynamoDbQuery(['count(*)'], $limit);
    
        if ($raw->op === 'Scan') {
            $res = $this->client->scan($raw->query);
        } else {
            $res = $this->client->query($raw->query);
        }
        $this->lastEvaluatedKey = Arr::get($res, 'LastEvaluatedKey');
        
        return (object)['count' => $res['Count'], 'scanned_count' => $res['ScannedCount'], 'lastKey' => $this->lastEvaluatedKey];
    }
}
