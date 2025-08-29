<?php
// データベース設定（ロリポップ用）
define('DB_HOST', 'mysql325.phy.lolipop.lan');
define('DB_NAME', 'LAA0956269-bingo');
define('DB_USER', 'LAA0956269');
define('DB_PASS', 'marie2011');

// デバッグモード（本番環境では false にする）
define('DEBUG_MODE', true);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// プリフライトリクエストへの対応
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

function getDB() {
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
        
        if (DEBUG_MODE) {
            error_log("Database connection successful");
        }
        
        return $pdo;
    } catch (PDOException $e) {
        $error_message = 'データベース接続エラー: ' . $e->getMessage();
        
        if (DEBUG_MODE) {
            error_log($error_message);
            // デバッグ情報をレスポンスに含める
            echo json_encode([
                'success' => false,
                'error' => $error_message,
                'debug_info' => [
                    'host' => DB_HOST,
                    'database' => DB_NAME,
                    'user' => DB_USER
                ]
            ]);
        }
        
        throw new Exception($error_message);
    }
}

// エラーハンドリング関数
function handleError($message, $details = null) {
    $response = [
        'success' => false,
        'error' => $message
    ];
    
    if (DEBUG_MODE && $details) {
        $response['debug_info'] = $details;
        error_log("Error: " . $message . " Details: " . json_encode($details));
    }
    
    echo json_encode($response);
    exit();
}

// データベース操作のログ記録
function logDatabaseOperation($operation, $data = null) {
    if (DEBUG_MODE) {
        $log_message = "DB Operation: " . $operation;
        if ($data) {
            $log_message .= " Data: " . json_encode($data);
        }
        error_log($log_message);
    }
}
?>
