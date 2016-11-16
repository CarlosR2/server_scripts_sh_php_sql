<?php;


// //php -f send_wp_myqsql_dumper_bring_back_and_decypher.php HOST FTP_HOST FTP_USER FTP_PASS FTP_REMOTE_DIR EXCLUDE_TABLES_CSV


//[0] -> send_wp_myqsql_dumper_bring_back_and_decypher.php
//[1] -> host
//[2] -> user
//[3] -> pass
//[4] -> db
//[5] -> dir


ini_set('max_execution_time', 0);
ini_set('memory_limit', '2048M');
ini_set('upload_max_filesize', '512MB');
set_time_limit(0);

$ftp_dir = "";

if(!isset($argv[0])){
	error('error 1');
}
if(!isset($argv[1])){
	error('error domain');
}
if(!isset($argv[2])){
	error('error ftp host');
}
if(!isset($argv[3])){
	error('error ftp user');
}
if(!isset($argv[4])){
	error('error ftp pass');
}
if(!isset($argv[5])){
	$argv[5] ="";
	//error('error folder');	
}else{
	$ftp_dir = $argv[5];		// 'public_html'
}
$exclude_tables ="";
if(isset($argv[6])){
	$exclude_tables = $argv[6];
}

$domain = $dir = $argv[1];
$ftp_host = $argv[2];
$ftp_user = $argv[3];
$ftp_pass = $argv[4];



define('DIR','/path/to/backups/backups/sql/'.$dir.'/');
define('DIR_LOGS',DIR.'_logs/');
define('DIR_BACKUPER','dump_mysql_database_WP_credencials_&_encrypt.php');

$private_key_path = __DIR__.'/_keys/private.pem'; 
$public_key_path = __DIR__.'/_keys/public.pem';

/*

1. Send file BACKUPER to remote server
2. Call sent file BACKUP
3. Copy FILE
4. Delete files

*/





function error($error){
	die($error);
}

function ok($ok){
	die($ok);
}


//echo '!'.$argv[1].'__'.$argv[2];




$exclude_tables;

$log_file = DIR_LOGS.''.$dir.'_'.date('Ymd').".log";
if(!file_exists(DIR)){
	mkdir(DIR);
}
if(!file_exists(DIR_LOGS)){
	mkdir(DIR_LOGS);
}




$ftp = new ftp_conn();
$con = $ftp->connect($ftp_host, $ftp_user, $ftp_pass);
if(!$con){			
	//logear error
	$error =	'no se conectó con servidor';
	die($error);
}else{
	echo 'connected';
}





