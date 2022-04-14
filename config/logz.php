<?php
  return [
    'logzioToken' => env("LOGZIO_TOKEN", null),
    'logzioListener' => env("LOGZIO_LISTENER_ADDRESS", 'listener.logz.io'),
    'logzioRegion' => env("LOGZIO_REGION", 'us'),
    'logzioAccount' => env("LOGZIO_ACCOUNT" , null),
    'logzioEndPoint' => env("LOGZIO_ENDPOINT", null),
    'disabledPathParams' => env("PATH_PARAMS_DISABLED", ['password','login']),
  ];
  