<?php
$pdo = new PDO('mysql:host=localhost;dbname=pos_system', 'randy', 'MUFASA');
$pdo->exec('ALTER TABLE utilisateurs DROP COLUMN IF EXISTS api_token');
echo 'Migration executed successfully';
