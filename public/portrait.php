<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/bootstrap.php';
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; sandbox");
header('Referrer-Policy: no-referrer');
try {
    $id = $_GET['id'] ?? '';
    if (!is_string($id) || !preg_match('/^[a-f0-9]{32}$/D', $id)) { http_response_code(404); exit; }
    $config = \Imaginary\configuration(); $store = new \Imaginary\Store($config['database']);
    $row = $store->one('SELECT mime, data FROM '.$store->table('portraits').' WHERE id = ?', [$id]);
    if (!$row) { http_response_code(404); exit; }
    header('Content-Type: '.$row['mime']); header('Cache-Control: private, max-age=86400');
    echo base64_decode($row['data'], true);
} catch (\Throwable $e) { error_log('[Portrait] '.$e->getMessage()); http_response_code(500); }
