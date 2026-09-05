param([string]$OutputName = 'imaginary-playtest.zip')
$ErrorActionPreference = 'Stop'
if ([IO.Path]::GetFileName($OutputName) -ne $OutputName -or $OutputName -notmatch '\.zip$') {
    throw 'OutputName must be a ZIP filename without a directory.'
}
$projectDirectory = Split-Path -Parent $PSScriptRoot
$outputDirectory = Join-Path $projectDirectory 'output'
New-Item -ItemType Directory -Path $outputDirectory -Force | Out-Null
$archivePath = Join-Path $outputDirectory $OutputName
Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem
$fileStream = [IO.File]::Open($archivePath, [IO.FileMode]::Create)
$archive = [IO.Compression.ZipArchive]::new($fileStream, [IO.Compression.ZipArchiveMode]::Create)
try {
    $includeFiles = @('README.md', 'start.ps1', 'config.example.php', '.gitignore', '.htaccess', 'var/.htaccess', 'var/.gitkeep')
    foreach ($directory in @('public', 'src', 'docs', 'tests', 'tools')) {
        foreach ($file in Get-ChildItem -LiteralPath (Join-Path $projectDirectory $directory) -File -Recurse -Force) {
            $relativePath = $file.FullName.Substring($projectDirectory.Length + 1).Replace('\', '/')
            if ($relativePath -notlike 'tools/runtime/*' -and $relativePath -ne 'docs/CONTRACT.md') { $includeFiles += $relativePath }
        }
    }
    foreach ($relativePath in $includeFiles | Sort-Object -Unique) {
        $sourcePath = Join-Path $projectDirectory $relativePath
        if (Test-Path -LiteralPath $sourcePath -PathType Leaf) {
            [IO.Compression.ZipFileExtensions]::CreateEntryFromFile($archive, $sourcePath, $relativePath, [IO.Compression.CompressionLevel]::Optimal) | Out-Null
        }
    }
} finally {
    $archive.Dispose()
    $fileStream.Dispose()
}
Get-Item -LiteralPath $archivePath | Select-Object FullName, Length
