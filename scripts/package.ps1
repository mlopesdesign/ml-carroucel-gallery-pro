#requires -Version 5.1
<#
.SYNOPSIS
    Empacota ml-carousel-gallery-pro/ em ZIP instalavel.
#>
[CmdletBinding()]
param(
    [Parameter(Mandatory)]
    [string]$Version
)

$ErrorActionPreference = "Stop"
$root       = Resolve-Path (Join-Path $PSScriptRoot "..")
$pluginDir  = Join-Path $root "ml-carousel-gallery-pro"
$distDir    = Join-Path $root "dist"
$pluginFile = Join-Path $pluginDir "ml-carousel-gallery-pro.php"

if ($Version -notmatch '^\d+\.\d+\.\d+$') { Write-Error "[package] Versao invalida: '$Version'"; exit 1 }

& (Join-Path $PSScriptRoot "sync-version.ps1") | Out-Null
if ($LASTEXITCODE -ne 0) { exit 1 }

$headerVer = (Select-String -Path $pluginFile -Pattern '^\s*\*\s*Version:\s*(\S+)' | Select-Object -First 1).Matches.Groups[1].Value
if ($headerVer -ne $Version) { Write-Error "[package] header ($headerVer) != requested ($Version)"; exit 1 }

$zipName = "ml-carroucel-gallery-pro-v$Version.zip"
$zipPath = Join-Path $distDir $zipName
New-Item -ItemType Directory -Force -Path $distDir | Out-Null
if (Test-Path $zipPath) { Remove-Item $zipPath -Force }

$stageDir    = Join-Path $env:TEMP ("mlcgp-stage-" + [Guid]::NewGuid().ToString('N'))
$stagePlugin = Join-Path $stageDir "ml-carousel-gallery-pro"
New-Item -ItemType Directory -Force -Path $stagePlugin | Out-Null

$exclude = @('\.git(/|\\|$)','\.DS_Store$','Thumbs\.db$','Desktop\.ini$','\.log$','\.swp$','\.swo$','node_modules(/|\\|$)','vendor(/|\\|$)','\.zip$')
function Test-Excluded([string]$relative) { foreach ($p in $exclude) { if ($relative -match $p) { return $true } }; return $false }

Get-ChildItem -Path $pluginDir -Recurse -Force | ForEach-Object {
    $rel = $_.FullName.Substring($pluginDir.Length).TrimStart('\','/')
    if ([string]::IsNullOrEmpty($rel)) { return }
    if (Test-Excluded $rel) { return }
    $tgt = Join-Path $stagePlugin $rel
    if ($_.PSIsContainer) { New-Item -ItemType Directory -Force -Path $tgt | Out-Null }
    else { $d = Split-Path $tgt -Parent; if (-not (Test-Path $d)) { New-Item -ItemType Directory -Force -Path $d | Out-Null }; Copy-Item -LiteralPath $_.FullName -Destination $tgt -Force }
}

Push-Location $stageDir
try { Compress-Archive -Path "ml-carousel-gallery-pro" -DestinationPath $zipPath -CompressionLevel Optimal } finally { Pop-Location }

# Normalize separators + add explicit directory entries (POSIX /)
Add-Type -AssemblyName System.IO.Compression.FileSystem
$tmpZip = $zipPath + ".tmp"
$src = [System.IO.Compression.ZipFile]::OpenRead($zipPath)
$dst = [System.IO.Compression.ZipFile]::Open($tmpZip, [System.IO.Compression.ZipArchiveMode]::Create)
try {
    $seen = New-Object 'System.Collections.Generic.HashSet[string]'
    foreach ($e in $src.Entries) {
        $n = $e.FullName -replace '\\','/'
        $parts = $n.Split('/')
        for ($i = 0; $i -lt $parts.Length - 1; $i++) {
            $dp = ($parts[0..$i] -join '/') + '/'
            if ($seen.Add($dp)) { $dst.CreateEntry($dp) }
        }
        if ($n.EndsWith('/')) { continue }
        $w = $dst.CreateEntry($n, [System.IO.Compression.CompressionLevel]::Optimal)
        $rs = $e.Open(); $ws = $w.Open()
        try { $rs.CopyTo($ws) } finally { $rs.Dispose(); $ws.Dispose() }
    }
} finally { $src.Dispose(); $dst.Dispose() }
Remove-Item -LiteralPath $zipPath -Force
Move-Item -LiteralPath $tmpZip -Destination $zipPath -Force
Remove-Item -LiteralPath $stageDir -Recurse -Force

$sha = (Get-FileHash -Path $zipPath -Algorithm SHA256).Hash
$size = (Get-Item $zipPath).Length
Write-Host "[package] OK"
Write-Host "  Arquivo: $zipName"
Write-Host "  Tamanho: $size bytes"
Write-Host "  SHA-256: $sha"
Write-Host "  Caminho: $zipPath"