<?php

namespace Compie\LogzHandler\Commands;

use Illuminate\Console\Command;
//use Compie\LogzHandler\Providers\LogzHandlerProvider;
use Compie\LogzHandler\LogzHandler;

class LogzSingle extends Command
{

  protected $handler;
  /**
   * The name and signature of the console command.
   *
   * @var string
   */
  protected $signature = 'logz:single {key}';

  /**
   * The console command description.
   *
   * @var string
   */
  protected $description = 'Take file form storage/debugbar parse them ,and print the input';

  /**
   * Create a new command instance.
   *
   * @return void
   */
  //
  public function __construct(LogzHandler $logzHandler)
  {
    var_dump('2');
    parent::__construct();
    $this->handler = $logzHandler;
  }

  /**
   * Execute the console command.
   *
   * @return mixed
   */
  public function handle()
  { 
    return $this->handler->processSingle($this->argument('key'));
  }
}
