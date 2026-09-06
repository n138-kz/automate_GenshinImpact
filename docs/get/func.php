<?php
function configuration_load($config_file){
	if(!file_exists($config_file)){
		http_response_code(500);
		die('Not such File or Directory: '.$config_file.PHP_EOL);
	}

	$config_data=file_get_contents($config_file);
	$config_data=json_decode($config_data, TRUE);

	return $config_data;
}
function fetch_enka_data(int $uid=0){
	$options=[
		'http' => [
			'method' => 'GET',
			'header' => 'User-Agent: php/'.phpversion(),
		],
	];
	$context = stream_context_create($options);
	return json_decode(file_get_contents("https://enka.network/api/uid/{$uid}", FALSE, $context), TRUE);
}
function get_genshin_server(int $uid): string {
	$first_digit = (int)substr((string)$uid, 0, 1);
	return match($first_digit) {
		1, 2, 5 => 'cn_gf01',      // 中国本土
		6       => 'os_usa',       // America
		7       => 'os_euro',      // Europe
		8, 18   => 'os_asia',      // Asia
		9       => 'os_cht',       // TW/HK/MO
		default => 'os_asia',
	};
}
function fetch_hoyolab_daily_note(int $uid, array $cookies): ?array {
	$server = get_genshin_server($uid);
	$url = "https://bbs-api-os.hoyolab.com/game_record/genshin/api/dailyNote?server={$server}&role_id={$uid}";

	// Cookieヘッダーの組み立て
	$cookie_str = sprintf(
		"ltuid_v2=%s; ltoken_v2=%s; ltmid_v2=%s; ltuid=%s; ltoken=%s; ltmid=%s;",
		$cookies['ltuid'] ?? '',
		$cookies['ltoken'] ?? '',
		$cookies['ltmid'] ?? '',
		$cookies['ltuid'] ?? '',
		$cookies['ltoken'] ?? '',
		$cookies['ltmid'] ?? ''
	);

	$options = [
		'http' => [
			'method' => 'GET',
			'header' => implode("\r\n", [
				'User-Agent: php/'.phpversion(),
				"Cookie: {$cookie_str}",
				"x-rpc-app_version: 1.5.0",
				"x-rpc-client_type: 5",
				"Accept: application/json",
			]),
			'ignore_errors' => true
		]
	];

	$context = stream_context_create($options);
	$res = file_get_contents($url, false, $context);

	return $res ? json_decode($res, true) : null;
}
function insertDB_genshin_status_log($datalist=[]){
	$database['host'] = $_ENV['INTERNAL_DB_HOST'] ?? 'db';
	$database['port'] = $_ENV['INTERNAL_DB_PORT'] ?? '5432';
	$database['db']   = $_ENV['INTERNAL_DB_DATABASE'] ?? 'myapp';
	$database['user'] = $_ENV['INTERNAL_DB_USERNAME'] ?? 'postgres';
	$database['pass'] = $_ENV['INTERNAL_DB_PASSWORD'] ?? 'password';
	$database['conn'] = "pgsql:host={$database['host']};port={$database['port']};dbname={$database['db']}";
	$database['activetable'] = 'genshin_status_log';

	if(!is_array($datalist)){
		$e='arg "datalist" is not list.';
		error_log('Error has occured on '.__LINE__.', '.__FILE__);
		error_log('Data Error: '.$e);
		return $e;
	}
	if(!isset($datalist['rawjson'])){
		$e='arg "datalist" key rawjson is null.';
		error_log('Error has occured on '.__LINE__.', '.__FILE__);
		error_log('Data Error: '.$e);
		return $e;
	}

	try {
		$pdo = new PDO($database['conn'], $database['user'], $database['pass'], [
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
		]);

		$pdo->beginTransaction();

		$sql = "INSERT INTO {$database['activetable']} (rawjson) VALUES (?);";
		$stmt = $pdo->prepare($sql);
		$stmt->execute([$datalist['rawjson']]);
		$pdo->commit();
	} catch (PDOException $e) {
		$pdo->rollback();
		error_log('Error has occured on '.__LINE__.', '.__FILE__);
		error_log('PDO Error has occured: '.$e->getMessage());
		return 'PDO Error has occured: '.$e->getMessage();
	}
	return NULL;
}
function insertDB_discord_webhooks_log($datalist=[]){
	$database['host'] = $_ENV['INTERNAL_DB_HOST'] ?? 'db';
	$database['port'] = $_ENV['INTERNAL_DB_PORT'] ?? '5432';
	$database['db']   = $_ENV['INTERNAL_DB_DATABASE'] ?? 'myapp';
	$database['user'] = $_ENV['INTERNAL_DB_USERNAME'] ?? 'postgres';
	$database['pass'] = $_ENV['INTERNAL_DB_PASSWORD'] ?? 'password';
	$database['conn'] = "pgsql:host={$database['host']};port={$database['port']};dbname={$database['db']}";
	$database['activetable'] = 'discord_webhooks_log';

	if(!is_array($datalist)){
		$e='arg "datalist" is not list.';
		error_log('Error has occured on '.__LINE__.', '.__FILE__);
		error_log('Data Error: '.$e);
		return $e;
	}
	if(!isset($datalist['rawjson'])){
		$e='arg "datalist" key rawjson is null.';
		error_log('Error has occured on '.__LINE__.', '.__FILE__);
		error_log('Data Error: '.$e);
		return $e;
	}

	try {
		$pdo = new PDO($database['conn'], $database['user'], $database['pass'], [
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
		]);

		$pdo->beginTransaction();

		$sql = "INSERT INTO {$database['activetable']} (rawjson) VALUES (?);";
		$stmt = $pdo->prepare($sql);
		$stmt->execute([$datalist['rawjson']]);
		$pdo->commit();
	} catch (PDOException $e) {
		$pdo->rollback();
		error_log('Error has occured on '.__LINE__.', '.__FILE__);
		error_log('PDO Error has occured: '.$e->getMessage());
		return 'PDO Error has occured: '.$e->getMessage();
	}
	return NULL;
}
class TooManyRequestException extends Exception {}
function delete_posted_messages(){
	$database['host'] = $_ENV['INTERNAL_DB_HOST'] ?? 'db';
	$database['port'] = $_ENV['INTERNAL_DB_PORT'] ?? '5432';
	$database['db']   = $_ENV['INTERNAL_DB_DATABASE'] ?? 'myapp';
	$database['user'] = $_ENV['INTERNAL_DB_USERNAME'] ?? 'postgres';
	$database['pass'] = $_ENV['INTERNAL_DB_PASSWORD'] ?? 'password';
	$database['conn'] = "pgsql:host={$database['host']};port={$database['port']};dbname={$database['db']}";
	$database['activetable'] = 'discord_webhooks_log';

	try {
		$pdo = new PDO($database['conn'], $database['user'], $database['pass'], [
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
		]);

		$pdo->beginTransaction();
		$beginTransactionAt = time();

		$sql = "SELECT INDEX, UPDATED_AT, WEBHOOKID, WEBHOOKURL FROM DISCORD_WEBHOOKS_LOG_VIEW WHERE WEBHOOKID IS NOT NULL AND DELETED = FALSE ORDER BY UPDATED_AT DESC OFFSET 1;";
		$stmt = $pdo->prepare($sql);
		$stmt->execute();

		$result = $stmt->fetchAll(PDO::FETCH_ASSOC);
		foreach($result as $v){
			$ch = curl_init($v['webhookurl']);
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_HTTPHEADER, [
				'Content-Type: application/json',
			]);
			$response = [
				'body' => curl_exec($ch),
				'curl_header' => curl_getinfo($ch),
			];
			if(($response['curl_header']['http_code']===204)||$response['curl_header']['http_code']===404){
				error_log("[{$beginTransactionAt}]HTTP DELETE {$v['webhookurl']} {$response['curl_header']['http_code']}");
				$sql = "UPDATE discord_webhooks_log SET DELETED = TRUE WHERE (rawjson->>'id') = ?;";
				$stmt = $pdo->prepare($sql);
				$stmt->execute([$v['webhookid']]);
			}elseif($response['curl_header']['http_code']===429) {
				throw new TooManyRequestException("[{$beginTransactionAt}]HTTP DELETE {$v['webhookurl']} {$response['curl_header']['http_code']}");
			}
			sleep(1);
		}

		$pdo->commit();
	} catch (TooManyRequestException $e) {
		$pdo->rollback();
		error_log('Error has occured on '.__LINE__.', '.__FILE__);
		error_log('TooManyRequestException Error has occured: '.$e->getMessage());
		return 'TooManyRequestException Error has occured: '.$e->getMessage();
	} catch (PDOException $e) {
		$pdo->rollback();
		error_log('Error has occured on '.__LINE__.', '.__FILE__);
		error_log('PDO Error has occured: '.$e->getMessage());
		return 'PDO Error has occured: '.$e->getMessage();
	}
	return NULL;
}
function getContainerPublishPort($targetContainer = 'web'){
	$socketPath = '/var/run/docker.sock';
	if (!file_exists($socketPath)) {
		error_log("Socket file does not exist: {$socketPath}");
		return null;
	}

	// 取得したい対象のコンテナ名またはサービス名
	// Docker Composeの場合は「プロジェクト名-サービス名-1」や「サービス名」で検索可能

	$fp = @stream_socket_client("unix://{$socketPath}", $errno, $errstr, 5);
	if (!$fp) {
		error_log("Socket connect error: {$errstr} ({$errno})");
		return null;
	}

	/* * Docker API (HTTP/1.1) リクエストを手動構築 * */
	$out  = "GET /containers/{$targetContainer}/json HTTP/1.1\r\n";
	$out .= "Host: localhost\r\n";
	$out .= "Connection: Close\r\n\r\n";
	fwrite($fp, $out);

	/* * レスポンス全件を取得 * */
	$response = '';
	while (!feof($fp)) {
		$response .= fgets($fp, 1024);
	}
	fclose($fp);

	/* * HTTPヘッダーとレスポンスボディ（JSON）を分離 * */
	$parts = explode("\r\n\r\n", $response, 2);
	$headers = $parts[0] ?? '';
	$body = $parts[1] ?? '';

	$publishedPort=NULL;

	if (strpos($headers, '200 OK') !== false && $body) {
		/* * 転送符号化(Chunked)が含まれる場合があるため整形 * */
		if (strpos($headers, 'Transfer-Encoding: chunked') !== false) {
			$body = preg_replace('/^[0-9a-fA-F]+\r\n/', '', $body);
			$body = preg_replace('/\r\n0\r\n\r\n$/', '', $body);
		}

		$data = json_decode(trim($body), true);
		$publishedPort = $data['NetworkSettings']['Ports']['80/tcp'][0]['HostPort'] ?? null;
	} else {
		$firstLine = strtok($headers, "\r\n");
		error_log("[".__FUNCTION__."] Socket HTTP response error: {$firstLine}");
	}

	error_log("[".__FUNCTION__."] Web Container Published Port: " . $publishedPort);
	return $publishedPort;
}

if (realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'])) {
	http_response_code(403);
	header('Content-Type: application/json');
	die(json_encode(['error'=>http_response_code(), 'message'=>'403 Forbidden']));
}
