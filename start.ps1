param([int]$Port = 17777)
$ErrorActionPreference = 'Stop'
if ($Port -lt 1024 -or $Port -gt 65535) { throw 'Port must be between 1024 and 65535.' }
$projectDirectory = $PSScriptRoot
$phpCommand = Get-Command php -ErrorAction Stop
$pdoDrivers = & $phpCommand.Source -r 'echo implode(",", PDO::getAvailableDrivers());'
$phpArguments = @()
if ($pdoDrivers -notmatch 'sqlite') { $phpArguments += @('-d', 'extension=pdo_sqlite') }
$phpArguments += @('-S', "127.0.0.1:$Port", '-t', (Join-Path $projectDirectory 'public'))
Write-Host "时空并错 · http://127.0.0.1:$Port  (Ctrl+C 停止)"
& $phpCommand.Source @phpArguments
