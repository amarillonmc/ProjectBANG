<?php
declare(strict_types=1);

/** Run: php -d extension=pdo_sqlite tests/api.php. Starts an isolated loopback test server. */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
if (!extension_loaded('pdo_sqlite') || !extension_loaded('curl')) {
    fwrite(STDERR, "These tests require pdo_sqlite and curl.\n");
    exit(1);
}

require_once dirname(__DIR__) . '/src/Store.php';
$assertions = 0;
function check(bool $condition, string $message): void
{
    global $assertions;
    if (!$condition) {
        throw new RuntimeException($message);
    }
    $assertions++;
}

function request(string $action, array $payload = [], string $token = '', int $expected = 200, string $method = 'POST', ?string $raw = null): array
{
    global $base;
    $url = $base . '/api.php?action=' . rawurlencode($action);
    if ($method === 'GET' && $payload) {
        $url .= '&' . http_build_query($payload);
    }
    $curl = curl_init($url);
    $headers = ['Content-Type: application/json'];
    if ($token !== '') {
        $headers[] = 'Authorization: Bearer ' . $token;
    }
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_HTTPHEADER => $headers, CURLOPT_CUSTOMREQUEST => $method]);
    if ($method === 'POST') {
        curl_setopt($curl, CURLOPT_POSTFIELDS, $raw ?? json_encode((object) $payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }
    $body = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl);
    curl_close($curl);
    check($body !== false, 'HTTP transport failed: ' . $error);
    $response = json_decode((string) $body, true);
    check(is_array($response), 'Invalid JSON from ' . $action . ': ' . $body);
    check($status === $expected, "$action expected HTTP $expected, received $status: $body");
    check(($response['ok'] ?? null) === ($expected < 400), 'Envelope status mismatch: ' . $action);
    return $response['data'] ?? $response;
}

