# Changelog

All notable changes to **ML Carousel Gallery Pro** are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project adheres
to [Semantic Versioning](https://semver.org/).

The WordPress.org changelog (used by the WP admin updater) lives in
[`ml-carousel-gallery-pro/readme.txt`](./ml-carousel-gallery-pro/readme.txt) and stays in lockstep
with this file.

---

## [1.11.0] - 2026-10-15

### Changed
- Licenciamento pelo site tools.mlopesdesign.com.br (`ML_License_Client_V2`, `includes/class-ml-license-client.php`); 1 licença = 1 site; teste grátis de 30 dias.
- Trava: sem teste/licença válidos, `admin_post_mlcgp_save_settings` e `mlcgp_refresh_covers` são recusados; o front-end segue igual.
- Aba Licença refeita; faixa de aviso no topo quando travado. Option antiga `mlcgp_license_state` preservada.

### Fixed
- A tela antiga deixava escolher status/plano à mão e salvava como licença ativa sem conferir nada.
- O formulário da aba Licença não fechava (`</form>` ausente).

## [1.10.14] - 2026-10-14

### Changed
- Atualizacoes vem do repositorio publico `ml-carousel-gallery-pro-releases` (release ponte; o codigo-fonte sera privado).
- `GitHubUpdater`: aceita somente `ml-carousel-gallery-pro-v{versao}.zip` (ou `-{versao}.zip`), sem fallback para qualquer ZIP.
- `GitHubUpdater`: preenche `no_update` quando nao ha versao nova e envia `icons` no transient e no `plugins_api`.
- Uma unica consulta ao GitHub por verificacao (lista antiga tinha 2 repositorios inexistentes).
- Scripts de empacotamento geram `ml-carousel-gallery-pro-vX.Y.Z.zip` (nome derivado do slug).

### Added
- `uninstall.php`: limpa somente caches e transients; `mlcgp_settings` e `mlcgp_license_state` preservados.
- Icones `assets/images/icon-128x128.png` e `icon-256x256.png`.

## [1.10.13] - 2026-07-26

### Fixed
- Carrossel preso a um único álbum na origem "todas": o resultado das galerias recentes era descartado e substituído pela listagem do álbum que continha a galeria mais nova.
- Ordenação por criação passa a usar `g.id DESC`; `created_at` vazio jogava galerias novas para o fim da lista.
- Cache inoperante em instalações com object cache persistente (Redis/Memcached): a leitura ia à tabela `wp_options` enquanto a escrita ia ao object cache.
- Invalidação de cache não surtia efeito com object cache ativo (o `DELETE ... LIKE` não alcança o object cache).
- Risco de recursão infinita quando uma página candidata do resolvedor de links continha o próprio shortcode.
- Resultados negativos de resolução de link não eram cacheados, reexecutando toda a resolução a cada visita.
- Carrossel vazio sob concorrência quando o lock de reconstrução não era obtido e não havia cache.
- Consultas duplicadas de capa por galeria e refazimento da consulta de páginas candidatas por galeria.
- `save_post` invalidava o cache em autosaves e revisões.

### Changed
- Invalidação de cache passa a usar geração incremental (`mlcgp_cache_version`), válida com e sem object cache.

### Removed
- Código morto do resolvedor de links (`resolve_gallery_url_from_site_render`, `find_rendered_gallery_url_across_public_posts`, `get_root_album_ids_for_gallery`, `walk_album_ancestors_to_roots`, `get_album_base_url_by_id`, `resolve_album_permalink_from_content`, `normalize_public_url`).

### Compatibility
- Resolvedor de link nativo do ML Gallery Pro preservado.
- Origem por álbum mantém a ordem manual `sort_order ASC, id ASC` do ML Gallery Pro.
- Shortcodes, perfis, capas, títulos, datas, admin UI, licenciamento e updater GitHub inalterados.

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
