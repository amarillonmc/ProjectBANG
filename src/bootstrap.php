<?php
declare(strict_types=1);

namespace Imaginary;

/** config.php belongs outside the web document root. Environment values are optional. */
function configuration(): array
{
    $root = dirname(__DIR__);
    $config = [
        'database' => [
            'driver' => getenv('IMAGINARY_DB_DRIVER') ?: 'sqlite',
            'sqlite_path' => getenv('IMAGINARY_SQLITE_PATH') ?: $root . '/var/game.sqlite',
            'host' => getenv('IMAGINARY_DB_HOST') ?: 'localhost',
            'port' => (int) (getenv('IMAGINARY_DB_PORT') ?: 3306),
            'database' => getenv('IMAGINARY_DB_NAME') ?: 'imaginary',
            'username' => getenv('IMAGINARY_DB_USER') ?: '',
            'password' => getenv('IMAGINARY_DB_PASSWORD') ?: '',
            'prefix' => 'im_',
        ],
        'debug' => false,
        'max_builds' => 30,
        'max_rooms_per_host' => 10,
    ];
    $file = $root . '/config.php';
    if (is_file($file)) {
        $local = require $file;
        if (!is_array($local)) {
            throw new \RuntimeException('config.php must return an array.');
        }
        $config = array_replace_recursive($config, $local);
    }
    return $config;
}

function encode(array $value): string
{
    return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
}

function decode(string $value): array
{
    $decoded = json_decode($value, true, 64, JSON_THROW_ON_ERROR);
    if (!is_array($decoded)) {
        throw new \InvalidArgumentException('数据必须是对象。');
    }
    return $decoded;
}

function identifier(): string
{
    return bin2hex(random_bytes(16));
}

final class ApiError extends \RuntimeException
{
    public $httpStatus;

    public function __construct(string $message, int $status = 400)
    {
        parent::__construct($message);
        $this->httpStatus = $status;
    }
}

require_once __DIR__ . '/Store.php';
require_once __DIR__ . '/Catalog.php';
require_once __DIR__ . '/Rules.php';
require_once __DIR__ . '/Engine.php';
