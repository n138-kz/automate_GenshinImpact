<?php
ini_set('display_errors', 0);
ini_set('error_log', 'php://stderr');
$processtime=['init'=>microtime(TRUE)];

require_once(__DIR__.'/../func.php');

function main(){
	global $processtime;

	if((bool)ini_get('display_errors')===false){
		$redirectUrl=$_SERVER['REQUEST_SCHEME'].'://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'].'latest/';

		$result=file_get_contents($redirectUrl);
		$result=json_decode($result,true);
		$processtime['done']=microtime(TRUE);
		$result['header']=[
			'redirect'=>[
				'url'=>$_SERVER['REQUEST_SCHEME'].'://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'].'latest/',
			],
			'processtime'=>[
				'init'=>$processtime['init'],
				'done'=>$processtime['done'],
			],
		];
		$result=json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		header('Content-Type: application/json;utf-8');
		die($result);
	}
}

if (realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'])) {
	main();
}
