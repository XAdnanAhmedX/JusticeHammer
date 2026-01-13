-- Seed Data for Justice Hammer DBMS
-- Run this after schema.sql has been executed

USE justice_hammer;

-- Admin user (id=1)
-- Password: AdminPass123
-- Generate hash with: php -r "echo password_hash('AdminPass123', PASSWORD_DEFAULT);"
INSERT INTO users (id, email, name, role, password_hash, verified) VALUES
(1, 'admin@example.test', 'Admin', 'ADMIN', '$2y$12$TVBEvNrzPB5K9d0zCWNCjOPOnyi4nd8Z7EG1XIIpnFg0R/SOQ2N3a', 1);

-- Officials
-- Official A: verified, district Dhaka
-- Official B: unverified, district Chittagong
-- Password: OfficialPass123
-- Generate hash with: php -r "echo password_hash('OfficialPass123', PASSWORD_DEFAULT);"
INSERT INTO users (email, name, role, district, password_hash, verified) VALUES
('official.a@example.test', 'Official A', 'OFFICIAL', 'Dhaka', '$2y$12$FFp0H70XV6SjrY6qEgUCd.bZnwRg.wSAHMI/qTC7gen4PQl0Lqoou', 1),
('official.b@example.test', 'Official B', 'OFFICIAL', 'Chittagong', '$2y$12$FFp0H70XV6SjrY6qEgUCd.bZnwRg.wSAHMI/qTC7gen4PQl0Lqoou', 0);

-- Lawyers
-- Lawyer A: verified
-- Lawyer B: unverified
-- Password: LawyerPass123
-- Generate hash with: php -r "echo password_hash('LawyerPass123', PASSWORD_DEFAULT);"
INSERT INTO users (email, name, role, district, password_hash, verified) VALUES
('lawyer.a@example.test', 'Lawyer A', 'LAWYER', 'Dhaka', '$2y$12$Ilz.ZB6J1jK1MEuX0D7OluzLbR6HvaXN4wbyF5GBhr3UmTDo94ASS', 1),
('lawyer.b@example.test', 'Lawyer B', 'LAWYER', 'Chittagong', '$2y$12$Ilz.ZB6J1jK1MEuX0D7OluzLbR6HvaXN4wbyF5GBhr3UmTDo94ASS', 0);

-- Litigants (3 sample litigants)
-- Password: LitigantPass123
-- Generate hash with: php -r "echo password_hash('LitigantPass123', PASSWORD_DEFAULT);"
INSERT INTO users (email, name, role, district, password_hash, verified) VALUES
('litigant.a@example.test', 'Litigant A', 'LITIGANT', 'Dhaka', '$2y$12$redRYiM/7OOYS/GC4iOdgOXplYh5YwWZIWteG0KwAp.CrhSMrd7ei', 1),
('litigant.b@example.test', 'Litigant B', 'LITIGANT', 'Chittagong', '$2y$12$redRYiM/7OOYS/GC4iOdgOXplYh5YwWZIWteG0KwAp.CrhSMrd7ei', 1),
('litigant.c@example.test', 'Litigant C', 'LITIGANT', 'Dhaka', '$2y$12$redRYiM/7OOYS/GC4iOdgOXplYh5YwWZIWteG0KwAp.CrhSMrd7ei', 1);

-- Sample Cases
-- Note: User IDs assumed: Admin=1, Official A=2, Official B=3, Lawyer A=4, Lawyer B=5, Litigant A=6, Litigant B=7, Litigant C=8
-- Cases must be created by litigants
INSERT INTO cases (tracking_code, title, description, type, district, incident_date, status, created_by, assigned_to) VALUES
('JH2024001', 'Sample Case 1: Property Dispute', 'A dispute over land ownership in Dhaka district', 'Land Dispute', 'Dhaka', '2024-01-15', 'RECEIVED', 6, NULL),
('JH2024002', 'Sample Case 2: Theft Report', 'Reported theft incident in Chittagong', 'Crime', 'Chittagong', '2024-02-20', 'ASSIGNED', 7, 2);

-- Sample Timeline entries
INSERT INTO timeline (case_id, actor_id, event, meta) VALUES
(1, 6, 'Received', JSON_OBJECT('contact_pref', 'EMAIL', 'sensitive', 0, 'open_consent', 1)),
(2, 7, 'Received', JSON_OBJECT('contact_pref', 'PHONE', 'sensitive', 0, 'open_consent', 1)),
(2, 1, 'Assigned to Official', JSON_OBJECT('officialId', 2));

-- Sample Evidence (placeholder files)
-- Note: You need to create placeholder files in /uploads directory
-- Generate SHA256 for placeholder: php -r "echo hash_file('sha256', 'uploads/placeholder1.pdf');"
-- For now, we'll insert with example hashes - update these after creating actual placeholder files
-- The hash 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855' is SHA256 of empty string
INSERT INTO evidence (case_id, filename, stored_path, sha256, uploaded_by) VALUES
(1, 'document1.pdf', 'uploads/placeholder1.pdf', 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855', 6),
(2, 'evidence1.jpg', 'uploads/placeholder2.jpg', 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855', 7);

-- Instructions:
-- 1. To regenerate password hashes, use: php -r "echo password_hash('YourPassword', PASSWORD_DEFAULT);"
-- 2. Before running this seed, create placeholder files in /uploads/ directory:
--    - uploads/placeholder1.pdf (0-byte file is fine for demo)
--    - uploads/placeholder2.jpg (0-byte file is fine for demo)
-- 3. To compute SHA256 hash: php -r "echo hash_file('sha256', 'uploads/placeholder1.pdf');"
-- 4. Update the evidence INSERT statements with actual hashes after creating placeholder files
