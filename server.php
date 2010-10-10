<?php
  
  class OpenKeyval {    
    const kMaxDataSize = 65536;
    
    public static function Dispatch() {
      if($_SERVER['REQUEST_METHOD'] == 'GET' && $_SERVER['REQUEST_URI'] == '/') {
        require_once 'templates/docs.html';
        exit;
      }

      if($_SERVER['REQUEST_METHOD'] == 'POST' && $_SERVER['REQUEST_URI'] == '/') {
        foreach ($_POST as $key=>$value) {
          if(!self::IsValidKey($key)) {
            self::Response(400, array('error' => 'invalid_key', 'key' => $key));
          }
        }
        foreach ($_POST as $key=>$value) {
          self::HandlePOST($key,$value);
        }
        self::Response(200, array('status' => 'multiset', 'keys' => array_keys($_POST)));
      }

      if(strpos($_REQUEST['key'], '.')) {
        list($key, $command) = explode('.', $_REQUEST['key']);
      } else {
        $key = $_REQUEST['key'];
        $command = '';
      }

      if(!self::IsValidKey($key)) {
        self::Response(400, array('error' => 'invalid_key'));
      }
      
      if($_SERVER['REQUEST_METHOD'] == 'GET') {
        self::HandleGET($key, $command);
      } elseif ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
        self::HandleOPTIONS();
      } else {
        self::HandlePOST($key, $_POST['data']);
        self::Response(200, array('status' => 'set', 'key' => $key));
      }
    }
    
    private function determineJSONPCallback() {
      foreach (array("jsonp_callback","callback") as $k) {
        if (isset($_GET[$k])) {
          $jsonp = $k;
        }
      }
      return $jsonp;
    }

    public static function HandleGET($key, $command) {
      if($value = OpenKeyval_Storage_Cached::Get($key)) {
        if(strpos($command, '/')) {
          $content_type = $command;
          header('Content-Disposition: filename="' . $key . '"');
        } else {
          $content_type = 'text/plain';
        }
        self::Response(200, $value, $content_type);
      }
      
      self::Response(404, array('error' => 'not_found'));
    }

    public static function HandleOPTIONS() {
      header("HTTP/1.1 200 OK");
      header("Allow: OPTIONS,GET,POST");
    }

    public static function HandlePOST($key, $value) {
      if(!isset($value)) {
        self::Response(400, array('error' => 'missing_field', 'message' => "Data must be sent as form data in the field 'data'"));
        return false;
      }
     
      if(strlen($value) > self::kMaxDataSize) {
        self::Response(413, array('error' => 'data too big, max length is ' . self::kMaxDataSize . ' bytes'));
        return false;
      }
      
      if(trim($value) == '') {
        if(OpenKeyval_Storage_Cached::Get($key)) {
          OpenKeyval_Storage_Cached::Delete($key);
          self::Response(200, array('status' => 'deleted'));
          return false;
        } else {
          self::Response(200, array('status' => 'did_not_exist'));
          return false;
        }
      }
      
      if(OpenKeyval_Storage_Cached::Set($key, $value)) {
        return true;
      } else {
        self::Response(500, array('error' => 'save_failed'));
        return false;
      }
    }
    
    public static function IsValidKey($key) {
      return !!preg_match('/^[-_a-z0-9]{5,128}$/i', $key);
    }
    
    public static function Response($http_code, $body, $content_type = null) {
      $jsonp = self::determineJSONPCallback();
      if (isset($jsonp)) {
        $content_type = "text/javascript";
        $command = "";
        $body = $_GET[$jsonp] . '(' . json_encode($body) . ');';
      } 
      $http_status_messages = array(
        200 => 'OK',
        400 => 'Bad Request',
        404 => 'Not Found',
        413 => 'Request Entity Too Large',
      );
      $http_message = $http_status_messages[$http_code];
      header("HTTP/1.0 {$http_code} {$http_message}");
      header("Content-Type: {$content_type}");
      if(!is_string($body)) {
        $body['documentation_url'] = 'http://openkeyval.org/';
        $body = json_encode($body);
      }
      $content_type = is_null($content_type) ? 'text/plain' : $content_type;
      echo $body;
      exit;
    }
  }
  
  class OpenKeyval_Storage {    
    protected function PathForKey($key) {
      $hash = sha1($key);
      $dirname = 'data/' . substr($hash, 0, 2);
      $filename = substr($hash, 2);
      if(!file_exists($dirname)) {
        mkdir($dirname);
      }
      return $dirname . '/' . $filename;
    }
    
    
    public static function Delete($key) {
      $path = self::PathForKey($key);
      if(!file_exists($path)) {
        return null;
      }
      return unlink($path);      
    }
    
    public static function Get($key) {
      $path = self::PathForKey($key);
      if(!file_exists($path)) {
        return null;
      }
      return file_get_contents($path);
    }
    
    public static function Set($key, $value) {
      return file_put_contents(self::PathForKey($key), $value, LOCK_EX);
    }
  }
  
  class OpenKeyval_Storage_Cached extends OpenKeyval_Storage {
    protected static $handle;
    protected function GetHandle() {
      if(is_null(self::$handle)) {
        self::$handle = new Memcache();
        self::GetHandle()->connect('localhost', 11211) or self::$handle = false;
      }
      return self::$handle;
    }
    
    public static function Delete($key) {
      if(!self::GetHandle()) {
        //  Memcache down?
        return parent::Delete($key);
      }
      
      self::GetHandle()->delete($key);
      return parent::Delete($key);
    }
    
    public static function Get($key) {
      if(!self::GetHandle()) {
        //  Memcache down?
        return parent::Get($key);
      }
      
      $value = self::GetHandle()->get($key);
      if($value === false) {
        //  not in cache
        $value = parent::Get($key);
        self::GetHandle()->set($key, $value);
      }
      return $value;
    }
    
    public static function Set($key, $value) {
      if(!self::GetHandle()) {
        //  Memcache down?
        return parent::Set($key);
      }
      
      $rv = parent::Set($key, $value);
      if($rv) {
        self::GetHandle()->set($key, $value);
      } else {
        //  weird, set failed
        self::GetHandle()->delete($key);
      }
      return $rv;
    }
  }
  
  OpenKeyval::Dispatch();
  
?>