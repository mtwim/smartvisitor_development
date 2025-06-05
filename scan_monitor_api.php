<?php
require_once __DIR__ . '/config/config.php';
// scan_monitor_api.php

error_reporting(E_ALL);
ini_set('display_errors', 1);

date_default_timezone_set('Europe/Amsterdam');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$host = 'localhost';
$db   = 'willem_smartvisitor';
$user = 'willem_smartvisitor';
$pass = '83_2Nlvz0';

try {
    $pdo = getDbConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET time_zone = '+01:00'");
} catch (PDOException $e) {
    echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'latest_scans':
        handleLatestScans($pdo);
        break;
    default:
        echo json_encode(['error' => 'Unknown action: ' . $action]);
}

function handleLatestScans($pdo) {
    $scanner_id = $_GET['scanner_id'] ?? '';
    $since_id = $_GET['since_id'] ?? 0;
    
    try {
        $sql = "
            SELECT ts.*, 
                   c.full_name as contact_name,
                   c.company,
                   c.function_title,
                   c.email,
                   c.phone,
                   c.dietary_requirements,
                   s.location,
                   p.name as project_name
            FROM tag_scans ts 
            LEFT JOIN tag_contacts tc ON ts.tag_id = tc.tag_id AND tc.status = 'active'
            LEFT JOIN contacts c ON tc.contact_id = c.id AND c.status = 'active'
            LEFT JOIN scanners s ON ts.scanner_id = s.scanner_id
            LEFT JOIN projects p ON ts.project_id = p.id
            WHERE ts.id > :since_id
        ";
        
        $params = ['since_id' => $since_id];
        
        if (!empty($scanner_id)) {
            $sql .= " AND ts.scanner_id = :scanner_id";
            $params['scanner_id'] = $scanner_id;
        }
        
        $sql .= " ORDER BY ts.created_at DESC LIMIT 10";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $scans = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'scans' => $scans,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['error' => 'Failed to load scans: ' . $e->getMessage()]);
    }
}
?>
