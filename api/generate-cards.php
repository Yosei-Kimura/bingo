<?php
require_once 'config.php';

if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    error_log("generate-cards.php: Starting request processing");
}

try {
    $pdo = getDB();
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('POSTメソッドのみサポートしています');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $count = intval($input['count'] ?? 10);
    $passwordLength = intval($input['password_length'] ?? 8);
    $passwordType = $input['password_type'] ?? 'numbers';
    
    if ($count < 1 || $count > 100) {
        throw new Exception('生成するカード数は1から100の間で指定してください');
    }
    
    if ($passwordLength < 4 || $passwordLength > 20) {
        throw new Exception('パスワード長は4から20の間で指定してください');
    }
    
    // テーブルの存在確認・作成
    ensureTables($pdo);
    
    $generatedCards = [];
    $duplicateAttempts = 0;
    $maxDuplicateAttempts = $count * 10; // 重複回避の最大試行回数
    
    for ($i = 0; $i < $count; $i++) {
        $attempts = 0;
        $maxAttempts = 100;
        
        do {
            $password = generatePassword($passwordLength, $passwordType);
            $attempts++;
            $duplicateAttempts++;
            
            // 重複チェック
            $stmt = $pdo->prepare("SELECT id FROM online_bingo_cards WHERE password = ?");
            $stmt->execute([$password]);
            $exists = $stmt->fetch();
            
            if ($duplicateAttempts > $maxDuplicateAttempts) {
                throw new Exception('パスワードの重複が多すぎます。パスワード長を長くするか、生成数を減らしてください。');
            }
            
        } while ($exists && $attempts < $maxAttempts);
        
        if ($attempts >= $maxAttempts) {
            throw new Exception('ユニークなパスワードの生成に失敗しました');
        }
        
        // ビンゴカード生成
        $cardData = generateBingoCard();
        
        // データベースに保存
        $stmt = $pdo->prepare("INSERT INTO online_bingo_cards (password, card_data, created_at) VALUES (?, ?, NOW())");
        $stmt->execute([$password, json_encode($cardData)]);
        
        $cardId = $pdo->lastInsertId();
        
        $generatedCards[] = [
            'id' => $cardId,
            'password' => $password,
            'card_data' => $cardData,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        if (DEBUG_MODE) {
            error_log("Generated card: ID $cardId, Password: $password");
        }
    }
    
    logDatabaseOperation("GENERATE cards", ['count' => $count, 'password_type' => $passwordType]);
    
    $response = [
        'success' => true,
        'generated_cards' => $generatedCards,
        'count' => count($generatedCards),
        'message' => count($generatedCards) . '個のパスワードとビンゴカードを生成しました'
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    $errorMessage = $e->getMessage();
    
    if (DEBUG_MODE) {
        error_log("generate-cards.php error: " . $errorMessage);
        error_log("generate-cards.php stack trace: " . $e->getTraceAsString());
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
 * テーブルの存在確認・作成
 */
function ensureTables($pdo) {
    // オンラインビンゴカードテーブル
    $tableExists = $pdo->query("SHOW TABLES LIKE 'online_bingo_cards'")->rowCount() > 0;
    
    if (!$tableExists) {
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
    
    // ビンゴ達成記録テーブル
    $achievementTableExists = $pdo->query("SHOW TABLES LIKE 'bingo_achievements'")->rowCount() > 0;
    
    if (!$achievementTableExists) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS bingo_achievements (
            id INT AUTO_INCREMENT PRIMARY KEY,
            card_id INT,
            achievement_type ENUM('line_horizontal', 'line_vertical', 'line_diagonal', 'full_house') NOT NULL,
            achieved_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            winning_numbers JSON,
            FOREIGN KEY (card_id) REFERENCES online_bingo_cards(id)
        )");
    }
}

/**
 * パスワード生成
 */
function generatePassword($length, $type) {
    switch ($type) {
        case 'numbers':
            $chars = '0123456789';
            break;
        case 'letters':
            $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
            break;
        case 'alphanumeric':
        default:
            $chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
            break;
    }
    
    $password = '';
    $charsLength = strlen($chars);
    
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, $charsLength - 1)];
    }
    
    return $password;
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
        shuffle($availableNumbers);
        $selectedNumbers = array_slice($availableNumbers, 0, 5);
        
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
