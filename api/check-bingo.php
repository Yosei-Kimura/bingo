<?php
require_once 'config.php';

// デバッグモードを強制的に有効にして詳細ログを出力
$DEBUG_MODE = true;
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json; charset=utf-8');

if ($DEBUG_MODE) {
    error_log("check-bingo.php: Starting bingo check");
}

try {
    $pdo = getDB();
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('POST method required');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $password = trim($input['password'] ?? '');
    $calledNumbers = $input['called_numbers'] ?? [];
    
    if ($DEBUG_MODE) {
        error_log("check-bingo.php: Input data - Password: $password, Called numbers: " . json_encode($calledNumbers));
    }
    
    if (empty($password)) {
        throw new Exception('パスワードが必要です');
    }
    
    // カード情報を取得
    $stmt = $pdo->prepare("SELECT * FROM online_bingo_cards WHERE password = ? AND is_active = 1");
    $stmt->execute([$password]);
    $card = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$card) {
        throw new Exception('カードが見つかりません');
    }
    
    if ($DEBUG_MODE) {
        error_log("check-bingo.php: Card found - ID: " . $card['id']);
    }
    
    // 最新の呼ばれた番号を取得（フォールバック）
    if (empty($calledNumbers)) {
        $stmt = $pdo->query("SELECT number FROM bingo_numbers ORDER BY called_at ASC");
        $calledNumbers = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $calledNumbers = array_map('intval', $calledNumbers);
    }
    
    if ($DEBUG_MODE) {
        error_log("check-bingo.php: Called numbers: " . json_encode($calledNumbers));
    }
    
    $cardData = json_decode($card['card_data'], true);
    
    if ($DEBUG_MODE) {
        error_log("check-bingo.php: Card data: " . json_encode($cardData));
    }
    
    // ビンゴ判定を実行
    $bingoResult = checkBingoWithCalledNumbers($cardData, $calledNumbers, $DEBUG_MODE);
    
    if ($DEBUG_MODE) {
        error_log("check-bingo.php: Bingo result: " . json_encode($bingoResult));
    }
    
    // ビンゴが成立している場合は記録を保存
    if ($bingoResult['has_bingo'] && !empty($bingoResult['lines'])) {
        foreach ($bingoResult['lines'] as $line) {
            // 重複チェック
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM bingo_achievements WHERE card_id = ? AND achievement_type = ?");
            $checkStmt->execute([$card['id'], $line['type']]);
            
            if ($checkStmt->fetchColumn() == 0) {
                $stmt = $pdo->prepare("INSERT INTO bingo_achievements (card_id, achievement_type, winning_numbers) VALUES (?, ?, ?)");
                $stmt->execute([
                    $card['id'],
                    $line['type'],
                    json_encode($calledNumbers)
                ]);
            }
        }
        
        if ($DEBUG_MODE) {
            error_log("check-bingo.php: Bingo achievements saved");
        }
    }
    
    echo json_encode([
        'success' => true,
        'bingo_result' => $bingoResult,
        'debug_info' => [
            'card_id' => $card['id'],
            'called_numbers_count' => count($calledNumbers),
            'card_data' => $cardData,
            'called_numbers' => $calledNumbers
        ]
    ]);
    
} catch (Exception $e) {
    $errorMessage = $e->getMessage();
    
    if ($DEBUG_MODE) {
        error_log("check-bingo.php: Error - " . $errorMessage);
        error_log("check-bingo.php: Stack trace - " . $e->getTraceAsString());
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $errorMessage,
        'debug_info' => [
            'error_details' => $e->getTraceAsString()
        ]
    ]);
}

/**
 * 呼ばれた番号のみでビンゴ判定
 */
