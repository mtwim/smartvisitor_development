<?php
require_once __DIR__ . '/config/config.php';
// admin_scanners.php - Basis admin interface

// Database verbinding (hergebruik je bestaande configuratie)
$pdo = getDbConnection();

// Scanners ophalen
$stmt = $pdo->query("SELECT * FROM scanners ORDER BY created_at DESC");
$scanners = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>SmartVisitor - Scanner Beheer</title>
    <style>
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .status-active { color: green; }
        .status-inactive { color: red; }
        .status-maintenance { color: orange; }
    </style>
</head>
<body>
    <h1>SmartVisitor Scanner Beheer</h1>
    
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Scanner ID</th>
                <th>MAC Adres</th>
                <th>Beschrijving</th>
                <th>Locatie</th>
                <th>Zone</th>
                <th>Status</th>
                <th>Laatst Gezien</th>
                <th>Acties</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($scanners as $scanner): ?>
            <tr>
                <td><?= $scanner['id'] ?></td>
                <td><?= htmlspecialchars($scanner['scanner_id']) ?></td>
                <td><?= htmlspecialchars($scanner['mac_address']) ?></td>
                <td><?= htmlspecialchars($scanner['description']) ?></td>
                <td><?= htmlspecialchars($scanner['location']) ?></td>
                <td><?= htmlspecialchars($scanner['zone']) ?></td>
                <td class="status-<?= $scanner['status'] ?>">
                    <?= ucfirst($scanner['status']) ?>
                </td>
                <td><?= $scanner['last_seen'] ?></td>
                <td>
                    <a href="edit_scanner.php?id=<?= $scanner['id'] ?>">Bewerken</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>
