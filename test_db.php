<?php
require_once __DIR__ . '/includes/db.php';

$pdo = getDbConnection();
echo 'Connected to DB: ' . $pdo->query('SELECT DATABASE()')->fetchColumn();
