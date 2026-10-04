<?php
declare(strict_types=1);

namespace Imaginary;

ini_set('display_errors', '0');
require_once dirname(__DIR__) . '/src/bootstrap.php';
require_once dirname(__DIR__) . '/src/Tutorial.php';

final class Api
{
    private $store;
    private $config;
    private $user;
    private $auth;
    private $workshop;

    public function __construct(Store $store, array $config)
    {
        $this->store = $store;
        $this->config = $config;
        $this->auth = new Auth($store);
        $this->workshop = new Workshop($store, $config);
    }

    public function dispatch(string $action, array $input, string $token, string $ip): array
    {
        if ($action === 'catalog') {
            return Catalog::all();
        }
        if (in_array($action, ['login', 'recover_account'], true)) {
            $this->throttle('login-ip:'.$ip, 30, 600);
            $handle = is_string($input['handle'] ?? null) ? strtolower($input['handle']) : '';
            $this->throttle('login-account:'.$handle, 15, 600);
            return $this->auth->login($input, $action === 'recover_account');
        }
        if (in_array($action, ['shared_work', 'shared_collection', 'gallery'], true)) {
            $this->throttle('browse:'.$ip, 240, 60);
            $viewer = $this->auth->user($token);
            if ($action === 'gallery') return $this->workshop->gallery($input);
            if ($action === 'shared_collection') return $this->workshop->sharedCollection($input['id'] ?? null, $viewer['id'] ?? null);
            return ['version'=>$this->workshop->describe($this->workshop->version($input['id'] ?? null, $viewer['id'] ?? null), true)];
        }
        if ($action === 'guest') {
            $this->throttle('guest:' . $ip, 20, 3600);
            $name = $this->label($input['name'] ?? '', 24, '玩家昵称');
            $token = bin2hex(random_bytes(32));
            $id = identifier();
            $this->store->execute('INSERT INTO ' . $this->store->table('users') . ' (id, name, token_hash, created_at) VALUES (?, ?, ?, ?)', [$id, $name, hash('sha256', $token), time()]);
            return ['token' => $token, 'user' => ['id' => $id, 'name' => $name]];
        }
        if (!preg_match('/^[a-f0-9]{64}$/D', $token)) {
            throw new ApiError('请先创建或恢复玩家身份。', 401);
        }
        $this->user = $this->auth->user($token);
        if (!$this->user) {
            throw new ApiError('玩家凭证无效，请登录账号或恢复访客身份备份。', 401);
        }
        $this->throttle('user:' . $this->user['id'], 240, 60);
        if ($action === 'register_account') {
            $this->throttle('register:'.$ip, 10, 3600);
            return $this->auth->register($this->user, $input);
        }
        if ($action === 'logout') return $this->auth->logout($token);
        if ($action === 'upload_portrait') $this->throttle('portrait:'.$this->user['id'], 20, 3600);
        if ($action === 'join_room') {
            $this->throttle('join:' . $ip, 30, 600);
        }
        if ($action === 'feedback') {
            $this->throttle('feedback:' . $this->user['id'], 10, 3600);
        }
        if ($action === 'me') {
            $rows = $this->store->all('SELECT data FROM ' . $this->store->table('builds') . ' WHERE user_id = ? ORDER BY updated_at DESC, id', [$this->user['id']]);
            $rooms = $this->store->all('SELECT r.data FROM ' . $this->store->table('rooms') . ' r INNER JOIN ' . $this->store->table('members') . ' m ON m.room_code = r.code WHERE m.user_id = ? ORDER BY r.updated_at DESC, r.code LIMIT 100', [$this->user['id']]);
            $history = array_map(function ($row) {
                $room = decode($row['data']);
                return ['code' => $room['code'], 'name' => $room['name'], 'status' => $room['status'], 'mode' => $room['mode'], 'revision' => $room['revision']];
            }, $rooms);
            return ['user' => $this->user, 'builds' => array_map(function ($row) { return decode($row['data']); }, $rows), 'rooms' => $history];
        }
        if ($action === 'validate_build') {
            $build = Rules::validateBuild($this->object($input['build'] ?? null, '构筑'), ($input['draft'] ?? false) !== true);
            return ['build' => $build, 'budget' => Rules::budget($build), 'tier'=>Workshop::tier(Rules::budget($build))];
        }
        if ($action === 'creation_test') {
            if (!is_array($input['answers'] ?? null)) throw new ApiError('请完成全部 16 道心象问答。');
            return CreationTest::compose($input['answers']);
        }
        if ($action === 'tutorial') {
            return $this->store->transaction(function () use ($input) {
                $id=$this->user['id']; $table=$this->store->table('tutorials');
                $this->store->one('SELECT id FROM '.$this->store->table('users').' WHERE id = ?'.$this->store->lockSuffix(),[$id]);
                $row=$this->store->one("SELECT data FROM $table WHERE user_id = ?",[$id]);
                $state=$row?decode($row['data']):Tutorial::start($id,$this->user['name']);
                $command=$input['command']??'resume';
                if (!in_array($command,['resume','restart','act','next','lesson'],true)) throw new ApiError('教学操作不存在。');
                if (in_array($command,['act','next','restart','lesson'],true)&&($input['revision']??null)!==$state['revision']) throw new ApiError('教学进度已更新，请重新进入这一课。',409);
                if ($command==='restart') $state=Tutorial::start($id,$this->user['name'],$state['step'],$state['revision']+1);
                if ($command==='lesson') {
                    $step=$input['step']??null;
                    if (!is_int($step)||$step<0||$step>=count(Tutorial::lessons())) throw new ApiError('请选择有效的课程。');
                    $state=Tutorial::start($id,$this->user['name'],$step,$state['revision']+1);
                }
                if ($command==='act') $state=Tutorial::act($state,$id,$this->object($input['action']??null,'教学行动'));
                if ($command==='next') {
                    if (!$state['done']) throw new ApiError('请先完成当前教学目标。');
                    $state=Tutorial::start($id,$this->user['name'],$state['step']+1,$state['revision']+1);
                }
                if ($row) $this->store->execute("UPDATE $table SET data = ? WHERE user_id = ?",[encode($state),$id]);
                else $this->store->execute("INSERT INTO $table (user_id,data) VALUES (?,?)",[$id,encode($state)]);
                return Tutorial::view($state,$id);
            });
        }
        $allowed = ['portfolio', 'work_history', 'set_visibility', 'save_collection', 'share_collection', 'delete_collection', 'revoke_collection_share', 'upload_portrait', 'save_build', 'delete_build', 'create_room', 'join_room', 'choose_build', 'add_bot', 'leave_room', 'start', 'room', 'act', 'feedback', 'export'];
        if (!in_array($action, $allowed, true)) {
            throw new ApiError('未知接口。', 404);
        }
        return $this->store->transaction(function () use ($action, $input) {
            // Lock identity first, then room. This also serializes idempotency/build quotas on MySQL.
            $this->store->one('SELECT id FROM ' . $this->store->table('users') . ' WHERE id = ?' . $this->store->lockSuffix(), [$this->user['id']]);
            $owner = $this->user['id'];
            if ($action === 'portfolio') return $this->workshop->portfolio($owner);
            if ($action === 'work_history') return $this->workshop->history($owner, $input['id'] ?? null);
            if ($action === 'set_visibility') return $this->workshop->visibility($owner, $input);
            if ($action === 'save_collection') return $this->workshop->saveCollection($owner, $input);
            if ($action === 'share_collection') return $this->workshop->shareCollection($owner, $input['id'] ?? null);
            if ($action === 'delete_collection' || $action === 'revoke_collection_share') return $this->workshop->removeCollection($owner, $input['id'] ?? null, $action === 'revoke_collection_share');
            if ($action === 'upload_portrait') return $this->workshop->upload($owner, $input);
            if ($action === 'save_build') {
                return $this->saveBuild($input);
            }
            if ($action === 'delete_build') {
                return $this->workshop->delete($owner, $input['id'] ?? null);
            }
            if ($action === 'create_room') {
                if (!isset($input['requestId'])) {
                    return $this->createRoom($input);
                }
                $requestId = $this->requestId($input['requestId']);
                $hash = hash('sha256', encode(['operation' => 'create_room', 'input' => $input]));
                $previous = $this->cachedRequest($requestId, $hash);
                if ($previous !== null) {
                    return $previous;
                }
                $response = $this->createRoom($input);
                $this->rememberRequest($requestId, $hash, $response);
                return $response;
            }
            if ($action === 'feedback') {
                return $this->feedback($input);
            }
            $code = $this->code($input['code'] ?? '');
            $room = $this->loadRoom($code);
            if ($action !== 'join_room') {
                $this->member($room);
            }
            if ($action === 'join_room') {
                return $this->joinRoom($room, $input);
            }
            if ($action === 'room') {
                if ($room['status'] === 'playing' && Engine::tick($room['game'])) {
                    $this->persistRoom($room, 'tick', []);
                }
                return $this->snapshot($room);
            }
            if ($action === 'export') {
                $events = $this->store->all('SELECT revision, user_id, kind, data, created_at FROM ' . $this->store->table('events') . ' WHERE room_code = ? ORDER BY revision, created_at', [$code]);
                foreach ($events as &$event) {
                    $event['data'] = $room['status'] === 'finished' ? decode($event['data']) : [];
                }
                unset($event);
                // A completed replay may reveal all cards; unfinished exports use only this player's view.
                return ['room' => $this->snapshot($room), 'events' => $events, 'replay' => $room['status'] === 'finished' ? $room['game'] : null, 'rulesVersion' => Catalog::all()['rulesVersion'] ?? 'alpha'];
            }
            if ($action === 'act') {
                return $this->act($room, $input);
            }
            if ($action === 'leave_room') {
                $this->lobby($room);
                if ($room['hostId'] === $this->user['id']) {
                    $this->store->execute('DELETE FROM ' . $this->store->table('members') . ' WHERE room_code = ?', [$code]);
                    $this->store->execute('DELETE FROM ' . $this->store->table('rooms') . ' WHERE code = ?', [$code]);
                    $this->store->execute('DELETE FROM ' . $this->store->table('events') . ' WHERE room_code = ?', [$code]);
                } else {
                    $this->store->execute('DELETE FROM ' . $this->store->table('members') . ' WHERE room_code = ? AND user_id = ?', [$code, $this->user['id']]);
                    $room['players'] = array_values(array_filter($room['players'], function ($player) { return $player['id'] !== $this->user['id']; }));
                    $this->persistRoom($room, 'leave_room', []);
                }
                return [];
            }
            $this->lobby($room);
            if ($action === 'choose_build') {
                $selected = $this->selectedBuild($input);
                $this->customAllowed($room, $selected);
                foreach ($room['players'] as &$player) {
                    if ($player['id'] === $this->user['id']) {
                        $player['build'] = $selected['build'];
                        $player['custom'] = $selected['custom'];
                        $player['versionId'] = $selected['versionId'] ?? null;
                    }
                }
                unset($player);
                $this->persistRoom($room, 'choose_build', []);
            } elseif ($action === 'add_bot') {
                $this->host($room);
                $this->addBot($room, $input);
                $this->persistRoom($room, 'add_bot', []);
            } elseif ($action === 'start') {
                $this->host($room);
                if (count($room['players']) < Catalog::modeRules($room['mode'])['minPlayers']) {
                    throw new ApiError('此模式至少需要 '.Catalog::modeRules($room['mode'])['minPlayers'].' 位玩家，可以加入练习机器人。');
                }
                $room['rulesSignature'] = Workshop::signature();
                $room['game'] = Engine::create($room['players'], $room['mode'], $room['budgetLimits'] ?? null);
                $room['game']['turnSeconds'] = $room['turnSeconds'];
                if (isset($room['game']['deadline'])) {
                    $room['game']['deadline'] = time() + ($room['game']['pending'] !== null ? min(45, $room['turnSeconds']) : $room['turnSeconds']);
                }
                $room['status'] = 'playing';
                $this->persistRoom($room, 'start', ['rulesVersion' => Catalog::all()['rulesVersion'] ?? 'alpha']);
            }
            return $this->snapshot($room);
        });
    }

