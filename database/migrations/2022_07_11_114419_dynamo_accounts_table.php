<?php

use Illuminate\Database\Migrations\Migration;
#use Illuminate\Database\Schema\Blueprint;
#use Illuminate\Support\Facades\Schema;
use BaoPham\DynamoDb\Facades\DynamoDb;

return new class extends Migration
{
  private $client;
  private $config = [];
  private $tableName;


  public function __construct()
  {
      $this->tableName = with(new App\Models\Account)->getTable();


      //DYNAMODB_CONNECTION=local

      if(env('DYNAMODB_CONNECTION') == 'local') {
          $this->config['endpoint'] = env('DYNAMODB_LOCAL_ENDPOINT');
      }

      //dd($this->config);

      $this->client = DynamoDb::client();
      //dd($this->client);
  }

  /**
   * Run the migrations.
   *
   * @return void
   */
  public function up()
  {
    $schema = [
          "AttributeDefinitions" => [
              [
                  "AttributeName" => "id",
                  "AttributeType" => "S"
              ]
          ],
          "TableName" => $this->tableName,
          "KeySchema" => [
              [
                  "AttributeName" => "id",
                  "KeyType" => "HASH"
              ]
          ],
          "ProvisionedThroughput" => [
              "ReadCapacityUnits" => 1,
              "WriteCapacityUnits" => 1
          ]
      ];

      $table = $this->client->createTable($schema);
      //dd($table);
  }

  /**
   * Reverse the migrations.
   *
   * @return void
   */
  public function down()
  {
    $this->client->deleteTable([
          "TableName" => $this->tableName,
      ]);
  }
};
