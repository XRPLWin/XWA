<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\BUnlreport;
use XRPLWin\UNLReportReader\UNLReportReader;

class XwaUnlreportsSync extends Command
{
  /**
   * The name and signature of the console command.
   *
   * @var string
   */
  protected $signature = 'xwa:unlreportssync';

  /**
   * The console command description.
   *
   * @var string
   */
  protected $description = 'Synces UNL reports if UNLReports feature is enabled.';

  /**
   * Pre-processed UNLReport data to be persisted in DB.
   */
  private $_unlreport_data = [];

  /**
   * Execute the console command.
   *
   * @return int
   */
  public function handle()
  {
    $last_report = BUnlreport::insert([
      'first_l' => 512,
      'last_l' => 512,
      'vlkey' => null,
      'validators' => [
        [
          'pk' => 'asdkasdfgjerngf',
          'acc' => 'd4g53h4ztrgr'
        ],
        [
          'pk' => 'AAAAAAA',
          'acc' => 'BBBBB'
        ]
      ]
    ]);
    exit;


    if(!config('xrpl.'.config('xrpl.net').'.feature_unlreport'))
      return Command::FAILURE;

    $last_synced_LI = config('xrpl.genesis_ledger');
    //$last_synced_LI needs to be at least 512
    if($last_synced_LI < 512)
      $last_synced_LI = 512;
   
    $last_report = BUnlreport::last('first_l,last_l');
    if($last_report == null) { //create first flag row
      $last_report = BUnlreport::insert([
        'first_l' => $last_synced_LI,
        'last_l' => $last_synced_LI,
        'vlkey' => null,
        'validators' => []
      ]);
    }
    dd('done');
    

    $reader = new UNLReportReader(config('xrpl.'.config('xrpl.net').'.rippled_fullhistory_server_uri'));
    $reports = $reader->fetchMulti($last_synced_LI, true, 3); //array

    foreach($reports as $report) {
      $this->processReport($report);
    }
    dd($reports);


    dd($reports);
    
    return Command::SUCCESS;
  }

  private function processReport(array $report)
  {
    if($report['import_vlkey'] === null && !count($report['active_validators']))
      return; //no data yet, skip

    dd($report);
  }
}
