<?php
/** Administrator CLI only. Export feedback to a private file, never to a public endpoint. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/src/bootstrap.php';
$config = Imaginary\configuration();
$store = new Imaginary\Store($config['database']);
$rows = $store->all('SELECT id, user_id, room_code, body, created_at FROM ' . $store->table('feedback') . ' ORDER BY created_at');
$destination = $argv[1] ?? dirname(__DIR__) . '/var/feedback-' . gmdate('Ymd-His') . '.json';
$encoded = json_encode(['exportedAt'=>gmdate('c'),'feedback'=>$rows], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
if (file_put_contents($destination, $encoded, LOCK_EX) === false) { fwrite(STDERR, "Could not write private export.\n"); exit(1); }
echo count($rows) . " feedback entries exported to " . $destination . "\n";
echo "Keep this file outside your web document root. It may contain player-provided personal data.\n";
