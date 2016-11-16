<?php
/*

Call this script remotely (including ?make_backup=true&file=some_file.sql) and it will dump MYSQL database w wp-config.php credentials & encrypt (optional) with random password (saved and encrypted with some public key: public.pem). inform later to callback



*/

set_time_limit(0);
ini_set('memory_limit','512M');
define('URL_CALLBACK','XXX.XXX.XXX.XXX');

$public_key = 'public.pem';
$encrypt = false;

$session_pass = generateRandomString(16);

if(isset($_GET['make_backup']) && isset($_GET['file']) /*&& isset($_GET['callback']) && isset($_GET['callback_false'])*/){
	
	
	
	if(isset($_GET['encrypt'])) $encrypt = true;
	$make_backup = $_GET['make_backup'];
	$file = $_GET['file'];
	$callback = isset($_GET['callback'])? $_GET['callback']: ''; 
	$callback_false = isset($_GET['callback_false'])? $_GET['callback_false']: '';


	$cred = get_wp_credentials('');
	if(!$cred){
		die('Credentials failed');
	}
	


	if(isset($_GET['exclude_tables'])){
		$exclude_tables = $_GET['exclude_tables'];
	}
	$backuper = new backup();
	$backuper->target_host = $cred['db_host'];
	$backuper->target_database = $cred['db_name'];
	$backuper->target_user = $cred['db_user'];
	$backuper->target_password = $cred['db_pass'];
	$backuper->exclude_tables_data = [];
	if($exclude_tables){
		$exclude_tables = explode(',',$exclude_tables);
		foreach($exclude_tables as $e){
			$backuper->exclude_tables_data[] = $e;
		}
	}

	$backuper->log_file = basename($_GET['file']).".log";
	$str = time();
	$file = basename($_GET['file']);
	//clean_old(DIR.'something');

	//$sql_dump = $backuper->push_dump_ssl($file); // si tenemos ssl

	$sql_dump = $backuper->push_dump($file);
	
	if($encrypt){
		$file_to_encrypt = $file;
		$encrypted_file= $file.'.enc.txt';
		
		
		$error ="";
		if(file_exists('public_encrypted_pass.txt')) unlink('public_encrypted_pass.txt');
		if(file_exists('public_encrypted_pass_ERROR.txt')) unlink('public_encrypted_pass_ERROR.txt');
		$encrypted_pass = encrypt_public($public_key,$session_pass,$error); 
		if(!$encrypted_pass) file_put_contents('public_encrypted_pass_ERROR.txt',$error);
		else{
			$encrypted_pass = base64_encode($encrypted_pass);
			file_put_contents('public_encrypted_pass.txt',$encrypted_pass);
		} 
		
		$key = $session_pass;
		$crypt = new Encryption($key);
		$error = "";
		$res = $crypt->encrypt_file($file_to_encrypt,$encrypted_file,$error);
		if(!$res){
			die('KO: ERROR encrypting: '.$error);
		}
	}
	
	if($sql_dump){
		$res = file_put_contents($backuper->log_file,"OK: ".$sql_dump,FILE_APPEND);
		die(basename($file));
	}else{
		$res = file_put_contents($backuper->log_file,"KO: ".$backuper->get_error(),FILE_APPEND);
		die('false');
	}

	if(!$res){

		// call to callback and inform

		if($callback_false){
			file_get_contents($callback_false.'?mensaje=ko');
		}




	}else{
		
		if($callback){
			file_get_contents($callback.'?mensaje=ok');
		}
		
		die('Nothng to do');
	}

}






