<?php

namespace Compie\LogzHandler;

use Illuminate\Support\Facades\Auth;


class ProcessLogFile
{
  protected $logs;
  protected $meta;
  protected $logData;
  protected $conf;

  public function __construct($logData, $conf)
  {
    $this->logData = $logData;
    $this->conf = $conf;
    $this->logs = collect([]);
  }

  public static function collect($logData, $conf)
  {
    $log =  new ProcessLogFile($logData, $conf);
    return $log->setMeta()
      ->setMessages()
      ->setRequest()
      ->setHeavyQueries()
      ->setExceptions()
      ->setMeasures()
      ->wrapLogsWithMetaData()
      ->format();
  }

  public function setMeta()
  {
    preg_match('/\"id\" => (\d+)/', $this->logData['auth']['guards']['web'] ?? null, $matches);
    $requestServerValues = $this->parseRequestStrings($this->logData['request']['request_server']);
    $timestamp = new \DateTimeImmutable($this->logData['__meta']['datetime']);
    $this->meta = [
      "@id" => $this->logData['__meta']['id'],
      "@timestamp" => $timestamp->format('c'),
      "@uri" => $this->logData['__meta']['uri'],
      "@ip" => $this->logData['__meta']['ip'],
      "@forwarded_for" => empty($requestServerValues['HTTP_X_FORWARDED_FOR'])
        ? "" :
        $requestServerValues['HTTP_X_FORWARDED_FOR'],
      "@token" => $this->logData['session']['_token'] ?? 'internal',
      "@name" => $this->logData['auth']['names'] ?? null,
      "@user_id" =>  $matches[1] ?? null,
      "@server_hostname" => gethostname(),
      "@server_address" =>  empty($requestServerValues['SERVER_ADDR'])
        ? "" :
        $requestServerValues['SERVER_ADDR'],
      "@user_agent" =>
      empty($requestServerValues['HTTP_USER_AGENT'])
        ? "" :
        $requestServerValues['HTTP_USER_AGENT'],
      "@env" => env("APP_ENV", 'dev')
    ];

    return $this;
  }

  public function mainMessageValue($prefix)
  {
    return $prefix . " " . ($this->logData['__meta']['method'] ?? '') . ' ' . ($this->logData['__meta']['uri'] ?? '');
  }

  public function setMessages()
  {
    if (!$this->logData['messages']['count']) {
      return $this;
    }

    $messages = collect($this->logData['messages']['messages'])
      ->map(function ($item) {
        $message = collect([
          'logType' => 'message',
          'message' => $this->mainMessageValue('message') . " " . $item['message'],
          'label' => $item['label'],
          'message_text' => $item['message']
        ]);
        if ($item['label'] === 'error' || $item['label'] === 'critical' || $item['label'] === 'emergency') {
          $message->put('notification', 'email');
        }
        return $message;
      });

    $this->logs = $this->logs->merge($messages);
    return $this;
  }

  public function setRequest()
  {

    $request = collect(["logType" => 'request']);
    $measures = collect($this->logData['time']['measures'])->filter(function ($value, $key) {
      return $value['label'] === 'Booting' || $value['label'] === 'Application';
    });
    $request->put('booting_time', $measures[0]['duration_str'] ?? null);
    $request->put('application_time', $measures[1]['duration_str'] ?? null);
    $request->put('booting_time_num', $measures[0]['duration'] ?? null);
    $request->put('application_time_num', $measures[1]['duration'] ?? null);
    $request->put('peak_usage', $this->logData['memory']['peak_usage_str'] ?? null);
    $request->put('peak_usage', $this->logData['memory']['peak_usage'] ?? null);
    $request->put('method', $this->logData['__meta']['method'] ?? null);
    $request->put('controller', $this->logData['route']['controller'] ?? null);
    $request->put('middleware', $this->logData['route']['middleware'] ?? null);
    $request->put('route_permissions', $this->logData['route']['as'] ?? null);
    $request->put('request_duration', $this->logData['time']['duration_str'] ?? null);
    $request->put('request_duration_num', $this->logData['time']['duration'] ?? null);
    $request->put(
      'gap_between_app_and_query',
      ($this->logData['time']['duration'] ?? 0) - ($this->logData['queries']['accumulated_duration'] ?? 0)
    );
    $request->put('events_count', $this->logData['event']['nb_measures'] ?? null);
    $request->put('events_duration', $this->logData['event']['duration_str'] ?? null);
    $request->put('events_duration_num', $this->logData['event']['duration'] ?? null);
    $request->put('query_count', $this->logData['queries']['nb_statements'] ?? null);
    $request->put('failed_queries', $this->logData['queries']['nb_failed_statements'] ?? null);
    $request->put('query_duration', $this->logData['queries']['accumulated_duration_str'] ?? null);
    $request->put('query_duration_num', $this->logData['queries']['accumulated_duration'] ?? null);
    $request->put('params_request', $this->getRequestParamsAsRequest());
    $request->put('params_post', $this->getRequestParamsAsPost());
    $request->put('message', $this->mainMessageValue('request'));

    if ($measures[0]['duration'] > 1) {
      $request->put('notifications', ['email']);
    }

    $this->logs = $this->logs->merge(collect([$request]));
    return $this;
  }

