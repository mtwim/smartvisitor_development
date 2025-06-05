<?php
require_once __DIR__ . '/config/config.php';
// tag_webhook.php - Uitgebreid met tag koppeling

error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo "Alleen POST toegestaan.";
    exit;
}

file_put_contents(__DIR__ . '/tag_webhook.log', date('Y-m-d H:i:s') . "\n" . print_r($_POST, true) . "\n", FILE_APPEND);

$tag_id = isset($_POST['tag_id']) ? trim($_POST['tag_id']) : null;
$scanner_id = isset($_POST['scanner_id']) ? trim($_POST['scanner_id']) : null;
$secret = isset($_POST['secret']) ? trim($_POST['secret']) : null;
$mac_address = isset($_POST['mac_address']) ? trim($_POST['mac_address']) : null;
$project_id = isset($_POST['project_id']) ? trim($_POST['project_id']) : null;
$zone_id = isset($_POST['zone_id']) ? trim($_POST['zone_id']) : null;

$expected_secret = 'y7s3fP9eV4qLm29X';

if (empty($tag_id) || empty($scanner_id) || empty($secret)) {
    http_response_code(400);
    echo "Fout: tag_id, scanner_id en secret zijn verplicht.";
    exit;
}

if ($secret !== $expected_secret) {
    http_response_code(403);
    echo "Fout: ongeldige secret.";
    exit;
}

try {
    $pdo = getDbConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET time_zone = '+01:00'");

    // Scanner ophalen
    $stmt = $pdo->prepare("SELECT id FROM scanners WHERE scanner_id = :scanner_id");
    $stmt->execute(['scanner_id' => $scanner_id]);
    $scanner = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$scanner) {
        http_response_code(404);
        echo "❌ Scanner niet gevonden.";
        exit;
    }

    // Check voor automatische tag koppeling
    $contact_id = null;
    $stmt = $pdo->prepare("
        SELECT ps.contact_id, c.full_name 
        FROM pending_scans ps 
        JOIN contacts c ON ps.contact_id = c.id 
        WHERE ps.status = 'waiting' 
        AND (ps.scanner_id IS NULL OR ps.scanner_id = :scanner_id)
        AND ps.expires_at > NOW()
        ORDER BY ps.created_at ASC 
        LIMIT 1
    ");
    $stmt->execute(['scanner_id' => $scanner_id]);
    $pending = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($pending) {
        $contact_id = $pending['contact_id'];
        
        // Koppel tag aan contact
        $stmt = $pdo->prepare("
            INSERT INTO tag_contacts (tag_id, contact_id, scanner_id, linked_by) 
            VALUES (:tag_id, :contact_id, :scanner_id, 'auto_scan')
            ON DUPLICATE KEY UPDATE 
            contact_id = :contact_id, scanner_id = :scanner_id, linked_at = NOW(), status = 'active'
        ");
        $stmt->execute([
            'tag_id' => $tag_id,
            'contact_id' => $contact_id,
            'scanner_id' => $scanner_id
        ]);
        
        // Markeer pending scan als voltooid
        $stmt = $pdo->prepare("UPDATE pending_scans SET status = 'completed' WHERE contact_id = :contact_id AND status = 'waiting'");
        $stmt->execute(['contact_id' => $contact_id]);
        
        file_put_contents(__DIR__ . '/tag_webhook.log', date('Y-m-d H:i:s') . " - Tag $tag_id automatisch gekoppeld aan contact: " . $pending['full_name'] . "\n", FILE_APPEND);
    } else {
        // Check of tag al gekoppeld is
        $stmt = $pdo->prepare("SELECT contact_id FROM tag_contacts WHERE tag_id = :tag_id AND status = 'active'");
        $stmt->execute(['tag_id' => $tag_id]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
            $contact_id = $existing['contact_id'];
        }
    }

    // Tag scan opslaan
    $stmt = $pdo->prepare("
        INSERT INTO tag_scans (tag_id, scanner_id, scanner_db_id, project_id, zone_id, contact_id, created_at) 
        VALUES (:tag_id, :scanner_id, :scanner_db_id, :project_id, :zone_id, :contact_id, NOW())
    ");
    $stmt->execute([
        'tag_id' => $tag_id,
        'scanner_id' => $scanner_id,
        'scanner_db_id' => $scanner['id'],
        'project_id' => $project_id ?: null,
        'zone_id' => $zone_id ?: null,
        'contact_id' => $contact_id
    ]);

    http_response_code(200);
    if ($pending) {
        echo "✅ Scan opgeslagen en tag gekoppeld aan: " . $pending['full_name'];
    } else {
        echo "✅ Scan opgeslagen: tag_id=$tag_id, scanner_id=$scanner_id";
    }

} catch (PDOException $e) {
    file_put_contents(__DIR__ . '/tag_webhook.log', date('Y-m-d H:i:s') . " - DB-fout: " . $e->getMessage() . "\n", FILE_APPEND);
    http_response_code(500);
    echo "❌ Databasefout: " . $e->getMessage();
}
?>
