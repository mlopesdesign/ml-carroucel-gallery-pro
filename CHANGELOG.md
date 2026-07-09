# Changelog

All notable changes to **ML Carousel Gallery Pro** are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project adheres
to [Semantic Versioning](https://semver.org/).

The WordPress.org changelog (used by the WP admin updater) lives in
[`ml-carousel-gallery-pro/readme.txt`](./ml-carousel-gallery-pro/readme.txt) and stays in lockstep
with this file.

---

## [1.10.12] - 2026-07-02

### Fixed
- Invalidação indevida de transients durante renderização pública.
- Consultas e escritas ao banco em páginas com carrosséis (reduzidas).
- Falhas fatais em cenários de cache ausente ou dependência indisponível.

### Added
- Leitura de cache com reconstrução controlada e lock anti-concorrência.

### Compatibility
- Preserva integração com ML Gallery Pro para capas, títulos e dados de galerias.
- Mantém compatibilidade com shortcodes existentes e páginas Nicepage.

---

## [1.10.11] - ~2026-06-26

### Internal
- Manutenção interna. Sem alterações funcionais documentadas.
- Veja [`ml-carousel-gallery-pro/readme.txt`](./ml-carousel-gallery-pro/readme.txt) para histórico completo.

---

## [1.10.10] - ~2026-06-26

### Internal
- Manutenção interna. Sem alterações funcionais documentadas.
- Veja [`ml-carousel-gallery-pro/readme.txt`](./ml-carousel-gallery-pro/readme.txt) para histórico completo.

---

## [1.10.9] - 2026-06-26

### Fixed
- Ordenação do carrossel quando a origem é um álbum.
- Origem por álbum agora segue a ordem manual salva pelo ML Gallery Pro em
  `wp_mlgp_album_items.sort_order ASC, id ASC`.

### Compatibility
- Links, preview, multi carousel, autoplay e updater GitHub sem alterações funcionais adicionais.

---

## [1.10.8] - ~2026-06-26

### Internal
- Manutenção interna. Sem alterações funcionais documentadas.
- Veja [`ml-carousel-gallery-pro/readme.txt`](./ml-carousel-gallery-pro/readme.txt) para histórico completo.

---

## [1.10.7] - ~2026-06-26

### Internal
- Manutenção interna. Sem alterações funcionais documentadas.
- Veja [`ml-carousel-gallery-pro/readme.txt`](./ml-carousel-gallery-pro/readme.txt) para histórico completo.

---

## [1.10.6] - ~2026-06-26

### Internal
- Manutenção interna. Sem alterações funcionais documentadas.
- Veja [`ml-carousel-gallery-pro/readme.txt`](./ml-carousel-gallery-pro/readme.txt) para histórico completo.

---

## [1.10.5] - ~2026-06-26

### Internal
- Manutenção interna. Sem alterações funcionais documentadas.
- Veja [`ml-carousel-gallery-pro/readme.txt`](./ml-carousel-gallery-pro/readme.txt) para histórico completo.

---

## [1.10.4] - ~2026-06-26

### Internal
- Manutenção interna. Sem alterações funcionais documentadas.
- Veja [`ml-carousel-gallery-pro/readme.txt`](./ml-carousel-gallery-pro/readme.txt) para histórico completo.

---

## [1.10.3] - 2026-06-26

### Changed
- Ordenação decrescente unificada: álbuns e galerias nas listas admin agora ordenam
  por `created_at DESC, id DESC` (mais recente primeiro) com `COALESCE` para valores nulos.
- Itens recém-adicionados aparecem sempre em primeiro nas listas de álbuns e galerias do painel.

### Compatibility
- Frontend carousel já usava ordering decrescente — confirmado sem regressão.

---

## Earlier versions

Veja [`ml-carousel-gallery-pro/readme.txt`](./ml-carousel-gallery-pro/readme.txt) para o histórico
completo desde a v1.6.0 (≥ 1.6.0).
