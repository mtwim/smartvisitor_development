-- SQL script voor een logtabel voor applicatielogs
CREATE TABLE IF NOT EXISTS system_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    log_time DATETIME DEFAULT CURRENT_TIMESTAMP,
    log_level VARCHAR(16) DEFAULT 'info', -- info, warning, error, debug
    context VARCHAR(64) DEFAULT NULL,     -- bijv. 'auto_link', 'scan', 'api'
    message TEXT,
    extra JSON DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4; 