<?php
require_once __DIR__ . '/../config/config.php';
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
    case 'auto_link_pending_scan':
        handleAutoLinkPendingScan($pdo);
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
            $params = [
                'full_name' => $full_name,
                'function_title' => $function_title ?: null,
                'company' => $company ?: null,
                'email' => $email ?: null,
                'phone' => $phone ?: null,
                'dietary_requirements' => $dietary_requirements ?: null,
                'notes' => $notes ?: null
            ];
        } else {
            // Bestaand contact updaten
            $stmt = $pdo->prepare("
                UPDATE contacts 
                SET full_name = :full_name, function_title = :function_title, company = :company, 
                    email = :email, phone = :phone, dietary_requirements = :dietary_requirements, notes = :notes
                WHERE id = :id
            ");
            $params = [
                'full_name' => $full_name,
                'function_title' => $function_title ?: null,
                'company' => $company ?: null,
                'email' => $email ?: null,
                'phone' => $phone ?: null,
                'dietary_requirements' => $dietary_requirements ?: null,
                'notes' => $notes ?: null,
                'id' => $id
            ];
        }
        $stmt->execute($params);
        logEvent($pdo, 'info', 'contact_save', empty($id) ? 'Contact aangemaakt' : 'Contact bijgewerkt', [
            'id' => $id,
            'full_name' => $full_name,
            'company' => $company,
            'email' => $email
        ]);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        logEvent($pdo, 'error', 'contact_save', 'Fout bij opslaan contact: ' . $e->getMessage(), [
            'id' => $id,
            'full_name' => $full_name,
            'company' => $company,
            'email' => $email
        ]);
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
            SELECT ts.*, 
                   c.full_name as contact_name, 
                   s.location, 
                   p.name as project_name,
                   CASE 
                       WHEN c.full_name IS NOT NULL THEN 
                           CASE WHEN c.status = 'inactive' THEN CONCAT(c.full_name, ' (Verwijderd)') ELSE c.full_name END
                       ELSE 'Onbekend'
                   END as display_name
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

function logEvent($pdo, $level, $context, $message, $extra = null) {
    $stmt = $pdo->prepare("
        INSERT INTO system_logs (log_level, context, message, extra)
        VALUES (:level, :context, :message, :extra)
    ");
    $stmt->execute([
        'level' => $level,
        'context' => $context,
        'message' => $message,
        'extra' => $extra ? json_encode($extra) : null
    ]);
}

function handleWaitForScan($pdo) {
    $contact_id = $_POST['contact_id'] ?? '';
    $scanner_id = $_POST['scanner_id'] ?? null;
    logEvent($pdo, 'info', 'wait_for_scan', 'Start koppeling', ['contact_id' => $contact_id, 'scanner_id' => $scanner_id]);
    if (empty($contact_id)) {
        logEvent($pdo, 'error', 'wait_for_scan', 'Contact ID required', ['contact_id' => $contact_id]);
        echo json_encode(['error' => 'Contact ID required']);
        return;
    }
    try {
        $stmt = $pdo->prepare("UPDATE pending_scans SET status = 'expired' WHERE contact_id = :contact_id AND status = 'waiting'");
        $stmt->execute(['contact_id' => $contact_id]);
        $stmt = $pdo->prepare("
            INSERT INTO pending_scans (contact_id, scanner_id, expires_at, status, created_at)
            VALUES (:contact_id, :scanner_id, DATE_ADD(NOW(), INTERVAL 10 MINUTE), 'waiting', NOW())
        ");
        $stmt->execute([
            'contact_id' => $contact_id,
            'scanner_id' => $scanner_id ?: null
        ]);
        logEvent($pdo, 'info', 'wait_for_scan', 'Pending scan aangemaakt', ['contact_id' => $contact_id, 'scanner_id' => $scanner_id]);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        logEvent($pdo, 'error', 'wait_for_scan', 'Fout bij aanmaken pending scan', ['contact_id' => $contact_id, 'scanner_id' => $scanner_id, 'error' => $e->getMessage()]);
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
                INSERT INTO tag_contacts (tag_id, contact_id, linked_by) 
                VALUES (:tag_id, :contact_id_insert, 'auto_scan')
                ON DUPLICATE KEY UPDATE 
                contact_id = :contact_id_update, linked_at = NOW(), status = 'active'
            ");
            $stmt->execute([
                'tag_id' => $tag_id,
                'contact_id_insert' => $pending['contact_id'],
                'contact_id_update' => $pending['contact_id']
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

function handleAutoLinkPendingScan($pdo) {
    $scanner_id = $_POST['scanner_id'] ?? '';
    logEvent($pdo, 'info', 'auto_link', 'Start auto-link', ['scanner_id' => $scanner_id]);
    if (empty($scanner_id)) {
        logEvent($pdo, 'error', 'auto_link', 'Scanner ID required', ['scanner_id' => $scanner_id]);
        echo json_encode(['error' => 'Scanner ID required']);
        return;
    }
    try {
        // Zoek eerst de pending scan voor deze scanner of alle scanners
        $stmt = $pdo->prepare("
            SELECT * FROM pending_scans 
            WHERE (scanner_id = :scanner_id OR scanner_id IS NULL) 
            AND status = 'waiting' 
            AND expires_at > NOW() 
            ORDER BY created_at ASC 
            LIMIT 1
        ");
        $stmt->execute(['scanner_id' => $scanner_id]);
        $pending = $stmt->fetch(PDO::FETCH_ASSOC);
        logEvent($pdo, 'info', 'auto_link', 'Pending scan gevonden', ['scanner_id' => $scanner_id, 'pending' => $pending]);
        
        if (!$pending) {
            logEvent($pdo, 'info', 'auto_link', 'No pending scan found', ['scanner_id' => $scanner_id]);
            echo json_encode(['success' => false, 'reason' => 'No pending scan found']);
            return;
        }

        // Zoek de eerste scan zonder contact_id voor deze scanner die NA de pending scan is gemaakt
        $stmt = $pdo->prepare("
            SELECT * FROM tag_scans 
            WHERE scanner_id = :scanner_id 
            AND contact_id IS NULL 
            AND created_at > :pending_created_at
            ORDER BY created_at ASC 
            LIMIT 1
        ");
        $stmt->execute([
            'scanner_id' => $scanner_id,
            'pending_created_at' => $pending['created_at']
        ]);
        $scan = $stmt->fetch(PDO::FETCH_ASSOC);
        logEvent($pdo, 'info', 'auto_link', 'Scan gevonden', [
            'scanner_id' => $scanner_id, 
            'scan' => $scan,
            'pending_created_at' => $pending['created_at']
        ]);
        
        if (!$scan) {
            logEvent($pdo, 'info', 'auto_link', 'No unlinked scan found after pending scan', [
                'scanner_id' => $scanner_id,
                'pending_created_at' => $pending['created_at']
            ]);
            echo json_encode(['success' => false, 'reason' => 'No unlinked scan found after pending scan']);
            return;
        }

        // Koppel tag aan contact
        $stmt = $pdo->prepare("
            INSERT INTO tag_contacts (tag_id, contact_id, linked_by) 
            VALUES (:tag_id, :contact_id_insert, 'auto_scan')
            ON DUPLICATE KEY UPDATE 
            contact_id = :contact_id_update, linked_at = NOW(), status = 'active'
        ");
        $stmt->execute([
            'tag_id' => $scan['tag_id'],
            'contact_id_insert' => $pending['contact_id'],
            'contact_id_update' => $pending['contact_id']
        ]);
        logEvent($pdo, 'info', 'auto_link', 'Tag gekoppeld aan contact', [
            'tag_id' => $scan['tag_id'], 
            'contact_id' => $pending['contact_id'],
            'scan_created_at' => $scan['created_at'],
            'pending_created_at' => $pending['created_at']
        ]);

        // Markeer pending scan als voltooid
        $stmt = $pdo->prepare("UPDATE pending_scans SET status = 'completed' WHERE id = :id");
        $stmt->execute(['id' => $pending['id']]);
        logEvent($pdo, 'info', 'auto_link', 'Pending scan op completed gezet', ['pending_id' => $pending['id']]);

        // Update contact_id in scanlog
        $stmt = $pdo->prepare("
            UPDATE tag_scans SET contact_id = :contact_id 
            WHERE id = :scan_id
        ");
        $stmt->execute([
            'contact_id' => $pending['contact_id'],
            'scan_id' => $scan['id']
        ]);
        logEvent($pdo, 'info', 'auto_link', 'Scanlog contact_id geüpdatet', [
            'scan_id' => $scan['id'], 
            'contact_id' => $pending['contact_id']
        ]);

        echo json_encode([
            'success' => true, 
            'linked' => true, 
            'contact_id' => $pending['contact_id'],
            'scan_created_at' => $scan['created_at'],
            'pending_created_at' => $pending['created_at']
        ]);
    } catch (Exception $e) {
        logEvent($pdo, 'error', 'auto_link', 'Fout bij auto-link', [
            'scanner_id' => $scanner_id, 
            'error' => $e->getMessage()
        ]);
        echo json_encode(['error' => 'Failed to auto-link scan: ' . $e->getMessage()]);
    }
}
?>
