#requires -Version 5.1
<#
.SYNOPSIS
    Snapshot local da pasta do plugin antes de qualquer mudanca.
#>
[CmdletBinding()]
param([string]$Reason = "manual")

$ErrorActionPreference = "Stop"
$root       = Resolve-Path (Join-Path $PSScriptRoot "..")
$pluginDir  = Join-Path $root "ml-carousel-gallery-pro"
$backupRoot = Join-Path $root "backups"
$stamp      = Get-Date -Format "yyyyMMdd-HHmmss"
$dest       = Join-Path $backupRoot "${stamp}_${Reason}"

if (-not (Test-Path $pluginDir)) { Write-Error "[backup] Pasta nao encontrada: $pluginDir"; exit 1 }
New-Item -ItemType Directory -Force -Path $dest | Out-Null
Copy-Item -Path (Join-Path $pluginDir "*") -Destination $dest -Recurse -Force
$count = (Get-ChildItem -Recurse -File $dest).Count
Write-Host "[backup] OK -> $dest ($count arquivos)"