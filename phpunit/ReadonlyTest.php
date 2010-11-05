<?php

require_once('../config.inc');
require_once('../api/server.inc');
require_once('curl.class.php');

global $CONFIG;

class Readonly extends PHPUnit_Framework_TestCase {
  private static $browser;
  private static $random_key;
  private static $random_value;
  private static $random_value2;
  private static $read_only_key;
  
  public function Readonly() {
    self::$random_key = generateRandStr(rand(5,20));
    self::$random_value = generateRandStr(rand(40,80));
    self::$random_value2 = generateRandStr(rand(40,80));
  }
  
  public function Setup() {
    self::$browser = new extractor();
  }
    
  public function testSet() {      
    $url = "http://".$GLOBALS['CONFIG']['api_hostname']."/phpunit-" . self::$random_key;
    $data = self::$browser->getdata($url, array('data'=>self::$random_value));
    $r = json_decode($data);
    self::$read_only_key = $r->read_only_key;
    $rok = OpenKeyval::ReadOnlyKey("phpunit-" . self::$random_key);
    $this->assertEquals($rok,self::$read_only_key);        

    $url = "http://".$GLOBALS['CONFIG']['api_hostname']."/phpunit-" . self::$random_key . "?key_info";
    $data = self::$browser->getdata($url);
    $r = json_decode($data);
    $this->assertEquals($r->read_only_key,$rok);
  }

  public function testFailAtSettingROK() {      
    $url = "http://".$GLOBALS['CONFIG']['api_hostname']."/".self::$read_only_key;
    $data = self::$browser->getdata($url, array('data'=>"this shouldn't work"));
    $r = json_decode($data);
    $this->assertEquals($r->error,"save_failed");    
  }

  public function testGet() {      
    $url = "http://".$GLOBALS['CONFIG']['api_hostname']."/".self::$read_only_key;
    $data = self::$browser->getdata($url);
    $this->assertEquals($data,self::$random_value);    
  }

  public function testSetAgain() {      
    $url = "http://".$GLOBALS['CONFIG']['api_hostname']."/phpunit-" . self::$random_key;
    $data = self::$browser->getdata($url, array('data'=>self::$random_value2));
    $r = json_decode($data);
    # make sure ROK hasn't changed
    $this->assertEquals($r->read_only_key,self::$read_only_key);        
  }

  public function testGetAgain() {      
    $url = "http://".$GLOBALS['CONFIG']['api_hostname']."/".self::$read_only_key;
    $data = self::$browser->getdata($url);
    $this->assertEquals($data,self::$random_value2);    
  }

  public function testDelete() {      
    $url = "http://".$GLOBALS['CONFIG']['api_hostname']."/phpunit-" . self::$random_key;
    $data = self::$browser->getdata($url, array('data'=>""));
    $r = json_decode($data);
    $this->assertEquals($r->status,"deleted");        
    $url = "http://".$GLOBALS['CONFIG']['api_hostname']."/".self::$read_only_key;
    $data = self::$browser->getdata($url);
    $r = json_decode($data);
    $this->assertEquals($r->error,"not_found");
  }



}

?>
