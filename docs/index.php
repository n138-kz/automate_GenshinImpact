<?php
ini_set('display_errors', 0);
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
function insertDB($datalist=[]){
  $database_host = $_ENV['INTERNAL_DB_HOST'] ?? 'db';
  $database_port = $_ENV['INTERNAL_DB_PORT'] ?? '5432';
  $database_db   = $_ENV['INTERNAL_DB_DATABASE'] ?? 'myapp';
  $database_user = $_ENV['INTERNAL_DB_USERNAME'] ?? 'postgres';
  $database_pass = $_ENV['INTERNAL_DB_PASSWORD'] ?? 'password';
  $database_conn = "pgsql:host={$database_host};port={$database_port};dbname={$database_db}";

  if(!is_array($datalist)){
    return 'arg "datalist" is not list.';
  }

  try {
    $pdo = new PDO($database_conn, $database_user, $database_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    /* * get id (RETURNING id を使うことで安全かつ確実に取得) */
    $sql = 'INSERT INTO users (updated_at) VALUES (CURRENT_TIMESTAMP) RETURNING id;';
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $newId = $stmt->fetchColumn();

    for($i=0;$i<count($datalist);$i++){
      $datalist[$i]=explode('=', $datalist[$i]);
      $datalist[$i][0]=str_replace('.', '_', $datalist[$i][0]);
    }

    /* * not exist column then add column */
    foreach ($datalist as $value) {
        $table = 'users';
        $column = strtolower($value[0]);
        $valuedata = $value[1];

  if($column===''){
      continue;
  }

        $sql = 'SELECT COUNT(*)
                FROM information_schema.columns
                WHERE table_catalog = current_database()
                  AND table_schema = \'public\'
                  AND table_name = ?
                  AND column_name = ?';

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$table, $column]);
        $exists = (int)$stmt->fetchColumn() > 0;

        if (!$exists) {
            $datatype='TEXT';
            $datatype=is_int($valuedata)?'INT':$datatype;
            $datatype=is_float($valuedata)?'DOUBLE PRECISION':$datatype; // PostgresはDOUBLEではなくDOUBLE PRECISION
            $sql = "ALTER TABLE \"{$table}\" ADD COLUMN \"{$column}\" {$datatype} DEFAULT NULL;";
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
        }
    }

    /* * update data */
    foreach($datalist as $value){
      $column = strtolower($value[0]);
      $valuedata = $value[1];

      if($column===''){
        continue;
      }

      $sql = "UPDATE \"users\" SET \"{$column}\" = ? WHERE id = ?;";
      $stmt = $pdo->prepare($sql);
      $stmt->execute([$valuedata, $newId]);
    }
  } catch (PDOException $e) {
    error_log('PDO Error has occured: '.$e->getMessage());
    return 'PDO Error has occured: '.$e->getMessage();
  }

  return NULL;
}

function main(){
  $config_file='/app'.'/users.json';

  if(!file_exists($config_file)){
    die('Not such File or Directory: '.$config_file.PHP_EOL);
  }

  $config_data=file_get_contents($config_file);
  $config_data=json_decode($config_data, TRUE);

  $result=[];
  $dbDataQueue = []; // バックグラウンド処理用にデータを一時保存

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

    /* * Notice to Discord * */
    if(isset($v['discord']['webhook']['url'])&&$v['discord']['webhook']['url']!==''){
      if($hoyolab['current_resin']<$hoyolab['max_resin'] && $hoyolab['current_resin']/$hoyolab['max_resin']>=0.95){
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

    if(FALSE){
    }elseif(isset($_GET['get'])&&$_GET['get']==='latest'){
      /* * latest value */
      $item = [
        'UPDATED_AT'=>(new DateTimeImmutable('now', new DateTimeZone('Asia/Tokyo')))->format('Y-m-d\TH:i:s.vP'),
        'PLAYER_NAME'=>$enka['playerInfo']['nickname'],
        'PLAYER_SIGNATURE'=>$enka['playerInfo']['signature'],
        'ENKA_UID'=>$enka['uid'],
        'CURRENT_RESIN'=>$hoyolab['current_resin'],
        'MAX_RESIN'=>$hoyolab['max_resin'],
        'CURRENT_RESIN_PERCENT'=>($hoyolab['current_resin']/$hoyolab['max_resin']*100),
        'RESIN_RECOVERY_TIME'=>sprintf('%d:%02d:%02d',
          floor((int)$hoyolab['resin_recovery_time']/3600),
          floor(((int)$hoyolab['resin_recovery_time']%3600)/60),
          (int)$hoyolab['resin_recovery_time']%60
        ),
      ];
      if($item['CURRENT_RESIN']===$item['MAX_RESIN']){
        unset($item['RESIN_RECOVERY_TIME']);
      }
    }else{
      $item = [
        'enka'=>$enka,
        'hoyolab'=>$hoyolab,
      ];
    }

    array_push($result, $item);
    $dbDataQueue[] = flatten_json($item);
  }

  /* * Notice to Discord * */
  {
    $payload_json = json_encode($discord_notifies_payload_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    if(mb_strlen($payload_json)<=2000) {
      $ch = curl_init($v['discord']['webhook']['url'].'?wait=true');
      curl_setopt($ch, CURLOPT_POST, true);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $payload_json);
      curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
      ]);
      if(count($discord_notifies_payload_data['embeds'])>0){
        error_log($payload_json);
        $curl_result = json_decode(curl_exec($ch), true);
        error_log(json_encode($curl_result));
      }
    }else{
      error_log('DISCORD push content has over 2k length');
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
      $sql = 'SELECT * FROM USERS_CURRENT_RESIN_LOG_VIEW LIMIT 2000;';
      $stmt = $pdo->prepare($sql);
      $stmt->execute();

      $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
      $html_output .= '</head>';
      $html_output .= '<body>';
      $html_output .= '<table border="1">';
      $html_output .= '<tr>';
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
        $html_output .= '<td>'.$r_v1['updated_at'].'</td>';
        $html_output .= '<td>'.$r_v1['player_name'].'</td>';
        $html_output .= '<td>'.$r_v1['player_signature'].'</td>';
        $html_output .= '<td><a href="https://enka.network/u/'.$r_v1['enka_uid'].'" target="enka.network.u.'.$r_v1['enka_uid'].'">'.$r_v1['enka_uid'].'</a></td>';
        $html_output .= '<td>'.$r_v1['current_resin'].'</td>';
        $html_output .= '<td>'.$r_v1['max_resin'].'</td>';
        $html_output .= '<td>'.$r_v1['current_resin_percent'].'</td>';
        $html_output .= '<td>'.$r_v1['resin_recovery_time'].'</td>';
        $html_output .= '</tr>';
      }
      $html_output .= '</table>';
      $html_output .= '</body>';
      $html_output .= '</html>';

      echo $html_output;
    }catch(\PDOException $e){
      error_log('PDO Exception: '.$e->getMessage());
    }
  }else{
    // 1. レスポンスを出力
    header('Content-Type: application/json');
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT).PHP_EOL;
  }

  // 2. ブラウザ・クライアントへ即座にレスポンスを返し接続を切断する
  if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
  } else {
    // CLIやFastCGI以外の環境用のフラッシュ処理
    if (ob_get_level() > 0) {
      ob_end_flush();
    }
    flush();
  }

  // 3. 接続切断後にバックグラウンドでDB処理を実行
  foreach ($dbDataQueue as $flatData) {
    insertDB($flatData);
  }
}

if (realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'])) {
  main();
}
