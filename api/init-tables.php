<?php
require_once 'config.php';

if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    error_log("init-tables.php: Starting request processing");
}

try {
    $pdo = getDB();
    
    // オンラインビンゴカードテーブルの作成
    $pdo->exec("CREATE TABLE IF NOT EXISTS online_bingo_cards (
        id INT AUTO_INCREMENT PRIMARY KEY,
        password VARCHAR(50) NOT NULL UNIQUE,
        card_data JSON NOT NULL,
        participant_name VARCHAR(100),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        last_access TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        is_active BOOLEAN DEFAULT TRUE
    )");
    
    // ビンゴ達成記録テーブルの作成
    $pdo->exec("CREATE TABLE IF NOT EXISTS bingo_achievements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        card_id INT,
        achievement_type ENUM('line_horizontal', 'line_vertical', 'line_diagonal', 'full_house') NOT NULL,
        achieved_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        winning_numbers JSON,
        FOREIGN KEY (card_id) REFERENCES online_bingo_cards(id)
    )");
    
    // ビンゴ番号テーブルの作成
    $pdo->exec("CREATE TABLE IF NOT EXISTS bingo_numbers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        number INT NOT NULL,
        called_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_number (number)
    )");
    
    logDatabaseOperation("INIT tables");
    
    $response = [
        'success' => true,
        'message' => 'データベーステーブルを初期化しました',
        'tables_created' => [
            'online_bingo_cards',
            'bingo_achievements', 
            'bingo_numbers'
        ]
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    $errorMessage = $e->getMessage();
    
    if (DEBUG_MODE) {
        error_log("init-tables.php error: " . $errorMessage);
        error_log("init-tables.php stack trace: " . $e->getTraceAsString());
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $errorMessage,
        'debug_info' => DEBUG_MODE ? [
            'error_details' => $errorMessage,
            'error_code' => $e->getCode(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ] : null
    ]);
}
?>
