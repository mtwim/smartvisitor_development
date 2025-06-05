<?php
require_once __DIR__ . '/config/config.php';
// tag_management_api.php

error_reporting(E_ALL);
ini_set('display_errors', 1);

date_default_timezone_set('Europe/Amsterdam');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    $pdo = getDbConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET time_zone = '+01:00'");
} catch (PDOException $e) {
    echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

$action = $_REQUEST['action'] ?? '';

switch ($action) {
    case 'contacts':
        handleContacts($pdo);
        break;
    case 'contacts_all':
        handleContactsAll($pdo);
        break;
    case 'restore_contact':
        handleRestoreContact($pdo);
        break;
    case 'get_contact':
        handleGetContact($pdo);
        break;
    case 'save_contact':
        handleSaveContact($pdo);
        break;
    case 'delete_contact':
        handleDeleteContact($pdo);
        break;
    case 'tag_links':
        handleTagLinks($pdo);
        break;
    case 'pending_scans':
        handlePendingScans($pdo);
        break;
    case 'recent_scans':
        handleRecentScans($pdo);
        break;
    case 'wait_for_scan':
        handleWaitForScan($pdo);
        break;
    case 'cancel_pending_scan':
        handleCancelPendingScan($pdo);
        break;
    case 'unlink_tag':
        handleUnlinkTag($pdo);
        break;
    case 'process_scan':
        handleProcessScan($pdo);
        break;
    default:
        echo json_encode(['error' => 'Unknown action: ' . $action]);
}

function handleContacts($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT c.*, tc.tag_id 
            FROM contacts c 
            LEFT JOIN tag_contacts tc ON c.id = tc.contact_id AND tc.status = 'active'
            WHERE c.status = 'active'
            ORDER BY c.full_name
        ");
        echo json_encode(['contacts' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    } catch (Exception $e) {
        echo json_encode(['error' => 'Failed to load contacts: ' . $e->getMessage()]);
    }
}

function handleContactsAll($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT c.*, tc.tag_id,
                   CASE 
                       WHEN c.status = 'active' THEN 'Actief'
                       WHEN c.status = 'inactive' THEN 'Verwijderd'
                       ELSE c.status
                   END as status_text
            FROM contacts c 
            LEFT JOIN tag_contacts tc ON c.id = tc.contact_id AND tc.status = 'active'
            ORDER BY c.status DESC, c.full_name
        ");
        echo json_encode(['contacts' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    } catch (Exception $e) {
        echo json_encode(['error' => 'Failed to load all contacts: ' . $e->getMessage()]);
    }
}

function handleRestoreContact($pdo) {
    $id = $_POST['id'] ?? '';
    if (empty($id)) {
        echo json_encode(['error' => 'Contact ID required']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("UPDATE contacts SET status = 'active' WHERE id = :id");
        $stmt->execute(['id' => $id]);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['error' => 'Failed to restore contact: ' . $e->getMessage()]);
    }
}

function handleGetContact($pdo) {
    $id = $_GET['id'] ?? '';
    if (empty($id)) {
        echo json_encode(['error' => 'Contact ID required']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM contacts WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $contact = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode(['contact' => $contact]);
    } catch (Exception $e) {
        echo json_encode(['error' => 'Failed to load contact: ' . $e->getMessage()]);
    }
}

function handleSaveContact($pdo) {
    $id = $_POST['id'] ?? '';
    $full_name = $_POST['full_name'] ?? '';
    $function_title = $_POST['function_title'] ?? '';
    $company = $_POST['company'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $dietary_requirements = $_POST['dietary_requirements'] ?? '';
    $notes = $_POST['notes'] ?? '';
    
    if (empty($full_name)) {
        echo json_encode(['error' => 'Volledige naam is verplicht']);
        return;
    }
    
    try {
        if (empty($id)) {
            // Nieuw contact
            $stmt = $pdo->prepare("
                INSERT INTO contacts (full_name, function_title, company, email, phone, dietary_requirements, notes) 
                VALUES (:full_name, :function_title, :company, :email, :phone, :dietary_requirements, :notes)
            ");
        } else {
            // Bestaand contact updaten
            $stmt = $pdo->prepare("
                UPDATE contacts 
                SET full_name = :full_name, function_title = :function_title, company = :company, 
                    email = :email, phone = :phone, dietary_requirements = :dietary_requirements, notes = :notes
                WHERE id = :id
            ");
            $stmt->bindParam(':id', $id);
        }
        
        $stmt->execute([
            'full_name' => $full_name,
            'function_title' => $function_title ?: null,
            'company' => $company ?: null,
            'email' => $email ?: null,
            'phone' => $phone ?: null,
            'dietary_requirements' => $dietary_requirements ?: null,
            'notes' => $notes ?: null
        ]);
        
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['error' => 'Failed to save contact: ' . $e->getMessage()]);
    }
}

function handleDeleteContact($pdo) {
    $id = $_POST['id'] ?? '';
    if (empty($id)) {
        echo json_encode(['error' => 'Contact ID required']);
        return;
    }
    
    try {
        // SOFT DELETE - markeer als inactive in plaats van verwijderen
        $stmt = $pdo->prepare("UPDATE contacts SET status = 'inactive' WHERE id = :id");
        $stmt->execute(['id' => $id]);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['error' => 'Failed to delete contact: ' . $e->getMessage()]);
    }
}

function handleTagLinks($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT tc.*, c.full_name as contact_name, c.company, c.status as contact_status
            FROM tag_contacts tc 
            JOIN contacts c ON tc.contact_id = c.id 
            WHERE tc.status = 'active'
            ORDER BY tc.linked_at DESC
        ");
        echo json_encode(['links' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    } catch (Exception $e) {
        echo json_encode(['error' => 'Failed to load tag links: ' . $e->getMessage()]);
    }
}

function handlePendingScans($pdo) {
    try {
        $pdo->exec("UPDATE pending_scans SET status = 'expired' WHERE expires_at < NOW() AND status = 'waiting'");
        
        $stmt = $pdo->query("
            SELECT ps.*, c.full_name as contact_name 
            FROM pending_scans ps 
            JOIN contacts c ON ps.contact_id = c.id 
            WHERE ps.status = 'waiting' AND c.status = 'active'
            ORDER BY ps.created_at DESC
        ");
        echo json_encode(['pending' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    } catch (Exception $e) {
        echo json_encode(['error' => 'Failed to load pending scans: ' . $e->getMessage()]);
    }
}

function handleRecentScans($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT ts.*, c.full_name as contact_name, s.location, p.name as project_name,
                   CASE WHEN c.status = 'inactive' THEN CONCAT(c.full_name, ' (Verwijderd)') 
                        ELSE c.full_name END as display_name
            FROM tag_scans ts 
            LEFT JOIN tag_contacts tc ON ts.tag_id = tc.tag_id AND tc.status = 'active'
            LEFT JOIN contacts c ON tc.contact_id = c.id
            LEFT JOIN scanners s ON ts.scanner_id = s.scanner_id
            LEFT JOIN projects p ON ts.project_id = p.id
            ORDER BY ts.created_at DESC 
            LIMIT 50
        ");
        echo json_encode(['scans' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    } catch (Exception $e) {
        echo json_encode(['error' => 'Failed to load recent scans: ' . $e->getMessage()]);
    }
}

function handleWaitForScan($pdo) {
    $contact_id = $_POST['contact_id'] ?? '';
    $scanner_id = $_POST['scanner_id'] ?? null;
    
    if (empty($contact_id)) {
        echo json_encode(['error' => 'Contact ID required']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("UPDATE pending_scans SET status = 'expired' WHERE contact_id = :contact_id AND status = 'waiting'");
        $stmt->execute(['contact_id' => $contact_id]);
        
        $stmt = $pdo->prepare("
            INSERT INTO pending_scans (contact_id, scanner_id, expires_at) 
            VALUES (:contact_id, :scanner_id, DATE_ADD(NOW(), INTERVAL 10 MINUTE))
        ");
        $stmt->execute([
            'contact_id' => $contact_id,
            'scanner_id' => $scanner_id ?: null
        ]);
        
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['error' => 'Failed to set up wait for scan: ' . $e->getMessage()]);
    }
}

function handleCancelPendingScan($pdo) {
    $id = $_POST['id'] ?? '';
    if (empty($id)) {
        echo json_encode(['error' => 'Pending scan ID required']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("UPDATE pending_scans SET status = 'expired' WHERE id = :id");
        $stmt->execute(['id' => $id]);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['error' => 'Failed to cancel pending scan: ' . $e->getMessage()]);
    }
}

function handleUnlinkTag($pdo) {
    $tag_id = $_POST['tag_id'] ?? '';
    if (empty($tag_id)) {
        echo json_encode(['error' => 'Tag ID required']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("UPDATE tag_contacts SET status = 'inactive' WHERE tag_id = :tag_id");
        $stmt->execute(['tag_id' => $tag_id]);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['error' => 'Failed to unlink tag: ' . $e->getMessage()]);
    }
}

function handleProcessScan($pdo) {
    $tag_id = $_POST['tag_id'] ?? '';
    $scanner_id = $_POST['scanner_id'] ?? '';
    
    if (empty($tag_id) || empty($scanner_id)) {
        echo json_encode(['error' => 'Tag ID and Scanner ID required']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT ps.*, c.full_name 
            FROM pending_scans ps 
            JOIN contacts c ON ps.contact_id = c.id 
            WHERE ps.status = 'waiting' 
            AND (ps.scanner_id IS NULL OR ps.scanner_id = :scanner_id)
            AND ps.expires_at > NOW()
            AND c.status = 'active'
            ORDER BY ps.created_at ASC 
            LIMIT 1
        ");
        $stmt->execute(['scanner_id' => $scanner_id]);
        $pending = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($pending) {
            $stmt = $pdo->prepare("
                INSERT INTO tag_contacts (tag_id, contact_id, scanner_id, linked_by) 
                VALUES (:tag_id, :contact_id, :scanner_id, 'auto_scan')
                ON DUPLICATE KEY UPDATE 
                contact_id = :contact_id, scanner_id = :scanner_id, linked_at = NOW(), status = 'active'
            ");
            $stmt->execute([
                'tag_id' => $tag_id,
                'contact_id' => $pending['contact_id'],
                'scanner_id' => $scanner_id
            ]);
            
            $stmt = $pdo->prepare("UPDATE pending_scans SET status = 'completed' WHERE id = :id");
            $stmt->execute(['id' => $pending['id']]);
            
            echo json_encode([
                'success' => true, 
                'linked' => true, 
                'contact_name' => $pending['full_name']
            ]);
        } else {
            echo json_encode(['success' => true, 'linked' => false]);
        }
    } catch (Exception $e) {
        echo json_encode(['error' => 'Failed to process scan: ' . $e->getMessage()]);
    }
}
?>
