<?php
// scanner_admin.php

error_reporting(E_ALL);
ini_set('display_errors', 1);

date_default_timezone_set('Europe/Amsterdam');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Only POST allowed']);
    exit;
}

$host = 'localhost';
$db   = 'willem_smartvisitor';
$user = 'willem_smartvisitor';
$pass = '83_2Nlvz0';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET time_zone = '+01:00'");
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

$action = $_POST['action'] ?? '';
$secret = $_POST['secret'] ?? '';
$expected_secret = 'y7s3fP9eV4qLm29X';

// Log alle requests voor debugging
file_put_contents(__DIR__ . '/scanner_admin.log', date('Y-m-d H:i:s') . " - Action: $action\n" . print_r($_POST, true) . "\n", FILE_APPEND);

if ($secret !== $expected_secret) {
    echo json_encode(['success' => false, 'error' => 'Invalid secret']);
    exit;
}

switch ($action) {
    case 'register':
        handleRegister($pdo);
        break;
    case 'heartbeat':
        handleHeartbeat($pdo);
        break;
    case 'check_config':
        handleCheckConfig($pdo);
        break;
    case 'identify':
        handleIdentify($pdo);
        break;
    case 'update_config':
        handleUpdateConfig($pdo);
        break;
    default:
        echo json_encode(['success' => false, 'error' => 'Unknown action']);
}

