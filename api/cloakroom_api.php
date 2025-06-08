<?php
require_once __DIR__ . '/../config/config.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

date_default_timezone_set('Europe/Amsterdam');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Logging functie
function logDebug($message, $context = [], $level = 'debug') {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            INSERT INTO system_logs (log_level, context, message, extra)
            VALUES (:level, 'cloakroom_debug', :message, :extra)
        ");
        $stmt->execute([
            'level' => $level,
            'message' => $message,
            'extra' => json_encode($context)
        ]);
    } catch (Exception $e) {
        // Fallback naar error_log als database logging faalt
        error_log("Cloakroom Debug: " . $message . " - " . json_encode($context));
    }
}

try {
    $pdo = getDbConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET time_zone = '+01:00'");
    logDebug("Database verbinding succesvol", ['time' => date('Y-m-d H:i:s')]);
} catch (PDOException $e) {
    logDebug("Database verbinding mislukt", ['error' => $e->getMessage()], 'error');
    echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

$action = $_REQUEST['action'] ?? '';
logDebug("API actie ontvangen", ['action' => $action, 'request' => $_REQUEST]);

switch ($action) {
    case 'check_in':
        handleCheckIn($pdo);
        break;
    case 'check_out':
        handleCheckOut($pdo);
        break;
    case 'get_items':
        handleGetItems($pdo);
        break;
    case 'get_contact_items':
        handleGetContactItems($pdo);
        break;
    case 'get_recent_scans':
        handleGetRecentScans($pdo);
        break;
    default:
        logDebug("Onbekende actie", ['action' => $action], 'warning');
        echo json_encode(['error' => 'Unknown action: ' . $action]);
}

function handleCheckIn($pdo) {
    $tag_id = $_POST['tag_id'] ?? '';
    $scanner_id = $_POST['scanner_id'] ?? '';
    $item_description = $_POST['item_description'] ?? '';
    $notes = $_POST['notes'] ?? '';
    
    logDebug("Check-in poging", [
        'tag_id' => $tag_id,
        'scanner_id' => $scanner_id,
        'item_description' => $item_description
    ]);
    
    if (empty($tag_id) || empty($scanner_id) || empty($item_description)) {
        logDebug("Check-in validatie mislukt", [
            'tag_id_empty' => empty($tag_id),
            'scanner_id_empty' => empty($scanner_id),
            'item_description_empty' => empty($item_description)
        ], 'warning');
        echo json_encode(['error' => 'Tag ID, Scanner ID en item beschrijving zijn verplicht']);
        return;
    }
    
    try {
        // Haal contact op basis van tag
        $stmt = $pdo->prepare("
            SELECT tc.contact_id, c.full_name 
            FROM tag_contacts tc 
            JOIN contacts c ON tc.contact_id = c.id 
            WHERE tc.tag_id = :tag_id AND tc.status = 'active'
        ");
        $stmt->execute(['tag_id' => $tag_id]);
        $contact = $stmt->fetch(PDO::FETCH_ASSOC);
        
        logDebug("Contact opgehaald", [
            'tag_id' => $tag_id,
            'contact_found' => !empty($contact),
            'contact_data' => $contact
        ]);
        
        if (!$contact) {
            logDebug("Geen actief contact gevonden", ['tag_id' => $tag_id], 'warning');
            echo json_encode(['error' => 'Geen actief contact gevonden voor deze tag']);
            return;
        }
        
        // Start transaction
        $pdo->beginTransaction();
        logDebug("Transaction gestart");
        
        // Voeg item toe
        $stmt = $pdo->prepare("
            INSERT INTO cloakroom_items (contact_id, tag_id, item_description, notes)
            VALUES (:contact_id, :tag_id, :item_description, :notes)
        ");
        $stmt->execute([
            'contact_id' => $contact['contact_id'],
            'tag_id' => $tag_id,
            'item_description' => $item_description,
            'notes' => $notes ?: null
        ]);
        $item_id = $pdo->lastInsertId();
        
        logDebug("Item toegevoegd", [
            'item_id' => $item_id,
            'contact_id' => $contact['contact_id'],
            'tag_id' => $tag_id
        ]);
        
        // Log scan
        $stmt = $pdo->prepare("
            INSERT INTO cloakroom_scans (tag_id, scanner_id, action, item_id)
            VALUES (:tag_id, :scanner_id, 'check_in', :item_id)
        ");
        $stmt->execute([
            'tag_id' => $tag_id,
            'scanner_id' => $scanner_id,
            'item_id' => $item_id
        ]);
        
        logDebug("Scan gelogd", [
            'tag_id' => $tag_id,
            'scanner_id' => $scanner_id,
            'item_id' => $item_id,
            'action' => 'check_in'
        ]);
        
        // Log actie
        $logStmt = $pdo->prepare("
            INSERT INTO system_logs (log_level, context, message, extra)
            VALUES ('info', 'cloakroom', :message, :extra)
        ");
        $logStmt->execute([
            'message' => 'Item ingecheckt in garderobe',
            'extra' => json_encode([
                'contact_id' => $contact['contact_id'],
                'contact_name' => $contact['full_name'],
                'tag_id' => $tag_id,
                'item_id' => $item_id,
                'item_description' => $item_description
            ])
        ]);
        
        $pdo->commit();
        logDebug("Transaction succesvol afgerond");
        
        echo json_encode([
            'success' => true,
            'contact' => $contact,
            'item_id' => $item_id
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        logDebug("Fout bij check-in", [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ], 'error');
        echo json_encode(['error' => 'Failed to check in item: ' . $e->getMessage()]);
    }
}

function handleCheckOut($pdo) {
    $item_id = $_POST['item_id'] ?? '';
    $tag_id = $_POST['tag_id'] ?? '';
    $scanner_id = $_POST['scanner_id'] ?? '';
    
    logDebug("Check-out poging", [
        'item_id' => $item_id,
        'tag_id' => $tag_id,
        'scanner_id' => $scanner_id
    ]);
    
    if (empty($item_id) || empty($tag_id) || empty($scanner_id)) {
        logDebug("Check-out validatie mislukt", [
            'item_id_empty' => empty($item_id),
            'tag_id_empty' => empty($tag_id),
            'scanner_id_empty' => empty($scanner_id)
        ], 'warning');
        echo json_encode(['error' => 'Item ID, Tag ID en Scanner ID zijn verplicht']);
        return;
    }
    
    try {
        // Start transaction
        $pdo->beginTransaction();
        logDebug("Transaction gestart voor check-out");
        
        // Update item status
        $stmt = $pdo->prepare("
            UPDATE cloakroom_items 
            SET status = 'checked_out', checked_out_at = NOW()
            WHERE id = :item_id AND status = 'checked_in'
        ");
        $stmt->execute(['item_id' => $item_id]);
        
        logDebug("Item status update", [
            'item_id' => $item_id,
            'rows_affected' => $stmt->rowCount()
        ]);
        
        if ($stmt->rowCount() === 0) {
            throw new Exception('Item niet gevonden of al uitgecheckt');
        }
        
        // Log scan
        $stmt = $pdo->prepare("
            INSERT INTO cloakroom_scans (tag_id, scanner_id, action, item_id)
            VALUES (:tag_id, :scanner_id, 'check_out', :item_id)
        ");
        $stmt->execute([
            'tag_id' => $tag_id,
            'scanner_id' => $scanner_id,
            'item_id' => $item_id
        ]);
        
        logDebug("Check-out scan gelogd", [
            'tag_id' => $tag_id,
            'scanner_id' => $scanner_id,
            'item_id' => $item_id
        ]);
        
        // Log actie
        $logStmt = $pdo->prepare("
            INSERT INTO system_logs (log_level, context, message, extra)
            VALUES ('info', 'cloakroom', :message, :extra)
        ");
        $logStmt->execute([
            'message' => 'Item uitgecheckt uit garderobe',
            'extra' => json_encode([
                'item_id' => $item_id,
                'tag_id' => $tag_id
            ])
        ]);
        
        $pdo->commit();
        logDebug("Check-out transaction succesvol afgerond");
        
        echo json_encode(['success' => true]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        logDebug("Fout bij check-out", [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ], 'error');
        echo json_encode(['error' => 'Failed to check out item: ' . $e->getMessage()]);
    }
}

function handleGetItems($pdo) {
    try {
        logDebug("Ophalen van alle items gestart");
        
        $stmt = $pdo->query("
            SELECT ci.*, c.full_name as contact_name, c.company,
                   tc.tag_id, s.location as scanner_location
            FROM cloakroom_items ci
            JOIN contacts c ON ci.contact_id = c.id
            JOIN tag_contacts tc ON ci.tag_id = tc.tag_id
            LEFT JOIN cloakroom_scans cs ON ci.id = cs.item_id AND cs.action = 'check_in'
            LEFT JOIN scanners s ON cs.scanner_id = s.scanner_id
            WHERE ci.status = 'checked_in'
            ORDER BY ci.checked_in_at DESC
        ");
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        logDebug("Items opgehaald", [
            'count' => count($items)
        ]);
        
        echo json_encode(['items' => $items]);
    } catch (Exception $e) {
        logDebug("Fout bij ophalen items", [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ], 'error');
        echo json_encode(['error' => 'Failed to load items: ' . $e->getMessage()]);
    }
}

function handleGetContactItems($pdo) {
    $tag_id = $_GET['tag_id'] ?? '';
    
    logDebug("Ophalen contact items gestart", ['tag_id' => $tag_id]);
    
    if (empty($tag_id)) {
        logDebug("Tag ID ontbreekt", [], 'warning');
        echo json_encode(['error' => 'Tag ID is verplicht']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT ci.*, c.full_name as contact_name, c.company,
                   tc.tag_id, s.location as scanner_location
            FROM cloakroom_items ci
            JOIN contacts c ON ci.contact_id = c.id
            JOIN tag_contacts tc ON ci.tag_id = tc.tag_id
            LEFT JOIN cloakroom_scans cs ON ci.id = cs.item_id AND cs.action = 'check_in'
            LEFT JOIN scanners s ON cs.scanner_id = s.scanner_id
            WHERE tc.tag_id = :tag_id AND ci.status = 'checked_in'
            ORDER BY ci.checked_in_at DESC
        ");
        $stmt->execute(['tag_id' => $tag_id]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        logDebug("Contact items opgehaald", [
            'tag_id' => $tag_id,
            'count' => count($items)
        ]);
        
        echo json_encode(['items' => $items]);
    } catch (Exception $e) {
        logDebug("Fout bij ophalen contact items", [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ], 'error');
        echo json_encode(['error' => 'Failed to load contact items: ' . $e->getMessage()]);
    }
}

function handleGetRecentScans($pdo) {
    try {
        logDebug("Ophalen recente scans gestart");
        
        $stmt = $pdo->query("
            SELECT cs.*, c.full_name as contact_name, ci.item_description,
                   s.location as scanner_location
            FROM cloakroom_scans cs
            JOIN cloakroom_items ci ON cs.item_id = ci.id
            JOIN contacts c ON ci.contact_id = c.id
            JOIN scanners s ON cs.scanner_id = s.scanner_id
            ORDER BY cs.scan_time DESC
            LIMIT 50
        ");
        $scans = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        logDebug("Recente scans opgehaald", [
            'count' => count($scans)
        ]);
        
        echo json_encode(['scans' => $scans]);
    } catch (Exception $e) {
        logDebug("Fout bij ophalen recente scans", [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ], 'error');
        echo json_encode(['error' => 'Failed to load recent scans: ' . $e->getMessage()]);
    }
} 