<?php

//DUMP REMOTE MYSQL database: structure & data


//php -f remote_mysql_dump.php DB_HOST DB_USER  DB_PASS DB_DB FOLDER EXCLUDE,TABLES,CSV,LIST



//[0] -> proc_sql.php
//[1] -> host
//[2] -> user
//[3] -> pass
//[4] -> db
//[5] -> dir

function error($error){
	die($error);
}

function ok($ok){
	die($ok);
}


//echo '!'.$argv[1].'__'.$argv[2];

if(!isset($argv[0])){
	error('error 1');
}
if(!isset($argv[1])){
	error('error 2');
}
if(!isset($argv[2])){
	error('error 3');
}
if(!isset($argv[3])){
	error('error 4');
}
if(!isset($argv[4])){
	error('error 5');
}
if(!isset($argv[5])){
	error('error 6');
}
$exclude_tables ="";
if(isset($argv[6])){
	$exclude_tables = $argv[6];
}

$host = $argv[1];
$user = $argv[2];
$pass = $argv[3];
$db = $argv[4];
$dir = $argv[5];


define('DIR','/home/backups/sql/'.$dir.'/');
define('DIR_LOGS',DIR.'_logs/');

if(!file_exists(DIR)){
	mkdir(DIR);
}
if(!file_exists(DIR_LOGS)){
	mkdir(DIR_LOGS);
}





/*
$backup_ae = false;
$backup_nativox = false;
/**/

//

$backuper = new backup();
$backuper->target_host = $host;
$backuper->target_database = $db;
$backuper->target_user = $user;
$backuper->target_password = $pass;
$backuper->exclude_tables_data = [];
if($exclude_tables){
	$exclude_tables = explode(',',$exclude_tables);
	foreach($exclude_tables as $e){
		$backuper->exclude_tables_data[] = $e;
	}
}


print_r($backuper->exclude_tables_data);
$backuper->log_file = DIR_LOGS.''.$dir.'_'.date('Ymd').".log";


$str = time();
$file = DIR.$db.'_'.date('Ymd').'.sql';
//clean_old(DIR.'something');


$sql_dump = $backuper->push_dump($file);
//$sql_dump = $backuper->push_dump_ssl($file); // si tenemos ssl


if($sql_dump){
	file_put_contents($backuper->log_file,"OK: ".$sql_dump,FILE_APPEND);
}else{
	file_put_contents($backuper->log_file,"KO: ".$backuper->get_error(),FILE_APPEND);
}
ok($file);
//$res = file_put_contents("AE_".$str.'.sql',$sql_dump,FILE_APPEND); // ,FILE_APPEND);





class core {

	public  $host0;
	public  $host1;
	public $ip;
	public $stamp;
	public $human_stamp;
	public $localpath;
	public $backup_path;

	public $log_file;

	protected $db_link;
	protected $dbcon;
	protected $dbuser;
	protected $dbpass;
	protected $dbhost;
	protected $dbname;

	public function __construct() 
	{	
		// Unix timestamp. Used for logging
		$this->stamp = time();
		// A human readable time stamp
		$this->human_stamp = date('r');
	} 

	// connect to db
	public function db_connect()  
	{
	
	}

	
}

class backup extends core {

	private $dumpfile;
	private $server_id;
	public $target_host;
	public $target_database;
	public $target_user;
	public $target_password;

	public $error;
	public function __construct() 
	{
		parent::__construct();
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
			error('error 7');
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
				echo 'Excluding '.$table.'\n';
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






	public function push_dump_ssl($file) 
	{ 
		$sql = null;
		$sql_structure = null;
		$sql_data = null;
		$iii = 0;


		$db = mysqli_init();
		mysqli_options ($db, MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, true);
		//$db->ssl_set('/Users/carlos/.ssh/id_rsa', '/Users/carlos/.ssh/id_rsa.pub', '/Users/carlos/Desktop/ae_certificate.crt', NULL, NULL);
		$db->ssl_set('', '', '', NULL, NULL);

		$link = mysqli_real_connect($db, 
									$this->target_host, 
									$this->target_user,
									$this->target_password, 
									$this->target_database, 
									3306, 
									NULL, 
									MYSQLI_CLIENT_SSL);
		if (!$link){
			$this->error ='Could not connect';
			error('error 8');
			return false;
		}else{

		}

		/*
		// check we are over ssl
		$res = mysqli_query($db,"show status like 'Ssl_cipher'; ");
		$res = mysqli_fetch_assoc($res);
		print_r($res);
		die();

		*/
		$total_filas = 0;
		$tables = mysqli_query($db,"SHOW TABLES"); 
		while ($td = mysqli_fetch_array($tables)) 
		{ 		
			$table = $td[0]; 
			$r = mysqli_query($db,"SHOW CREATE TABLE `$table`"); 
			if ($r) 
			{ 
				if($iii++>0) $sql_structure .= ";\n\n";
				$d = mysqli_fetch_array($r); 
				$sql_structure .= $d[1];
			} 

			$insert_sql = null;

			if(in_array($table,$this->exclude_tables_data)){
				continue;
			}

			//$table_query = mysqli_query($db,"SELECT * FROM `$table`"); 
			$table_query = mysqli_real_query($db,"SELECT * FROM `$table`"); 
			$table_query = mysqli_use_result($db);


			//echo "TABLE: ".$table.'
			//';
			//print_r($table_query);

			$sql_structure .=";\n\n\n";			
			$res = file_put_contents($file,$sql_structure,FILE_APPEND); // ,FILE_APPEND);

			$filas =0;

			$table_rows = mysqli_query($link,"SELECT count(*) as total FROM `$table` "); 			
			$table_rows = mysqli_fetch_assoc($table_rows);
			$table_rows = $table_rows['total'];

			$filas = 0;
			$ini = 0;
			$limit = 5000;
			do{
				$insert_sql = "";
				$table_query = mysqli_query($link,"SELECT * FROM `$table` limit ".$ini.", ".$limit." "); 
				while ($fetch_row = mysqli_fetch_row($table_query)) 
				{ 
					$insert_sql .= "INSERT INTO `$table` VALUES("; 
					$iiii = 0;
					foreach ($fetch_row as $qry) 
					{ 
						if ($iiii++>0) $insert_sql .=", ";
						$insert_sql .=  "'" . mysqli_real_escape_string($db,$qry) . "'";
					} 
					$insert_sql .= ");\n";
					$res = file_put_contents($file,$insert_sql,FILE_APPEND); // ,FILE_APPEND);
					$insert_sql = '';
					//$this->update_progress();
					/*	echo $filas.'
				';
				*/
					$filas++;
					$total_filas++;
				} 	
				$ini+=$limit;
			}while($ini<$table_rows);
			//$save = $sql_structure . ";\n\n\n" . $insert_sql;
			//$res = file_put_contents($file,$save,FILE_APPEND); // ,FILE_APPEND);
			$sql_structure = $sql_data = "";
		}
		mysqli_close ($db);
		return $total_filas; // $sql_structure . ";\n\n\n" . $sql_data; 
	} 







}

?>