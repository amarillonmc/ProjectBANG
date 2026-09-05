# 部署与维护说明

## 1. 主机条件

推荐 PHP 8.2+、PDO、JSON、mbstring，搭配 **PDO SQLite** 或 **PDO MySQL**。无需 Composer、npm、SSH、Redis、WebSocket、消息队列或后台守护进程。前端通过短轮询更新；一般 Apache / LiteSpeed 虚拟主机即可运行。

SQLite 适合第一批小规模内测；若主机不开放 SQLite，或希望提高并发房间承载能力，使用 MySQL 5.7+ / MariaDB 的 InnoDB 表。具体承载量取决于主机 PHP 进程数、I/O 和玩家轮询，不能凭“支持 MySQL”推定生产容量。

## 2. 推荐目录部署（最安全，支持传统主机）

解压发布包。如果控制面板可自定义域名的文档根目录，将它指定到项目的 `public/`。

如果主机固定网站目录为 `public_html/`：

```text
账户目录/
├── public_html/          ← 把项目 public/ 内的所有文件放在这里
│   ├── index.html
│   ├── api.php
│   └── assets/
├── src/                  ← 项目 src/，与 public_html/ 同级
├── var/                  ← PHP 可写，仅数据库和本地数据
└── config.php            ← 可选配置文件，位于网站目录之外
```

`api.php` 从上一级 `src/` 启动；这样不需要改代码。不要仅上传 `public/` 而忘记 `src/`。不要把 `src/` 放入 `public_html/src/`，因为那与默认相对路径不匹配。

子目录部署也可用：例如 `public_html/game/public/` 为访问路径，游戏入口 `https://example.com/game/public/`，其上一级放 `src/`、`var/` 和 `config.php`。此时必须保证主机执行项目中用于拒绝敏感目录访问的 `.htaccess`。如能放到网站目录之外，优先使用上一种布局。

### 主机不允许写到 Web 根目录外

可以上传整个项目，访问 `/项目目录/public/`，但必须是 **Apache / LiteSpeed 且 AllowOverride 生效**。`src/.htaccess`、`var/.htaccess` 和根目录文件保护不能省略。上传后用无登录浏览器请求 `/项目目录/var/game.sqlite`、`/项目目录/src/Engine.php`、`/项目目录/config.php`，应返回 403/404，绝不能下载数据。不要将 ZIP、数据库备份、日志或源码备份放在 Web 根目录里。Nginx 不读取 `.htaccess`，必须由管理员配置拒绝目录规则；无法配置就用网站目录外的布局。

## 3. SQLite：零配置启动

默认无需创建 `config.php`，首次访问 API 自动建表并创建 `var/game.sqlite`。

1. 在主机 PHP 扩展面板启用 `pdo_sqlite`。
2. 确保 PHP 进程能写入 `var/`，包括创建 journal/WAL 等辅助文件；只允许数据库文件可写而目录不可写是不够的。
3. 通常目录权限 0750 / 0755 配合正确属主，数据库 0640 / 0644 即可；主机使用独立 PHP 用户时按主机指导设置。不要盲目设置 0777。
4. 打开网站，创建昵称和一个练习房间，即完成初始化。

配置示例：

```php
<?php
return [
    'database' => [
        'driver' => 'sqlite',
        'sqlite_path' => __DIR__ . '/var/game.sqlite',
        'prefix' => 'im_',
    ],
    'debug' => false,
];
```

## 4. MySQL / MariaDB

在主机控制面板创建数据库与专用用户，授权该数据库的建表、读写权限。启用 `pdo_mysql`。复制 `config.example.php` 为 `config.php` 并填写：

```php
<?php
return [
    'database' => [
        'driver' => 'mysql',
        'host' => 'localhost',
        'port' => 3306,
        'database' => 'account_imaginary',
        'username' => 'account_gameuser',
        'password' => '仅在自己的服务器填写',
        'prefix' => 'im_',
    ],
    'debug' => false,
    'max_builds' => 30,
    'max_rooms_per_host' => 10,
];
```

使用主机提供的数据库地址，未必为 localhost。不要在前端或公开仓库中放密码。表由 PHP 自动初始化，默认前缀 `im_`。MySQL 必须使用事务表；程序使用 InnoDB 和 utf8mb4。

数据库切换不会自动迁移 SQLite 数据。首次内测前选定即可；已有数据迁移应另做按表导出、导入和校验，不要仅修改驱动名后期待原房间还在。

## 5. PHP 与 HTTP 设置

