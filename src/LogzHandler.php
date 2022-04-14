<?php namespace Compie\LogzHandler;

use Barryvdh\Debugbar\Storage\FilesystemStorage;
use Symfony\Component\Finder\Finder;



class LogzHandler {
  protected $app;
  protected $version;
  protected $storage;
  protected $dirname;
  public function __construct($app){

    if (!$app) {
      $app = app();   //Fallback when $app is not given
    }
    $this->app = $app;
    $this->version = $app->version();
    $path = $this->app['config']->get('debugbar.storage.path');
    $this->dirname = rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    $this->storage = new FilesystemStorage($this->app['files'], $path);
  }

  public function process(){
    $logzSender  = new LogzIoSender($this->app->config['logz']);

    collect($this->getAllFiles())
    ->map(function($file){
        return pathinfo($file);
      })
    ->pluck('filename')
    ->map(function($fileId){
        return $this->storage->get($fileId);
      })->each(function($singleLogInfo) use($logzSender){

        $logs = ProcessLogFile::collect($singleLogInfo, $this->app->config['logz']);
        //print_r($logs);
        $result = $logzSender->send($logs);
        //var_dump($result);
        unlink($this->dirname . '/'. $singleLogInfo['__meta']['id'] . ".json");
        
      });
  }

  public function getAllFiles (){
    return Finder::create()->files()->name('*.json')->in($this->dirname);
  }

  public function buildConfiguration(){

  }
}