    private function saveBuild(array $input): array
    {
        return $this->workshop->save($this->user['id'], $input);
    }

    private function createRoom(array $input): array
    {
        $name = $this->label($input['name'] ?? '心象内测室', 40, '房间名称');
        $mode = $input['mode'] ?? 'color';
        if (!in_array($mode, ['color', 'series', 'identity'], true)) {
            throw new ApiError('请选择冷暖对抗、系列对抗或人狼身份模式。');
        }
        $table = $this->store->table('rooms');
        $active = $this->store->one("SELECT COUNT(*) AS total FROM $table WHERE host_id = ? AND status <> 'finished'", [$this->user['id']]);
        if ((int) $active['total'] >= (int) $this->config['max_rooms_per_host']) {
            throw new ApiError('进行中的房间过多，请先关闭旧大厅或完成对局。');
        }
        $selected = $this->selectedBuild($input);
        $allowCustom = $input['allowCustom'] ?? true;
        if (!is_bool($allowCustom)) {
            throw new ApiError('allowCustom 必须是布尔值。');
        }
        $seconds = $input['turnSeconds'] ?? 120;
        if (!is_int($seconds) || $seconds < 30 || $seconds > 300) {
            throw new ApiError('回合时限必须为 30 至 300 秒的整数。');
        }
        do {
            $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
            $code = '';
            for ($i = 0; $i < 6; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while ($this->store->one("SELECT code FROM $table WHERE code = ?", [$code]));
        $room = [
            'code' => $code, 'name' => $name, 'mode' => $mode, 'hostId' => $this->user['id'],
            'budgetLimits' => Workshop::limits($input['budgetLimits'] ?? null),
            'status' => 'lobby', 'allowCustom' => $allowCustom, 'turnSeconds' => $seconds,
            'revision' => 1, 'createdAt' => time(), 'updatedAt' => time(),
            'players' => [['id' => $this->user['id'], 'name' => $this->user['name'], 'bot' => false, 'custom' => $selected['custom'], 'build' => $selected['build'], 'versionId' => $selected['versionId'] ?? null]],
            'game' => null,
        ];
        $this->customAllowed($room, $selected);
        if (array_key_exists('bots', $input)) {
            $bots = $input['bots'];
            if (!is_array($bots) || count($bots) < 1 || count($bots) >= Catalog::modeRules($mode)['maxPlayers'] || array_keys($bots) !== range(0, count($bots) - 1)) {
                throw new ApiError('请选择 1～'.(Catalog::modeRules($mode)['maxPlayers']-1).' 名机器人，并逐一指定构筑。');
            }
            foreach ($bots as $bot) {
                $this->addBot($room, $this->object($bot, '机器人的构筑选择'));
            }
        }
        $this->store->execute("INSERT INTO $table (code, host_id, status, revision, data, updated_at) VALUES (?, ?, ?, ?, ?, ?)", [$code, $room['hostId'], 'lobby', 1, encode($room), time()]);
        $this->store->execute('INSERT INTO ' . $this->store->table('members') . ' (room_code, user_id) VALUES (?, ?)', [$code, $this->user['id']]);
        $this->event($room, 'create_room', []);
        return $this->snapshot($room);
    }

    private function joinRoom(array &$room, array $input): array
    {
        foreach ($room['players'] as $player) {
            if ($player['id'] === $this->user['id']) {
                return $this->snapshot($room); // Reconnect even after the game started.
            }
        }
        $this->lobby($room);
        if (count($room['players']) >= Catalog::modeRules($room['mode'])['maxPlayers']) {
            throw new ApiError('房间已满（最多 '.Catalog::modeRules($room['mode'])['maxPlayers'].' 人）。');
        }
        $selected = $this->selectedBuild($input);
        $this->customAllowed($room, $selected);
        $room['players'][] = ['id' => $this->user['id'], 'name' => $this->user['name'], 'bot' => false, 'custom' => $selected['custom'], 'build' => $selected['build'], 'versionId' => $selected['versionId'] ?? null];
        $this->store->execute('INSERT INTO ' . $this->store->table('members') . ' (room_code, user_id) VALUES (?, ?)', [$room['code'], $this->user['id']]);
        $this->persistRoom($room, 'join_room', []);
        return $this->snapshot($room);
    }

    private function act(array &$room, array $input): array
    {
        $requestId = $this->requestId($input['requestId'] ?? '');
        $command = $this->object($input['action'] ?? null, '操作');
        if (!isset($input['revision']) || !is_int($input['revision'])) {
            throw new ApiError('操作必须携带整数 revision。');
        }
        $hash = hash('sha256', encode(['code' => $room['code'], 'revision' => $input['revision'], 'action' => $command]));
        $previous = $this->cachedRequest($requestId, $hash);
        if ($previous !== null) {
            return $previous;
        }
        if ($input['revision'] !== $room['revision']) {
            throw new ApiError('房间状态已更新，请刷新后重试。', 409);
        }
        if ($room['status'] !== 'playing') {
            throw new ApiError('当前没有进行中的对局。', 409);
        }
        Engine::act($room['game'], $this->user['id'], $command);
        $this->persistRoom($room, 'act', ['action' => $command]);
        $response = $this->snapshot($room);
        $this->rememberRequest($requestId, $hash, $response);
        return $response;
    }

    private function cachedRequest(string $requestId, string $hash): ?array
    {
        $previous = $this->store->one('SELECT payload_hash, response FROM ' . $this->store->table('requests') . ' WHERE user_id = ? AND request_id = ?', [$this->user['id'], $requestId]);
        if (!$previous) {
            return null;
        }
        if (!hash_equals($previous['payload_hash'], $hash)) {
            throw new ApiError('此 requestId 已用于另一项操作。', 409);
        }
        return decode($previous['response']);
    }

    private function rememberRequest(string $requestId, string $hash, array $response): void
    {
        $table = $this->store->table('requests');
        $this->store->execute("INSERT INTO $table (user_id, request_id, payload_hash, response, created_at) VALUES (?, ?, ?, ?, ?)", [$this->user['id'], $requestId, $hash, encode($response), time()]);
        // Replay protection has a 24-hour horizon; older game commands still fail revision checks.
        $this->store->execute("DELETE FROM $table WHERE created_at < ?", [time() - 86400]);
    }

    private function feedback(array $input): array
    {
        $body = $this->label($input['text'] ?? '', 4000, '反馈内容');
        $code = null;
        if (isset($input['code']) && $input['code'] !== '') {
            $code = $this->code($input['code']);
            $room = $this->loadRoom($code);
            $this->member($room);
        }
        $this->store->execute('INSERT INTO ' . $this->store->table('feedback') . ' (id, user_id, room_code, body, created_at) VALUES (?, ?, ?, ?, ?)', [identifier(), $this->user['id'], $code, $body, time()]);
        return [];
    }

    private function loadRoom(string $code): array
    {
        $row = $this->store->one('SELECT data FROM ' . $this->store->table('rooms') . ' WHERE code = ?' . $this->store->lockSuffix(), [$code]);
        if (!$row) {
            throw new ApiError('房间不存在或已关闭。', 404);
        }
        return decode($row['data']);
    }

    private function persistRoom(array &$room, string $kind, array $payload): void
    {
        if ($room['game'] && ($room['game']['status'] ?? '') === 'finished') {
            $room['status'] = 'finished';
            $this->workshop->recordTrials($room);
        }
        $previous = $room['revision'];
        $room['revision']++;
        $room['updatedAt'] = time();
        $count = $this->store->execute('UPDATE ' . $this->store->table('rooms') . ' SET data = ?, status = ?, revision = ?, updated_at = ? WHERE code = ? AND revision = ?', [encode($room), $room['status'], $room['revision'], time(), $room['code'], $previous]);
        if ($count !== 1) {
            throw new ApiError('房间状态发生冲突，请刷新后重试。', 409);
        }
        $this->event($room, $kind, $payload);
    }

    private function event(array $room, string $kind, array $payload): void
    {
        $this->store->execute('INSERT INTO ' . $this->store->table('events') . ' (id, room_code, revision, user_id, kind, data, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)', [identifier(), $room['code'], $room['revision'], $this->user['id'], $kind, encode($payload), time()]);
    }

    private function snapshot(array $room): array
    {
        return [
            'code' => $room['code'], 'name' => $room['name'], 'mode' => $room['mode'],
            'hostId' => $room['hostId'], 'status' => $room['status'], 'allowCustom' => $room['allowCustom'],
            'budgetLimits' => $room['budgetLimits'] ?? Workshop::limits(),
            'modeRules' => Catalog::modeRules($room['mode']),
            'turnSeconds' => $room['turnSeconds'], 'revision' => $room['revision'],
            'players' => array_map(function ($player) {
                return ['id' => $player['id'], 'name' => $player['name'], 'bot' => $player['bot'], 'custom' => $player['custom'] ?? false, 'character' => $player['build']['character']];
            }, $room['players']),
            'game' => $room['game'] ? Engine::view($room['game'], $this->user['id']) : null,
        ];
    }

    private function selectedBuild(array $input): array
    {
        if (!empty($input['versionId'])) {
            $v = $this->workshop->version($input['versionId'], $this->user['id']);
            return ['build'=>Rules::validateBuild(decode($v['data']), false), 'custom'=>true, 'versionId'=>$v['id']];
        }
        if (isset($input['buildId']) && $input['buildId'] !== '') {
            $id = $this->id($input['buildId']);
            $row = $this->store->one('SELECT * FROM ' . $this->store->table('builds') . ' WHERE id = ? AND user_id = ?', [$id, $this->user['id']]);
            if (!$row) {
                throw new ApiError('找不到你的构筑。', 404);
            }
            $v = $this->workshop->ensureVersion($row);
            return ['build' => Rules::validateBuild(decode($v['data']), false), 'custom' => true, 'versionId'=>$v['id']];
        }
        $presets = Catalog::all()['presets'];
        $first = reset($presets);
        $presetId = $input['presetId'] ?? ($first['id'] ?? '');
        if (!is_string($presetId)) {
            throw new ApiError('请选择示例构筑。');
        }
        foreach ($presets as $preset) {
            if (($preset['id'] ?? '') === $presetId) {
                return ['build' => Rules::validateBuild($preset), 'custom' => false];
            }
        }
        throw new ApiError('示例构筑不存在。', 404);
    }

    private function opponentPreset(array $room): string
    {
        $first = $room['players'][0]['build']['character'];
        $presets = Catalog::all()['presets'];
        foreach ($presets as $preset) {
            $other = $preset['character'];
            if ($room['mode'] === 'identity' || ($other['color'] !== $first['color'] && ($room['mode'] !== 'series' || $other['series'] !== $first['series']))) {
                return $preset['id'];
            }
        }
        return reset($presets)['id'];
    }

    private function addBot(array &$room, array $input): void
    {
        if (count($room['players']) >= Catalog::modeRules($room['mode'])['maxPlayers']) {
            throw new ApiError('房间已满（最多 '.Catalog::modeRules($room['mode'])['maxPlayers'].' 人）。');
        }
        if (empty($input['versionId']) && (!isset($input['buildId']) || $input['buildId'] === '') && (!isset($input['presetId']) || $input['presetId'] === '')) {
            $input = ['presetId' => $this->opponentPreset($room)];
        }
        $selected = $this->selectedBuild($input);
        $this->customAllowed($room, $selected);
        $room['players'][] = [
            'id' => 'bot_' . substr(identifier(), 0, 12),
            'name' => '练习机 ' . (count(array_filter($room['players'], function ($player) { return $player['bot']; })) + 1),
            'bot' => true, 'custom' => $selected['custom'], 'build' => $selected['build'], 'versionId' => $selected['versionId'] ?? null,
        ];
    }

    private function customAllowed(array $room, array $selected): void
    {
        Rules::validateBuild($selected['build'], true, $room['budgetLimits'] ?? null);
        if (!$room['allowCustom'] && $selected['custom']) {
            throw new ApiError('此房间只允许内置示例构筑。');
        }
    }

    private function member(array $room): void
    {
        foreach ($room['players'] as $player) {
            if ($player['id'] === $this->user['id']) {
                return;
            }
        }
        throw new ApiError('只有房间参与者可以读取或操作该房间。', 403);
    }

    private function host(array $room): void
    {
        if ($room['hostId'] !== $this->user['id']) {
            throw new ApiError('只有房主可以执行此操作。', 403);
        }
    }

    private function lobby(array $room): void
    {
        if ($room['status'] !== 'lobby') {
            throw new ApiError('该操作仅可在未开始的大厅进行。', 409);
        }
    }

    private function label($value, int $maximum, string $field): string
    {
        if (!is_string($value) || !preg_match('//u', $value)) {
            throw new ApiError($field . '必须是有效 UTF-8 文本。');
        }
        $value = trim($value);
        $length = preg_match_all('/./us', $value, $matches);
        if ($length < 1 || $length > $maximum || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $value)) {
            throw new ApiError($field . '长度应为 1 至 ' . $maximum . ' 字，且不能含控制字符。');
        }
        return $value;
    }

    private function object($value, string $field): array
    {
        if (!is_array($value) || ($value !== [] && array_keys($value) === range(0, count($value) - 1))) {
            throw new ApiError($field . '必须是 JSON 对象。');
        }
        return $value;
    }

    private function id($value): string
    {
        if (!is_string($value) || !preg_match('/^[a-f0-9]{32}$/D', $value)) {
            throw new ApiError('构筑 ID 无效。');
        }
        return $value;
    }

    private function requestId($value): string
    {
        if (!is_string($value) || !preg_match('/^[a-zA-Z0-9_-]{8,80}$/D', $value)) {
            throw new ApiError('操作需要 8 至 80 位 requestId（字母、数字、下划线或短横线）。');
        }
        return $value;
    }

    private function code($value): string
    {
        if (!is_string($value)) {
            throw new ApiError('请输入六位房间码。');
        }
        $value = strtoupper(trim($value));
        if (!preg_match('/^[A-HJ-NP-Z2-9]{6}$/D', $value)) {
            throw new ApiError('请输入六位房间码。');
        }
        return $value;
    }

    private function throttle(string $key, int $maximum, int $seconds): void
    {
        if (!$this->store->rateLimit($key, $maximum, $seconds)) {
            throw new ApiError('请求过于频繁，请稍后再试。', 429);
        }
    }
}

if (!defined('IMAGINARY_API_LIBRARY')) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, private');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('X-Frame-Options: DENY');
    $config = [];
    try {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $action = $_GET['action'] ?? '';
        if (!is_string($action)) {
            throw new ApiError('接口名称无效。');
        }
        $readActions = ['catalog', 'me', 'room', 'export', 'shared_work', 'shared_collection', 'gallery'];
        if ($method !== 'POST' && !($method === 'GET' && in_array($action, $readActions, true))) {
            header('Allow: GET, POST');
            throw new ApiError('此操作需要 POST 请求。', 405);
        }
        $input = [];
        if ($method === 'POST') {
            $bodyLimit = $action === 'upload_portrait' ? 2900000 : 131072;
            if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > $bodyLimit) {
                throw new ApiError('请求体过大（普通请求最大 128 KiB，上传图片最大 2 MiB）。', 413);
            }
            $contentType = strtolower(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0]);
            if ($contentType !== 'application/json') {
                throw new ApiError('请使用 application/json 请求。', 415);
            }
            $stream = fopen('php://input', 'rb');
            $raw = stream_get_contents($stream, $bodyLimit + 1);
            fclose($stream);
            if (strlen($raw) > $bodyLimit) {
                throw new ApiError('请求体过大（普通请求最大 128 KiB，上传图片最大 2 MiB）。', 413);
            }
            $raw = $raw === '' ? '{}' : $raw;
            $input = decode($raw);
            if (substr(ltrim($raw), 0, 1) !== '{') {
                throw new ApiError('请求体必须为 JSON 对象。');
            }
        } else {
            $input = $_GET;
            unset($input['action']);
        }
        $authorization = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if ($authorization === '' && function_exists('getallheaders')) {
            foreach (getallheaders() as $key => $value) {
                if (strcasecmp($key, 'Authorization') === 0) {
                    $authorization = $value;
                }
            }
        }
        $token = preg_match('/^Bearer ([a-f0-9]{64})$/D', $authorization, $matches) ? $matches[1] : '';
        $config = configuration();
        $api = new Api(new Store($config['database']), $config);
        $result = $api->dispatch($action, $input, $token, $_SERVER['REMOTE_ADDR'] ?? 'unknown');
        echo encode(['ok' => true, 'data' => $result === [] ? (object) [] : $result]);
    } catch (ApiError $error) {
        http_response_code($error->httpStatus);
        if ($error->httpStatus === 429) {
            header('Retry-After: 60');
        }
        echo encode(['ok' => false, 'error' => $error->getMessage()]);
    } catch (\InvalidArgumentException | \JsonException $error) {
        http_response_code(400);
        echo encode(['ok' => false, 'error' => $error->getMessage()]);
    } catch (\Throwable $error) {
        error_log('[Imaginary API] ' . get_class($error) . ': ' . $error->getMessage());
        http_response_code(500);
        echo encode(['ok' => false, 'error' => !empty($config['debug']) ? $error->getMessage() : '服务器暂时无法完成请求，请检查 PHP 错误日志及数据库配置。']);
    }
}