- 开启 HTTPS，避免内测凭证经过明文网络。
- 若 FastCGI 没有把 Authorization 请求头传给 PHP，前端会无法保持身份。Apache 可在 `public/.htaccess` 中使用项目提供的请求头转发设置；Nginx 使用 `fastcgi_param HTTP_AUTHORIZATION $http_authorization;`。
- `display_errors=Off`，`log_errors=On`；生产配置 `debug=false`。PHP 错误写到主机后台日志，API 返回可处理的 JSON 错误。
- 请求体有服务端上限，自创牌名称、结构深度、牌数和积木数也会检查；增大 `post_max_size` 不会绕过这些限制。
- API 回应不应经过页面缓存。关闭主机对 `api.php` 的 CDN/全页缓存，保持 Authorization 头。图片、CSS 和 JavaScript 可正常缓存。
- 项目不依赖 URL 重写。入口页面的路径保留末尾 `/`，静态资源和 API 使用相对路径。

## 6. 首次部署验收

1. 打开首页，确认插画、中文、工坊都正常。
2. 创建一个练习房间，摸牌、出牌、使用装备、结束回合，观察机器人行动。
3. 用另一浏览器创建独立身份，加入真人房间，确认只看到自己的手牌和心象顺序。
4. 刷新页面，确认身份和当前房间可以恢复。
5. 在工坊修改示例构筑并保存；将超量数值或不合规 JSON 导入，确认服务端拒绝。
6. 完成一局后导出对局记录，提交一条反馈。
7. 访问敏感目录测试 403/404，检查主机日志无 PHP 错误。

有命令行权限时可运行 `php tools/doctor.php` 检查环境，自动测试见 `docs/TESTING.md`。验收期间使用测试身份；不要把自己的真实数据库交给破坏性测试脚本。

## 7. 身份、备份与升级

这是内测访客身份制，没有邮箱找回。浏览器 localStorage 保存随机访问凭证，服务器只保存其摘要。刷新、关闭后重开可继续；清理浏览器存储会失去该身份。凭证不写进房间邀请链接。需要在别的设备继续时使用当前版本提供的凭证恢复方式，若界面没有恢复入口，先不要清理原浏览器数据。

备份 `config.php` 和整个数据库，图片与源码另行版本保存。SQLite 应暂停站点写入后复制数据库，或使用 SQLite 一致性备份功能；正在写入时只复制主文件可能漏掉 WAL。MySQL 用控制面板或 `mysqldump --single-transaction`。备份务必保存在 Web 根目录之外，并按个人数据处理。

更新前先结束测试房间并备份。新版本覆盖 `public/`、`src/` 和文档，保留 `config.php` 与 `var/` 数据。当前版本启动会创建缺失的表，但不是任意未来数据库结构的迁移系统。规则版本改变后不要强行继续旧版本未结束对局；先在备份副本上检查兼容性。

清理房间应先导出需要的内测记录。首版没有公开管理后台，也没有无条件自动删历史的定时任务；管理员可在数据库控制面板管理数据。用户、构筑、房间和反馈均属服务端数据，切勿直接删除仍被房间引用的记录。

有命令行权限时，执行 `php tools/export-feedback.php` 可将反馈导出到私有 `var/` 文件；也可在命令后给出私有目标文件路径。无 SSH 的主机使用数据库管理面板查看 `im_feedback` 表。此工具不会导出身份访问凭证。

## 8. 故障排查

| 现象 | 检查 |
| --- | --- |
| 首页能开，所有操作失败 | 浏览器 Network 中 api.php 状态、主机 PHP 错误日志、src 相对路径 |
| could not find driver | 启用所选驱动 pdo_sqlite / pdo_mysql；确认修改的是网站所用 PHP 版本 |
| database is locked | 检查磁盘、慢请求、并发；SQLite 有超时等待，持续出现应降低轮询压力或迁移 MySQL |
| unable to open database | var 的目录权限、属主、open_basedir 限制、磁盘配额 |
| 总是要求创建身份 | Authorization 头是否被主机丢弃，浏览器是否禁用本地存储 |
| 动作提示版本已更新 | 另一标签页或响应已推进状态；刷新对局后重试，不重复应用旧动作 |
| 机器人不动 | 至少有一个客户端保持轮询；全体离线时无后台守护程序 |
| 新资源没有显示 | 浏览器强制刷新，并检查主机静态缓存；不要缓存 API |
| 子目录图片或 API 404 | 访问入口末尾斜线、public/assets 是否完整上传、API 及 src 层级 |
| 反馈后看不到管理页面 | 首版反馈保存在数据库，供管理员导出；没有网页管理后台 |

不要为了排障在公网放置 `phpinfo()`、数据库下载器或无认证日志接口。可以使用私有命令行检查脚本或主机控制面板。