function handleRegister($pdo) {
    $mac_address = $_POST['mac_address'] ?? '';
    
    if (empty($mac_address)) {
        echo json_encode(['success' => false, 'error' => 'MAC address required']);
        return;
    }
    
    try {
        // Check of scanner al bestaat
        $stmt = $pdo->prepare("SELECT * FROM scanners WHERE mac_address = :mac_address");
        $stmt->execute(['mac_address' => $mac_address]);
        $scanner = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($scanner) {
            // Update bestaande scanner
            $stmt = $pdo->prepare("UPDATE scanners SET last_seen = NOW(), status = 'active' WHERE id = :id");
            $stmt->execute(['id' => $scanner['id']]);
            
            file_put_contents(__DIR__ . '/scanner_admin.log', date('Y-m-d H:i:s') . " - REGISTER: Updated existing scanner " . $scanner['scanner_id'] . "\n", FILE_APPEND);
            
            echo json_encode([
                'success' => true,
                'scanner_id' => $scanner['scanner_id'],
                'configured' => !empty($scanner['project_id'])
            ]);
        } else {
            // Nieuwe scanner registreren
            $scanner_id = 'SCN_' . strtoupper(substr(md5($mac_address . time()), 0, 8));
            
            $stmt = $pdo->prepare("
                INSERT INTO scanners (scanner_id, mac_address, description, status, last_seen) 
                VALUES (:scanner_id, :mac_address, :description, 'active', NOW())
            ");
            $stmt->execute([
                'scanner_id' => $scanner_id,
                'mac_address' => $mac_address,
                'description' => 'Auto-registered scanner'
            ]);
            
            file_put_contents(__DIR__ . '/scanner_admin.log', date('Y-m-d H:i:s') . " - REGISTER: Created new scanner $scanner_id\n", FILE_APPEND);
            
            echo json_encode([
                'success' => true,
                'scanner_id' => $scanner_id,
                'configured' => false
            ]);
        }
    } catch (Exception $e) {
        file_put_contents(__DIR__ . '/scanner_admin.log', date('Y-m-d H:i:s') . " - REGISTER ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
        echo json_encode(['success' => false, 'error' => 'Registration failed: ' . $e->getMessage()]);
    }
}

function handleHeartbeat($pdo) {
    $scanner_id = $_POST['scanner_id'] ?? '';
    $status = $_POST['status'] ?? 'unknown';
    $mac_address = $_POST['mac_address'] ?? '';
    
    if (empty($scanner_id)) {
        echo json_encode(['success' => false, 'error' => 'Scanner ID required']);
        return;
    }
    
    try {
        // Check welke kolommen bestaan
        $stmt = $pdo->query("DESCRIBE scanners");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $setParts = [
            "last_seen = NOW()",
            "status = :status"
        ];
        
        if (in_array('updated_at', $columns)) {
            $setParts[] = "updated_at = NOW()";
        }
        
        if (!empty($mac_address)) {
            $setParts[] = "mac_address = :mac_address";
        }
        
        $sql = "UPDATE scanners SET " . implode(', ', $setParts) . " WHERE scanner_id = :scanner_id";
        
        $params = [
            'status' => $status,
            'scanner_id' => $scanner_id
        ];
        
        if (!empty($mac_address)) {
            $params['mac_address'] = $mac_address;
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        $rowCount = $stmt->rowCount();
        
        file_put_contents(__DIR__ . '/scanner_admin.log', date('Y-m-d H:i:s') . " - HEARTBEAT: $scanner_id, status: $status, rows updated: $rowCount\n", FILE_APPEND);
        
        if ($rowCount > 0) {
            echo json_encode([
                'success' => true, 
                'timestamp' => date('Y-m-d H:i:s'),
                'status_updated' => $status,
                'rows_affected' => $rowCount
            ]);
        } else {
            echo json_encode([
                'success' => false, 
                'error' => 'Scanner not found',
                'scanner_id' => $scanner_id
            ]);
        }
        
    } catch (Exception $e) {
        file_put_contents(__DIR__ . '/scanner_admin.log', date('Y-m-d H:i:s') . " - HEARTBEAT ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
        echo json_encode(['success' => false, 'error' => 'Heartbeat failed: ' . $e->getMessage()]);
    }
}

function handleCheckConfig($pdo) {
    $scanner_id = $_POST['scanner_id'] ?? '';
    
    if (empty($scanner_id)) {
        echo json_encode(['success' => false, 'error' => 'Scanner ID required']);
        return;
    }
    
    try {
        // Update last_seen ook bij config check
        $stmt = $pdo->prepare("UPDATE scanners SET last_seen = NOW() WHERE scanner_id = :scanner_id");
        $stmt->execute(['scanner_id' => $scanner_id]);
        
        $stmt = $pdo->prepare("SELECT * FROM scanners WHERE scanner_id = :scanner_id");
        $stmt->execute(['scanner_id' => $scanner_id]);
        $scanner = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$scanner) {
            echo json_encode(['success' => false, 'error' => 'Scanner not found']);
            return;
        }
        
        $response = [
            'success' => true,
            'identify' => false,
            'config_updated' => false
        ];
        
        // Check voor identify request
        if (isset($scanner['identify_requested']) && $scanner['identify_requested']) {
            $response['identify'] = true;
            
            // Reset identify flag ONMIDDELLIJK
            $stmt = $pdo->prepare("UPDATE scanners SET identify_requested = FALSE WHERE id = :id");
            $stmt->execute(['id' => $scanner['id']]);
            
            file_put_contents(__DIR__ . '/scanner_admin.log', date('Y-m-d H:i:s') . " - CONFIG_CHECK: Identify TRUE returned for $scanner_id, flag reset\n", FILE_APPEND);
        }
        
        // Check voor configuratie updates
        if (!empty($scanner['project_id'])) {
            $response['config_updated'] = true;
            $response['project_id'] = $scanner['project_id'];
            $response['zone_id'] = $scanner['zone'] ?? '';
            $response['location'] = $scanner['location'] ?? '';
        }
        
        echo json_encode($response);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Config check failed: ' . $e->getMessage()]);
    }
}

function handleIdentify($pdo) {
    $scanner_id = $_POST['scanner_id'] ?? '';
    
    if (empty($scanner_id)) {
        echo json_encode(['success' => false, 'error' => 'Scanner ID required']);
        return;
    }
    
    try {
        // Check of identify_requested kolom bestaat
        $stmt = $pdo->query("DESCRIBE scanners");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (in_array('identify_requested', $columns)) {
            $stmt = $pdo->prepare("UPDATE scanners SET identify_requested = TRUE WHERE scanner_id = :scanner_id");
            $stmt->execute(['scanner_id' => $scanner_id]);
            
            file_put_contents(__DIR__ . '/scanner_admin.log', date('Y-m-d H:i:s') . " - IDENTIFY: Set flag for $scanner_id\n", FILE_APPEND);
        } else {
            // Als kolom niet bestaat, voeg deze toe
            $pdo->exec("ALTER TABLE scanners ADD COLUMN identify_requested BOOLEAN DEFAULT FALSE");
            $stmt = $pdo->prepare("UPDATE scanners SET identify_requested = TRUE WHERE scanner_id = :scanner_id");
            $stmt->execute(['scanner_id' => $scanner_id]);
            
            file_put_contents(__DIR__ . '/scanner_admin.log', date('Y-m-d H:i:s') . " - IDENTIFY: Added column and set flag for $scanner_id\n", FILE_APPEND);
        }
        
        echo json_encode(['success' => true, 'message' => 'Identify signal sent']);
    } catch (Exception $e) {
        file_put_contents(__DIR__ . '/scanner_admin.log', date('Y-m-d H:i:s') . " - IDENTIFY ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
        echo json_encode(['success' => false, 'error' => 'Identify failed: ' . $e->getMessage()]);
    }
}

function handleUpdateConfig($pdo) {
    $scanner_id = $_POST['scanner_id'] ?? '';
    $project_id = $_POST['project_id'] ?? '';
    $zone = $_POST['zone'] ?? '';
    $location = $_POST['location'] ?? '';
    $description = $_POST['description'] ?? '';
    
    if (empty($scanner_id)) {
        echo json_encode(['success' => false, 'error' => 'Scanner ID required']);
        return;
    }
    
    try {
        $stmt = $pdo->query("DESCRIBE scanners");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $setParts = ["last_seen = NOW()"];
        $params = ['scanner_id' => $scanner_id];
        
        if (in_array('updated_at', $columns)) {
            $setParts[] = "updated_at = NOW()";
        }
        
        if (in_array('project_id', $columns)) {
            $setParts[] = "project_id = :project_id";
            $params['project_id'] = $project_id ?: null;
        }
        
        if (in_array('zone', $columns)) {
            $setParts[] = "zone = :zone";
            $params['zone'] = $zone;
        }
        
        if (in_array('location', $columns)) {
            $setParts[] = "location = :location";
            $params['location'] = $location;
        }
        
        if (in_array('description', $columns)) {
            $setParts[] = "description = :description";
            $params['description'] = $description;
        }
        
        $sql = "UPDATE scanners SET " . implode(', ', $setParts) . " WHERE scanner_id = :scanner_id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        file_put_contents(__DIR__ . '/scanner_admin.log', date('Y-m-d H:i:s') . " - UPDATE_CONFIG: Updated $scanner_id\n", FILE_APPEND);
        
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Update failed: ' . $e->getMessage()]);
    }
}
?>
