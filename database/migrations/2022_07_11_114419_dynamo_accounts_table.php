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
    $schema = [
      'TableName' => 'transactions',
      'AttributeDefinitions' => [
        [
          'AttributeName' => 'a', //account
          'AttributeType' => 'S'
        ]
      ],
      'KeySchema' => [
        [
          'AttributeName' => 'a', //account
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
      'TableName' => 'transactions',
    ]);
  }
};
