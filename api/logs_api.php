<?php
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$action = $_GET['action'] ?? '';
if ($action !== 'list') {
    echo json_encode(['error' => 'Unknown action']);
    exit;
}

try {
    $pdo = getDbConnection();
    $stmt = $pdo->query("SELECT * FROM system_logs ORDER BY log_time DESC, id DESC LIMIT 200");
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // Decode JSON extra veld
    foreach ($logs as &$log) {
        if (!empty($log['extra'])) {
            $log['extra'] = json_decode($log['extra'], true);
        }
    }
    echo json_encode(['logs' => $logs]);
} catch (Exception $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
} 