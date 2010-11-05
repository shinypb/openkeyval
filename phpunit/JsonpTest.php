<?php

require_once('../config.inc');
require_once('curl.class.php');

global $CONFIG;

class Jsonp extends PHPUnit_Framework_TestCase {
  private static $browser;
  private static $random_key;
  private static $random_value;
  
  public function Jsonp() {
    self::$random_key = self::generateRandStr(rand(5,20));
    self::$random_value = self::generateRandStr(rand(40,80));
  }
  
  public function Setup() {
    self::$browser = new extractor();
  }
    
  public function testJsonpSet() {      
    $callback = self::generateRandStr(10);
    $url = "http://".$GLOBALS['CONFIG']['api_hostname']."/store/?jsonp_callback=".$callback."&".self::$random_key."=".self::$random_value;
    $data = self::$browser->getdata($url);
    $left = substr($data,0,strlen($callback)+1);
    $mid = substr($data,strlen($callback)+1,strlen($data)-strlen($callback)-6);
    $right = substr($data,-5);
    $this->assertEquals($left,$callback.'(');        
    $this->assertEquals($right,',"");');        
    $r = json_decode($mid);    
    $this->assertEquals($r->status,"multiset");        
    $this->assertNotNull($r->keys->{self::$random_key});    
  }

  public function testGet() {      
    $callback = self::generateRandStr(10);
    $url = "http://".$GLOBALS['CONFIG']['api_hostname']."/".self::$random_key."?jsonp_callback=".$callback;
    $data = self::$browser->getdata($url);
    
    $left = substr($data,0,strlen($callback)+1);
    $mid = substr($data,strlen($callback)+1,strlen($data)-strlen($callback)-strlen(self::$random_key)-6);
    $right = substr($data,-5-strlen(self::$random_key));
    
    $this->assertEquals($left,$callback.'(');        
    $this->assertEquals($right,',"'.self::$random_key.'");');        
    $r = json_decode($mid);    
    $this->assertEquals($r,self::$random_value);           
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
