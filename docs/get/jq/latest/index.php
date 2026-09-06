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

	{
		foreach($config_data as $v){
			$enka=fetch_enka_data($v['genshin']['uid']);

			$cookies = $v['hoyolab']['cookies'] ?? [];
			$hoyolab = fetch_hoyolab_daily_note($v['genshin']['uid'], $cookies);
			if($hoyolab['retcode']===0 && isset($hoyolab['data'])){
				$hoyolab=$hoyolab['data'];
			}

			$item = [
				'enka'=>$enka,
				'hoyolab'=>$hoyolab,
			];

			array_push($result, $item);
		}
	}

	{
		foreach($result as $r_k1 => $r_v1){
			$item = [];
			$item['updated_at']=time();
			$item['player_name']=$r_v1['enka']['playerInfo']['nickname'];
			$item['player_signature']=$r_v1['enka']['playerInfo']['signature'];
			$item['enka_uid']=$r_v1['enka']['uid'];
			$item['current_resin']=$r_v1['hoyolab']['current_resin'];
			$item['max_resin']=$r_v1['hoyolab']['max_resin'];
			$item['resin_recovery_time']=(int)$r_v1['hoyolab']['resin_recovery_time'];

			$result[$r_k1] = $item;
		}

		if((bool)ini_get('display_errors')===false){
			header('Content-Type: application/json;utf-8');
			$processtime['done']=microtime(TRUE);
			echo json_encode(['content'=>$result, 'header'=>['processtime'=>$processtime]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL;
		}
	}
}

if (realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'])) {
	main();
}