$directory = sys_get_temp_dir() . '/imaginary-api-test-' . bin2hex(random_bytes(6));
mkdir($directory, 0700, true);
$database = $directory . '/test.sqlite';
$log = $directory . '/server.log';
$process = null;
$secondProcess = null;
$exit = 0;
try {
    // Store-level transaction rollback and rate-limit atomicity.
    $store = new Imaginary\Store(['driver' => 'sqlite', 'sqlite_path' => $database]);
    check($store->rateLimit('unit', 1, 60), 'First rate allowance');
    check(!$store->rateLimit('unit', 1, 60), 'Second rate rejection');
    try {
        $store->transaction(function () use ($store) {
            $store->execute('INSERT INTO ' . $store->table('feedback') . ' (id, user_id, body, created_at) VALUES (?, ?, ?, ?)', ['rollback', 'unit', 'must disappear', time()]);
            throw new RuntimeException('rollback sentinel');
        });
    } catch (RuntimeException $error) {
        check($error->getMessage() === 'rollback sentinel', 'Rollback preserves exception');
    }
    check(!$store->one('SELECT id FROM ' . $store->table('feedback') . ' WHERE id = ?', ['rollback']), 'Failed transaction made no changes');

    $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
    if (!$socket) {
        throw new RuntimeException('Cannot reserve a test port: ' . $error);
    }
    $address = stream_socket_get_name($socket, false);
    fclose($socket);
    $base = 'http://' . $address;
    $environment = getenv();
    $environment['IMAGINARY_SQLITE_PATH'] = $database;
    $environment['IMAGINARY_DB_DRIVER'] = 'sqlite';
    $command = [PHP_BINARY, '-d', 'extension=pdo_sqlite', '-d', 'display_errors=0', '-S', $address, '-t', dirname(__DIR__) . '/public'];
    $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']], $pipes, dirname(__DIR__), $environment, ['bypass_shell' => true]);
    if (!is_resource($process)) {
        throw new RuntimeException('Cannot start PHP test server.');
    }
    fclose($pipes[0]);
    $ready = false;
    for ($attempt = 0; $attempt < 50; $attempt++) {
        $connection = @stream_socket_client('tcp://' . $address, $errno, $error, 0.1);
        if ($connection) {
            fclose($connection);
            $ready = true;
            break;
        }
        usleep(100000);
    }
    check($ready, 'PHP test server became ready');

    $catalog = request('catalog', [], '', 200, 'GET');
    check(count($catalog['presets']) >= 4, 'Catalog has four example builds');
    $presets = array_values($catalog['presets']);
    $first = $presets[0];
    $opponent = $presets[1];
    foreach ($presets as $candidate) {
        if ($candidate['character']['color'] !== $first['character']['color'] && $candidate['character']['series'] !== $first['character']['series']) {
            $opponent = $candidate;
            break;
        }
    }
    request('me', [], '', 401);
    request('guest', ['name' => ''], '', 400);
    request('guest', [], '', 405, 'GET');
    request('guest', [], '', 400, 'POST', '[]');
    request('guest', [], '', 413, 'POST', str_repeat('x', 131073));
    $alice = request('guest', ['name' => '内测甲']);
    $bob = request('guest', ['name' => '内测乙']);
    $eve = request('guest', ['name' => '<script>旁观</script>']);
    check(strlen($alice['token']) === 64, 'Bearer secret uses 256 bits');
    $stored = $store->one('SELECT token_hash FROM ' . $store->table('users') . ' WHERE id = ?', [$alice['user']['id']]);
    check($stored['token_hash'] === hash('sha256', $alice['token']), 'Server stores only a token hash');

    $clone = $first;
    unset($clone['id']);
    $clone['name'] = 'API 自创测试';
    $validated = request('validate_build', ['build' => $clone], $alice['token']);
    check(isset($validated['budget']), 'Validation returns budget');
    $saved = request('save_build', ['build' => $clone], $alice['token']);
    $buildId = $saved['build']['id'];
    check((bool) preg_match('/^[a-f0-9]{32}$/D', $buildId), 'Saved build ID generated server-side');
    request('save_build', ['build' => $saved['build']], $bob['token'], 403);
    request('delete_build', ['id' => $buildId], $bob['token'], 404);
    request('create_room', ['name' => '示例限定', 'mode' => 'color', 'buildId' => $buildId, 'allowCustom' => false], $alice['token'], 400);

    $create = ['name' => 'API 对战测试', 'mode' => 'color', 'buildId' => $buildId, 'allowCustom' => true, 'requestId' => 'create_test_0001'];
    $room = request('create_room', $create, $alice['token']);
    $code = $room['code'];
    $repeat = request('create_room', $create, $alice['token']);
    check($repeat === $room, 'Duplicate create returns same room');
    $history = request('me', [], $alice['token']);
    check(count($history['rooms']) === 1 && $history['rooms'][0]['code'] === $code, 'Own room history supports reconnect');
    check(!isset($room['players'][0]['build']) && !isset($room['players'][0]['deck']), 'Lobby hides ordered mind decks');
    request('room', ['code' => $code], $eve['token'], 403);
    request('export', ['code' => $code], $eve['token'], 403);
    $room = request('join_room', ['code' => strtolower($code), 'presetId' => $opponent['id']], $bob['token']);
    $revision = $room['revision'];
    $repeat = request('join_room', ['code' => $code], $bob['token']);
    check($repeat['revision'] === $revision && count($repeat['players']) === 2, 'Join is idempotent');
    request('add_bot', ['code' => $code], $bob['token'], 403);
    request('start', ['code' => $code], $bob['token'], 403);
    $unchanged = request('room', ['code' => $code], $alice['token']);
    check($unchanged['revision'] === $revision, 'Unauthorized lobby mutation did not change revision');
    $room = request('start', ['code' => $code], $alice['token']);
    check($room['status'] === 'playing' && $room['game']['status'] === 'playing', 'Two-human game starts');
    request('choose_build', ['code' => $code, 'buildId' => $buildId], $alice['token'], 409);
    request('join_room', ['code' => $code], $eve['token'], 409);
    $reconnect = request('join_room', ['code' => $code], $bob['token']);
    check($reconnect['game']['me']['id'] === $bob['user']['id'], 'Reconnect restores perspective');
    foreach ($room['game']['players'] as $player) {
        check(!isset($player['hand']) && !isset($player['mind']) && !isset($player['build']), 'Public game player has no hidden card arrays');
    }
    $current = $room['game']['turn'] === $alice['user']['id'] ? $alice : $bob;
    $currentView = request('room', ['code' => $code], $current['token']);
    $before = $currentView['revision'];
    $action = ['code' => $code, 'revision' => $before, 'requestId' => 'draw_test_000001', 'action' => ['type' => 'draw', 'mind' => 0]];
    $draw = request('act', $action, $current['token']);
    check($draw['revision'] === $before + 1, 'Action increments revision once');
    $duplicate = request('act', $action, $current['token']);
    check($duplicate === $draw, 'Idempotent action returns exact stored response');
    $action['requestId'] = 'stale_test_00001';
    request('act', $action, $current['token'], 409);
    $action['requestId'] = 'draw_test_000001';
    $action['action']['mind'] = 1;
    request('act', $action, $current['token'], 409);
    $latest = request('room', ['code' => $code], $current['token']);
    check($latest['revision'] === $draw['revision'], 'Stale and reused-ID failures did not change state');

    // Two independent PHP workers contend for the same database, as on a real shared host.
    $secondSocket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
    check((bool) $secondSocket, 'Reserve second worker port');
    $secondAddress = stream_socket_get_name($secondSocket, false);
    fclose($secondSocket);
    $secondCommand = [PHP_BINARY, '-d', 'extension=pdo_sqlite', '-d', 'display_errors=0', '-S', $secondAddress, '-t', dirname(__DIR__) . '/public'];
    $secondProcess = proc_open($secondCommand, [0 => ['pipe', 'r'], 1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']], $secondPipes, dirname(__DIR__), $environment, ['bypass_shell' => true]);
    check(is_resource($secondProcess), 'Second PHP worker started');
    fclose($secondPipes[0]);
    $secondReady = false;
    for ($attempt = 0; $attempt < 50; $attempt++) {
        $connection = @stream_socket_client('tcp://' . $secondAddress, $errno, $error, 0.1);
        if ($connection) {
            fclose($connection);
            $secondReady = true;
            break;
        }
        usleep(100000);
    }
    check($secondReady, 'Second PHP worker became ready');
    $multi = curl_multi_init();
    $handles = [];
    foreach ([$base, 'http://' . $secondAddress] as $index => $workerBase) {
        $handle = curl_init($workerBase . '/api.php?action=act');
        $payload = ['code' => $code, 'revision' => $latest['revision'], 'requestId' => 'concurrent_' . $index . '_0001', 'action' => ['type' => 'end']];
        curl_setopt_array($handle, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $current['token']], CURLOPT_POSTFIELDS => json_encode($payload)]);
        curl_multi_add_handle($multi, $handle);
        $handles[] = $handle;
    }
    do {
        $multiStatus = curl_multi_exec($multi, $running);
        if ($running) {
            curl_multi_select($multi, 0.2);
        }
    } while ($running && $multiStatus === CURLM_OK);
    $statuses = [];
    foreach ($handles as $handle) {
        $statuses[] = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        $concurrent = json_decode(curl_multi_getcontent($handle), true);
        check(is_array($concurrent), 'Concurrent response is valid JSON');
        curl_multi_remove_handle($multi, $handle);
        curl_close($handle);
    }
    curl_multi_close($multi);
    sort($statuses);
    check($statuses === [200, 409], 'Exactly one concurrent command commits; stale command rejected');
    $afterConcurrent = request('room', ['code' => $code], $current['token']);
    check($afterConcurrent['revision'] === $latest['revision'] + 1, 'Concurrent commands increment revision exactly once');
    $beforeState = $store->one('SELECT data FROM ' . $store->table('rooms') . ' WHERE code = ?', [$code]);
    $frozen = json_decode($beforeState['data'], true);
    $newBuild = $saved['build'];
    $newBuild['name'] = '对局开始后修改';
    request('save_build', ['build' => $newBuild], $alice['token']);
    $afterState = $store->one('SELECT data FROM ' . $store->table('rooms') . ' WHERE code = ?', [$code]);
    check($beforeState === $afterState, 'Editing saved build cannot modify a started game');
    $export = request('export', ['code' => $code], $bob['token']);
    check($export['replay'] === null, 'Unfinished export does not expose full game');
    foreach ($export['events'] as $event) {
        check($event['data'] === [], 'Unfinished event payload is redacted');
    }
    request('feedback', ['code' => $code, 'text' => 'API 集成测试反馈'], $alice['token']);
    request('feedback', ['code' => $code, 'text' => '禁止越权'], $eve['token'], 403);
    check((int) $store->one('SELECT COUNT(*) AS total FROM ' . $store->table('feedback'))['total'] === 1, 'Only authorized feedback persisted');

    $practice = request('create_room', ['name' => '机器人练习', 'mode' => 'series', 'presetId' => $first['id']], $alice['token']);
    $practice = request('add_bot', ['code' => $practice['code']], $alice['token']);
    check($practice['players'][1]['bot'] === true, 'Host adds compatible bot');
    $practice = request('start', ['code' => $practice['code']], $alice['token']);
    check($practice['status'] === 'playing', 'Series practice starts');

    $lobby = request('create_room', ['name' => '关闭测试', 'presetId' => $first['id']], $alice['token']);
    request('join_room', ['code' => $lobby['code'], 'presetId' => $opponent['id']], $bob['token']);
    request('leave_room', ['code' => $lobby['code']], $bob['token']);
    request('room', ['code' => $lobby['code']], $bob['token'], 403);
    request('leave_room', ['code' => $lobby['code']], $alice['token']);
    request('room', ['code' => $lobby['code']], $alice['token'], 404);
    request('delete_build', ['id' => $buildId], $alice['token']);
    echo "PASS: $assertions API/store assertions (SQLite, real HTTP, isolated database).\n";
    echo "MySQL SQL path is implemented but requires a separate MySQL deployment smoke test.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL: ' . $error->getMessage() . "\n");
    if (is_file($log)) {
        fwrite(STDERR, substr((string) file_get_contents($log), -6000) . "\n");
    }
    $exit = 1;
} finally {
    if (is_resource($secondProcess)) {
        proc_terminate($secondProcess);
        proc_close($secondProcess);
    }
    if (is_resource($process)) {
        proc_terminate($process);
        proc_close($process);
    }
    unset($store);
    foreach ([$database . '-wal', $database . '-shm', $database, $log] as $file) {
        if (is_file($file)) {
            @unlink($file);
        }
    }
    @rmdir($directory);
}
exit($exit);
