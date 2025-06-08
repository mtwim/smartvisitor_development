<?php
require_once __DIR__ . '/../config/config.php';

date_default_timezone_set('Europe/Amsterdam');
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

function logSystemCloakroom($pdo, $level, $message, $extra = null) {
    try {
        $stmt = $pdo->prepare("INSERT INTO system_logs (log_level, context, message, extra) VALUES (:level, 'cloakroom', :message, :extra)");
        $stmt->execute([
            'level' => $level,
            'message' => $message,
            'extra' => $extra ? json_encode($extra) : null
        ]);
    } catch (Exception $e) {
        error_log('System logging DB error: ' . $e->getMessage());
    }
}

function logCloakroom($pdo, $action, $message, $item_id = null, $extra = null) {
    try {
        $stmt = $pdo->prepare("INSERT INTO cloakroom_logs (item_id, action, message, extra) VALUES (:item_id, :action, :message, :extra)");
        $stmt->execute([
            'item_id' => $item_id,
            'action' => $action,
            'message' => $message,
            'extra' => $extra ? json_encode($extra) : null
        ]);
    } catch (Exception $e) {
        error_log('Cloakroom logging DB error: ' . $e->getMessage());
    }
    // Ook naar system_logs loggen
    logSystemCloakroom($pdo, $action === 'error' ? 'error' : 'info', $message, $extra);
}

