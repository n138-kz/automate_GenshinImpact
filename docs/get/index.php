<?php
ini_set('display_errors', 0);
ini_set('error_log', 'php://stderr');
$processtime=['init'=>microtime(TRUE)];
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
function deleteDB_genshin_status_log_NotCompleteData(){
	$database['host'] = $_ENV['INTERNAL_DB_HOST'] ?? 'db';
	$database['port'] = $_ENV['INTERNAL_DB_PORT'] ?? '5432';
	$database['db']   = $_ENV['INTERNAL_DB_DATABASE'] ?? 'myapp';
	$database['user'] = $_ENV['INTERNAL_DB_USERNAME'] ?? 'postgres';
	$database['pass'] = $_ENV['INTERNAL_DB_PASSWORD'] ?? 'password';
	$database['conn'] = "pgsql:host={$database['host']};port={$database['port']};dbname={$database['db']}";
	$database['activetable'] = 'genshin_status_log';
	
	try {
		$pdo = new PDO($database['conn'], $database['user'], $database['pass'], [
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
		]);

		$pdo->beginTransaction();

		$sql = 'DELETE FROM genshin_status_log WHERE id IN ( SELECT index FROM genshin_status_log_view_php WHERE uid IS NULL OR nickname IS NULL OR current_resin IS NULL OR max_resin IS NULL OR full_recovery_at IS NULL);';
		$stmt = $pdo->prepare($sql);
		$stmt->execute();
		$pdo->commit();
	} catch (PDOException $e) {
		$pdo->rollback();
		error_log('Error has occured on '.__LINE__.', '.__FILE__);
		error_log('PDO Error has occured: '.$e->getMessage());
		return 'PDO Error has occured: '.$e->getMessage();
	}
	return NULL;
}
function main(){
	global $processtime;

	$document_root='http://172.21.83.191:{port}/?get=history';
	$config_file='/app'.'/users.json';

	if(!file_exists($config_file)){
		die('Not such File or Directory: '.$config_file.PHP_EOL);
	}

	$config_data=file_get_contents($config_file);
	$config_data=json_decode($config_data, TRUE);

	$document_root=str_replace('{port}', getContainerPublishPort('genshin_automate_web')??80, $document_root);

	if(isset($_GET['get'])&&$_GET['get']==='health'){
		$processtime['done']=microtime(TRUE);
		header('Content-Type: application/json');
		echo json_encode(['content'=>[
			'urls'=>[
				$document_root.'',
			],
		], 'header'=>['processtime'=>$processtime]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL;
		exit();
	}

	$result=[];

	$discord_notifies_payload_data = [
		'content' => null,
		'embeds' => [],
	];
	foreach($config_data as $v){
		$enka=fetch_enka_data($v['genshin']['uid']);

		$cookies = $v['hoyolab']['cookies'] ?? [];
		$hoyolab = fetch_hoyolab_daily_note($v['genshin']['uid'], $cookies);
		if($hoyolab['retcode']===0 && isset($hoyolab['data'])){
			$hoyolab=$hoyolab['data'];
		}

		delete_posted_messages();

		/* * Notice to Discord * */
		if(isset($v['discord']['webhook']['url'])&&$v['discord']['webhook']['url']!==''){
			if($hoyolab['current_resin']<$hoyolab['max_resin'] && $hoyolab['current_resin']/$hoyolab['max_resin']>=0.9){
				$embed = [];
				$embed['color'] = hexdec('FFA500');
				$embed['timestamp'] = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.v\Z');
				$embed['title'] = "{$enka['playerInfo']['nickname']}({$v['genshin']['uid']})の樹脂が溢れそう";
				$embed['description'] = "{$hoyolab['current_resin']}/{$hoyolab['max_resin']}";
				$embed['url'] = "{$document_root}&uid={$v['genshin']['uid']}";
				$fields = [];
				$field = [];
				$field['name'] = 'UID';
				$field['value'] = '['.$v['genshin']['uid'].'](https://enka.network/u/'.$v['genshin']['uid'].')';
				$field['inline'] = TRUE;
				array_push($fields, $field);
				$field = [];
				$field['name'] = 'Name';
				$field['value'] = $enka['playerInfo']['nickname'];
				$field['inline'] = TRUE;
				array_push($fields, $field);
				$field = [];
				$field['name'] = '';
				$field['value'] = '';
				$field['inline'] = FALSE;
				array_push($fields, $field);
				$field = [];
				$field['name'] = 'Resin';
				$field['value'] = $hoyolab['current_resin'].'/'.$hoyolab['max_resin'].'('.($hoyolab['current_resin']/$hoyolab['max_resin']*100).'%)';
				$field['inline'] = TRUE;
				array_push($fields, $field);
				$field = [];
				$field['name'] = 'Resin fully at';
				$field['value'] = '<t:'.(time()+(int)$hoyolab['resin_recovery_time']).':f> (<t:'.(time()+(int)$hoyolab['resin_recovery_time']).':R>)';
				$field['inline'] = TRUE;
				array_push($fields, $field);
				$embed['fields'] = $fields;

				array_push($discord_notifies_payload_data['embeds'], $embed);
			}
		}

		$item = [
			'enka'=>$enka,
			'hoyolab'=>$hoyolab,
		];

		insertDB_genshin_status_log([
			'rawjson' => json_encode($item),
		]);

		array_push($result, $item);
	}

	/* * Notice to Discord * */
	{
		$payload_json = json_encode($discord_notifies_payload_data);
		if(mb_strlen($payload_json)<=2000) {
			$ch = curl_init($v['discord']['webhook']['url'].'?wait=true');
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $payload_json);
			curl_setopt($ch, CURLOPT_HTTPHEADER, [
				'Content-Type: application/json',
			]);
			$curl_result = json_decode(curl_exec($ch), true);
			$curl_result['url']="{$v['discord']['webhook']['url']}/messages/" . ( $curl_result['id'] ?? 'null');
			$curl_result['curl_header']=curl_getinfo($ch);
			$curl_result['curl_header']['Retry-After']=curl_getinfo($ch, CURLINFO_RETRY_AFTER) ?? 0;

			insertDB_discord_webhooks_log([
				'rawjson' => json_encode($curl_result),
			]);
		}else{
			error_log('DISCORD push content has over 2k length: '.mb_strlen($payload_json));
		}
	}

	if((bool)ini_get('display_errors')===false){
		header('Content-Type: application/json');
		$processtime['done']=microtime(TRUE);
		echo json_encode(['content'=>$result, 'header'=>['processtime'=>$processtime]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL;
	}
}

if (realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'])) {
	main();
}
