USE justice_hammer;

--  lawyer_id to cases table for admin-assigned lawyers
ALTER TABLE cases 
ADD COLUMN lawyer_id INT NULL AFTER assigned_to,
ADD FOREIGN KEY (lawyer_id) REFERENCES users(id);

-- for status
ALTER TABLE cases 
MODIFY COLUMN status ENUM('RECEIVED','TRIAGED','ASSIGNED','IN_PROGRESS','CLOSED','SOLVED') DEFAULT 'RECEIVED';

--  chat_messages
CREATE TABLE IF NOT EXISTS chat_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    case_id INT NOT NULL,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    message TEXT NOT NULL,
    file_path VARCHAR(255) NULL,
    file_name VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (case_id) REFERENCES cases(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id),
    FOREIGN KEY (receiver_id) REFERENCES users(id),
    INDEX idx_case_id (case_id),
    INDEX idx_sender_receiver (sender_id, receiver_id)
);

-- lawyer_ratings table
CREATE TABLE IF NOT EXISTS lawyer_ratings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    case_id INT NOT NULL,
    lawyer_id INT NOT NULL,
    litigant_id INT NOT NULL,
    rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    comment TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (case_id) REFERENCES cases(id) ON DELETE CASCADE,
    FOREIGN KEY (lawyer_id) REFERENCES users(id),
    FOREIGN KEY (litigant_id) REFERENCES users(id),
    UNIQUE KEY unique_case_rating (case_id, litigant_id),
    INDEX idx_lawyer_id (lawyer_id)
);

-- Adds verification_file_path to users table for lawyer verification documents
ALTER TABLE users 
ADD COLUMN verification_file_path VARCHAR(255) NULL AFTER verified;

-- admin_actions table for tracking admin actions (verification approvals/denials)
CREATE TABLE IF NOT EXISTS admin_actions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    target_user_id INT NOT NULL,
    action_type ENUM('VERIFY_LAWYER', 'DENY_LAWYER', 'ASSIGN_CASE') NOT NULL,
    details JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES users(id),
    FOREIGN KEY (target_user_id) REFERENCES users(id),
    INDEX idx_admin_id (admin_id),
    INDEX idx_target_user (target_user_id)
);