try {
    $pdo = getDbConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET time_zone = '+01:00'");
} catch (PDOException $e) {
    logSystemCloakroom(null, 'error', 'Database connection failed', ['error' => $e->getMessage()]);
    echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

$action = $_REQUEST['action'] ?? '';
logSystemCloakroom($pdo, 'info', 'API actie ontvangen', ['action' => $action, 'request' => $_REQUEST]);

switch ($action) {
    case 'get_last_scan':
        $scanner_id = $_GET['scanner_id'] ?? '';
        if (!$scanner_id) {
            logCloakroom($pdo, 'error', 'Geen scanner_id opgegeven bij get_last_scan');
            echo json_encode(['error' => 'Geen scanner_id opgegeven']);
            break;
        }
        try {
            $sql = "SELECT * FROM tag_scans WHERE scanner_id = :scanner_id ORDER BY created_at DESC LIMIT 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['scanner_id' => $scanner_id]);
            $scan = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$scan) {
                logCloakroom($pdo, 'info', 'Geen scan gevonden voor scanner', null, ['scanner_id' => $scanner_id]);
                echo json_encode(['scan' => null]);
            } else {
                echo json_encode(['scan' => $scan]);
            }
        } catch (Exception $e) {
            logCloakroom($pdo, 'error', 'Fout bij ophalen laatste scan', null, ['error' => $e->getMessage(), 'scanner_id' => $scanner_id]);
            echo json_encode(['error' => 'Fout bij ophalen laatste scan: ' . $e->getMessage()]);
        }
        break;

    case 'get_contact_info':
        $tag_id = $_GET['tag_id'] ?? '';
        try {
            $sql = "SELECT c.*, tc.tag_id FROM tag_contacts tc JOIN contacts c ON tc.contact_id = c.id WHERE tc.tag_id = :tag_id AND tc.status = 'active'";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['tag_id' => $tag_id]);
            $contact = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode(['contact' => $contact]);
        } catch (Exception $e) {
            logCloakroom($pdo, 'error', 'Fout bij ophalen contact info', null, ['error' => $e->getMessage(), 'tag_id' => $tag_id]);
            echo json_encode(['error' => 'Fout bij ophalen contact info: ' . $e->getMessage()]);
        }
        break;

    case 'check_in_item':
        $tag_id = $_POST['tag_id'] ?? '';
        $item_description = $_POST['item_description'] ?? '';
        $notes = $_POST['notes'] ?? '';
        if (!$tag_id || !$item_description) {
            logCloakroom($pdo, 'error', 'Tag en item zijn verplicht bij check_in_item', null, ['tag_id' => $tag_id, 'item_description' => $item_description]);
            echo json_encode(['error' => 'Tag en item zijn verplicht']);
            break;
        }
        try {
            $stmt = $pdo->prepare("SELECT contact_id FROM tag_contacts WHERE tag_id = :tag_id AND status = 'active'");
            $stmt->execute(['tag_id' => $tag_id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                logCloakroom($pdo, 'error', 'Geen actief contact voor deze tag bij check_in_item', null, ['tag_id' => $tag_id]);
                echo json_encode(['error' => 'Geen actief contact voor deze tag']);
                break;
            }
            $contact_id = $row['contact_id'];
            $stmt = $pdo->prepare("INSERT INTO cloakroom_items (contact_id, tag_id, item_description, notes) VALUES (:contact_id, :tag_id, :item_description, :notes)");
            $stmt->execute([
                'contact_id' => $contact_id,
                'tag_id' => $tag_id,
                'item_description' => $item_description,
                'notes' => $notes ?: null
            ]);
            $item_id = $pdo->lastInsertId();
            logCloakroom($pdo, 'check_in', 'Item ingecheckt', $item_id, ['tag_id' => $tag_id, 'contact_id' => $contact_id]);
            echo json_encode(['success' => true, 'item_id' => $item_id]);
        } catch (Exception $e) {
            logCloakroom($pdo, 'error', 'Fout bij check_in_item', null, ['error' => $e->getMessage(), 'tag_id' => $tag_id]);
            echo json_encode(['error' => 'Fout bij innemen item: ' . $e->getMessage()]);
        }
        break;

    case 'check_out_item':
        $item_id = $_POST['item_id'] ?? '';
        if (!$item_id) {
            logCloakroom($pdo, 'error', 'Item ID is verplicht bij check_out_item');
            echo json_encode(['error' => 'Item ID is verplicht']);
            break;
        }
        try {
            $stmt = $pdo->prepare("UPDATE cloakroom_items SET status = 'checked_out', checked_out_at = NOW() WHERE id = :item_id AND status = 'checked_in'");
            $stmt->execute(['item_id' => $item_id]);
            if ($stmt->rowCount() === 0) {
                logCloakroom($pdo, 'error', 'Item niet gevonden of al uitgecheckt bij check_out_item', $item_id);
                echo json_encode(['error' => 'Item niet gevonden of al uitgecheckt']);
                break;
            }
            logCloakroom($pdo, 'check_out', 'Item uitgecheckt', $item_id);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            logCloakroom($pdo, 'error', 'Fout bij check_out_item', $item_id, ['error' => $e->getMessage()]);
            echo json_encode(['error' => 'Fout bij uitgeven item: ' . $e->getMessage()]);
        }
        break;

    case 'get_items_for_contact':
        $tag_id = $_GET['tag_id'] ?? '';
        try {
            $sql = "SELECT ci.*, c.full_name, c.company FROM cloakroom_items ci JOIN contacts c ON ci.contact_id = c.id WHERE ci.tag_id = :tag_id ORDER BY ci.checked_in_at DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['tag_id' => $tag_id]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['items' => $items]);
        } catch (Exception $e) {
            logCloakroom($pdo, 'error', 'Fout bij ophalen items voor contact', null, ['error' => $e->getMessage(), 'tag_id' => $tag_id]);
            echo json_encode(['error' => 'Fout bij ophalen items: ' . $e->getMessage()]);
        }
        break;

    case 'get_all_items':
        try {
            $sql = "SELECT ci.*, c.full_name, c.company FROM cloakroom_items ci JOIN contacts c ON ci.contact_id = c.id ORDER BY ci.checked_in_at DESC";
            $stmt = $pdo->query($sql);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['items' => $items]);
        } catch (Exception $e) {
            logCloakroom($pdo, 'error', 'Fout bij ophalen alle items', null, ['error' => $e->getMessage()]);
            echo json_encode(['error' => 'Fout bij ophalen alle items: ' . $e->getMessage()]);
        }
        break;

    default:
        logCloakroom($pdo, 'error', 'Onbekende actie', null, ['action' => $action]);
        echo json_encode(['error' => 'Unknown action']);
} 