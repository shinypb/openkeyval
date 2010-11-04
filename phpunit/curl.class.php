<?
	/*
	Class developed by Sab Malik 
	www.decodeit.biz
	Please donot touch unless you are absolutely sure about what your doing!! :)
	*/
	class extractor{
		var $cookiefile ; 
		var $timeout ; 
		var $error ;
		var $hdr;
		var $status;
		var $proxyaddr;
		var $proxyport;
		var $proxyuser;
		var $proxypass;
		
		function extractor($cookies=false , $timeout=5 , $sesscookiefile = ""){
			$this->timeout = $timeout;
			if($cookies){
				if($mycookiefile){
					$this->cookiefile = "cookies/".$sesscookiefile;
					if(!is_file($this->cookiefile)){
						$fp = fopen ($this->cookiefile , "w");
						fclose($fp);
					}
				}else{
					$this->cookiefile = tempnam("tmp","EXT");
				}
			}
			
			$this->cleanupOldCookies();	
		}
		
		function getdata($url , $post=array() ,  $referer = "" , $setcookie=false , $usecookie=false , $useragent="" , $alternate_post_format = false){
			$ch = curl_init();
			#curl_setopt ($ch, CURLOPT_HEADER, true);
			curl_setopt ($ch, CURLOPT_FOLLOWLOCATION, true);
			
			if($proxyport && $proxyaddr) curl_setopt($ch, CURLOPT_PROXY,trim($proxyaddr).":".trim($proxyport));
			if($proxyuser && $proxypass) curl_setopt($ch, CURLOPT_PROXYUSERPWD,trim($proxyuser).":".trim($proxypass));
			
			if($setcookie)	curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookiefile);
			if($usecookie)	curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookiefile);
			if($this->timeout) curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
			
			if($referer) curl_setopt($ch, CURLOPT_REFERER, trim($referer));
			else curl_setopt($ch, CURLOPT_REFERER, trim($url));
			
			if(trim($useragent)) curl_setopt($ch, CURLOPT_USERAGENT, $useragent);
			else curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible; MSIE 6.0;Windows NT 5.1)");
			
			if(substr_count($url , "https://")){
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
			}
			
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_URL,$url);
			
						
			if(count($post)){
				curl_setopt($ch, CURLOPT_POST, true);
				
				if($alternate_post_format) {
					foreach($post as $key=>$val){
						$str .="$key=".urlencode($val)."&";
					}
					$str = substr($str , 0 , -1);
					curl_setopt($ch, CURLOPT_POSTFIELDS, $str);
				}else{
					curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
				}	
			}
			
			$data = curl_exec($ch);
			$err = curl_error($ch);
			curl_close ($ch);
			unset($ch);
			
			$theData = preg_split("/(\r\n){2,2}/", $data, 2) ;
			$showData = $theData[0];
			
			$this->error = $err;
			$this->hdr = $theData[0];
			$this->parseHeader($theData[0]) ;
			
			return $showData;
		}
		
		
		function parseHeader($theHeader){
			$theArray = preg_split("/(\r\n)+/", $theHeader) ;
			foreach ($theArray as $theHeaderString){
				$theHeaderStringArray = preg_split("/\s*:\s*/", $theHeaderString, 2) ;
				if (preg_match('/^HTTP/', $theHeaderStringArray[0])){
					$this->status = 	$theHeaderStringArray[0];
				}
			}
		}
		
		
		//--- this doesnt really belong here , but mostly when this class is used , i use this function as well , so i have placed it here
		function search($start,$end,$string, $borders=true){
			$reg="!".preg_quote($start)."(.*?)".preg_quote($end)."!is";
			preg_match_all($reg,$string,$matches);
			
			if($borders) return $matches[0];	
			else return $matches[1];	
		}
		
		
		function cleanupOldCookies(){
			$delbefore = 86400; ///-- delete cookies older than 1 day
			$tmpdir = "cookies";
			
			if ($dir = @opendir($tmpdir)) {
			  while (($file = readdir($dir)) !== false) {
				if($file != "." && $file != ".."){
					$stat = stat($tmpdir."/".$file);
					if($stat[atime] < (mktime() - $delbefore)){
						unlink($tmpdir."/".$file);
					}	
				}
			  }  
			  closedir($dir);
			  
			}
		}
	}	
?>