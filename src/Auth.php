<?php
declare(strict_types=1);
namespace Imaginary;

/** Accounts attach to an existing guest ID, preserving its works and room membership. */
final class Auth
{
    private $store;
    public function __construct(Store $store) { $this->store = $store; }
    public function user(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/D', $token)) return null;
        $s = $this->store;
        $row = $s->one('SELECT u.id, u.name FROM '.$s->table('users').' u INNER JOIN '.$s->table('sessions').' s ON s.user_id = u.id WHERE s.token_hash = ? AND s.expires_at > ?', [hash('sha256', $token), time()]);
        if (!$row) $row = $s->one('SELECT u.id, u.name FROM '.$s->table('users').' u LEFT JOIN '.$s->table('accounts').' a ON a.user_id = u.id WHERE u.token_hash = ? AND a.user_id IS NULL', [hash('sha256', $token)]);
        if (!$row) return null;
        $account = $s->one('SELECT handle FROM '.$s->table('accounts').' WHERE user_id = ?', [$row['id']]);
        $row['handle'] = $account['handle'] ?? null;
        return $row;
    }
    private function handle($value): string
    {
        if (!is_string($value) || !preg_match('/^[a-zA-Z0-9_]{3,24}$/D', $value)) throw new ApiError('账号名需为 3～24 位英文字母、数字或下划线。');
        return strtolower($value);
    }
    private function password($value): string
    {
        if (!is_string($value) || !preg_match('//u', $value) || strpos($value, "\0") !== false || preg_match_all('/./us', $value) < 10 || strlen($value) > 72) throw new ApiError('密码至少 10 个字符，最多 72 字节（中文约 24 字）。');
        return password_hash($value, PASSWORD_DEFAULT);
    }
    private function session(string $id): string
    {
        $s = $this->store; $table = $s->table('sessions');
        $s->execute("DELETE FROM $table WHERE user_id = ? AND expires_at <= ?", [$id, time()]);
        $old = $s->all("SELECT token_hash FROM $table WHERE user_id = ? ORDER BY created_at DESC, token_hash", [$id]);
        foreach (array_slice($old, 19) as $row) $s->execute("DELETE FROM $table WHERE token_hash = ?", [$row['token_hash']]);
        $token = bin2hex(random_bytes(32));
        $s->execute("INSERT INTO $table (token_hash, user_id, created_at, expires_at) VALUES (?, ?, ?, ?)", [hash('sha256', $token), $id, time(), time()+30*86400]);
        return $token;
    }
    public function register(array $user, array $input): array
    {
        $handle = $this->handle($input['handle'] ?? null); $hash = $this->password($input['password'] ?? null);
        return $this->store->transaction(function () use ($user, $handle, $hash) {
            $s = $this->store; $accounts = $s->table('accounts'); $id = $user['id'];
            $s->one('SELECT id FROM '.$s->table('users').' WHERE id = ?'.$s->lockSuffix(), [$id]);
            if ($s->one("SELECT user_id FROM $accounts WHERE user_id = ?", [$id])) throw new ApiError('当前身份已绑定账号。', 409);
            if ($s->one("SELECT user_id FROM $accounts WHERE handle = ?", [$handle])) throw new ApiError('这个账号名已被使用。', 409);
            $recovery = bin2hex(random_bytes(24));
            try { $s->execute("INSERT INTO $accounts (user_id, handle, password_hash, recovery_hash, created_at) VALUES (?, ?, ?, ?, ?)", [$id, $handle, $hash, hash('sha256', $recovery), time()]); }
            catch (\PDOException $e) { if (in_array((string)$e->getCode(), ['23000','23505'], true)) throw new ApiError('这个账号名已被使用。', 409); throw $e; }
            // A downloaded guest token must cease to be an alternate account password.
            $s->execute('UPDATE '.$s->table('users').' SET token_hash = ? WHERE id = ?', [hash('sha256', random_bytes(32)), $id]);
            $token = $this->session($id);
            return ['token'=>$token, 'user'=>$this->user($token), 'recoveryCode'=>$recovery];
        });
    }
    public function login(array $input, bool $recover = false): array
    {
        $handle = $this->handle($input['handle'] ?? null);
        $newHash = $recover ? $this->password($input['password'] ?? null) : null;
        return $this->store->transaction(function () use ($handle, $input, $recover, $newHash) {
            $s = $this->store; $table = $s->table('accounts');
            // Lock the identity before its account, consistently with registration and API writes.
            $candidate = $s->one("SELECT user_id FROM $table WHERE handle = ?", [$handle]);
            if ($candidate) $s->one('SELECT id FROM '.$s->table('users').' WHERE id = ?'.$s->lockSuffix(), [$candidate['user_id']]);
            $row = $s->one("SELECT * FROM $table WHERE handle = ?".$s->lockSuffix(), [$handle]);
            $valid = false;
            if ($recover) $valid = $row && is_string($input['recoveryCode'] ?? null) && hash_equals($row['recovery_hash'], hash('sha256', trim($input['recoveryCode'])));
            else {
                $password = $input['password'] ?? '';
                if (is_string($password) && strlen($password) <= 72) $valid = password_verify($password, $row['password_hash'] ?? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.');
                $valid = $valid && $row;
            }
            if (!$valid) throw new ApiError($recover ? '账号名或恢复码不正确。' : '账号名或密码不正确。', 401);
            $id = $row['user_id']; $result = [];
            if ($recover) {
                $code = bin2hex(random_bytes(24));
                $s->execute("UPDATE $table SET password_hash = ?, recovery_hash = ? WHERE user_id = ?", [$newHash, hash('sha256', $code), $id]);
                $s->execute('DELETE FROM '.$s->table('sessions').' WHERE user_id = ?', [$id]);
                $result['recoveryCode'] = $code;
            }
            $token = $this->session($id);
            return $result + ['token'=>$token, 'user'=>$this->user($token)];
        });
    }
    public function logout(string $token): array
    {
        $this->store->execute('DELETE FROM '.$this->store->table('sessions').' WHERE token_hash = ?', [hash('sha256', $token)]);
        return [];
    }
}