if(isset($_GET['callback'])){
	//copy file

	//get copy and delete

}else{

	//check if its file

	$listing = $ftp->getDirListing();
	//print_r($listing);
	$exists = false;
	foreach($listing as $l){
		/*
		echo $l.'
		';
		*/
		if( false ){
			$exists = true; break;
		}

	}




	if(!$exists){	
		$res = $ftp->uploadFile (DIR_BACKUPER, $ftp_dir.basename(DIR_BACKUPER));	
		$res = $ftp->uploadFile ($public_key_path, $ftp_dir.basename($public_key_path));	
	}

	$url_callback = "";

	//$host = '178.62.234.139';
	$url_to_call = "http://".$domain.'/'.basename(DIR_BACKUPER).'?';;	
	// http://dianaorero.com/_read_database.php?make_backup=1&file=backup.sql&callback=&callback_false=
	$db = "";
	$remote_backup_file = 'backup.sql';
	$local_backup_file = DIR.$db.'_'.date('Ymd').'.sql';
	$delete_previous = $ftp->deleteFile($remote_backup_file);

	$key_enc_file = DIR.$db.'_key_'.date('Ymd').'.txt';

	$encrypt = true;

	$url_to_call.='make_backup=1&';
	$url_to_call.='file='.$remote_backup_file.'&';
	$url_to_call.='callback=&';
	$url_to_call.='callback_false=&';
	if($encrypt) $url_to_call.='encrypt=true&';
	$url_to_call.='exclude_tables='.$exclude_tables;


	$curl = curl_init();

	curl_setopt_array($curl, array(
		CURLOPT_RETURNTRANSFER => 1,
		CURLOPT_URL => $url_to_call,
		CURLOPT_TIMEOUT=>900,
		CURLOPT_CONNECTTIMEOUT=>900
	));

	$result = curl_exec($curl);

	if($result){
		//Ftp get file
		if(trim($result)=='false'){
			file_put_contents($log_file,"KO: no ok response server. Url:".$url_to_call,FILE_APPEND);
			die('error false');
		}else{
			echo 'ok: '.$result;	
			//bring it
		}
		$result = trim($result);






		if($encrypt){
			//download only encrypted
			$local_backup_file_enc = $local_backup_file.'.enc.txt';
			//$res_download = $ftp->downloadFile ($result.'.enc.txt', $local_backup_file_enc);

			///// WGET INSTEAD of FTP (more reliable for larger files)			
			$command = 'wget --quiet -O '.'"'.$local_backup_file_enc.'"'.' '.'"http://'.$domain.'/'.$remote_backup_file.'.enc.txt"'." ";
			//echo $command;
			file_put_contents($log_file,"COMMAND WGET: ".$command.' ',FILE_APPEND);
			exec($command);
			$res_download = true; // check;


			$res2 = $ftp->downloadFile('public_encrypted_pass.txt', $key_enc_file);

			$key_enc = file_get_contents($key_enc_file);

			$error = "";
			$key_enc = base64_decode($key_enc);
			$key_dec = decrypt_private($private_key_path,$key_enc,$error);
			if($error){
				die('error decrypting symmetric key: '.$error.' KEY= '.$key_dec);
			}
			$key = $key_dec;



			$crypt = new Encryption($key);
			$error = "";
			$res = $crypt->decrypt_file($local_backup_file_enc,$local_backup_file_enc.'.dec.txt',$error);
			unlink($local_backup_file_enc);
			// and decrypt with same password
			// get that pass  encrypted with private key
		}else{
			//$res = $ftp->downloadFile ($result, $local_backup_file);
			//download plain
			$res_download = $ftp->downloadFile ($result, $local_backup_file); // delete if encypted;
		}

		if($res_download){
			echo 'its here';
			//lets delete
			$delete = $ftp->deleteFile($remote_backup_file);
			$delete = $ftp->deleteFile(basename(DIR_BACKUPER)); // and the backuper file;
			if($encrypt){
				$delete = $ftp->deleteFile($result.'.enc.txt');	
				$delete = $ftp->deleteFile('public_encrypted_pass.txt');	
			} 
			file_put_contents($log_file,"OK: ".$result. " Url:".$url_to_call,FILE_APPEND);
		}else{
			echo 'error bringint it: '.$result.' url:'.$url_to_call;
		}
		//and then delete
	}else{
		file_put_contents($log_file,"KO: no result curl_exec. Url:".$url_to_call,FILE_APPEND);
		die('no result');
	}
	//now call file 


}




































class ftp_conn{

	private $connectionId;
	private $loginOk = false;
	private $messageArray = array();


	private $saved_server ="";
	private $saved_ftpUser ="";
	private $saved_ftpPassword ="";
	private $saved_isPassive ="";

	public function __construct() { 

	}
	public function connect ($server, $ftpUser, $ftpPassword, $isPassive = true)
	{

		// *** Set up basic connection

		$this->saved_server =$server;
		$this->saved_ftpUser=$ftpUser;
		$this->saved_ftpPassword=$ftpPassword;
		$this->saved_isPassive=$isPassive;

		$this->connectionId = ftp_connect($server,21,60*20); // 20minutos
		if(!$this->connectionId){			
			//	echo 'no conect';
			return false;	
		}

		// *** Login with username and password
		$loginResult = ftp_login($this->connectionId, $ftpUser, $ftpPassword);
		if(!$loginResult){
			//	echo 'no login';
			return false;	
		}

		// *** Sets passive mode on/off (default off)
		ftp_pasv($this->connectionId, $isPassive);

		// *** Check connection
		if ((!$this->connectionId) || (!$loginResult)) {
			return false;
		} else {
			$this->loginOk = true;
			return true;
		}
	}

