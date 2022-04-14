<?php

namespace Compie\LogzHandler\Commands;

use Illuminate\Console\Command;
//use Compie\LogzHandler\Providers\LogzHandlerProvider;
use Compie\LogzHandler\LogzHandler;

class LogzSender extends Command
{

    protected $handler;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'logz:send';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Take files form storage/debugbar parse them , send them to logz.io and remove the files';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    //
    public function __construct(LogzHandler $logzHandler)
    {
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
        return $this->handler->process();
    }
}
