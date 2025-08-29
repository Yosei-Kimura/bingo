<?php
require_once 'config.php';

if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    error_log("admin-stats.php: Starting request processing");
}

try {
    $pdo = getDB();
    
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('GETメソッドのみサポートしています');
    }
    
    // テーブルの存在確認
    $tableExists = $pdo->query("SHOW TABLES LIKE 'online_bingo_cards'")->rowCount() > 0;
    
    if (!$tableExists) {
        $response = [
            'success' => true,
            'stats' => [
                'total_cards' => 0,
                'active_cards' => 0,
                'recent_access' => 0,
                'total_achievements' => 0,
                'bingo_lines' => 0,
                'full_houses' => 0
            ]
        ];
        echo json_encode($response);
        exit;
    }
    
    // 基本統計
    $stmt = $pdo->query("SELECT COUNT(*) as total_cards FROM online_bingo_cards");
    $totalCards = $stmt->fetch()['total_cards'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as active_cards FROM online_bingo_cards WHERE is_active = 1");
    $activeCards = $stmt->fetch()['active_cards'];
    
    // 最近1時間のアクセス数
    $stmt = $pdo->query("SELECT COUNT(*) as recent_access FROM online_bingo_cards WHERE last_access >= DATE_SUB(NOW(), INTERVAL 1 HOUR)");
    $recentAccess = $stmt->fetch()['recent_access'];
    
    // ビンゴ達成統計
    $achievementTableExists = $pdo->query("SHOW TABLES LIKE 'bingo_achievements'")->rowCount() > 0;
    $totalAchievements = 0;
    $bingoLines = 0;
    $fullHouses = 0;
    
    if ($achievementTableExists) {
        $stmt = $pdo->query("SELECT COUNT(*) as total_achievements FROM bingo_achievements");
        $totalAchievements = $stmt->fetch()['total_achievements'];
        
        $stmt = $pdo->query("SELECT COUNT(*) as bingo_lines FROM bingo_achievements WHERE achievement_type IN ('line_horizontal', 'line_vertical', 'line_diagonal')");
        $bingoLines = $stmt->fetch()['bingo_lines'];
        
        $stmt = $pdo->query("SELECT COUNT(*) as full_houses FROM bingo_achievements WHERE achievement_type = 'full_house'");
        $fullHouses = $stmt->fetch()['full_houses'];
    }
    
    logDatabaseOperation("GET admin stats");
    
    $response = [
        'success' => true,
        'stats' => [
            'total_cards' => intval($totalCards),
            'active_cards' => intval($activeCards),
            'recent_access' => intval($recentAccess),
            'total_achievements' => intval($totalAchievements),
            'bingo_lines' => intval($bingoLines),
            'full_houses' => intval($fullHouses)
        ],
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    $errorMessage = $e->getMessage();
    
    if (DEBUG_MODE) {
        error_log("admin-stats.php error: " . $errorMessage);
        error_log("admin-stats.php stack trace: " . $e->getTraceAsString());
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
