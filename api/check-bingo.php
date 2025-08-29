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
    $markedCells = $input['marked_cells'] ?? [];
    
    if ($DEBUG_MODE) {
        error_log("check-bingo.php: Input data - Password: $password, Marked cells: " . json_encode($markedCells));
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
    
    // 呼ばれた番号を取得
    $stmt = $pdo->query("SELECT number FROM bingo_numbers ORDER BY called_at ASC");
    $calledNumbersData = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if ($DEBUG_MODE) {
        error_log("check-bingo.php: Called numbers: " . json_encode($calledNumbersData));
    }
    
    $cardData = json_decode($card['card_data'], true);
    
    if ($DEBUG_MODE) {
        error_log("check-bingo.php: Card data: " . json_encode($cardData));
    }
    
    // ビンゴ判定を実行
    $bingoResult = checkBingoWithDebug($cardData, $markedCells, $calledNumbersData, $DEBUG_MODE);
    
    if ($DEBUG_MODE) {
        error_log("check-bingo.php: Bingo result: " . json_encode($bingoResult));
    }
    
    // ビンゴが成立している場合は記録を保存
    if ($bingoResult['has_bingo'] && !empty($bingoResult['lines'])) {
        foreach ($bingoResult['lines'] as $line) {
            $stmt = $pdo->prepare("INSERT INTO bingo_achievements (card_id, achievement_type, winning_numbers) VALUES (?, ?, ?)");
            $stmt->execute([
                $card['id'],
                $line['type'],
                json_encode($calledNumbersData)
            ]);
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
            'called_numbers_count' => count($calledNumbersData),
            'marked_cells_count' => count($markedCells),
            'card_data' => $cardData,
            'called_numbers' => $calledNumbersData,
            'marked_cells' => $markedCells
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
 * デバッグ機能付きビンゴ判定
 */
function checkBingoWithDebug($cardData, $markedCells, $calledNumbers, $debug = false) {
    $lines = [];
    $invalidMarkedCount = 0;
    
    if ($debug) {
        error_log("checkBingoWithDebug: Starting bingo check");
        error_log("checkBingoWithDebug: Card data: " . json_encode($cardData));
        error_log("checkBingoWithDebug: Marked cells: " . json_encode($markedCells));
        error_log("checkBingoWithDebug: Called numbers: " . json_encode($calledNumbers));
    }
    
    // マークされたセルの妥当性をチェック
    $validMarkedCells = [];
    foreach ($markedCells as $cellId) {
        $parts = explode('-', $cellId);
        if (count($parts) !== 2) {
            if ($debug) error_log("checkBingoWithDebug: Invalid cell ID format: $cellId");
            continue;
        }
        
        $row = intval($parts[0]);
        $col = intval($parts[1]);
        
        if ($row < 0 || $row > 4 || $col < 0 || $col > 4) {
            if ($debug) error_log("checkBingoWithDebug: Cell out of range: $cellId");
            continue;
        }
        
        $number = $cardData[$row][$col];
        
        // FREEセル（中央、値が0）は常に有効
        if ($row === 2 && $col === 2 && $number === 0) {
            $validMarkedCells[] = $cellId;
            if ($debug) error_log("checkBingoWithDebug: FREE cell marked: $cellId");
            continue;
        }
        
        // 呼ばれた番号かチェック
        if (in_array($number, $calledNumbers)) {
            $validMarkedCells[] = $cellId;
            if ($debug) error_log("checkBingoWithDebug: Valid marked cell: $cellId (number: $number)");
        } else {
            $invalidMarkedCount++;
            if ($debug) error_log("checkBingoWithDebug: Invalid marked cell: $cellId (number: $number not called)");
        }
    }
    
    if ($debug) {
        error_log("checkBingoWithDebug: Valid marked cells: " . json_encode($validMarkedCells));
        error_log("checkBingoWithDebug: Invalid marked count: $invalidMarkedCount");
    }
    
    // 横のライン判定
    for ($row = 0; $row < 5; $row++) {
        $lineComplete = true;
        $lineCells = [];
        
        for ($col = 0; $col < 5; $col++) {
            $cellId = "$row-$col";
            $lineCells[] = $cellId;
            
            if (!in_array($cellId, $validMarkedCells)) {
                $lineComplete = false;
                break;
            }
        }
        
        if ($lineComplete) {
            $lines[] = [
                'type' => 'line_horizontal',
                'description' => '横のライン（' . ($row + 1) . '行目）',
                'cells' => $lineCells
            ];
            
            if ($debug) {
                error_log("checkBingoWithDebug: Horizontal line completed: row $row");
            }
        } else {
            if ($debug) {
                $markedInLine = array_intersect($lineCells, $validMarkedCells);
                error_log("checkBingoWithDebug: Horizontal line incomplete: row $row, marked: " . count($markedInLine) . "/5");
            }
        }
    }
    
    // 縦のライン判定
    for ($col = 0; $col < 5; $col++) {
        $lineComplete = true;
        $lineCells = [];
        
        for ($row = 0; $row < 5; $row++) {
            $cellId = "$row-$col";
            $lineCells[] = $cellId;
            
            if (!in_array($cellId, $validMarkedCells)) {
                $lineComplete = false;
                break;
            }
        }
        
        if ($lineComplete) {
            $lines[] = [
                'type' => 'line_vertical',
                'description' => '縦のライン（' . ['B', 'I', 'N', 'G', 'O'][$col] . '列）',
                'cells' => $lineCells
            ];
            
            if ($debug) {
                error_log("checkBingoWithDebug: Vertical line completed: col $col");
            }
        } else {
            if ($debug) {
                $markedInLine = array_intersect($lineCells, $validMarkedCells);
                error_log("checkBingoWithDebug: Vertical line incomplete: col $col, marked: " . count($markedInLine) . "/5");
            }
        }
    }
    
    // 斜めのライン判定（左上から右下）
    $diagonalCells1 = [];
    $diagonal1Complete = true;
    for ($i = 0; $i < 5; $i++) {
        $cellId = "$i-$i";
        $diagonalCells1[] = $cellId;
        
        if (!in_array($cellId, $validMarkedCells)) {
            $diagonal1Complete = false;
        }
    }
    
    if ($diagonal1Complete) {
        $lines[] = [
            'type' => 'line_diagonal',
            'description' => '斜めのライン（左上から右下）',
            'cells' => $diagonalCells1
        ];
        
        if ($debug) {
            error_log("checkBingoWithDebug: Diagonal line 1 completed");
        }
    } else {
        if ($debug) {
            $markedInLine = array_intersect($diagonalCells1, $validMarkedCells);
            error_log("checkBingoWithDebug: Diagonal line 1 incomplete: marked: " . count($markedInLine) . "/5");
        }
    }
    
    // 斜めのライン判定（右上から左下）
    $diagonalCells2 = [];
    $diagonal2Complete = true;
    for ($i = 0; $i < 5; $i++) {
        $cellId = "$i-" . (4 - $i);
        $diagonalCells2[] = $cellId;
        
        if (!in_array($cellId, $validMarkedCells)) {
            $diagonal2Complete = false;
        }
    }
    
    if ($diagonal2Complete) {
        $lines[] = [
            'type' => 'line_diagonal',
            'description' => '斜めのライン（右上から左下）',
            'cells' => $diagonalCells2
        ];
        
        if ($debug) {
            error_log("checkBingoWithDebug: Diagonal line 2 completed");
        }
    } else {
        if ($debug) {
            $markedInLine = array_intersect($diagonalCells2, $validMarkedCells);
            error_log("checkBingoWithDebug: Diagonal line 2 incomplete: marked: " . count($markedInLine) . "/5");
        }
    }
    
    // フルハウス判定
    $totalCells = 25;
    $validMarkedCount = count($validMarkedCells);
    $isFullHouse = ($validMarkedCount === $totalCells);
    
    if ($isFullHouse) {
        $lines[] = [
            'type' => 'full_house',
            'description' => 'フルハウス（全マス）',
            'cells' => $validMarkedCells
        ];
        
        if ($debug) {
            error_log("checkBingoWithDebug: Full house completed");
        }
    } else {
        if ($debug) {
            error_log("checkBingoWithDebug: Full house incomplete: marked: $validMarkedCount/$totalCells");
        }
    }
    
    $hasBingo = !empty($lines);
    
    if ($debug) {
        error_log("checkBingoWithDebug: Final result - Has bingo: " . ($hasBingo ? 'YES' : 'NO') . ", Lines: " . count($lines));
    }
    
    return [
        'has_bingo' => $hasBingo,
        'lines' => $lines,
        'invalid_marked_count' => $invalidMarkedCount,
        'total_marked_count' => count($markedCells),
        'valid_marked_count' => count($validMarkedCells)
    ];
}
?>
