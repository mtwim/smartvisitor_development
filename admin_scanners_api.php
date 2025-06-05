<?php
// admin_scanners_api.php

require_once __DIR__ . '/config/config.php';

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
    case 'list':
        try {
            $stmt = $pdo->query("
                SELECT s.*, 
                       p.name as project_name,
                       CASE 
                           WHEN s.last_seen > DATE_SUB(NOW(), INTERVAL 1 MINUTE) THEN 'online'
                           ELSE 'offline'
                       END as connection_status,
                       TIMESTAMPDIFF(SECOND, s.last_seen, NOW()) as seconds_since_last_seen,
                       s.last_seen as last_heartbeat_display
                FROM scanners s 
                LEFT JOIN projects p ON s.project_id = p.id
                ORDER BY s.last_seen DESC
            ");
            
            $scanners = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Debug info toevoegen
            foreach ($scanners as &$scanner) {
                $scanner['debug_info'] = [
                    'last_seen_raw' => $scanner['last_seen'],
                    'seconds_ago' => $scanner['seconds_since_last_seen'],
                    'current_time' => date('Y-m-d H:i:s'),
                    'timezone' => date_default_timezone_get(),
                    'status_raw' => $scanner['status']
                ];
            }
            
            echo json_encode(['scanners' => $scanners]);
        } catch (Exception $e) {
            echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        }
        break;
        
    case 'projects':
        try {
            $stmt = $pdo->query("SHOW TABLES LIKE 'projects'");
            if ($stmt->rowCount() == 0) {
                $pdo->exec("
                    CREATE TABLE projects (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        name VARCHAR(255) NOT NULL,
                        description TEXT NULL,
                        status ENUM('active', 'inactive') DEFAULT 'active',
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    )
                ");
                $pdo->exec("INSERT INTO projects (name, description) VALUES ('Test Project', 'Standaard test project')");
            }
            
            $stmt = $pdo->query("SELECT id, name FROM projects WHERE status = 'active'");
            echo json_encode(['projects' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        } catch (Exception $e) {
            echo json_encode(['error' => 'Projects error: ' . $e->getMessage()]);
        }
        break;
        
    case 'get':
        $scanner_id = $_GET['scanner_id'] ?? '';
        if (empty($scanner_id)) {
            echo json_encode(['error' => 'Scanner ID required']);
            break;
        }
        
        try {
            $stmt = $pdo->prepare("SELECT * FROM scanners WHERE scanner_id = :scanner_id");
            $stmt->execute(['scanner_id' => $scanner_id]);
            $scanner = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'scanner' => $scanner]);
        } catch (Exception $e) {
            echo json_encode(['error' => 'Get scanner error: ' . $e->getMessage()]);
        }
        break;
        
    default:
        echo json_encode(['error' => 'Unknown action: ' . $action]);
}
?>
