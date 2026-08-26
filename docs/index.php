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

    $item = [
      'enka'=>$enka,
      'hoyolab'=>$hoyolab,
    ];

    array_push($result, $item);
    $dbDataQueue[] = flatten_json($item);
  }

  // 1. レスポンスを出力
  header('Content-Type: application/json');
  echo json_encode($result).PHP_EOL;

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
