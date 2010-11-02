<?php

  class OpenKeyval {
    const kMaxDataSize = 65536;
    const kReadOnlyKeyPrefix = 'rok-';

    public static function Dispatch() {
      self::fixMagicQuotesIfNeccessary();

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

        $key_map = array();
        foreach ($_POST as $key=>$value) {
          self::HandlePOST($key,$value);
          $key_map[$key] = self::ReadOnlyKey($key);
        }
        self::Response(200, array('status' => 'multiset', 'keys' => $key_map));
      }

      if($_REQUEST['key'] == 'store/') {
        unset($_REQUEST['key']);
        unset($_GET['key']);
        unset($_POST['key']);
      }

      if(strpos($_REQUEST['key'], '.')) {
        list($key, $command) = explode('.', $_REQUEST['key']);
      } else {
        $key = $_REQUEST['key'];
        $command = '';
      }

      if (isset($_REQUEST['key_info'])) {
        # Mainly used for debugging, but also provides a way to lookup a the read-only key for a given key (without setting)
        self::Response(200, array('key' => $key, 'hash' => self::HashForKey($key), 'read_only_key' => self::ReadOnlyKey($key)));
      }

      if (strpos($_SERVER['REQUEST_URI'],'/store/')===0) {
        self::HandleBastardSonOfAProtocolJSONP();
      }

      if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
        self::HandleOPTIONS();
      }

      if(!self::IsValidKey($key)) {
        self::Response(400, array('error' => 'invalid_key'));
      }

      if($_SERVER['REQUEST_METHOD'] == 'GET') {
        self::HandleGET($key, $command);
      } else {
        self::HandlePOST($key, $_POST['data']);
        self::Response(200, array('status' => 'set', 'key' => $key, 'read_only_key' => self::ReadOnlyKey($key)));
      }
    }

    private function fixMagicQuotesIfNeccessary() {
      if (get_magic_quotes_gpc()) {
        function strip_array($var) {
          return is_array($var) ? array_map("strip_array", $var) : stripslashes($var);
        }
        $_POST = strip_array($_POST);
        $_SESSION = strip_array($_SESSION);
        $_GET = strip_array($_GET);
      }
    }

    private function determineJSONPCallback() {
      $jsonp = null;
      foreach (array("jsonp_callback","callback") as $k) {
        if (isset($_GET[$k])) {
          $jsonp = $k;
        }
      }
      return $jsonp;
    }


    public static function HandleBastardSonOfAProtocolJSONP() {
      $set = array();
      // Pretend GET params were each a POST
      $key_map = array();
      foreach ($_GET as $key=>$value) {
        if ($key != self::determineJSONPCallback()) {
          self::HandlePOST($key, $value);
          $key_map[$key] = self::ReadOnlyKey($key);
        }
      }
      self::Response(200, array('status' => 'multiset', 'keys' => $key_map));
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
      header("HTTP/1.0 200 OK");
      header("Allow: OPTIONS,GET,POST");
      exit;
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

      if($value == '') {
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

    public static function IsReadOnlyKey($key) {
      return (strpos($key, OpenKeyval::kReadOnlyKeyPrefix) === 0);
    }

    public static function IsValidKey($key) {
      if(self::IsReadOnlyKey($key)) {
        $key = substr($key, strlen(self::kReadOnlyKeyPrefix));
      }
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

    protected static function Salt() {
      static $salt = null;
      if(is_null($salt)) {
        $filename = dirname(__FILE__) . '/salt.txt';
        if(!file_exists($filename)) {
          die("Missing salt file (salt.txt in the same directory as this script).");
        }
        $salt = trim(file_get_contents($filename));
      }
      return $salt;
    }

    public static function HashForKey($key) {
      return sha1(self::Salt() . $key);
    }

    public static function ReadOnlyKey($key) {
      return OpenKeyval::kReadOnlyKeyPrefix . self::HashForKey($key);
    }
  }

  class OpenKeyval_Storage {
    protected function PathForHash($hash) {
      $dirname = 'data/' . substr($hash, 0, 2) . '/'  . substr($hash, 2, 2) . '/'  . substr($hash, 4, 2) . '/'  . substr($hash, 6, 2);

      if(!file_exists($dirname)) {
        mkdir($dirname, 0777, $recursive = true);
      }

      return $dirname . '/' . $hash;
    }


    public static function Delete($key) {
      if(OpenKeyval::IsReadOnlyKey($key)) {
        //  Can't delete with a read-only key
        return false;
      }

      $hash = OpenKeyval::HashForKey($key);
      $path = self::PathForHash($hash);
      if(!file_exists($path)) {
        return null;
      }
      return unlink($path);
    }

    public static function Get($key) {
      if(OpenKeyval::IsReadOnlyKey($key)) {
        $key = substr($key, strlen(OpenKeyval::kReadOnlyKeyPrefix));
        $path = self::PathForHash($key);
      } else {
        $hash = OpenKeyval::HashForKey($key);
        $path = self::PathForHash($key);
      }
      if(!file_exists($path)) {
        return null;
      }
      return file_get_contents($path);
    }

    public static function Set($key, $value) {
      if(OpenKeyval::IsReadOnlyKey($key)) {
        //  Can't write to a read-only key
        return false;
      }

      $hash = OpenKeyval::HashForKey($key);
      $path = self::PathForHash($hash);

      return file_put_contents($path, $value, LOCK_EX);
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

      if($value = self::GetHandle()->get($key)) {
        $raw_value = unserialize($value);
        if ($raw_value != null) {
          // Not sure why there are NULLs in the cache, but there are.  TODO: Fix root cause.
          return $raw_value;
        }
      }

      //  not in cache
      $value = parent::Get($key);
      self::GetHandle()->set($key, serialize($value));

      return $value;
    }

    public static function Set($key, $value) {
      if(!self::GetHandle()) {
        //  Memcache down?
        return parent::Set($key);
      }

      $hash = OpenKeyval::HashForKey($key);

      $rv = parent::Set($key, $value);
      if($rv) {
        self::GetHandle()->set($key, serialize($value));
        self::GetHandle()->set(OpenKeyval::kReadOnlyKeyPrefix . $hash, serialize($value));
      } else {
        //  weird, set failed
        self::GetHandle()->delete($key);
        self::GetHandle()->delete(OpenKeyval::kReadOnlyKeyPrefix . $hash);
      }
      return $rv;
    }
  }

OpenKeyval::Dispatch();

?>
