-- Nieuwe garderobe structuur (v2)

CREATE TABLE IF NOT EXISTS cloakroom_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    contact_id INT NOT NULL,
    tag_id VARCHAR(64) NOT NULL,
    item_description VARCHAR(255) NOT NULL,
    status ENUM('checked_in','checked_out') DEFAULT 'checked_in',
    checked_in_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    checked_out_at DATETIME NULL,
    notes TEXT,
    FOREIGN KEY (contact_id) REFERENCES contacts(id),
    FOREIGN KEY (tag_id) REFERENCES tag_contacts(tag_id)
);

CREATE TABLE IF NOT EXISTS cloakroom_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT,
    action ENUM('check_in','check_out','error','info') NOT NULL,
    log_time DATETIME DEFAULT CURRENT_TIMESTAMP,
    message TEXT,
    extra JSON NULL,
    FOREIGN KEY (item_id) REFERENCES cloakroom_items(id)
);

CREATE INDEX idx_cloakroom_items_contact ON cloakroom_items(contact_id);
CREATE INDEX idx_cloakroom_items_tag ON cloakroom_items(tag_id);
CREATE INDEX idx_cloakroom_items_status ON cloakroom_items(status); 