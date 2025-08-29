<?php
require_once 'config.php';

if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    error_log("add-number.php: Starting number addition");
}

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = getDB();
    
    $input = json_decode(file_get_contents('php://input'), true);
    $number = intval($input['number'] ?? 0);
    
    if ($number < 1 || $number > 75) {
        throw new Exception('番号は1-75の範囲で入力してください');
    }
    
    if (DEBUG_MODE) {
        error_log("add-number.php: Adding number: $number");
    }
    
    // 現在のゲームIDを取得（固定値1を使用）
    $gameId = 1;
    
    // 重複チェック（bingo_numbersテーブル）
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM bingo_numbers WHERE number = ?");
    $stmt->execute([$number]);
    if ($stmt->fetchColumn() > 0) {
        throw new Exception("番号 $number は既に呼ばれています");
    }
    
    $pdo->beginTransaction();
    
    try {
        // bingo_numbersテーブルに追加（メイン）
        $stmt = $pdo->prepare("INSERT INTO bingo_numbers (number, called_at) VALUES (?, NOW())");
        $stmt->execute([$number]);
        
        // called_numbersテーブルにも追加（互換性のため）
        $stmt = $pdo->prepare("INSERT INTO called_numbers (game_id, number, called_at) VALUES (?, ?, NOW())");
        $stmt->execute([$gameId, $number]);
        
        $pdo->commit();
        
        if (DEBUG_MODE) {
            error_log("add-number.php: Number $number added successfully to both tables");
        }
        
        echo json_encode([
            'success' => true,
            'message' => "番号 $number を追加しました",
            'number' => $number
        ]);
        
    } catch (Exception $e) {
        $pdo->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    $errorMessage = $e->getMessage();
    
    if (DEBUG_MODE) {
        error_log("add-number.php: Error - " . $errorMessage);
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $errorMessage
    ]);
}
?>
