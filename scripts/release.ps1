#requires -Version 5.1
<#
.SYNOPSIS
    Fluxo completo de release: backup, sync, package, instrucoes de commit/tag/push.
#>
[CmdletBinding()]
param(
    [Parameter(Mandatory)]
    [string]$Version,
    [string]$Title = "",
    [string]$Notes = ""
)

$ErrorActionPreference = "Stop"
$root = Resolve-Path (Join-Path $PSScriptRoot "..")
Set-Location $root

if ($Version -notmatch '^\d+\.\d+\.\d+$') { Write-Error "[release] Versao invalida"; exit 1 }
$tag = "v$Version"
$zipName = "ml-carroucel-gallery-pro-v$Version.zip"
$distZip = Join-Path $root "dist\$zipName"
$changesReadme = Join-Path $root "ml-carousel-gallery-pro\readme.txt"

Write-Host "`n=== [1/4] Sync ===" -ForegroundColor Cyan
& (Join-Path $PSScriptRoot "sync-version.ps1")
if ($LASTEXITCODE -ne 0) { exit 1 }

Write-Host "`n=== [2/4] Backup ===" -ForegroundColor Cyan
& (Join-Path $PSScriptRoot "backup.ps1") -Reason "pre-release-$tag"
if ($LASTEXITCODE -ne 0) { exit 1 }

Write-Host "`n=== [3/4] Package ===" -ForegroundColor Cyan
& (Join-Path $PSScriptRoot "package.ps1") -Version $Version
if ($LASTEXITCODE -ne 0) { exit 1 }

Write-Host "`n=== [4/4] Resumo ===" -ForegroundColor Cyan
if (-not $Title) { $Title = "ML Carousel Gallery Pro v$Version" }
$sha  = (Get-FileHash -Path $distZip -Algorithm SHA256).Hash
$size = (Get-Item $distZip).Length
Write-Host ""
Write-Host "  Tag    : $tag"
Write-Host "  Titulo : $Title"
Write-Host "  ZIP    : $zipName ($size bytes)"
Write-Host "  SHA-256: $sha"
Write-Host ""
Write-Host "Comandos sugeridos:"
Write-Host "  git add -A"
Write-Host "  git -c user.name=mlopesdesign -c user.email=mlopesdesign@gmail.com commit -m `"release: $tag`""
Write-Host "  git -c user.name=mlopesdesign -c user.email=mlopesdesign@gmail.com tag -a $tag -m `"$Title`""
Write-Host "  git push origin main --follow-tags"
Write-Host ""
Write-Host "Depois:"
Write-Host "  gh release create $tag `"$distZip`" --title `"$Title`" --notes @`"$changesReadme`""