  public function getRequestParamsAsRequest()
  {

    if (($this->logData['request']['request_request'] ?? '[]') === '[]') {
      return null;
    }
    $disabledPathParams = collect($this->conf['disabledPathParams']);
    $uri = $this->logData['__meta']['uri'];
    $items = $disabledPathParams->filter(function ($item) use ($uri) {
      return strpos($uri, $item) !== false;
    });
    if ($items->count() > 0) {
      return null;
    }
    return json_encode($this->parseRequestStrings($this->logData['request']['request_request']));
  }

  public function getRequestParamsAsPost()
  {

    if (($this->logData['request']['$_POST'] ?? '[]') === '[]') {
      return null;
    }
    $disabledPathParams = collect($this->conf['disabledPathParams']);
    $uri = $this->logData['__meta']['uri'];
    $items = $disabledPathParams->filter(function ($item) use ($uri) {
      return strpos($uri, $item) !== false;
    });
    if ($items->count() > 0) {
      return null;
    }
    return $this->logData['request']['$_POST'];
  }

  public function setMeasures()
  {
    $measures = collect($this->logData['time']['measures'])
      ->filter(function ($value, $key) {
        return ($value['label'] !== 'Booting' && $value['label'] !== 'Application');
      })
      ->map(function ($item) {
        return collect([
          "logType" => "measures",
          "measure_key" => $item['label'],
          "duration" => $item['duration_str'],
          "duration_num" => $item['duration'],
          'message' =>   $this->mainMessageValue('measures') . " " . $item['label']
        ]);
      });
    $this->logs = $this->logs->merge($measures);
    return $this;
  }

  public function setHeavyQueries()
  {
    if (!$this->logData['queries']['nb_statements']) {
      return $this;
    }

    $queries = collect($this->logData['queries']['statements'])
      ->filter(function ($value, $key) {
        return $value['duration'] > $this->conf['queryCaptureMinTime'];
      })
      ->map(function ($item) {
        return collect([
          'logType' => 'queries',
          'sql' => $item['sql'],
          'stmt_id' => $item['stmt_id'],
          'duration' => $item['duration_str'],
          'duration_num' => $item['duration'],
          'message' => $this->mainMessageValue('queries'),
          'notifications' => ['email']
        ]);
      });

    $this->logs  = $this->logs->merge($queries);
    return $this;
  }

  public function setExceptions()
  {
    if (!$this->logData['exceptions']['count']) {
      return $this;
    }

    $exceptions = collect($this->logData['exceptions']['exceptions'])
      ->map(function ($item) {
        return collect([
          "logType" => "exception",
          "exception_message" => $item['message'],
          "file" => $item["file"],
          "line" => $item['line'],
          "surrounding_lines" => implode("", $item["surrounding_lines"]),
          'message' => $this->mainMessageValue('exceptions'),
          'notifications' => ['email', 'sms']

        ]);
      });
    $this->logs  = $this->logs->merge($exceptions);
    return $this;
  }

  public function wrapLogsWithMetaData()
  {
    $this->logs = $this->logs->map(function ($logItem) {
      return $logItem->merge($this->meta);
    });
    return $this;
  }

  public function format()
  {

    $records = $this->logs->reduce(function ($carry, $item) {
      array_push($carry, $item->toJson());
      return $carry;
    }, []);
    return implode("\n", $records);
  }

  public function parseRequestStrings($str)
  {
    $values = [];
    $res = [];
    preg_match_all('/\\"(.*?)\\"\s?=>\s?\\"(.*?)\\"/', $str, $values, PREG_SET_ORDER);
    foreach ($values as  $valueArr) {
      $res[$valueArr[1]] = $valueArr[2];
    }
    return $res;
  }
}
