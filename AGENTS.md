# ML Carousel Gallery Pro — Permanent Agent Rules

## Environment

This project does NOT use Champ.

Never search for Champ.
Never inspect Champ configuration.
Never ask about Champ.
Never attempt to run Champ.
Never use Champ as part of build, validation, packaging, tests, or deployment.

This project uses Docker for safe build/validation/packaging when execution is required.

## Execution Rules

* Use Docker-based execution only when commands are needed.
* Do not use local PowerShell, CMD, bash/sh, wp-cli, or host-level package tools.
* Do not assume Champ exists.
* Do not waste time or tokens looking for Champ.
* If a build/test/package step is needed, use Docker or the existing repository workflow only.

## WordPress Plugin Rules

* Work only on the existing plugin root.
* Keep slug unchanged: `ml-carousel-gallery-pro`
* Keep root folder unchanged: `ml-carousel-gallery-pro/`
* Keep main file unchanged: `ml-carousel-gallery-pro/ml-carousel-gallery-pro.php`
* Final ZIP must install as UPDATE over the existing plugin.
* Never create a parallel plugin.
* Never rename the root folder.
* Never package from a temporary renamed folder.

## Versioning Rules

Synchronize version in:

* plugin header (`Version:`)
* `MLCGP_VERSION` constant
* `readme.txt` Stable tag
* changelog (top entry)

## Scope Control

Only modify files required by the task.
Do not inspect unrelated systems unless needed.
Do not refactor unrelated code.
Do not touch the carousel engine, gallery discovery, or admin UI unless the task explicitly requires it.

---

## Release Workflow

Every change that ships a new version follows the same flow. **No exceptions.**

### 1. Backup first (local)

```powershell
cd scripts
.\backup.ps1 -Reason "pre-release-vX.Y.Z"
```

### 2. Update version in **all** sync points

| Location                                       | Field                       |
|------------------------------------------------|-----------------------------|
| `ml-carousel-gallery-pro/ml-carousel-gallery-pro.php` | Plugin header `Version:`    |
| `ml-carousel-gallery-pro/ml-carousel-gallery-pro.php` | `define('MLCGP_VERSION', ...)` |
| `ml-carousel-gallery-pro/readme.txt`           | `Stable tag:`               |
| Git tag                                        | `vX.Y.Z`                    |
| Release asset (ZIP)                            | `ml-carroucel-gallery-pro-vX.Y.Z.zip` |

Validate:

```powershell
.\sync-version.ps1
```

### 3. Build the ZIP

```powershell
.\package.ps1 -Version X.Y.Z
```

ZIP MUST have `ml-carousel-gallery-pro/` as the **root** folder.

### 4. Commit, tag, push

```powershell
git add -A
git -c user.name=mlopesdesign -c user.email=mlopesdesign@gmail.com commit -m "release: vX.Y.Z"
git -c user.name=mlopesdesign -c user.email=mlopesdesign@gmail.com tag -a vX.Y.Z -m "ML Carousel Gallery Pro vX.Y.Z"
git push origin main --follow-tags
```

### 5. CI auto-publishes the Release

`.github/workflows/release.yml` listens for tag pushes matching `v*`, runs `php -l` + `node --check`, re-runs `package.sh`, computes SHA-256 and creates a GitHub Release with the ZIP as the only asset.

### Auto-update on customer sites

`includes/Core/GitHubUpdater.php` (do not touch without explicit request) calls
`https://api.github.com/repos/mlopesdesign/ml-carroucel-gallery-pro/releases/latest`
and exposes the asset as a native WordPress update.

---

## What this agent should NOT do

- Don't rename `ml-carousel-gallery-pro/`, the main file, or the slug.
- Don't touch the carousel engine, gallery discovery, admin UI, license manager, or updater unless the task explicitly says so.
- Don't commit `*.zip`, `backups/`, `dist/`, or scratch folders.
- Don't rebase or force-push `main` (unless rebuilding history explicitly approved).