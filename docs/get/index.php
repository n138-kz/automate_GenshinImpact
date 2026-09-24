<?php
ini_set('display_errors', 0);
ini_set('error_log', 'php://stderr');
$processtime=['init'=>microtime(TRUE)];

require_once(__DIR__.'/func.php');

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