	public function makeDir($directory)
	{
		// *** If creating a directory is successful...
		if (ftp_mkdir($this->connectionId, $directory)) {

			$this->logMessage('Directory "' . $directory . '" created successfully');
			return true;

		} else {

			// *** ...Else, FAIL.
			$this->logMessage('Failed creating directory "' . $directory . '"');
			return false;
		}
	}
	public function getDirListing($directory = '.', $parameters = '') //$parameters = '-la'
	{
		// get contents of the current directory
		//$contentsArray = ftp_nlist($this->connectionId, $parameters . '  ' . $directory);		
		$contentsArray = ftp_nlist($this->connectionId,  $directory);		
		return $contentsArray;
	}
	public function uploadFile ($fileFrom, $fileTo)
	{
		// *** Set the transfer mode
		$asciiArray = array('txt', 'csv');
		$a = explode('.', $fileFrom);
		$extension = end($a);
		if (in_array($extension, $asciiArray)) {
			$mode = FTP_ASCII;      
		} else {
			$mode = FTP_BINARY;
		}

		// *** Upload the file
		$upload = ftp_put($this->connectionId, $fileTo, $fileFrom, $mode);

		// *** Check upload status
		if (!$upload) {

			//	$this->logMessage('FTP upload has failed!');
			return false;

		} else {
			//	$this->logMessage('Uploaded "' . $fileFrom . '" as "' . $fileTo);
			return true;
		}
	}

	public function deleteFile($file){
		if(!$file) return false;
		return @ftp_delete($this->connectionId,$file);
	}

	public function changeDir($directory)
	{
		if (ftp_chdir($this->connectionId, $directory)) {
			//	$this->logMessage('Current directory is now: ' . ftp_pwd($this->connectionId));
			return true;
		} else { 
			//	$this->logMessage('Couldn\'t change directory');
			return false;
		}
	}
	public function downloadFile ($fileFrom, $fileTo)
	{
		$this->close();
		$this->connect($this->saved_server,
					   $this->saved_ftpUser,
					   $this->saved_ftpPassword,
					   $this->saved_isPassive);

		// *** Set the transfer mode
		$asciiArray = array('txt', 'csv');
		$extension = end(explode('.', $fileFrom));
		if (in_array($extension, $asciiArray)) {
			$mode = FTP_ASCII;      
		} else {
			$mode = FTP_BINARY;
		}

		$mode = FTP_BINARY;

		// try to download $remote_file and save it to $handle
		if (ftp_get($this->connectionId, $fileTo, $fileFrom, $mode, 0)) {

			return true;
			$this->logMessage(' file "' . $fileTo . '" successfully downloaded');
		} else {

			return false;
			$this->logMessage('There was an error downloading file "' . $fileFrom . '" to "' . $fileTo . '"');
		}

	}

	public function close(){
		if ($this->connectionId) {
			ftp_close($this->connectionId);
		}
	}

	public function __deconstruct()
	{
		if ($this->connectionId) {
			ftp_close($this->connectionId);
		}
	}
}


















class Encryption
{
	const CIPHER = MCRYPT_RIJNDAEL_128; // Rijndael-128 is AES
	const MODE   = MCRYPT_MODE_CBC;

	/* Cryptographic key of length 16, 24 or 32. NOT a password! */
	private $key;


	private $init = 0;
	private $how_many_per_block; //chars; //every block will be a line


	public function __construct($key) {
		$this->key = $key;


		$this->how_many_per_block = 1024; //chars; //every block will be a line

	}

