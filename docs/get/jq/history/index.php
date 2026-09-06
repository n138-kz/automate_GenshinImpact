<?php
ini_set('display_errors', 0);
ini_set('error_log', 'php://stderr');
$processtime=['init'=>microtime(TRUE)];

require_once(__DIR__.'/../../func.php');

function main(){
	global $processtime;

	$config_file='/app'.'/users.json';

	$config_data=configuration_load($config_file);

	$result=[];

	try {
		$database_host = $_ENV['INTERNAL_DB_HOST'] ?? 'db';
		$database_port = $_ENV['INTERNAL_DB_PORT'] ?? '5432';
		$database_db   = $_ENV['INTERNAL_DB_DATABASE'] ?? 'myapp';
		$database_user = $_ENV['INTERNAL_DB_USERNAME'] ?? 'postgres';
		$database_pass = $_ENV['INTERNAL_DB_PASSWORD'] ?? 'password';
		$database_conn = "pgsql:host={$database_host};port={$database_port};dbname={$database_db}";

		$pdo = new PDO($database_conn, $database_user, $database_pass, [
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
		]);
		$sql = "SELECT * FROM GENSHIN_STATUS_LOG_VIEW_PHP WHERE UPDATED_AT >= NOW() - INTERVAL '1 day' ORDER BY UPDATED_AT DESC;";
		$stmt = $pdo->prepare($sql);
		$stmt->execute();
		$result = $stmt->fetchAll(PDO::FETCH_ASSOC);

		header('Content-Type: application/json;utf-8');
		$processtime['done']=microtime(TRUE);
		echo json_encode(['content'=>$result, 'header'=>['processtime'=>$processtime]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL;
	}catch(\PDOException $e){
		error_log('Error has occured on '.__LINE__.', '.__FILE__);
		error_log('PDO Error has occured: '.$e->getMessage());
		return 'PDO Error has occured: '.$e->getMessage();
	}
}

if (realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'])) {
	main();
}
