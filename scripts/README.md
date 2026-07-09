# scripts/

Pipelines locais (PowerShell, Windows) e do CI (bash, Linux runner) que garantem que cada mudança vire release versionada e empacotada de forma reprodutível.

## Local (Windows / PowerShell 5.1+)

| Script              | Função                                                                 |
|---------------------|------------------------------------------------------------------------|
| `backup.ps1`        | Snapshot da pasta `ml-carousel-gallery-pro/` antes de qualquer mudança. |
| `sync-version.ps1`  | Valida header / constante / readme estão com a mesma versão.           |
| `package.ps1`       | Empacota `ml-carousel-gallery-pro/` em `dist/ml-carroucel-gallery-pro-vX.Y.Z.zip`. |
| `release.ps1`       | Fluxo completo: backup → sync → package → instruções de commit/push.   |

### Uso típico

```powershell
cd B:\PLUGINS MINIMAX CODE\ml-carroucel-gallery-pro\scripts

# 1) Antes de mexer
.\backup.ps1 -Reason "pre-feature-foo"

# 2) Apos ajustar versao
.\sync-version.ps1

# 3) Build do ZIP
.\package.ps1 -Version 1.10.13

# 4) Release completa
.\release.ps1 -Version 1.10.13 -Title "ML Carousel Gallery Pro v1.10.13 - Fix" -Notes "..."
```

## CI (Linux / GitHub Actions runner)

| Script              | Função                              |
|---------------------|-------------------------------------|
| `sync-version.sh`   | Mesma validação, em bash.           |
| `package.sh`        | Mesmo empacotamento, em bash + zip. |

## Convenções

- **Versão** sempre `X.Y.Z`.
- **Tag** sempre `vX.Y.Z`.
- **ZIP** sempre `ml-carroucel-gallery-pro-vX.Y.Z.zip`.
- **Pastas proibidas no ZIP**: `.git/`, `node_modules/`, `vendor/`, `*.log`, `.DS_Store`, `Thumbs.db`, `Desktop.ini`.
- **Pastas locais ignoradas pelo git**: `backups/`, `dist/`.