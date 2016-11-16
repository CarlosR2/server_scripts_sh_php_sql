<?php
/*


Sistema alertas similar a cron basado en una tabla en MySQL

-Instalar tabla
-Instalar cron para ejecutar este script cada poco (minutos)
-Insertar filas para enviar alarmas en el tiempo indicado



*/
/*
CREATE TABLE `alarma` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `minuto` varchar(11) DEFAULT NULL,
  `hora` varchar(11) DEFAULT NULL,
  `dia` varchar(11) DEFAULT NULL,
  `mes` varchar(11) DEFAULT NULL,
  `anyo` varchar(11) DEFAULT NULL,
  `asunto` varchar(500) DEFAULT NULL,
  `mensaje` text,
  `confirmar` int(11) NOT NULL DEFAULT '0',
  `enviado` int(11) DEFAULT NULL,
  `enviado_minuto` varchar(11) NOT NULL DEFAULT '0',
  `enviado_hora` varchar(11) DEFAULT NULL,
  `enviado_dia` varchar(11) DEFAULT NULL,
  `enviado_mes` varchar(11) DEFAULT NULL,
  `enviado_anyo` varchar(11) DEFAULT NULL,
  `caducado` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8;
*/

define('DB_HOST','localhost');
define('DB_USER','DB_USER');
define('DB_PASS','DB_PASS');
define('DB_DB','DB_DB');

//require_once('libs/send_email.php');
$link = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_DB);


$res = $link->query("select * from alarma");
$br ='
';

if(!$res){
	exit('Error db'.$br);
}
if(!$res->num_rows){
	exit('No data');
}



$min = date('i');
$h = date('G');
$d = date('d');
$mo = date('m');
$y = date('Y');


echo 'min: '.$min.$br;
echo 'h: '.$h.$br;
echo 'd: '.$d.$br;
echo 'mo: '.$mo.$br;
echo 'y: '.$y.$br;

//tiene que ejecutarse 2 veces por hora
while($arr = $res->fetch_assoc()){ 

	extract($arr);
	if($caducado) continue;

	// hay que reiniciar?
	$reiniciar = 0;
	if($enviado){		
		// time to reboot the counter because one of the vars changed??
		if($anyo=='*' && $enviado_anyo<$y){ // se envio en el ultimo anyo y hemos cambiado
			$reiniciar = 1;	
		}else if($anyo=='*' || $anyo==$y){ // Sent this year: check more conditions
			if($mes=='*' &&  $enviado_mes<$mo){ // se envio en el ultimo mes y hemos cambiado	
				$reiniciar = 1;	
			}else if($mes=='*' || $mes==$mo){ // this month. Check more
				if($dia=='*' && $enviado_dia<$d){ // se envio en el ultimo dia;	
					$reiniciar = 1;	
				}else if($dia=='*' || $dia == $d){
					if($hora=='*' && $enviado_hora<$h){ // se envio en el ultima hora;	
						$reiniciar = 1;	
					}else if($hora=='*' || $hora==$h){
						if($minuto=='*' && $enviado_minuto<$min){ // se envio en el ultima hora;	
							$reiniciar = 1;	
						}else if($minuto=='*' && $minuto==$min){
							//enough resolution
						}		
					} 	
				} 
			} 
		}
		if($reiniciar){

			$link->query("update alarm set enviado = 0 where id = '".$id."'");			
			echo $link->error;
		}  
	}




	if(!$enviado || $reiniciar){
		// has the time to send it arrived?
		if($anyo == '*' || $anyo<= $y){
			if($mes == '*' || $mes <= $mo){ //with leading 0
				if($dia == '*' || $dia <= $d){ //with leading 0;
					if($hora=='*' || $hora<=$h){
						if($minuto=='*' || $minuto<=$min){
							//faltaria minuto
							echo 'actualizando'.$br;
							$updated = $link->query("update alarm set enviado = 1, enviado_minuto ='".$min."', enviado_hora='".$h."', enviado_dia ='".$d."', enviado_mes='".$mo."', enviado_anyo='".$y."' where id = '".$id."'");
							echo $link->error;
							if($updated){
								echo 'enviando'.$br;
								$issue = $asunto;
								$message = $mensaje;
								//	$sent = send_mail($issue,$message);
							}

						}
					}

				}
			}
		}
	}








}

?>
