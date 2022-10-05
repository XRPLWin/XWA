<?php

namespace App\Override\DynamoDb;

use BaoPham\DynamoDb\DynamoDbModel;
use App\Override\DynamoDb\DynamoDbQueryBuilder;

/**
 * Class DynamoDbModel.
 */
class XWDynamoDbModel extends DynamoDbModel
{
    /**
     * @return DynamoDbQueryBuilder
     */
    public function newQuery()
    {
        $builder = new DynamoDbQueryBuilder($this);

        foreach ($this->getGlobalScopes() as $identifier => $scope) {
            $builder->withGlobalScope($identifier, $scope);
        }

        return $builder;
    }
}
