<?php

require_once('../api/server.inc');
require_once('curl.class.php');

class Api extends PHPUnit_Framework_TestCase {
  private static $browser;
  private static $random_key;
  private static $random_value;
  
  public function Api() {
    self::$random_key = generateRandStr(rand(5,20));
    self::$random_value = generateRandStr(rand(40,80));
  }
  
  public function Setup() {
     global $CONFIG;
    include('../config.inc');
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
    $this->assertContains("Content-Length: ".strlen(self::$random_value),self::$browser->hdr);    
    $this->assertContains("Content-Type: text/plain",self::$browser->hdr);
    $this->assertContains("200 OK",self::$browser->hdr);
  }
  
  public function testMIMEtype() {      
    $type = "application/octet-stream";
    $url = "http://".$GLOBALS['CONFIG']['api_hostname']."/phpunit-" . self::$random_key . "." . $type;
    $data = self::$browser->getdata($url);
    $this->assertEquals($data,self::$random_value);    
    $this->assertContains("Content-Type: $type",self::$browser->hdr);
  }  

  public function testZeroValue() {      
    # Literal value of "0" should be treated like a valud value (See bug #10).
    $url = "http://".$GLOBALS['CONFIG']['api_hostname']."/zerome";
    # set
    $data = self::$browser->getdata($url, array('data'=>"0"));
    # get
    $data = self::$browser->getdata($url);
    $this->assertEquals($data,"0"); 
  }  

  public function testDelete() {      
    $url = "http://".$GLOBALS['CONFIG']['api_hostname']."/deleteme";
    $data = self::$browser->getdata($url, array('data'=>"smiles"));
    $data = self::$browser->getdata($url);
    $this->assertEquals($data,"smiles"); 
    $data = self::$browser->getdata($url, array('data'=>""));
    $r = json_decode($data);
    $this->assertEquals($r->status,"deleted");
    $data = self::$browser->getdata($url);
    $r = json_decode($data);
    $this->assertEquals($r->error,"not_found");
    $this->assertContains("404 Not Found",self::$browser->hdr);
  }  

  public function testLeelooDallsMultiSet() {  
    $random_key1 = generateRandStr(rand(5,20));
    $random_value1 = generateRandStr(rand(40,80));
    $random_key2 = generateRandStr(rand(5,20));
    $random_value2 = generateRandStr(rand(40,80));
    
    $post = array($random_key1=>$random_value1,$random_key2=>$random_value2);
    
    $url = "http://".$GLOBALS['CONFIG']['api_hostname']."/";
    $data = self::$browser->getdata($url, $post);
    $r = json_decode($data);
    $this->assertEquals($r->status,"multiset");        
    $this->assertNotNull($r->keys->$random_key1);    
    $this->assertNotNull($r->keys->$random_key2);    
  }
  
  public function testJonesBigAssTruckRentalAndKeyValueStorage() {  
    $huge_random_key = generateRandStr(128);
    $huge_random_value = generateRandStr(65535);
    
    # set
    $url = "http://".$GLOBALS['CONFIG']['api_hostname']."/" . $huge_random_key;
    $data = self::$browser->getdata($url, array('data' => $huge_random_value) );
    $r = json_decode($data);
    $this->assertEquals($r->status,"set");        
    $this->assertEquals($r->key, $huge_random_key);    
    
    # get
    $data = self::$browser->getdata($url);
    $this->assertEquals($data,$huge_random_value);    
  }

  public function testMySpoonIsTooBig() {  
    $small_key = generateRandStr(10);
    $oversize_key = generateRandStr(129);
    $small_value = generateRandStr(10);
    $oversize_value = generateRandStr(65537);
    
    $url = "http://".$GLOBALS['CONFIG']['api_hostname']."/" . $small_key;
    $data = self::$browser->getdata($url, array('data' => $oversize_value) );
    $r = json_decode($data);
    $this->assertContains("data too big",$r->error);    

    $url = "http://".$GLOBALS['CONFIG']['api_hostname']."/" . $oversize_key;
    $data = self::$browser->getdata($url, array('data' => $small_value) );
    $r = json_decode($data);
    $this->assertContains("invalid_key",$r->error);    
}

  public function testNoKey() {      
    $url = "http://".$GLOBALS['CONFIG']['api_hostname']."/";
    $data = self::$browser->getdata($url);
    $r = json_decode($data);
    $this->assertEquals($r->error,"no_key_specified");        
  }

  public function testInvalidKey() {      
    # test a bunch with GET
    foreach (array("bad","invalid!","rok-foobar","bad keys are bad","jack+jill")as $key) {
      $url = "http://".$GLOBALS['CONFIG']['api_hostname']."/".$key;
      $data = self::$browser->getdata($url);
      $r = json_decode($data);
      $this->assertEquals($r->error,"invalid_key");        
    }
    
    # try to sneak one bad one in a multi-set POST
    $post = array("goodkey"=>"joy","bad"=>"not joy");
    $url = "http://".$GLOBALS['CONFIG']['api_hostname']."/";
    $data = self::$browser->getdata($url, $post);
    $r = json_decode($data);
    $this->assertEquals($r->error,"invalid_key");
    $this->assertEquals($r->key,"bad");
  }

  public function testKeyInfo() {      
    $url = "http://".$GLOBALS['CONFIG']['api_hostname']."/phpunit-" . self::$random_key . "?key_info";
    $data = self::$browser->getdata($url);
    $r = json_decode($data);
    $this->assertEquals('phpunit-'.self::$random_key,$r->key);
  }  

  public function testHEAD() {      
    $url = "/phpunit-" . self::$random_key;
    $portno = 80;
    $method = "HEAD";
    $http_response = "";
    $http_request .= $method." ".$url ." HTTP/1.1\r\n";
    $http_request .= "Host: ".$GLOBALS['CONFIG']['api_hostname']."\r\n";
    $http_request .= "\r\n";

    $fp = fsockopen($GLOBALS['CONFIG']['api_hostname'], $portno, $errno, $errstr);
    if($fp){
        fputs($fp, $http_request);
        while (!feof($fp)) $http_response .= fgets($fp, 128);
        fclose($fp);
    }
    $this->assertContains("200 OK",$http_response);
    # HEAD request shouldn't have the body in it
    $this->assertEquals(strpos($http_response,self::$random_value),false);

  }  

}

?>
