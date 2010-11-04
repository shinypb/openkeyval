<?php

include('../config.inc');
include('curl.class.php');

global $CONFIG;

class Basic extends PHPUnit_Framework_TestCase {
  private static $browser;
  private static $random_key;
  private static $random_value;
  
  public function Basic() {
    self::$random_key = self::generateRandStr(10);
    self::$random_value = self::generateRandStr(50);
  }
  
  public function Setup() {
    self::$browser = new extractor();
  }
    
  public function testSet() {      
    $url = "http://".$GLOBALS['CONFIG']['api_hostname']."/phpunit-" . self::$random_key;
    $data = self::$browser->getdata($url, array('data'=>self::$random_value));
    $r = json_decode($data);
    $this->assertEquals($r->status,"set");        
    $this->assertEquals($r->key,"phpunit-". self::$random_key);    
  }

  public function testGet() {      
    $url = "http://".$GLOBALS['CONFIG']['api_hostname']."/phpunit-" . self::$random_key;
    $data = self::$browser->getdata($url);
    $this->assertEquals($data,self::$random_value);    
  }

  private function generateRandStr($length){ 
    $randstr = ""; 
    for($i=0; $i<$length; $i++) { 
       $randnum = mt_rand(0,61); 
       if($randnum < 10) { 
          $randstr .= chr($randnum+48); 
       } else if($randnum < 36) { 
          $randstr .= chr($randnum+55); 
       } else { 
          $randstr .= chr($randnum+61); 
       }
    }
    return $randstr; 
  } 

}

?>
