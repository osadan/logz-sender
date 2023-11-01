<?php
return [
  'logzioToken' => env("LOGZIO_TOKEN", null),
  'logzioListener' => env("LOGZIO_LISTENER_ADDRESS", 'listener.logz.io'),
  'logzioRegion' => env("LOGZIO_REGION", 'us'),
  'logzioAccount' => env("LOGZIO_ACCOUNT", null),
  'logzioEndPoint' => env("LOGZIO_ENDPOINT", null),
  'disabledPathParams' => explode(',', env("PATH_PARAMS_DISABLED", 'password,login')),
  'disabledUris' => explode(',', env("DISABLED_URIS", 'favicon.ico,/')),
  'queryCaptureMinTime' => env("LOGZIO_QUERY_CAPTURE_MIN_TIME", 0.250),
  'appEnv' => env("APP_ENV", 'dev')
];