	public function encrypt($plaintext) {
		$ivSize = mcrypt_get_iv_size(self::CIPHER, self::MODE);
		$iv = mcrypt_create_iv($ivSize, MCRYPT_DEV_URANDOM);
		$ciphertext = mcrypt_encrypt(self::CIPHER, $this->key, $plaintext, self::MODE, $iv);
		return base64_encode($iv.$ciphertext);
	}

	public function decrypt($ciphertext) {
		$ciphertext = base64_decode($ciphertext);
		$ivSize = mcrypt_get_iv_size(self::CIPHER, self::MODE);
		if (strlen($ciphertext) < $ivSize) {
			throw new Exception('Missing initialization vector');
		}

		$iv = substr($ciphertext, 0, $ivSize);
		$ciphertext = substr($ciphertext, $ivSize);
		$plaintext = mcrypt_decrypt(self::CIPHER, $this->key, $ciphertext, self::MODE, $iv);
		return rtrim($plaintext, "\0");
	}


	public function encrypt_file($file_to_encrypt,$encrypted_file,&$error){

		$data = file_get_contents($file_to_encrypt);;
		if(!$data){
			$error = 'No data';
			return false;
		} 

		if(!file_exists($file_to_encrypt)){
			$error = 'no file to encrypt';	
			return false;
		} 
		if(file_exists($encrypted_file)) unlink($encrypted_file);

		$final_encrypted_string ="";
		$total = strlen($data);

		$init = 0;
		$how_many_per_block = $this->how_many_per_block;
		do{
			$partial_encrypted="";
			$last_one = false;
			if($init+$how_many_per_block>$total){
				$how_many_per_block = $total-$init;
				$last_one = true;
			} 	
			$partial_string = substr($data,$init,$how_many_per_block);	
			$partial_encrypted = $this->encrypt(base64_encode($partial_string));
			file_put_contents($encrypted_file, ($partial_encrypted.PHP_EOL),FILE_APPEND);	
			$final_encrypted_string.=$partial_encrypted.'\n';
			$init+=$how_many_per_block;
		}while(!$last_one);

		return true;
	}

	public function decrypt_file($encrypted_file,$encrypted_decrypted_file,&$error){


		if(file_exists($encrypted_decrypted_file)) unlink($encrypted_decrypted_file);
		if(!file_exists($encrypted_file)){
			$error = 'No file to decrypt';
			return false;
		} 
		$file = fopen($encrypted_file, "r");
		if ($file) {
			while(!feof($file)){
				$line = fgets($file);		
				if(!$line) continue;

				$decrypted = $this->decrypt($line);
				$decrypted = base64_decode($decrypted);
				file_put_contents($encrypted_decrypted_file, $decrypted,FILE_APPEND);
			}
			fclose($file);
		} else {
			// error opening the file.
		} 
		return true;

	}

}

function encrypt_public($pub_key_path,$string,&$error){

	$pub_key = openssl_get_publickey(file_get_contents($pub_key_path)); 
	if(!$pub_key){
		$error = 'no pub_key';
		return false;
	}
	$cyphered="";
	$encrypted_keys="";
	$res = openssl_public_encrypt($string,$cyphered,$pub_key);
	if(!$res){
		$error ='_coult not cypher';
		return false;
	} 
	$cyphered2 = $cyphered;
	return $cyphered2;

}

function decrypt_private($priv_key_path,$string,&$error){


	$enc_data = $string;
	$pk = file_get_contents($priv_key_path);
	$p_k = openssl_pkey_get_private($pk);
	if(!$p_k){
		$error = 'no pk';	
		return false;	
	} 
	$decrypted="";
	$res = openssl_private_decrypt($enc_data,$decrypted,$p_k);
	if(!$res){
		$error = 'no decrypt';	
		return false;		
	} 	
	$string = $decrypted;
	return $string;
}




?>