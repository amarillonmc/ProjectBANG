<?php
declare(strict_types=1);
namespace Imaginary;

/** Called inside the API's identity/room transaction. Versions are immutable snapshots. */
final class Workshop
{
    private $s;
    private $config;
    public function __construct(Store $store, array $config) { $this->s = $store; $this->config = $config; }
    public static function id($value): string
    {
        if (!is_string($value) || !preg_match('/^[a-f0-9]{32}$/D', $value)) throw new ApiError('作品编号无效。');
        return $value;
    }
    private static function label($value, int $max, bool $empty = false): string
    {
        if (!is_string($value) || !preg_match('//u', $value) || preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/u', $value)) throw new ApiError('请填写有效文字。');
        $value = trim($value);
        if ((!$empty && $value === '') || preg_match_all('/./us', $value) > $max) throw new ApiError('文字长度应为 '.($empty ? '0' : '1').'～'.$max.' 字。');
        return $value;
    }
    public static function limits($input = null): array
    {
        $base = array_intersect_key(RuleConfig::all(), array_flip(['characterBudget','customBudget','customCardBudget']));
        if ($input === null) return $base;
        if (!is_array($input) || array_diff(array_keys($input), array_keys($base))) throw new ApiError('房间只能调整人物、限定牌总额和单牌这三项预算。');
        foreach ($input as $value) if (!is_int($value) || $value < 1 || $value > 100000) throw new ApiError('房间预算需为 1～100000 的整数。');
        return array_replace($base, $input);
    }
    public static function tier(array $budget): string
    {
        return $budget['character'] > $budget['characterMax'] || $budget['custom'] > $budget['customMax'] || max(array_merge([0], $budget['cards'])) > $budget['cardMax'] ? 'extended' : 'standard';
    }
    public static function signature(): string { return hash('sha256', encode(['version'=>SkillBlocks::VERSION, 'rules'=>RuleConfig::all()])); }
    public static function fingerprint(array $build): string
    {
        // IDs, series, card order, and skill/custom-card names can participate in rules.
        unset($build['id'], $build['name'], $build['character']['name'], $build['character']['title'], $build['character']['art']);
        return hash('sha256', encode($build));
    }
    private function latest(string $id): ?array
    {
        return $this->s->one('SELECT * FROM '.$this->s->table('versions').' WHERE build_id = ? ORDER BY number DESC LIMIT 1', [$id]);
    }
    public function ensureVersion(array $row): array
    {
        $latest = $this->latest($row['id']);
        if ($latest) return $latest;
        $build = Rules::validateBuild(decode($row['data']), false); $build['id'] = $row['id'];
        return $this->insertVersion($build, $row['user_id'], 1);
    }
    private function insertVersion(array $build, string $owner, int $number): array
    {
        $v = ['id'=>identifier(), 'build_id'=>$build['id'], 'user_id'=>$owner, 'number'=>$number, 'data'=>encode($build), 'fingerprint'=>self::fingerprint($build), 'visibility'=>'private', 'created_at'=>time()];
        $this->s->execute('INSERT INTO '.$this->s->table('versions').' (id, build_id, user_id, number, data, fingerprint, visibility, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)', array_values($v));
        return $v;
    }
    public function save(string $owner, array $input): array
    {
        if (!is_array($input['build'] ?? null)) throw new ApiError('请提供完整构筑。');
        $build = Rules::validateBuild($input['build'], false);
        $id = !empty($build['id']) ? self::id($build['id']) : identifier(); $build['id'] = $id;
        $s = $this->s; $table = $s->table('builds');
        $row = $s->one("SELECT * FROM $table WHERE id = ?", [$id]);
        if ($row && $row['user_id'] !== $owner) throw new ApiError('不能覆盖他人的构筑。', 403);
        if (is_string($build['character']['art']) && strpos($build['character']['art'], 'upload_') === 0) {
            $art = substr($build['character']['art'], 7);
            if (!preg_match('/^[a-f0-9]{32}$/D', $art) || !$s->one('SELECT id FROM '.$s->table('portraits').' WHERE id = ?', [$art])) throw new ApiError('角色图片不存在，请重新上传。');
        }
        $latest = $row ? $this->ensureVersion($row) : null;
        if (array_key_exists('expectedVersion', $input) && $input['expectedVersion'] !== ($latest ? $latest['id'] : null)) throw new ApiError('此作品已在另一窗口更新，请到我的作品打开最新版；可先导出当前编辑内容。', 409);
        if ($latest && $latest['data'] === encode($build)) return ['build'=>$build, 'budget'=>Rules::budget($build), 'version'=>$this->describe($latest)];
        $number = $latest ? (int)$latest['number'] + 1 : 1;
        if ($number > ($this->config['max_versions_per_build'] ?? 200)) throw new ApiError('此作品的版本数已达上限，请另存为新作品。');
        if (!$row) {
            $count = $s->one("SELECT COUNT(*) AS n FROM $table WHERE user_id = ?", [$owner]);
            if ((int)$count['n'] >= $this->config['max_builds']) throw new ApiError('构筑数量已达上限，请先导出并删除旧构筑。');
            $s->execute("INSERT INTO $table (id, user_id, data, updated_at) VALUES (?, ?, ?, ?)", [$id, $owner, encode($build), time()]);
        } else $s->execute("UPDATE $table SET data = ?, updated_at = ? WHERE id = ?", [encode($build), time(), $id]);
        return ['build'=>$build, 'budget'=>Rules::budget($build), 'version'=>$this->describe($this->insertVersion($build, $owner, $number))];
    }
    private function proof(array $version): ?array
    {
        $row = $this->s->one('SELECT data FROM '.$this->s->table('trials').' WHERE user_id = ? AND fingerprint = ? AND rules_signature = ? ORDER BY created_at DESC LIMIT 1', [$version['user_id'], $version['fingerprint'], self::signature()]);
        return $row ? decode($row['data']) : null;
    }
    public function describe(array $version, bool $full = false): array
    {
        $build = decode($version['data']); $budget = Rules::budget($build); $valid = true;
        try { Rules::validateBuild($build, false); } catch (\InvalidArgumentException $e) { $valid = false; }
        $author = $this->s->one('SELECT name FROM '.$this->s->table('users').' WHERE id = ?', [$version['user_id']]);
        $result = ['id'=>$version['id'], 'buildId'=>$version['build_id'], 'number'=>(int)$version['number'], 'name'=>$build['name'], 'character'=>$build['character'], 'author'=>$author['name'] ?? '旅人', 'visibility'=>$version['visibility'], 'createdAt'=>(int)$version['created_at'], 'rulesVersion'=>$build['rulesVersion'], 'valid'=>$valid, 'budget'=>$budget, 'tier'=>self::tier($budget), 'trial'=>$this->proof($version)];
        if ($full) $result['build'] = $build;
        return $result;
    }
    public function version($id, ?string $viewer): array
    {
        $row = $this->s->one('SELECT * FROM '.$this->s->table('versions').' WHERE id = ?', [self::id($id)]);
        if (!$row || ($row['user_id'] !== $viewer && $row['visibility'] === 'private')) throw new ApiError('作品不存在，或作者尚未开放这个版本。', 404);
        return $row;
    }
    public function portfolio(string $owner): array
    {
        $s = $this->s; $rows = $s->all('SELECT * FROM '.$s->table('builds').' WHERE user_id = ? ORDER BY updated_at DESC, id', [$owner]);
        $works = [];
        foreach ($rows as $row) $works[] = $this->describe($this->ensureVersion($row));
        $collections = $s->all('SELECT id, data FROM '.$s->table('collections').' WHERE user_id = ? ORDER BY updated_at DESC, id', [$owner]);
        $shares = $s->all('SELECT id, collection_id, data FROM '.$s->table('collection_shares').' WHERE user_id = ? ORDER BY created_at DESC', [$owner]);
        return ['works'=>$works, 'collections'=>array_map(function ($r) { return ['id'=>$r['id']] + decode($r['data']); }, $collections), 'shares'=>array_map(function ($r) { return ['id'=>$r['id'], 'collectionId'=>$r['collection_id']] + decode($r['data']); }, $shares)];
    }
    public function history(string $owner, $buildId): array
    {
        $row = $this->s->one('SELECT * FROM '.$this->s->table('builds').' WHERE id = ? AND user_id = ?', [self::id($buildId), $owner]);
        if (!$row) throw new ApiError('找不到你的作品。', 404);
        $this->ensureVersion($row);
        $rows = $this->s->all('SELECT * FROM '.$this->s->table('versions').' WHERE build_id = ? ORDER BY number DESC', [$row['id']]);
        return ['versions'=>array_map(function ($v) { return $this->describe($v); }, $rows)];
    }
    public function visibility(string $owner, array $input): array
    {
        $v = $this->version($input['id'] ?? null, $owner);
        if ($v['user_id'] !== $owner) throw new ApiError('只有作者可以更改作品的开放状态。', 403);
        $state = $input['visibility'] ?? '';
        if (!in_array($state, ['private','link','published'], true)) throw new ApiError('请选择仅自己、链接分享或投稿。');
        if ($state === 'published') {
            Rules::validateBuild(decode($v['data']), false);
            if (!$this->proof($v)) throw new ApiError('请先亲自使用此构筑完成一局合法对局（胜负不限，可与机器人练习）。');
            // One submitted version per work; older links remain pinned and usable.
            $this->s->execute('UPDATE '.$this->s->table('versions')." SET visibility = 'link' WHERE build_id = ? AND visibility = 'published'", [$v['build_id']]);
        }
        $this->s->execute('UPDATE '.$this->s->table('versions').' SET visibility = ? WHERE id = ?', [$state, $v['id']]);
        $v['visibility'] = $state;
        return ['version'=>$this->describe($v, true)];
    }
    public function gallery(array $input): array
    {
        $tier = $input['tier'] ?? 'standard';
        if (!in_array($tier, ['standard','extended'], true)) throw new ApiError('预算分类无效。');
        $where = "visibility = 'published'"; $params = [];
        if (!empty($input['cursor'])) {
            $cursor = $this->s->one('SELECT id, created_at FROM '.$this->s->table('versions').' WHERE id = ?', [self::id($input['cursor'])]);
            if (!$cursor) throw new ApiError('分页位置已失效，请刷新投稿库。');
            $where .= ' AND (created_at < ? OR (created_at = ? AND id < ?))'; $params = [$cursor['created_at'],$cursor['created_at'],$cursor['id']];
        }
        $rows = $this->s->all('SELECT * FROM '.$this->s->table('versions')." WHERE $where ORDER BY created_at DESC, id DESC LIMIT 50", $params);
        $versions = [];
        foreach ($rows as $row) { $v = $this->describe($row); if ($v['valid'] && $v['trial'] && $v['tier'] === $tier) $versions[] = $v; }
        return ['versions'=>$versions, 'cursor'=>count($rows) === 50 ? end($rows)['id'] : null, 'tier'=>$tier];
    }
    public function delete(string $owner, $id): array
    {
        $id = self::id($id); $s = $this->s;
        if (!$s->execute('DELETE FROM '.$s->table('builds').' WHERE id = ? AND user_id = ?', [$id, $owner])) throw new ApiError('找不到你的构筑。', 404);
        $s->execute('DELETE FROM '.$s->table('versions').' WHERE build_id = ? AND user_id = ?', [$id, $owner]);
        foreach ($s->all('SELECT id, data FROM '.$s->table('collections').' WHERE user_id = ?', [$owner]) as $c) {
            $data = decode($c['data']); $data['buildIds'] = array_values(array_diff($data['buildIds'], [$id]));
            $s->execute('UPDATE '.$s->table('collections').' SET data = ? WHERE id = ?', [encode($data), $c['id']]);
        }
        return [];
    }
    public function saveCollection(string $owner, array $input): array
    {
        $id = isset($input['id']) ? self::id($input['id']) : identifier(); $s = $this->s; $table = $s->table('collections');
        $old = $s->one("SELECT user_id FROM $table WHERE id = ?", [$id]);
        if ($old && $old['user_id'] !== $owner) throw new ApiError('不能修改他人的作品系列。', 403);
        $ids = $input['buildIds'] ?? null;
        if (!is_array($ids) || count($ids) > $this->config['max_builds'] || ($ids && array_keys($ids) !== range(0, count($ids)-1))) throw new ApiError('请选择要收入系列的作品。');
        foreach ($ids as $bid) if (!$s->one('SELECT id FROM '.$s->table('builds').' WHERE id = ? AND user_id = ?', [self::id($bid), $owner])) throw new ApiError('系列只能收录自己的作品。');
        $data = ['name'=>self::label($input['name'] ?? null, 60), 'description'=>self::label($input['description'] ?? '', 1000, true), 'buildIds'=>array_values(array_unique($ids))];
        if ($old) $s->execute("UPDATE $table SET data = ?, updated_at = ? WHERE id = ?", [encode($data), time(), $id]);
        else {
            $count = $s->one("SELECT COUNT(*) AS n FROM $table WHERE user_id = ?", [$owner]);
            if ((int)$count['n'] >= ($this->config['max_collections'] ?? 30)) throw new ApiError('作品系列数量已达上限。');
            $s->execute("INSERT INTO $table (id, user_id, data, updated_at) VALUES (?, ?, ?, ?)", [$id,$owner,encode($data),time()]);
        }
        return ['collection'=>['id'=>$id]+$data];
    }
    public function shareCollection(string $owner, $id): array
    {
        $s = $this->s; $id = self::id($id);
        $row = $s->one('SELECT data FROM '.$s->table('collections').' WHERE id = ? AND user_id = ?', [$id,$owner]);
        if (!$row) throw new ApiError('找不到你的系列。', 404);
        $data = decode($row['data']); $versions = [];
        foreach ($data['buildIds'] as $bid) {
            $build = $s->one('SELECT * FROM '.$s->table('builds').' WHERE id = ? AND user_id = ?', [$bid,$owner]);
            if ($build) { $v = $this->ensureVersion($build); $versions[] = $v['id']; $s->execute('UPDATE '.$s->table('versions')." SET visibility = 'link' WHERE id = ? AND visibility = 'private'", [$v['id']]); }
        }
        if (!$versions) throw new ApiError('请先向系列加入作品。');
        $snapshot = ['name'=>$data['name'], 'description'=>$data['description'], 'versionIds'=>$versions];
        $table = $s->table('collection_shares'); $existing = $s->all("SELECT id, data FROM $table WHERE collection_id = ? AND user_id = ?", [$id,$owner]);
        foreach ($existing as $old) if ($old['data'] === encode($snapshot)) return ['id'=>$old['id']];
        if (count($existing) >= 20) throw new ApiError('此系列已保留 20 个分享快照，请先撤销旧链接。');
        $share = identifier(); $s->execute("INSERT INTO $table (id, collection_id, user_id, data, created_at) VALUES (?, ?, ?, ?, ?)", [$share,$id,$owner,encode($snapshot),time()]);
        return ['id'=>$share];
    }
    public function sharedCollection($id, ?string $viewer): array
    {
        $row = $this->s->one('SELECT data FROM '.$this->s->table('collection_shares').' WHERE id = ?', [self::id($id)]);
        if (!$row) throw new ApiError('系列分享已撤销或不存在。', 404);
        $data = decode($row['data']); $versions = []; $unavailable = 0;
        foreach ($data['versionIds'] as $vid) {
            try { $versions[] = $this->describe($this->version($vid, $viewer)); } catch (ApiError $e) { $unavailable++; }
        }
        unset($data['versionIds']); return $data + ['versions'=>$versions, 'unavailable'=>$unavailable];
    }
    public function removeCollection(string $owner, $id, bool $shareOnly): array
    {
        $id = self::id($id); $table = $this->s->table($shareOnly ? 'collection_shares' : 'collections');
        if (!$this->s->execute("DELETE FROM $table WHERE id = ? AND user_id = ?", [$id,$owner])) throw new ApiError('系列或分享不存在。', 404);
        if (!$shareOnly) $this->s->execute('DELETE FROM '.$this->s->table('collection_shares').' WHERE collection_id = ? AND user_id = ?', [$id,$owner]);
        return [];
    }
    public function recordTrials(array $room): void
    {
        if(!empty($room['arena'])) return; // Watching AI cannot certify an author's play trial.
        if (($room['game']['status'] ?? '') !== 'finished' || ($room['rulesSignature'] ?? '') !== self::signature()) return;
        $s = $this->s; $table = $s->table('trials');
        foreach ($room['players'] as $p) {
            if (!empty($p['bot']) || empty($p['versionId'])) continue;
            $v = $s->one('SELECT * FROM '.$s->table('versions').' WHERE id = ? AND user_id = ?', [$p['versionId'],$p['id']]);
            if (!$v || $v['fingerprint'] !== self::fingerprint(Rules::validateBuild($p['build'], false))) continue;
            if ($s->one("SELECT id FROM $table WHERE room_code = ? AND version_id = ?", [$room['code'],$v['id']])) continue;
            $data = ['roomCode'=>$room['code'], 'mode'=>$room['mode'], 'winner'=>$room['game']['winner'], 'completedAt'=>time(), 'rulesVersion'=>SkillBlocks::VERSION, 'budgetLimits'=>$room['budgetLimits'] ?? self::limits(), 'withBots'=>count(array_filter($room['players'], function ($p) { return !empty($p['bot']); })) > 0];
            $s->execute("INSERT INTO $table (id, user_id, version_id, fingerprint, rules_signature, room_code, data, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)", [identifier(),$p['id'],$v['id'],$v['fingerprint'],self::signature(),$room['code'],encode($data),time()]);
        }
    }
    public function upload(string $owner, array $input): array
    {
        if (!function_exists('imagecreatefromstring')) throw new ApiError('服务器尚未启用 GD 图片扩展，请联系管理员。', 503);
        $value = $input['image'] ?? null;
        if (!is_string($value) || !preg_match('#^data:image/(png|jpeg);base64,([A-Za-z0-9+/=]+)$#D', $value, $match)) throw new ApiError('请选择 PNG 或 JPEG 图片。');
        $raw = base64_decode($match[2], true);
        if ($raw === false || strlen($raw) > 2*1024*1024) throw new ApiError('图片最大 2 MiB。');
        $info = @getimagesizefromstring($raw);
        if (!$info || !in_array($info[2], [IMAGETYPE_JPEG,IMAGETYPE_PNG], true) || $info[0] < 1 || $info[1] < 1 || $info[0] > 4096 || $info[1] > 4096 || $info[0]*$info[1] > 8388608) throw new ApiError('图片无法读取，或尺寸超过 4096 边长 / 800 万像素。');
        $source = @imagecreatefromstring($raw); if (!$source) throw new ApiError('图片损坏，请重新选择。');
        $scale = min(1, 1024/max($info[0],$info[1])); $w = max(1,(int)round($info[0]*$scale)); $h = max(1,(int)round($info[1]*$scale));
        $canvas = imagecreatetruecolor($w,$h); imagealphablending($canvas,false); imagesavealpha($canvas,true);
        imagecopyresampled($canvas,$source,0,0,0,0,$w,$h,$info[0],$info[1]);
        ob_start(); if ($info[2] === IMAGETYPE_PNG) imagepng($canvas,null,6); else imagejpeg($canvas,null,85); $encoded = ob_get_clean();
        imagedestroy($source); imagedestroy($canvas);
        if (!$encoded || strlen($encoded) > 4*1024*1024) throw new ApiError('转换后的图片过大，请缩小图片后重试。');
        $s = $this->s; $table = $s->table('portraits'); $digest = hash('sha256',$encoded);
        $old = $s->one("SELECT id FROM $table WHERE user_id = ? AND digest = ?", [$owner,$digest]);
        if ($old) return ['art'=>'upload_'.$old['id']];
        $count = $s->one("SELECT COUNT(*) AS n FROM $table WHERE user_id = ?", [$owner]);
        if ((int)$count['n'] >= ($this->config['max_portraits'] ?? 100)) throw new ApiError('图片数量已达上限。历史版本使用的图片会保留，请联系管理员调整配额。');
        $id = identifier(); $s->execute("INSERT INTO $table (id,user_id,mime,data,digest,created_at) VALUES (?,?,?,?,?,?)", [$id,$owner,$info[2] === IMAGETYPE_PNG ? 'image/png' : 'image/jpeg',base64_encode($encoded),$digest,time()]);
        return ['art'=>'upload_'.$id];
    }
}
