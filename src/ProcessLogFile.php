<?php namespace Compie\LogzHandler;

use Monolog\Utils;

class ProcessLogFile {
  protected $logs;
  protected $meta;
  protected $logData;
  protected $conf;
  
  public function __construct($logData,$conf){
    $this->logData = $logData;
    $this->conf = $conf;
    $this->logs = collect([]);
  }

  public static function collect($logData,$conf){
    $log =  new ProcessLogFile($logData,$conf);
    return $log->setMeta()
                ->setMessages()
                ->setRequest()
                ->setHeavyQueries()
                ->setExceptions()
                ->setMeasures()
                ->wrapLogsWithMetaData()
                ->format();
  }

  public function setMeta(){
      $timestamp = new \DateTimeImmutable($this->logData['__meta']['datetime']);
      $this->meta = [
        "@id" => $this->logData['__meta']['id'],
        "@timestamp" => $timestamp->format('c'),
        "@uri" => $this->logData['__meta']['uri'],
        "@ip" => $this->logData['__meta']['ip'],
        "@token" => $this->logData['session']['_token'] ?? 'internal',
      ];

    return $this;
  }

  public function mainMessageValue($prefix){
    return $prefix. " ".($this->logData['__meta']['method'] ?? '').' '.($this->logData['__meta']['uri'] ?? '');
}

  public function setMessages(){
    if (!$this->logData['messages']['count']) {
      return $this;
    }

    $messages = collect($this->logData['messages']['messages'])
      ->map(function ($item) {
        $message = collect([
          'logType' => 'message',
          'message' => $item['message'],
          'label' => $item['label'],
        ]);
        if($item['label'] === 'exception'){
          $message->put('notification', 'email');
        }
        return $message;
      });

    $this->logs = $this->logs->merge($messages);
    return $this;
  }

  public function setRequest(){
    
    $request = collect(["logType" => 'request']);
    $measures = collect($this->logData['time']['measures'])->filter(function($value, $key){
      return $value['label'] === 'Booting' || $value['label'] === 'Application';
    });
    $request->put('booting_time',$measures[0]['duration_str'] ?? null);
    $request->put('application_time', $measures[1]['duration_str'] ?? null);
    $request->put('peak_usage', $this->logData['memory']['peak_usage_str'] ?? null);
    $request->put('method', $this->logData['__meta']['method'] ?? null) ;
    $request->put('controller', $this->logData['route']['controller'] ?? null) ;
    $request->put('request_duration', $this->logData['time']['duration_str'] ?? null);
    $request->put('events_count', $this->logData['event']['nb_measures'] ?? null);
    $request->put('events_duration', $this->logData['event']['duration_str'] ?? null);
    $request->put('query_count',$this->logData['queries']['nb_statements'] ?? null);
    $request->put('query_duration', $this->logData['queries']['accumulated_duration_str'] ?? null);
    $request->put('params', $this->getRequestParams());
    $request->put('message', $this->mainMessageValue('request'));
    
    $this->logs = $this->logs->merge(collect([$request]));
    return $this;
  }

  public function getRequestParams(){
    
    if($this->logData['request']['$_POST'] === '[]'){
        return null;
    }
    $disabledPathParams = collect($this->conf['disabledPathParams']);
    $uri = $this->logData['__meta']['uri'];
     $items = $disabledPathParams->filter(function($item) use ($uri){
        return strpos($uri, $item) !== false;
     }); 
     if($items->count() > 0){
       return null; 
     } 
     return $this->logData['request']['$_POST'];
  }

  public function setMeasures(){
      $measures = collect($this->logData['time']['measures'])
                  ->filter(function ($value, $key) {
                      return ($value['label'] !== 'Booting' && $value['label'] !== 'Application');
                    })
                  ->map(function($item){
                    return collect([
                      "logType" => "measures",
                      "measure_key" => $item['label'],
                      "duration" => $item['duration_str'],
                      'message' =>   $this->mainMessageValue('measures')
                    ]);
                  });
      $this->logs = $this->logs->merge($measures);
      return $this;
  }

  public function setHeavyQueries(){
    if(!$this->logData['queries']['nb_statements']){
      return $this;
    }

    $queries = collect($this->logData['queries']['statements'])
      ->filter(function($value,$key){
          return $value['duration'] > 0.0001;
        })
      ->map(function($item){
          return collect([
            'logType' => 'queries',
            'sql' => $item['sql'],
            'stmt_id' => $item['stmt_id'],
            'duration' => $item['duration_str'],
            'message' => $this->mainMessageValue('queries'),
            'notifications' => ['email']
          ]); 
        });

    $this->logs  = $this->logs->merge($queries);
    return $this;
  }

  public function setExceptions(){
    if(!$this->logData['exceptions']['count']){
      return $this;
    }

    $exceptions = collect($this->logData['exceptions']['exceptions'])
                  ->map(function($item){
                    return collect([
                      "logType" => "exception",
                      "message" => $item['message'],
                      "file" => $item["file"],
                      "line" => $item['line'],
                      "surrounding_lines" => implode("",$item["surrounding_lines"]),
                      'message' => $this->mainMessageValue('exceptions'),
                      'notifications' => ['email','sms']
 
                    ]);
                  });
    $this->logs  = $this->logs->merge($exceptions);
    return $this;
  }

  public function wrapLogsWithMetaData(){
      $this->logs = $this->logs->map(function ($logItem) {
        return $logItem->merge($this->meta);
      });
      return $this;
  }

  public function format (){

    $records = $this->logs->reduce(function($carry,$item){
      array_push($carry, $item->toJson());
      return $carry;
     },[]);
     return implode("\n", $records);
}


}
