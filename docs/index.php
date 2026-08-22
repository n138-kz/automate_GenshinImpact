<?php
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
function flatten_json($data, string $parent_key = ''): array {
  $items = [];

  // 連想配列（連想キーを持つ配列）の判定
  if (is_array($data) && (empty($data) || array_keys($data) !== range(0, count($data) - 1))) {
    foreach ($data as $k => $v) {
      $new_key = $parent_key !== '' ? "{$parent_key}.{$k}" : $k;
      $items = array_merge($items, flatten_json($v, $new_key));
    }
  }
  // インデックス配列（リスト）の判定
  elseif (is_array($data)) {
    foreach ($data as $item) {
      $items = array_merge($items, flatten_json($item, $parent_key));
    }
  }
  // スカラー値（文字列、数値、真偽値など）
  else {
    // nullやbooleanなどの出力調整が必要な場合はここでハンドリング
    $val_str = is_bool($data) ? ($data ? 'True' : 'False') : (string)$data;
    $items[] = "{$parent_key}={$val_str}";
  }

  return $items;
}
function main(){
  $config_file='/app'.'/users.json';

  if(!file_exists($config_file)){
    die('Not such File or Directory: '.$config_file.PHP_EOL);
  }

  $config_data=file_get_contents($config_file);
  $config_data=json_decode($config_data, TRUE);

  $result=[];
  foreach($config_data as $v){
    $enka=fetch_enka_data($v['genshin']['uid']);

    $cookies = $v['hoyolab']['cookies'] ?? [];
    $hoyolab = fetch_hoyolab_daily_note($v['genshin']['uid'], $cookies);
    if($hoyolab['retcode']===0 && isset($hoyolab['data'])){
      $hoyolab=$hoyolab['data'];
    }

    array_push($result, [
      'enka'=>$enka,
      'hoyolab'=>$hoyolab,
      'flatten_json'=>flatten_json([
        'enka'=>$enka,
        'hoyolab'=>$hoyolab,
      ]),
    ]);
  }
  echo json_encode($result).PHP_EOL;
}
if (realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'])) {
  main();
}
