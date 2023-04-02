<?php

namespace Compie\LogzHandler;

use Symfony\Component\Finder\Finder;
use Barryvdh\Debugbar\Storage\FilesystemStorage;



class LogzHandler
{
  protected $app;
  protected $version;
  protected $storage;
  protected $dirname;
  public function __construct($app)
  {

    if (!$app) {
      $app = app();   //Fallback when $app is not given
    }
    $this->app = $app;
    $this->version = $app->version();
    $path = $this->app['config']->get('debugbar.storage.path');
    $this->dirname = rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    $this->storage = new FilesystemStorage($this->app['files'], $path);
  }

  public function process()
  {
    $logzSender  = new LogzIoSender($this->app->config['logz']);

    collect($this->getAllFiles())
      ->map(function ($file) {
        return pathinfo($file);
      })
      ->pluck('filename')
      ->map(function ($fileId) {
        return $this->storage->get($fileId);
      })->map(function ($singleLogInfo) use ($logzSender) {
        try {
          if ($singleLogInfo['__meta']['method'] !== 'HEAD') {
            echo  $singleLogInfo['__meta']['id'] . "was sent \n\r";
            $logs = ProcessLogFile::collect($singleLogInfo, $this->app->config['logz']);
            $logzSender->send($logs);
          }
        } catch (\Exception $exception) {
          echo 'And my error is: ' . $exception->getMessage();
          echo 'error in ' . $singleLogInfo['__meta']['id'];
        } finally {
          unlink($this->dirname . '/' . $singleLogInfo['__meta']['id'] . ".json");
          echo  $singleLogInfo['__meta']['id'] . "was removed \n\r";
        }
      });
  }

  public function processSingle($id)
  {
    $singleLogInfo = $this->storage->get($id);
    $logs = ProcessLogFile::collect($singleLogInfo, $this->app->config['logz']);
    print(json_encode($logs));
  }

  public function getAllFiles()
  {
    return Finder::create()->files()->name('*.json')->in($this->dirname);
  }

  public function buildConfiguration()
  {
  }
}
