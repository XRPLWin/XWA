<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\BUnlreport;
use App\Models\BUnlvalidator;
use XRPLWin\UNLReportReader\UNLReportReader;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;


class XwaUnlreportsSync extends Command
{
  /**
   * The name and signature of the console command.
   *
   * @var string
   */
  protected $signature = 'xwa:unlreportssync {--limit=2 : How much flag ledgers to check in this job}';

  /**
   * The console command description.
   *
   * @var string
   */
  protected $description = 'Synces UNL reports if UNLReports feature is enabled.';


  /**
   * Last active BUnlreport model in memory.
   */
  private ?BUnlreport $xwa_last_saved_report = null;

  /**
   * Limit how much flag ledgers to process in single job.
   * Note: Assuming average tick for one ledger is 3 seconds, each flag ledger occurs every 256 ledgers
   *       then calculation how much time between ledgers is (256*3s) = 768 seconds = 12.8 minutes
   * If you running this job every 10 minutes, theoretically it is enough to set limit to 2.
   * If your syncer is behind, set this value higher for script to catch up faster.
   * Limit of 2 is recommended.
   */
  private int $xwa_limit;
  private int $xwa_current_row = 0;
  private Collection $xwa_validators;

  /**
   * When debugging enabled it will log output to log file.
   */
  private bool $debug = true;
  private string $debug_id = '';

  /**
   * Execute the console command.
   *
   * @return int
   */
  public function handle()
  {
    if(!config('xrpl.'.config('xrpl.net').'.feature_unlreport'))
      return Command::FAILURE;

    $this->debug = config('app.debug');
    $this->debug_id = \substr(\md5(rand(1,999).\time()),0,5);
    
    $this->xwa_limit = (int)$this->option('limit'); //int

    $this->log('Scan limit is: '.$this->xwa_limit);

    $last_synced_LI = config('xrpl.'.config('xrpl.net').'.feature_unlreport_first_flag_ledger');
    
    //$last_synced_LI needs to be at least 512
    if($last_synced_LI < 512)
      $last_synced_LI = 512;

    
    $this->xwa_last_saved_report = BUnlreport::repo_last(['first_l','last_l','vlkey','validators']);
    
    if($this->xwa_last_saved_report == null) { //create first flag row
      Cache::put('job_xwaunlreports_sync_running', true, 245); //240 = 4 mins
      # Close time:
      //$first_l_close_time = config('xrpl.'.config('xrpl.net').'.feature_unlreport_first_flag_ledger_close_time');

      $this->xwa_last_saved_report = BUnlreport::repo_insert([
        //'first_t' => ripple_epoch_to_carbon($first_l_close_time)->format('Y-m-d H:i:s.uP'),
        'first_l' => $last_synced_LI,
        'last_l' => $last_synced_LI,
        'vlkey' => null,
        'validators' => BUnlreport::normalizeValidatorsList([])
      ]);
      $this->log('Created new initial flag ledger row');
    } else {
      $this->log('Last scanned flag ledger: '. $this->xwa_last_saved_report->last_l);
    }


    ###
    //fetch all stored validators
    $this->xwa_validators = BUnlvalidator::repo_fetchAll();
    ###
    
    $last_synced_LI = ($this->xwa_last_saved_report->last_l + 1);
    $this->log('Starting scan from ledger_index: '. $last_synced_LI);
    //$this->xwa_last_saved_unlreport_hash = $this->xwa_last_saved_report->generateHash();

    $reader = new UNLReportReader(config('xrpl.'.config('xrpl.net').'.rippled_fullhistory_server_uri'));
    
    //This can throw ConnectException
    $reports = $reader->fetchMulti($last_synced_LI, true, $this->xwa_limit); //array
    
    if(count($reports) != $this->xwa_limit) {
      //Latest ledger reached, no more data to sync, stop.
      //Hint: Wait and try again later with $this->limit or try now with limit: count($reports)
      $this->log('Requested ledgers out of range, try again later ('.count($reports).'/'.$this->xwa_limit.')');
      Cache::delete('job_xwaunlreports_sync_running');

    } else {
      Cache::put('job_xwaunlreports_sync_running', true, 245); //240 = 4 mins
      foreach($reports as $report) {
        $this->processReport($report);
      }
      $this->commitLastChanges();
      Cache::delete('job_xwaunlreports_sync_running');
    }
    return Command::SUCCESS;
  }

