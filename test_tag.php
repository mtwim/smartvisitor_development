<?php
// test_tag.php

header('Content-Type: application/json');

// Alleen POST toestaan
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Alleen POST toegestaan']);
    exit;
}

// Data uitlezen
$tag_id = $_POST['tag_id'] ?? '';
$scanner_id = $_POST['scanner_id'] ?? '';
$secret = $_POST['secret'] ?? '';

// Eenvoudige controle
if (empty($tag_id) || empty($scanner_id) || empty($secret)) {
    http_response_code(400);
    echo json_encode(['error' => 'Veld(en) ontbreken']);
    exit;
}

// Loggen in bestand
$log = date('Y-m-d H:i:s') . " | tag: $tag_id | scanner: $scanner_id\n";
file_put_contents(__DIR__ . '/tag_log.txt', $log, FILE_APPEND);

// JSON-response
echo json_encode([
    'status' => 'ok',
    'echo' => [
        'tag_id' => $tag_id,
        'scanner_id' => $scanner_id
    ]
]);
