<?php

/*
Ping ports of a number of servers stored in a table
*/

/*
CREATE TABLE `web` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `host` varchar(100) DEFAULT NULL,
  `last_checked` timestamp NULL DEFAULT NULL,
  `active` int(1) DEFAULT NULL,
  `folder` varchar(100) DEFAULT NULL,
  `info` varchar(300) DEFAULT NULL,
  `ssl` int(1) DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8;
*/

define(DIR_LOGS,'/path/to/logs/dir');
ini_set('max_execution_time', 0);
//require_once('libs/send_email.php');
$break = "
";

$check_http = false;
$check_ftp = false;
$check_sql = false;
$check_ssl  = false;
$check_web_content = false;

$link = mysqli_connect("localhost", "USER", "PASS", "DB");

if (!$link) {
	//echo "Error: Unable to connect to MySQL." . PHP_EOL;
	//echo "Debugging errno: " . mysqli_connect_errno() . PHP_EOL;
	//echo "Debugging error: " . mysqli_connect_error() . PHP_EOL;
	error();
}

echo 'connected'.$break;



$res = $link->query("select * from web ");
if(!$res) error();
if(!$res->num_rows) error();

$webs = [];
while($arr = $res->fetch_assoc()){ 	
	$webs[] = $arr;	
} 


$ctx = stream_context_create(array('http'=>array('timeout' => 120)));
foreach($webs as $w){	
	extract($w);
	if(!$host){
		continue;	
	}

	if($check_http)	$http = ping_port($host,80);
	if($check_ftp) $ftp = ping_port($host,21);		
	if($check_sql) $sql = ping_port($host,3306); // not sql since port not poen
	if($check_ssl) $ssl = ping_port($host,443);


	$log="";
	if($check_http && !$http){
		$repeat_ok = false;
		for($i=0;$i<2;$i++){ // lets repeat in 10 seconds, 2 times more
			sleep(10);
			$http = ping_port($host,80);
			if($http){ 
				$repeat_ok = true;
				break;
			} 			
		}
		if($repeat_ok){

		}else{
			$message = "PING http failed en ".$host;
			$log = $message.' '.date('h:i d/m/y');
			//send_mail($message,$message);
			continue;	
		}
	}
	
	if($check_ftp && !$ftp){
			$message = 'FTP connection failed';
			//send_mail($message,$message);		
	}

	if($check_sql && !$sql){
			$message = 'SQL connection failed';
			//send_mail($message,$message);		
	}
	
	if($check_ssl && !$check_ssl){
			$message = 'SSL connection failed';
			//send_mail($message,$message);		
	}
	
	
	
	
	// check web content is okay
	
	if($check_web_content){
		
		$web = file_get_contents('http://'.$host, false, $ctx);
		if(strlen($web)<1000){
			// repetimos dejando espacio
			$repeat_ok = false;
			for($i=0;$i<4;$i++){
				sleep(10);
				$web = file_get_contents('http://'.$host, false, $ctx);			
				if(strlen($web)>1000){			
					$repeat_ok = true;
					break;
				} 			
			}
			if($repeat_ok){
	
			}else{
				$message = "PING web poco contenido: ".strlen($web).' _ '.$host.' _ '.$web;
				$log .= $message.' '.date('h:i d/m/y').$break;
				send_mail("PING web poco contenido: ".$host,$message);	
			}
		}
		$web = strtolower($web);
		if(strpos($web,'warning:')!==false){
			$message = "WPSeguro PING web WARNING en source code ".$host;
			$log .= $message.' '.date('h:i d/m/y').$break;
			send_mail($message,$message);
		}
		
	}


	if($log){
		$log = 'PINGs KO '.date('h:i d/m/y').''.$break;
		file_put_contents(DIR_LOGS.'/ERROR_'.$host.'.txt',$log,FILE_APPEND);	
	}else{
		$log = 'PINGs OK '.date('h:i d/m/y').''.$break;
		file_put_contents(DIR_LOGS.'/OK_'.$host.'.txt',$log,FILE_APPEND);	
	}

}



function ping_port($host,$port){
	$connection = @fsockopen($host, $port);
	if (is_resource($connection)){
		//echo '<h2>' . $host . ':' . $port . ' ' . '(' . getservbyport($port, 'tcp') . ') is open.</h2>' . "\n";
		fclose($connection);
		return true;
	}else{
		//echo '<h2>' . $host . ':' . $port . ' is not responding.</h2>' . "\n";
		return false;
	}
}






?>