function checkBingoWithCalledNumbers($cardData, $calledNumbers, $debug = false) {
    $lines = [];
    
    if ($debug) {
        error_log("checkBingoWithCalledNumbers: Starting bingo check");
        error_log("checkBingoWithCalledNumbers: Card data: " . json_encode($cardData));
        error_log("checkBingoWithCalledNumbers: Called numbers: " . json_encode($calledNumbers));
    }
    
    // 横のライン判定
    for ($row = 0; $row < 5; $row++) {
        $lineComplete = true;
        $lineCells = [];
        $lineNumbers = [];
        
        for ($col = 0; $col < 5; $col++) {
            $cellId = "$row-$col";
            $number = $cardData[$row][$col];
            $lineCells[] = $cellId;
            $lineNumbers[] = $number;
            
            // FREEセル（中央、値が0）は常に有効
            if ($row === 2 && $col === 2 && $number === 0) {
                continue;
            }
            
            // 呼ばれた番号に含まれているかチェック
            if (!in_array($number, $calledNumbers)) {
                $lineComplete = false;
                break;
            }
        }
        
        if ($lineComplete) {
            $lines[] = [
                'type' => 'line_horizontal',
                'description' => '横のライン（' . ($row + 1) . '行目）',
                'cells' => $lineCells,
                'numbers' => $lineNumbers
            ];
            
            if ($debug) {
                error_log("checkBingoWithCalledNumbers: Horizontal line completed: row $row");
            }
        }
    }
    
    // 縦のライン判定
    for ($col = 0; $col < 5; $col++) {
        $lineComplete = true;
        $lineCells = [];
        $lineNumbers = [];
        
        for ($row = 0; $row < 5; $row++) {
            $cellId = "$row-$col";
            $number = $cardData[$row][$col];
            $lineCells[] = $cellId;
            $lineNumbers[] = $number;
            
            // FREEセル（中央、値が0）は常に有効
            if ($row === 2 && $col === 2 && $number === 0) {
                continue;
            }
            
            // 呼ばれた番号に含まれているかチェック
            if (!in_array($number, $calledNumbers)) {
                $lineComplete = false;
                break;
            }
        }
        
        if ($lineComplete) {
            $lines[] = [
                'type' => 'line_vertical',
                'description' => '縦のライン（' . ['B', 'I', 'N', 'G', 'O'][$col] . '列）',
                'cells' => $lineCells,
                'numbers' => $lineNumbers
            ];
            
            if ($debug) {
                error_log("checkBingoWithCalledNumbers: Vertical line completed: col $col");
            }
        }
    }
    
    // 斜めのライン判定（左上から右下）
    $diagonalCells1 = [];
    $diagonalNumbers1 = [];
    $diagonal1Complete = true;
    for ($i = 0; $i < 5; $i++) {
        $cellId = "$i-$i";
        $number = $cardData[$i][$i];
        $diagonalCells1[] = $cellId;
        $diagonalNumbers1[] = $number;
        
        // FREEセル（中央、値が0）は常に有効
        if ($i === 2 && $number === 0) {
            continue;
        }
        
        if (!in_array($number, $calledNumbers)) {
            $diagonal1Complete = false;
        }
    }
    
    if ($diagonal1Complete) {
        $lines[] = [
            'type' => 'line_diagonal',
            'description' => '斜めのライン（左上から右下）',
            'cells' => $diagonalCells1,
            'numbers' => $diagonalNumbers1
        ];
        
        if ($debug) {
            error_log("checkBingoWithCalledNumbers: Diagonal line 1 completed");
        }
    }
    
    // 斜めのライン判定（右上から左下）
    $diagonalCells2 = [];
    $diagonalNumbers2 = [];
    $diagonal2Complete = true;
    for ($i = 0; $i < 5; $i++) {
        $cellId = "$i-" . (4 - $i);
        $number = $cardData[$i][4 - $i];
        $diagonalCells2[] = $cellId;
        $diagonalNumbers2[] = $number;
        
        // FREEセル（中央、値が0）は常に有効
        if ($i === 2 && $number === 0) {
            continue;
        }
        
        if (!in_array($number, $calledNumbers)) {
            $diagonal2Complete = false;
        }
    }
    
    if ($diagonal2Complete) {
        $lines[] = [
            'type' => 'line_diagonal',
            'description' => '斜めのライン（右上から左下）',
            'cells' => $diagonalCells2,
            'numbers' => $diagonalNumbers2
        ];
        
        if ($debug) {
            error_log("checkBingoWithCalledNumbers: Diagonal line 2 completed");
        }
    }
    
    // フルハウス判定
    $allNumbers = [];
    $allCells = [];
    for ($row = 0; $row < 5; $row++) {
        for ($col = 0; $col < 5; $col++) {
            $number = $cardData[$row][$col];
            $cellId = "$row-$col";
            $allNumbers[] = $number;
            $allCells[] = $cellId;
        }
    }
    
    $isFullHouse = true;
    foreach ($allNumbers as $index => $number) {
        // FREEセル（値が0）は常に有効
        if ($number === 0) {
            continue;
        }
        
        if (!in_array($number, $calledNumbers)) {
            $isFullHouse = false;
            break;
        }
    }
    
    if ($isFullHouse) {
        $lines[] = [
            'type' => 'full_house',
            'description' => 'フルハウス（全マス）',
            'cells' => $allCells,
            'numbers' => $allNumbers
        ];
        
        if ($debug) {
            error_log("checkBingoWithCalledNumbers: Full house completed");
        }
    }
    
    $hasBingo = !empty($lines);
    
    if ($debug) {
        error_log("checkBingoWithCalledNumbers: Final result - Has bingo: " . ($hasBingo ? 'YES' : 'NO') . ", Lines: " . count($lines));
    }
    
    return [
        'has_bingo' => $hasBingo,
        'lines' => $lines,
        'called_numbers_count' => count($calledNumbers),
        'card_numbers_matched' => array_intersect($allNumbers, $calledNumbers)
    ];
}
?>

