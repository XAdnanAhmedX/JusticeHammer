CREATE DATABASE IF NOT EXISTS justice_hammer_analytics;
USE justice_hammer_analytics;

CREATE TABLE case_snapshots (
    snapshot_id INT AUTO_INCREMENT PRIMARY KEY,
    case_id INT NOT NULL,
    title VARCHAR(200),
    district VARCHAR(80),
    current_status VARCHAR(50),
    evidence_count INT DEFAULT 0,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE monthly_stats (
    stat_id INT AUTO_INCREMENT PRIMARY KEY,
    report_month VARCHAR(7),
    district VARCHAR(80),
    total_cases INT,
    closed_cases INT
);
