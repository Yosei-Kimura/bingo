<?php
require_once 'config.php';

// CORS対応
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

// OPTIONSリクエストの場合は200を返す
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    $pdo = getDB();
    
    // まずbingo_numbersテーブルの存在を確認
    $tableCheckStmt = $pdo->query("SHOW TABLES LIKE 'bingo_numbers'");
    $bingoTableExists = $tableCheckStmt->rowCount() > 0;
    
    if ($bingoTableExists) {
        // bingo_numbersテーブルから取得
        $stmt = $pdo->query("SELECT number FROM bingo_numbers ORDER BY called_at ASC");
        $numbers = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $sourceTable = 'bingo_numbers';
    } else {
        // フォールバック: called_numbersテーブルから取得
        $stmt = $pdo->query("SELECT number FROM called_numbers ORDER BY called_at ASC");
        $numbers = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $sourceTable = 'called_numbers';
    }
    
    // 番号を整数に変換
    $numbers = array_map('intval', $numbers);
    
    // キャッシュ用のETag生成
    $etag = md5(json_encode($numbers));
    header('Cache-Control: public, max-age=5');
    header('ETag: "' . $etag . '"');
    
    // ETags対応
    if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] === '"' . $etag . '"') {
        http_response_code(304);
        exit;
    }
    
    // 統一されたレスポンス形式
    echo json_encode([
        'success' => true,
        'numbers' => $numbers,
        'calledNumbers' => $numbers, // 互換性のため
        'count' => count($numbers),
        'gameStatus' => 'active',
        'lastUpdate' => time(),
        'sourceTable' => $sourceTable,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    // エラーログに記録
    error_log("get-data.php error: " . $e->getMessage());
    
    // エラーレスポンス
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'numbers' => [],
        'calledNumbers' => [],
        'count' => 0,
        'debug_info' => [
            'file' => __FILE__,
            'line' => __LINE__,
            'trace' => $e->getTraceAsString()
        ]
    ]);
}
?>
