CREATE DATABASE IF NOT EXISTS justice_hammer;
USE justice_hammer;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    name VARCHAR(120) NOT NULL,
    role ENUM('LITIGANT','LAWYER','OFFICIAL','ADMIN') NOT NULL,
    district VARCHAR(80) NULL,
    password_hash VARCHAR(255) NOT NULL,
    verified TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE cases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tracking_code VARCHAR(12) UNIQUE NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    type ENUM('Crime','Gender-Based Violence','Land Dispute','Corruption','Other') NOT NULL,
    district VARCHAR(80) NOT NULL,
    incident_date DATE,
    status ENUM('RECEIVED','TRIAGED','ASSIGNED','IN_PROGRESS','CLOSED') DEFAULT 'RECEIVED',
    created_by INT NOT NULL,
    assigned_to INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (assigned_to) REFERENCES users(id),
    INDEX idx_status (status),
    INDEX idx_district (district)
);

CREATE TABLE evidence (
    id INT AUTO_INCREMENT PRIMARY KEY,
    case_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    stored_path VARCHAR(255) NOT NULL,
    sha256 CHAR(64) NOT NULL,
    uploaded_by INT NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (case_id) REFERENCES cases(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id)
);

CREATE TABLE timeline (
    id INT AUTO_INCREMENT PRIMARY KEY,
    case_id INT NULL,
    actor_id INT NULL,
    event VARCHAR(255) NOT NULL,
    meta JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (case_id) REFERENCES cases(id) ON DELETE CASCADE,
    FOREIGN KEY (actor_id) REFERENCES users(id)
);














-- Trigger (demo)
DELIMITER //
CREATE TRIGGER trg_cases_after_update
AFTER UPDATE ON cases
FOR EACH ROW
BEGIN
    IF NEW.status <> OLD.status THEN
        INSERT INTO timeline (case_id, actor_id, event, meta)
        VALUES (NEW.id, NULL, 'Status Change', JSON_OBJECT('from', OLD.status, 'to', NEW.status));
    END IF;
END;
//
DELIMITER ;
