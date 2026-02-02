
USE justice_hammer;

INSERT INTO users (id, email, name, role, password_hash, verified) VALUES
(1, 'admin@example.test', 'Admin', 'ADMIN', '$2y$12$TVBEvNrzPB5K9d0zCWNCjOPOnyi4nd8Z7EG1XIIpnFg0R/SOQ2N3a', 1);



INSERT INTO users (email, name, role, district, password_hash, verified) VALUES
('lawyer.a@example.test', 'Lawyer A', 'LAWYER', 'Dhaka', '$2y$12$Ilz.ZB6J1jK1MEuX0D7OluzLbR6HvaXN4wbyF5GBhr3UmTDo94ASS', 1),
('lawyer.b@example.test', 'Lawyer B', 'LAWYER', 'Chittagong', '$2y$12$Ilz.ZB6J1jK1MEuX0D7OluzLbR6HvaXN4wbyF5GBhr3UmTDo94ASS', 0);


INSERT INTO users (email, name, role, district, password_hash, verified) VALUES
('litigant.a@example.test', 'Litigant A', 'LITIGANT', 'Dhaka', '$2y$12$redRYiM/7OOYS/GC4iOdgOXplYh5YwWZIWteG0KwAp.CrhSMrd7ei', 1),
('litigant.b@example.test', 'Litigant B', 'LITIGANT', 'Chittagong', '$2y$12$redRYiM/7OOYS/GC4iOdgOXplYh5YwWZIWteG0KwAp.CrhSMrd7ei', 1),
('litigant.c@example.test', 'Litigant C', 'LITIGANT', 'Dhaka', '$2y$12$redRYiM/7OOYS/GC4iOdgOXplYh5YwWZIWteG0KwAp.CrhSMrd7ei', 1);


INSERT INTO cases (tracking_code, title, description, type, district, incident_date, status, created_by, assigned_to) VALUES
('JH2024001', 'Sample Case 1: Property Dispute', 'A dispute over land ownership in Dhaka district', 'Land Dispute', 'Dhaka', '2024-01-15', 'RECEIVED', 6, NULL),
('JH2024002', 'Sample Case 2: Theft Report', 'Reported theft incident in Chittagong', 'Crime', 'Chittagong', '2024-02-20', 'ASSIGNED', 7, 2);

-- Sample Timeline entries
INSERT INTO timeline (case_id, actor_id, event, meta) VALUES
(1, 6, 'Received', JSON_OBJECT('contact_pref', 'EMAIL', 'sensitive', 0, 'open_consent', 1)),
(2, 7, 'Received', JSON_OBJECT('contact_pref', 'PHONE', 'sensitive', 0, 'open_consent', 1)),
(2, 1, 'Assigned to Official', JSON_OBJECT('officialId', 2));

-- Sample Evidence (placeholder files)
--  SHA256 for placeholder: php -r "echo hash_file('sha256', 'uploads/placeholder1.pdf');"
INSERT INTO evidence (case_id, filename, stored_path, sha256, uploaded_by) VALUES
(1, 'document1.pdf', 'uploads/placeholder1.pdf', 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855', 6),
(2, 'evidence1.jpg', 'uploads/placeholder2.jpg', 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855', 7);

