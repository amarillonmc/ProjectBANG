<?php
/** Read-only environment check. Run from CLI; never expose a phpinfo endpoint. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
echo "Imaginary / 时空并错 environment check\n";
echo 'PHP: ' . PHP_VERSION . "\n";
$failed = false;
foreach (['json', 'PDO'] as $extension) {
    $ok = extension_loaded($extension);
    echo $extension . ': ' . ($ok ? 'OK' : 'MISSING') . "\n";
    $failed = $failed || !$ok;
}
$drivers = class_exists('PDO') ? PDO::getAvailableDrivers() : [];
echo 'PDO drivers: ' . implode(', ', $drivers) . "\n";
if (!in_array('sqlite', $drivers, true) && !in_array('mysql', $drivers, true)) {
    echo "Enable pdo_sqlite or pdo_mysql in the PHP used by your website.\n";
    $failed = true;
}
$root = dirname(__DIR__);
foreach (['src/Engine.php','src/Rules.php','src/Catalog.php','src/Store.php','public/api.php','public/index.html','public/assets/app.js','public/assets/style.css','public/assets/mind-atlas.png','public/assets/mindscape.png'] as $file) {
    $ok = is_file($root . '/' . $file) && filesize($root . '/' . $file) > 0;
    echo $file . ': ' . ($ok ? 'OK' : 'MISSING/EMPTY') . "\n";
    $failed = $failed || !$ok;
}
echo 'var writable: ' . (is_writable($root . '/var') ? 'YES' : 'NO (required for default SQLite)') . "\n";
echo 'Config: ' . (is_file($root . '/config.php') ? 'local config.php present (values hidden)' : 'default SQLite / environment') . "\n";
echo "No credentials or database contents were printed. HTTP/FastCGI settings must be checked on the host.\n";
exit($failed ? 1 : 0);

