<?php
require_once 'config.php';

if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    error_log("card-manager.php: Starting request processing");
}

try {
    $pdo = getDB();
    
    // オンラインビンゴカードテーブルの存在確認・作成
    $tableExists = $pdo->query("SHOW TABLES LIKE 'online_bingo_cards'")->rowCount() > 0;
    
    if (!$tableExists) {
        if (DEBUG_MODE) {
            error_log("card-manager.php: Creating online_bingo_cards table");
        }
        
        $pdo->exec("CREATE TABLE IF NOT EXISTS online_bingo_cards (
            id INT AUTO_INCREMENT PRIMARY KEY,
            password VARCHAR(50) NOT NULL UNIQUE,
            card_data JSON NOT NULL,
            participant_name VARCHAR(100),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_access TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            is_active BOOLEAN DEFAULT TRUE
        )");
    }
    
    // ビンゴ達成記録テーブルの存在確認・作成
    $achievementTableExists = $pdo->query("SHOW TABLES LIKE 'bingo_achievements'")->rowCount() > 0;
    
    if (!$achievementTableExists) {
        if (DEBUG_MODE) {
            error_log("card-manager.php: Creating bingo_achievements table");
        }
        
        $pdo->exec("CREATE TABLE IF NOT EXISTS bingo_achievements (
            id INT AUTO_INCREMENT PRIMARY KEY,
            card_id INT,
            achievement_type ENUM('line_horizontal', 'line_vertical', 'line_diagonal', 'full_house') NOT NULL,
            achieved_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            winning_numbers JSON,
            FOREIGN KEY (card_id) REFERENCES online_bingo_cards(id)
        )");
    }
    
    $method = $_SERVER['REQUEST_METHOD'];
    
    if ($method === 'POST') {
        // カード作成または取得
        $input = json_decode(file_get_contents('php://input'), true);
        $password = trim($input['password'] ?? '');
        $participantName = trim($input['participant_name'] ?? '');
        
        if (empty($password)) {
            throw new Exception('パスワードが入力されていません');
        }
        
        // 既存のカードをチェック
        $stmt = $pdo->prepare("SELECT * FROM online_bingo_cards WHERE password = ? AND is_active = 1");
        $stmt->execute([$password]);
        $existingCard = $stmt->fetch();
        
        if ($existingCard) {
            // 既存カードを返す（最終アクセス時間を更新）
            $stmt = $pdo->prepare("UPDATE online_bingo_cards SET last_access = CURRENT_TIMESTAMP, participant_name = COALESCE(NULLIF(participant_name, ''), ?) WHERE id = ?");
            $stmt->execute([$participantName, $existingCard['id']]);
            
            $response = [
                'success' => true,
                'action' => 'existing_card',
                'card' => [
                    'id' => $existingCard['id'],
                    'password' => $existingCard['password'],
                    'card_data' => json_decode($existingCard['card_data'], true),
                    'participant_name' => $existingCard['participant_name'] ?: $participantName,
                    'created_at' => $existingCard['created_at']
                ]
            ];
        } else {
            // 事前に生成されたパスワードのみ許可
            throw new Exception('指定されたパスワードは無効です。運営から配布されたパスワードを入力してください。');
        }
        
        echo json_encode($response);
        
    } elseif ($method === 'GET') {
        // カード情報取得
        $password = $_GET['password'] ?? '';
        
        if (empty($password)) {
            throw new Exception('パスワードが指定されていません');
        }
        
        $stmt = $pdo->prepare("SELECT * FROM online_bingo_cards WHERE password = ? AND is_active = 1");
        $stmt->execute([$password]);
        $card = $stmt->fetch();
        
        if (!$card) {
            throw new Exception('指定されたパスワードのカードが見つかりません');
        }
        
        // 最終アクセス時間を更新
        $stmt = $pdo->prepare("UPDATE online_bingo_cards SET last_access = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$card['id']]);
        
        $response = [
            'success' => true,
            'card' => [
                'id' => $card['id'],
                'password' => $card['password'],
                'card_data' => json_decode($card['card_data'], true),
                'participant_name' => $card['participant_name'],
                'created_at' => $card['created_at']
            ]
        ];
        
        echo json_encode($response);
    }
    
} catch (Exception $e) {
    $errorMessage = $e->getMessage();
    
    if (DEBUG_MODE) {
        error_log("card-manager.php error: " . $errorMessage);
        error_log("card-manager.php stack trace: " . $e->getTraceAsString());
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

/**
 * 5x5のビンゴカードを生成する
 * B列: 1-15, I列: 16-30, N列: 31-45, G列: 46-60, O列: 61-75
 * 中央のN列はFREE（0で表現）
 */
function generateBingoCard() {
    $card = [];
    
    // 各列の数字範囲
    $ranges = [
        [1, 15],   // B列
        [16, 30],  // I列  
        [31, 45],  // N列
        [46, 60],  // G列
        [61, 75]   // O列
    ];
    
    // 各列ごとに5つの数字を選択
    for ($col = 0; $col < 5; $col++) {
        $availableNumbers = range($ranges[$col][0], $ranges[$col][1]);
        $selectedNumbers = array_rand(array_flip($availableNumbers), 5);
        
        for ($row = 0; $row < 5; $row++) {
            // 中央（N列の真ん中）はFREE
            if ($col === 2 && $row === 2) {
                $card[$row][$col] = 0; // FREEを0で表現
            } else {
                $card[$row][$col] = $selectedNumbers[$row];
            }
        }
    }
    
    if (DEBUG_MODE) {
        error_log("Generated bingo card: " . json_encode($card));
    }
    
    return $card;
}
?>
