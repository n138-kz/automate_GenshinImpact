<?php
ini_set('display_errors', 0);
ini_set('error_log', 'php://stderr');
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
		error_log("Socket HTTP response error: {$firstLine}");
	}

	error_log("Web Container Published Port: " . $publishedPort);
	return $publishedPort;
}
function main(){
	$document_root='http://172.21.83.191:{port}/?get=history';
	$config_file='/app'.'/users.json';

	if(!file_exists($config_file)){
		die('Not such File or Directory: '.$config_file.PHP_EOL);
	}

	$config_data=file_get_contents($config_file);
	$config_data=json_decode($config_data, TRUE);

	$document_root=str_replace('{port}', getContainerPublishPort('web'), $document_root);

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

	if(isset($_GET['get'])&&$_GET['get']==='history'){
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

			$html_output = '';
			$html_output .= '';
			$html_output .= '<!DOCTYPE html>';
			$html_output .= '<html>';
			$html_output .= '<head>';
			$html_output .= '<meta charset="utf-8">';
			$html_output .= '<meta http-equiv="X-UA-Compatible" content="IE=edge">';
			$html_output .= '<meta http-equiv="Pragma" content="no-cache">';
			$html_output .= '<meta http-equiv="Cache-Control" content="no-cache">';
			$html_output .= '<meta http-equiv="Expires" content="0">';
			$html_output .= '<meta http-equiv="refresh" content="30">';
			$html_output .= '<link rel="preconnect dns-prefetch" href="//github.com">';
			$html_output .= '<link rel="preconnect dns-prefetch" href="//n138-kz.github.io">';
			$html_output .= '<link rel="preconnect dns-prefetch" href="//code.jquery.com">';
			$html_output .= '<link rel="preconnect dns-prefetch" href="//accounts.google.com">';
			$html_output .= '<link rel="preconnect dns-prefetch" href="//www.google.com">';
			$html_output .= '<link rel="stylesheet" type="text/css" href="https://n138-kz.github.io/lib/master.css?t=0" />';
			$html_output .= '<script src="https://n138-kz.github.io/lib/master.js"></script>';
			$html_output .= '<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>';
			$html_output .= '<script src="https://accounts.google.com/gsi/client" async defer></script>';
			$html_output .= '<script src="https://www.google.com/recaptcha/api.js?render=6LfCHdcUAAAAAOwkHsW_7W7MfoOrvoIw9CXdLRBA"></script>';
			$html_output .= '<script src="https://n138-kz.github.io/lib/grecaptcha.js"></script>';
			$html_output .= '<style>';
			$html_output .= '/* CSS */';
			$html_output .= 'progress {';
			$html_output .= '  /* ブラウザ標準のデザインをリセット */';
			$html_output .= '  -webkit-appearance: none;';
			$html_output .= '  appearance: none;';
			$html_output .= '  height: 20px;';
			$html_output .= '}';
			$html_output .= '/* 背景色（トラック部分） */';
			$html_output .= 'progress::-webkit-progress-bar {';
			$html_output .= '  background-color: #eee;';
			$html_output .= '  border-radius: 4px;';
			$html_output .= '}';
			$html_output .= '';
			$html_output .= '/* デフォルトのバーの色（例: 青） */';
			$html_output .= 'progress::-webkit-progress-value {';
			$html_output .= '  background-color: #007bff;';
			$html_output .= '  border-radius: 4px;';
			$html_output .= '  transition: background-color 0.3s ease;';
			$html_output .= '}';
			$html_output .= '';
			$html_output .= '/* 条件ごとの色変更 */';
			$html_output .= 'progress[data-status="warning"]::-webkit-progress-value {';
			$html_output .= '  background-color: orange; /* 0.8超〜0.9以下: 黄色 */';
			$html_output .= '}';
			$html_output .= '';
			$html_output .= 'progress[data-status="danger"]::-webkit-progress-value {';
			$html_output .= '  background-color: red; /* 0.9超: 赤色 */';
			$html_output .= '}';
			$html_output .= '</style>';
			$html_output .= '</head>';
			$html_output .= '<body>';
			$html_output .= '<table border="1">';
			$html_output .= '<tr>';
			$html_output .= '<td>#</td>';
			$html_output .= '<th>updated_at</th>';
			$html_output .= '<th>player_name</th>';
			$html_output .= '<th>player_signature</th>';
			$html_output .= '<th>enka_uid</th>';
			$html_output .= '<th>current_resin</th>';
			$html_output .= '<th>max_resin</th>';
			$html_output .= '<th>current_resin_percent</th>';
			$html_output .= '<th>resin_recovery_time</th>';
			$html_output .= '</tr>';
			foreach($result as $r_k1 => $r_v1){
				$html_output .= '<tr>';
				$html_output .= '<td>#</td>';
				$html_output .= '<td>latest</td>';
				$html_output .= '<td>'.$r_v1['enka']['playerInfo']['nickname'].'</td>';
				$html_output .= '<td>'.$r_v1['enka']['playerInfo']['signature'].'</td>';
				$html_output .= '<td><a href="https://enka.network/u/'.$r_v1['enka']['uid'].'" target="enka.network.u.'.$r_v1['enka']['uid'].'">'.$r_v1['enka']['uid'].'</a></td>';
				$html_output .= '<td>'.$r_v1['hoyolab']['current_resin'].'</td>';
				$html_output .= '<td>'.$r_v1['hoyolab']['max_resin'].'</td>';
				$progress = $r_v1['hoyolab']['current_resin']/$r_v1['hoyolab']['max_resin'];
				if(false){
				}elseif($progress>=0.9){
					$progress='danger';
				}elseif($progress>=0.8){
					$progress='warning';
				}else{
					$progress='';
				}
				$html_output .= '<td><progress data-status="'.$progress.'" value="'.($r_v1['hoyolab']['current_resin']/$r_v1['hoyolab']['max_resin']).'"></progress>'.($r_v1['hoyolab']['current_resin']/$r_v1['hoyolab']['max_resin']*100).'%</td>';
				$html_output .= '<td>' . (new DateTimeImmutable('now', new DateTimeZone('Asia/Tokyo')))->modify('+' . (int)$r_v1['hoyolab']['resin_recovery_time'] . ' seconds')->format('n/d H:i:s') . '</td>';
				$html_output .= '</tr>';
			}
			$html_output .= '<tr>';
			$html_output .= '<td colspan="9"></td>';
			$html_output .= '</tr>';

			$result = $stmt->fetchAll(PDO::FETCH_ASSOC);
			foreach($result as $r_k1 => $r_v1){
				$html_output .= '<tr>';
				$html_output .= '<td>'.($r_k1+1).'</td>';
				$html_output .= '<td>'.(new DateTimeImmutable($r_v1['updated_at']))->setTimezone(new DateTimeZone('Asia/Tokyo'))->format('n/d H:i:s').'</td>';
				$html_output .= "<td>{$r_v1['nickname']}</td>";
				$html_output .= "<td>{$r_v1['signature']}</td>";
				$html_output .= "<td><a href=\"https://enka.network/u/{$r_v1['uid']}\" target=\"enka.network.u.{$r_v1['uid']}\">{$r_v1['uid']}</a></td>";
				$html_output .= "<td>{$r_v1['current_resin']}</td>";
				$html_output .= "<td>{$r_v1['max_resin']}</td>";
				$html_output .= '<td>'.$r_v1['current_resin_percent'].'</td>';
				$html_output .= '<td>'.(new DateTimeImmutable($r_v1['full_recovery_at']))->setTimezone(new DateTimeZone('Asia/Tokyo'))->format('n/d H:i:s').'</td>';
				$html_output .= '</tr>';
			}
			$html_output .= '</table>';
			$html_output .= '</body>';
			$html_output .= '</html>';

			if((bool)ini_get('display_errors')===false){
				header('Content-Type: text/html;utf-8');
				echo $html_output;
			}
		}catch(\PDOException $e){
			error_log('Error has occured on '.__LINE__.', '.__FILE__);
			error_log('PDO Error has occured: '.$e->getMessage());
			return 'PDO Error has occured: '.$e->getMessage();
		}
	}else{
		if((bool)ini_get('display_errors')===false){
			header('Content-Type: application/json');
			echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT).PHP_EOL;
		}
	}
}

if (realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'])) {
	main();
}
