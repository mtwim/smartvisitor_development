-- Garderobe items tabel
CREATE TABLE IF NOT EXISTS cloakroom_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    contact_id INT NOT NULL,
    tag_id VARCHAR(64) NOT NULL,
    item_description TEXT NOT NULL,
    status ENUM('checked_in', 'checked_out') NOT NULL DEFAULT 'checked_in',
    checked_in_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    checked_out_at DATETIME DEFAULT NULL,
    notes TEXT,
    FOREIGN KEY (contact_id) REFERENCES contacts(id),
    FOREIGN KEY (tag_id) REFERENCES tag_contacts(tag_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Garderobe scans tabel (voor logging)
CREATE TABLE IF NOT EXISTS cloakroom_scans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tag_id VARCHAR(64) NOT NULL,
    scanner_id VARCHAR(64) NOT NULL,
    scan_time DATETIME DEFAULT CURRENT_TIMESTAMP,
    action ENUM('check_in', 'check_out') NOT NULL,
    item_id INT,
    FOREIGN KEY (tag_id) REFERENCES tag_contacts(tag_id),
    FOREIGN KEY (scanner_id) REFERENCES scanners(scanner_id),
    FOREIGN KEY (item_id) REFERENCES cloakroom_items(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Indexes voor betere performance
CREATE INDEX idx_cloakroom_items_contact ON cloakroom_items(contact_id);
CREATE INDEX idx_cloakroom_items_tag ON cloakroom_items(tag_id);
CREATE INDEX idx_cloakroom_items_status ON cloakroom_items(status);
CREATE INDEX idx_cloakroom_scans_tag ON cloakroom_scans(tag_id);
CREATE INDEX idx_cloakroom_scans_scanner ON cloakroom_scans(scanner_id);
CREATE INDEX idx_cloakroom_scans_time ON cloakroom_scans(scan_time); 