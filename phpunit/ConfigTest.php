<?php

class Config extends PHPUnit_Framework_TestCase {

  public function Setup() {
    global $CONFIG;
    include('../config.inc');
  }

  public function testHostname() { 
    $this->assertNotEquals(gethostbyname($GLOBALS['CONFIG']['api_hostname']),$GLOBALS['CONFIG']['api_hostname'],"Hostname did not resolve");
  }

  public function testDirectories() { 
    $this->assertTrue(is_dir($GLOBALS['CONFIG']['dir']));
    $this->assertTrue(is_dir($GLOBALS['CONFIG']['data_dir']));
    $this->assertFalse(substr($GLOBALS['CONFIG']['dir'],-1)=="/","Directory shouldn't have a trailing slash");
    $this->assertFalse(substr($GLOBALS['CONFIG']['data_dir'],-1)=="/","Directory shouldn't have a trailing slash");
  }
  
}

?>
