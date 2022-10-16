<?php
//Docs:
//https://github.com/baopham/laravel-dynamodb/issues/90#issuecomment-330301215
//https://igliop.medium.com/building-a-serverless-application-with-laravel-react-and-aws-lambda-d1f978a69fde
//https://github.com/aws/aws-sdk-php-laravel
//https://bitbucket.org/teamhelium/com.helium.laravel-dynamodb/src/master/
//https://docs.aws.amazon.com/amazondynamodb/latest/developerguide/bp-sort-keys.html
//https://docs.aws.amazon.com/amazondynamodb/latest/APIReference/Welcome.html


use Illuminate\Database\Migrations\Migration;
use BaoPham\DynamoDb\Facades\DynamoDb;

return new class extends Migration
{
  private $client;

  public function __construct()
  {
    $this->client = DynamoDb::client();
  }

  /**
   * Run the migrations.
   *
   * @return void
   */
  public function up()
  {
    $model = new \App\Models\DTransaction;
    $schema = [
      'TableName' => $model->getTable(),
      'AttributeDefinitions' => [
        [
          'AttributeName' => 'PK', //rAccount
          'AttributeType' => 'S'
        ],
        [
          'AttributeName' => 'SK', //ledger index
          'AttributeType' => 'N'
        ],
      ],
      'KeySchema' => [
        [
          'AttributeName' => 'PK', //rAccount
          'KeyType' => 'HASH',
        ],
        [
          'AttributeName' => 'SK', //ledger index
          'KeyType' => 'RANGE',
        ]
      ],
      # Optional:
      //'BillingMode' => 'PROVISIONED',
      /*'GlobalSecondaryIndexes' => [
        [
          'IndexName' => 'TxByType',
          'KeySchema' => [
            [
              'AttributeName' => 'PK', //rAccount
              'KeyType' => 'HASH',
            ],
            [
              'AttributeName' => 'TType', //Transaction Type - example
              'KeyType' => 'RANGE',
            ]
          ],
          'Projection' => [
            'ProjectionType' => 'INCLUDE',
            'NonKeyAttributes' => ['TH']
          ],
          'ProvisionedThroughput' => [
            'ReadCapacityUnits' => 1,  //TODO test ProvisionedThroughputExceededException's
            'WriteCapacityUnits' => 1, //TODO test ProvisionedThroughputExceededException's
          ],
        ]
      ],*/
      //'LocalSecondaryIndexes' => [],
      'ProvisionedThroughput' => [
        'ReadCapacityUnits' => 1,  //TODO test ProvisionedThroughputExceededException's
        'WriteCapacityUnits' => 1, //TODO test ProvisionedThroughputExceededException's
      ],
    ];
    $this->client->createTable($schema);
  }

  /**
   * Reverse the migrations.
   *
   * @return void
   */
  public function down()
  {
    $model = new \App\Models\DTransaction;
    $this->client->deleteTable([
      'TableName' => $model->getTable(),
    ]);
  }
};