  private function commitLastChanges()
  {
    $this->log('Committing last active row to database...');
    $this->xwa_last_saved_report->save();
    $this->log('Committing validators to database...');
    $bar = $this->output->createProgressBar($this->xwa_validators->count());
    $bar->start();
    foreach($this->xwa_validators as $v) {
      $v->save();
      $bar->advance();
    }
    $bar->finish();
  }

  private function processReport(array $report)
  {
    $this->xwa_current_row++;
    $mock = new BUnlreport;
    $mock->vlkey = $report['import_vlkey'];
    $mock->validators = BUnlreport::normalizeValidatorsList($report['active_validators']);
    foreach($report['active_validators'] as $_v) {
      
      if($V = $this->xwa_validators->where('validator',$_v['PublicKey'])->first()) {
        if($report['report_range'][0]-1 == $V->last_l) {
          //dump('last one was'.$V->last_l);  
          $V->current_successive_fl_count = $V->current_successive_fl_count+1;
        } else {
          $V->current_successive_fl_count = 1;
        }

        if($V->current_successive_fl_count > $V->max_successive_fl_count)
          $V->max_successive_fl_count = $V->current_successive_fl_count;

        $V->last_l = $report['report_range'][1];
        $V->active_fl_count = ($V->active_fl_count+1);
        if(!$V->account)
          $V->account = $_v['Account'];
        //$V->save();
      } else {
        $newV = new BUnlvalidator;
        $newV->validator = $_v['PublicKey'];
        $newV->account = $_v['Account'];
        $newV->first_l = $report['report_range'][0];
        $newV->last_l = $report['report_range'][1];
        $newV->current_successive_fl_count = 1;
        $newV->max_successive_fl_count = 1;
        $newV->active_fl_count = 1;
        //$newV->save();
        $this->xwa_validators->push($newV);
      }
    }
    if($mock->generateHash() != $this->xwa_last_saved_report->generateHash()) {
      $this->commitLastChanges();
      //there is diff in data, create new row, and set it to $this->xwa_last_saved_report
      $this->xwa_last_saved_report = BUnlreport::repo_insert([
        //Add 3 seconds (approx 1 ledger after flag ledger - to match first_l)
        //'first_t' => ripple_epoch_to_carbon($report['flag_ledger_close_time'])->addSeconds(3)->format('Y-m-d H:i:s.uP'),
        'first_l' => $report['report_range'][0],
        'last_l' => $report['report_range'][1],
        'vlkey' => $report['import_vlkey'],
        'validators' => BUnlreport::normalizeValidatorsList($report['active_validators'])
      ]);
      $this->log('['.$this->xwa_current_row.'/'.$this->xwa_limit.'] Added new row ['.$this->xwa_last_saved_report->first_l.','.$this->xwa_last_saved_report->last_l.']');
    } else {
      //there is no diff in data, update existing $this->xwa_last_saved_report
      $this->xwa_last_saved_report->last_l = $report['report_range'][1];
      $this->log('['.$this->xwa_current_row.'/'.$this->xwa_limit.'] Updated existing row in memory ['.$this->xwa_last_saved_report->first_l.','.$this->xwa_last_saved_report->last_l.'] (no content changes)');
    }
  }

  private function log(string $logline)
  {
    $logline = '['.$this->debug_id.'] '.$logline;
    $this->info($logline);

    if(!$this->debug)
      return;

    Log::channel('unlreportsyncjob')->info($logline);
  }
}
