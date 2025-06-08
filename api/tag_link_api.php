<?php
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Alleen POST toegestaan']);
    exit;
}

$contact_id = $_POST['contact_id'] ?? '';
$scanner_id = $_POST['scanner_id'] ?? '';

if (empty($contact_id) || empty($scanner_id)) {
    echo json_encode(['error' => 'Contact en scanner zijn verplicht']);
    exit;
}

try {
    $pdo = getDbConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET time_zone = '+01:00'");

    // Oude pending scans voor dit contact op deze scanner verlopen maken
    $stmt = $pdo->prepare("UPDATE pending_scans SET status = 'expired' WHERE contact_id = :contact_id AND scanner_id = :scanner_id AND status = 'waiting'");
    $stmt->execute(['contact_id' => $contact_id, 'scanner_id' => $scanner_id]);

    // Nieuwe pending scan aanmaken
    $stmt = $pdo->prepare("
        INSERT INTO pending_scans (contact_id, scanner_id, expires_at, status, created_at)
        VALUES (:contact_id, :scanner_id, DATE_ADD(NOW(), INTERVAL 10 MINUTE), 'waiting', NOW())
    ");
    $stmt->execute([
        'contact_id' => $contact_id,
        'scanner_id' => $scanner_id
    ]);

    echo json_encode(['success' => true, 'message' => 'Pending scan aangemaakt']);
} catch (Exception $e) {
    echo json_encode(['error' => 'Fout bij aanmaken pending scan: ' . $e->getMessage()]);
} 