function get_wp_credentials($dir_wp_config){



	$config = file_get_contents($dir_wp_config.'wp-config.php');
	$config = str_replace('<?php','',$config);

	$lines = explode('
',$config);

	$db_name = "";
	$db_user = "";
	$db_pass = "";
	$db_host = "";
	$db_charset = "";
	foreach($lines as $l){
		if(strpos($l,'define')!==false){
			//	echo $l;
			//	echo '<br>';
		}
		if(strpos($l,'DB_NAME')!==false){
			$db_name = substr($l,strpos($l,',')+3, strpos(substr($l,strpos($l,',')+3),"'"));
		}
		if(strpos($l,'DB_USER')!==false){
			$db_user = substr($l,strpos($l,',')+3, strpos(substr($l,strpos($l,',')+3),"'"));
		}
		if(strpos($l,'DB_PASSWORD')!==false){
			$db_pass = substr($l,strpos($l,',')+3, strpos(substr($l,strpos($l,',')+3),"'"));
		}
		if(strpos($l,'DB_HOST')!==false){
			$db_host = substr($l,strpos($l,',')+3, strpos(substr($l,strpos($l,',')+3),"'"));
		}
		if(strpos($l,'DB_CHARSET')!==false){
			$db_charset = substr($l,strpos($l,',')+3, strpos(substr($l,strpos($l,',')+3),"'"));
		}

	}
	return Array(
		'db_name'=>$db_name,
		'db_user'=>$db_user,
		'db_pass'=>$db_pass,
		'db_host'=>$db_host,
		'db_charset'=>$db_charset
	);



}

 







class backup  {

	private $dumpfile;
	private $server_id;
	public $target_host;
	public $target_database;
	public $target_user;
	public $target_password;


	public $error;
	public function __construct() 
	{
		$this->dbuser = "dbuser";
		$this->dbpass = "dbpass";
		$this->dbhost = "localhost";
		$this->dbname = "dbname";

		$this->backuppath = ".";

		$this->stamp = time();
	 
	} 

	public function get_error(){
		$error=$this->error;
		$this->error = '';
		return $error;
	}

	public function push_dump($file) 
	{ 
		$sql = null;
		$sql_structure = null;
		$sql_data = null;
		$iii = 0;

		$total_filas = 0;

		$link = mysqli_connect($this->target_host, $this->target_user, $this->target_password,$this->target_database,'3306'); 
		if(!$link){
			$this->error ='Couldt not connect to '.$this->target_host;
			die('false');
			return false;
		};

		//mysqli_select_db($this->target_database); 

		//$tables = mysqli_list_tables($this->target_database); 
		$tables = mysqli_query($link,"SHOW TABLES"); 
		while ($td = mysqli_fetch_array($tables)) 
		{ 
			$table = $td[0]; 
			$r = mysqli_query($link,"SHOW CREATE TABLE `$table`"); 
			if ($r) 
			{ 
				if($iii++>0) $sql_structure .= ";\n\n";
				$d = mysqli_fetch_array($r); 
				$sql_structure .= $d[1];
			} 

			$insert_sql = null;

			$sql_structure .=";\n\n\n";			
			$res = file_put_contents($file,$sql_structure,FILE_APPEND); // ,FILE_APPEND);

			if(in_array($table,$this->exclude_tables_data)){
				//echo 'Excluding '.$table.'\n';
				$sql_structure = $sql_data = "";
				continue;
			}else{
				//echo 'Including '.$table.'\n';				

			}

			$filas = 0;
			$ini = 0;
			$limit = 5000;
			$table_rows = mysqli_query($link,"SELECT count(*) as total FROM `$table`"); 			
			$table_rows = mysqli_fetch_assoc($table_rows);
			$table_rows = $table_rows['total'];

			do{
				$insert_sql = "";
				$table_query = mysqli_query($link,"SELECT * FROM `$table` limit ".$ini.", ".$limit."");
				while ($fetch_row = mysqli_fetch_row($table_query)) 
				{ 
					$insert_sql .= "INSERT INTO `$table` VALUES("; 
					$iiii = 0;
					foreach ($fetch_row as $qry) 
					{ 
						if ($iiii++>0) $insert_sql .=", ";
						$insert_sql .=  "'" . mysqli_real_escape_string($link,$qry) . "'";
					} 
					$insert_sql .= ");\n";
					$res = file_put_contents($file,$insert_sql,FILE_APPEND); // ,FILE_APPEND);
					$insert_sql = '';
					//$this->update_progress();
					$filas++;
					$total_filas++;
				} 	
				$ini+=$limit;
			}while($ini<$table_rows);

			//$save = $sql_structure . ";\n\n\n" . $insert_sql;
			//$res = file_put_contents($file,$save,FILE_APPEND); // ,FILE_APPEND);
			$sql_structure = $sql_data = "";
		}
		mysqli_close ($link);
		//return $sql_structure . ";\n\n\n" . $sql_data; 
		return $total_filas;
	} 












}






// SYMETRIC ENCR
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
			//$final_encrypted_string.=$partial_encrypted.'\n';
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









// ASSYM ENCR

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


function generateRandomString($length = 10) {
	$characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
	$charactersLength = strlen($characters);
	$randomString = '';
	for ($i = 0; $i < $length; $i++) {
		$randomString .= $characters[rand(0, $charactersLength - 1)];
	}
	return $randomString;
}


