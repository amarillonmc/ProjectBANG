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
echo 'GD portrait uploads: '.(function_exists('imagecreatefromstring') ? 'OK' : 'UNAVAILABLE (enable gd for character image uploads)')."\n";
foreach (['src/Auth.php','src/Workshop.php','public/portrait.php','public/assets/portfolio.js','public/assets/portfolio.css'] as $file) {
    $ok = is_file($root.'/'.$file) && filesize($root.'/'.$file) > 0;
    echo $file.': '.($ok ? 'OK' : 'MISSING/EMPTY')."\n";
    $failed = $failed || !$ok;
}
foreach (['src/CreationTest.php','src/Tutorial.php','public/assets/learn.js','public/assets/table-ui.js','public/assets/onboarding.css'] as $file) {
    $ok = is_file($root . '/' . $file) && filesize($root . '/' . $file) > 0;
    echo $file . ': ' . ($ok ? 'OK' : 'MISSING/EMPTY') . "\n";
    $failed = $failed || !$ok;
}
foreach (['src/Engine.php','src/Rules.php','src/SkillBlocks.php','src/Catalog.php','src/ContentPack.php','src/content/kf3-classics.json','src/content/magireco-expansions.json','src/content/adventure-king.json','src/content/vtuber-summons.json','src/Store.php','public/api.php','public/index.html','public/assets/app.js','public/assets/style.css','public/assets/mind-atlas.png','public/assets/mindscape.png'] as $file) {
    $ok = is_file($root . '/' . $file) && filesize($root . '/' . $file) > 0;
    echo $file . ': ' . ($ok ? 'OK' : 'MISSING/EMPTY') . "\n";
    $failed = $failed || !$ok;
}
try {
    require_once $root.'/src/ContentPack.php';
    $pack = \Imaginary\ContentPack::all();
    $artCount=0; $expected=count($pack['arts']);
    foreach ($pack['arts'] as $art) {
        $url=$art['url']??'';
        if (!preg_match('~\Aassets/characters/[a-z][a-z0-9_]*\.png\z~D',$url)||!is_file($root.'/public/'.$url)) {
            echo 'Missing portrait: '.($art['id']??'unknown')."\n"; $failed=true;
        } else { $artCount++; }
    }
    echo 'Bundled content packs: '.count($pack['contentPacks']).'; characters: '.count($pack['presets'])."\n";
    echo 'Bundled character portraits: '.$artCount.' / '.$expected."\n";
    if ($artCount!==$expected) $failed=true;
} catch (\Throwable $e) {
    echo 'Content packs: ERROR '.$e->getMessage()."\n"; $failed=true;
}
echo 'var writable: ' . (is_writable($root . '/var') ? 'YES' : 'NO (required for default SQLite)') . "\n";
echo 'Config: ' . (is_file($root . '/config.php') ? 'local config.php present (values hidden)' : 'default SQLite / environment') . "\n";
echo "No credentials or database contents were printed. HTTP/FastCGI settings must be checked on the host.\n";
exit($failed ? 1 : 0);
