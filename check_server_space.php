<?php

//check server free size and send alert if free < 1GB


define(DIR_LOGS,'/path/to/logs/dir');
//require_once('libs/send_email.php');
$gb_size = 1023168512;
$df = disk_free_space("/");
echo $df;
if($df<$gb_size*1){ // less than 1GB
	// enviar alerta
	$message='SERVER < 1Gb!!!!!';
	//send_mail($message,$message);
}


$bytes = disk_free_space("."); 
$si_prefix = array( 'B', 'KB', 'MB', 'GB', 'TB', 'EB', 'ZB', 'YB' );
$base = 1024;
$class = min((int)log($bytes , $base) , count($si_prefix) - 1);
echo $bytes;

$date = date('h:i d/m/Y');


$str= sprintf('%1.2f' , $bytes / pow($base,$class)) . ' ' . $si_prefix[$class] . '';
echo $str;
echo "
";

file_put_contents(DIR_LOGS.'log.txt',$date.' '.'CAP: '.$str.' ok 
',FILE_APPEND);	


?>