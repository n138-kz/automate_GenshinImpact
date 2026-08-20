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

$config_file=__DIR__.'/users.json';

if(!file_exists($config_file)){
  die('Not such File or Directory: '.$config_file.PHP_EOL);
}

$config_data=file_get_contents($config_file);
$config_data=json_decode($config_data, TRUE);

foreach($config_data as $v){
  $enka=fetch_enka_data($v['genshin']['uid']);
  unset($enka['playerInfo']['showAvatarInfoList']);
  unset($enka['playerInfo']['showNameCardIdList']);
  unset($enka['avatarInfoList']);

  $cookies = $v['hoyolab']['cookies'] ?? [];
  $hoyolab = fetch_hoyolab_daily_note($v['genshin']['uid'], $cookies);
  if($hoyolab['retcode']===0 && isset($hoyolab['data'])){
    $hoyolab=$hoyolab['data'];
  }

  echo json_encode([
    'enka'=>$enka,
    'hoyolab'=>$hoyolab,
  ]).PHP_EOL;
}
