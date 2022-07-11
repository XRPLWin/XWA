<?php
use Illuminate\Database\Migrations\Migration;
use BaoPham\DynamoDb\Facades\DynamoDb;
//https://docs.aws.amazon.com/amazondynamodb/latest/developerguide/bp-modeling-nosql-B.html
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
    $schema = [
      'TableName' => 'activations',
      'AttributeDefinitions' => [
        [
          'AttributeName' => 'account',
          'AttributeType' => 'S'
        ]
      ],
      'KeySchema' => [
        [
          'AttributeName' => 'account',
          'KeyType' => 'HASH',
        ]
      ],

      # Optional:

      //'BillingMode' => 'PROVISIONED',
      //'GlobalSecondaryIndexes' => [],
      //'LocalSecondaryIndexes' => [],
      'ProvisionedThroughput' => [
        'ReadCapacityUnits' => 1,  //TODO test ProvisionedThroughputExceededException's
        'WriteCapacityUnits' => 1, //TODO test ProvisionedThroughputExceededException's
      ],


    ];

    $table = $this->client->createTable($schema);
  }

  /**
   * Reverse the migrations.
   *
   * @return void
   */
  public function down()
  {
    $this->client->deleteTable([
      'TableName' => 'activations',
    ]);
  }
};
