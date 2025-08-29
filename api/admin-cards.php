<?php
require_once 'config.php';

if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    error_log("admin-cards.php: Starting request processing");
}

try {
    $pdo = getDB();
    $method = $_SERVER['REQUEST_METHOD'];
    
    // テーブルの存在確認
    $tableExists = $pdo->query("SHOW TABLES LIKE 'online_bingo_cards'")->rowCount() > 0;
    
    if (!$tableExists && $method === 'GET') {
        echo json_encode([
            'success' => true,
            'cards' => []
        ]);
        exit;
    }
    
    if ($method === 'GET') {
        // カード一覧取得
        $stmt = $pdo->query("
            SELECT 
                id, 
                password, 
                participant_name, 
                created_at, 
                last_access, 
                is_active 
            FROM online_bingo_cards 
            ORDER BY created_at DESC
        ");
        $cards = $stmt->fetchAll();
        
        logDatabaseOperation("GET admin cards list", ['count' => count($cards)]);
        
        $response = [
            'success' => true,
            'cards' => $cards,
            'count' => count($cards)
        ];
        
        echo json_encode($response);
        
    } elseif ($method === 'DELETE') {
        // カード削除
        $cardId = $_GET['id'] ?? null;
        
        if ($cardId) {
            // 個別カード削除
            $stmt = $pdo->prepare("DELETE FROM online_bingo_cards WHERE id = ?");
            $stmt->execute([$cardId]);
            
            $deletedRows = $stmt->rowCount();
            
            if ($deletedRows > 0) {
                // 関連する達成記録も削除
                $achievementTableExists = $pdo->query("SHOW TABLES LIKE 'bingo_achievements'")->rowCount() > 0;
                if ($achievementTableExists) {
                    $stmt = $pdo->prepare("DELETE FROM bingo_achievements WHERE card_id = ?");
                    $stmt->execute([$cardId]);
                }
                
                logDatabaseOperation("DELETE admin card", ['card_id' => $cardId]);
                
                $response = [
                    'success' => true,
                    'message' => 'カードを削除しました',
                    'deleted_card_id' => $cardId
                ];
            } else {
                throw new Exception('指定されたカードが見つかりません');
            }
            
        } else {
            // 全カード削除
            if (!$tableExists) {
                throw new Exception('削除するカードがありません');
            }
            
            // カード数をカウント
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM online_bingo_cards");
            $cardCount = $stmt->fetch()['count'];
            
            // 全ての達成記録を削除
            $achievementTableExists = $pdo->query("SHOW TABLES LIKE 'bingo_achievements'")->rowCount() > 0;
            if ($achievementTableExists) {
                $pdo->exec("DELETE FROM bingo_achievements");
            }
            
            // 全てのカードを削除
            $pdo->exec("DELETE FROM online_bingo_cards");
            
            // AUTO_INCREMENTをリセット
            $pdo->exec("ALTER TABLE online_bingo_cards AUTO_INCREMENT = 1");
            if ($achievementTableExists) {
                $pdo->exec("ALTER TABLE bingo_achievements AUTO_INCREMENT = 1");
            }
            
            logDatabaseOperation("DELETE ALL admin cards", ['count' => $cardCount]);
            
            $response = [
                'success' => true,
                'message' => "{$cardCount}枚のカードを全て削除しました",
                'deleted_count' => $cardCount
            ];
        }
        
        echo json_encode($response);
        
    } elseif ($method === 'PUT') {
        // カード更新（アクティブ状態の変更など）
        $input = json_decode(file_get_contents('php://input'), true);
        $cardId = $input['id'] ?? null;
        $isActive = $input['is_active'] ?? null;
        
        if (!$cardId) {
            throw new Exception('カードIDが指定されていません');
        }
        
        $updateFields = [];
        $params = [];
        
        if ($isActive !== null) {
            $updateFields[] = "is_active = ?";
            $params[] = $isActive ? 1 : 0;
        }
        
        if (empty($updateFields)) {
            throw new Exception('更新する項目が指定されていません');
        }
        
        $params[] = $cardId;
        
        $sql = "UPDATE online_bingo_cards SET " . implode(', ', $updateFields) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        $updatedRows = $stmt->rowCount();
        
        if ($updatedRows > 0) {
            logDatabaseOperation("UPDATE admin card", ['card_id' => $cardId, 'fields' => $updateFields]);
            
            $response = [
                'success' => true,
                'message' => 'カードを更新しました',
                'updated_card_id' => $cardId
            ];
        } else {
            throw new Exception('指定されたカードが見つかりません');
        }
        
        echo json_encode($response);
        
    } else {
        throw new Exception('サポートされていないHTTPメソッドです');
    }
    
} catch (Exception $e) {
    $errorMessage = $e->getMessage();
    
    if (DEBUG_MODE) {
        error_log("admin-cards.php error: " . $errorMessage);
        error_log("admin-cards.php stack trace: " . $e->getTraceAsString());
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
