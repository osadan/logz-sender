<?php namespace Compie\LogzHandler;

class LogzIoSender {
  
  public function __construct($logzIoConfig){
    $this->logzIoConfig = $logzIoConfig;
  }

  public function send($data){

    $path  = $this->logzIoConfig['logzioEndPoint'] .'?'. http_build_query([
      'token' => $this->logzIoConfig['logzioToken'],
      'type' => 'http-bulk'
    ]);

    $headers = ['Content-Type: application/json'];

    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $path);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

     
     $result = curl_exec($ch);
      if ($result === false) {
        $curlErrno = curl_errno($ch);
        var_dump($curlErrno);
        throw new \Exception('curl error '.$curlErrno);
      }
      curl_close($ch);
      return $result;
  }
}
