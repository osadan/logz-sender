<?php

namespace Compie\LogzHandler\Providers;

use Illuminate\Support\ServiceProvider;
use Compie\LogzHandler as LogzHandler;

class LogzHandlerProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
      //$configPath = __DIR__ . '/../config/logz.php';
      //$this->publishes([$configPath => $this->getConfigPath()], 'config');

    }

    /**
     * Register the application services./
     *
     * @return void
     */
    public function register()
    {
      $configPath = __DIR__ . '/../../config/logz.php';
      $this->mergeConfigFrom($configPath, 'logz');

      $this->app->singleton(
        'logz-handler',
        function ($app) {
          $handler = new LogzHandler\LogzHandler($app);
          return $handler;
        }
      );
      $this->app->alias("logz-handler", '\Compie\LogzHandler\LogzHandler');


    $this->app->singleton(
      'command.logz.send',
      function ($app) {
        return new LogzHandler\Commands\LogzSender($app['logz-handler']);
      }
    );
    $this->app->singleton(
      'command.logz.single',
      function ($app) {
        return new LogzHandler\Commands\LogzSingle($app['logz-handler']);
      }
    );
    $this->commands(['command.logz.send', 'command.logz.single']);
    }

  /**
   * Get the config path
   *
   * @return string
   */
  protected function getConfigPath()
  {
    return config_path('logz.php');
  }

  /**
   * Publish the config file
   *
   * @param  string $configPath
   */
  protected function publishConfig($configPath)
  {
    $this->publishes([$configPath => config_path('logz.php')], 'config');
  }
}
