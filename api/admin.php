<?php
require_once 'config.php';

try {
    $pdo = getDB();
    
    // テーブルが存在しない場合は作成
    $pdo->exec("CREATE TABLE IF NOT EXISTS bingo_numbers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        number INT NOT NULL,
        called_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_number (number)
    )");
    
    logDatabaseOperation("Table check/creation completed");
    
    $method = $_SERVER['REQUEST_METHOD'];
    
    switch ($method) {
        case 'GET':
            // 出た番号の一覧を取得
            $stmt = $pdo->query("SELECT number, called_at FROM bingo_numbers ORDER BY called_at ASC");
            $numbers = $stmt->fetchAll();
            
            logDatabaseOperation("GET numbers", ['count' => count($numbers)]);
            
            echo json_encode([
                'success' => true,
                'numbers' => array_column($numbers, 'number'),
                'details' => $numbers
            ]);
            break;
            
        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input) {
                handleError('Invalid JSON input');
            }
            
            $action = $input['action'] ?? '';
            
            switch ($action) {
                case 'add':
                    $number = $input['number'] ?? null;
                    
                    if (!is_numeric($number) || $number < 1 || $number > 75) {
                        handleError('Invalid number. Must be between 1 and 75');
                    }
                    
                    try {
                        $stmt = $pdo->prepare("INSERT INTO bingo_numbers (number) VALUES (?)");
                        $result = $stmt->execute([$number]);
                        
                        if ($result) {
                            logDatabaseOperation("ADD number", ['number' => $number]);
                            echo json_encode([
                                'success' => true,
                                'message' => "番号 {$number} を追加しました"
                            ]);
                        } else {
                            handleError('Failed to add number');
                        }
                    } catch (PDOException $e) {
                        if ($e->getCode() == 23000) { // Duplicate entry
                            handleError("番号 {$number} は既に出ています");
                        } else {
                            handleError('Database error: ' . $e->getMessage());
                        }
                    }
                    break;
                    
                case 'remove':
                    $number = $input['number'] ?? null;
                    
                    if (!is_numeric($number)) {
                        handleError('Invalid number');
                    }
                    
                    $stmt = $pdo->prepare("DELETE FROM bingo_numbers WHERE number = ?");
                    $result = $stmt->execute([$number]);
                    
                    if ($stmt->rowCount() > 0) {
                        logDatabaseOperation("REMOVE number", ['number' => $number]);
                        echo json_encode([
                            'success' => true,
                            'message' => "番号 {$number} を削除しました"
                        ]);
                    } else {
                        handleError("番号 {$number} は見つかりませんでした");
                    }
                    break;
                    
                case 'undo':
                    // 最後に追加された番号を削除
                    $stmt = $pdo->query("SELECT number FROM bingo_numbers ORDER BY called_at DESC LIMIT 1");
                    $lastNumber = $stmt->fetchColumn();
                    
                    if ($lastNumber) {
                        $stmt = $pdo->prepare("DELETE FROM bingo_numbers WHERE number = ?");
                        $result = $stmt->execute([$lastNumber]);
                        
                        if ($result) {
                            logDatabaseOperation("UNDO last number", ['number' => $lastNumber]);
                            echo json_encode([
                                'success' => true,
                                'message' => "番号 {$lastNumber} を取り消しました"
                            ]);
                        } else {
                            handleError('Failed to undo last number');
                        }
                    } else {
                        handleError('取り消す番号がありません');
                    }
                    break;
                    
                case 'reset':
                    $stmt = $pdo->query("DELETE FROM bingo_numbers");
                    $deletedCount = $stmt->rowCount();
                    
                    logDatabaseOperation("RESET all numbers", ['deleted_count' => $deletedCount]);
                    
                    echo json_encode([
                        'success' => true,
                        'message' => '全ての番号をリセットしました',
                        'deleted_count' => $deletedCount
                    ]);
                    break;
                    
                default:
                    handleError('Invalid action');
            }
            break;
            
        default:
            handleError('Method not allowed');
    }
    
} catch (Exception $e) {
    handleError('Server error: ' . $e->getMessage());
}
?>
