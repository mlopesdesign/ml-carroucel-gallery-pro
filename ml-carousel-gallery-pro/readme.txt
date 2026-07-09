ML Carousel Gallery Pro
Contributors: mlopesdesign
Tags: gallery, carousel, slider, ml-gallery-pro
Requires at least: 6.0
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.10.12
License: GPLv2 or later

Carousel horizontal de galerias do ML Gallery Pro com autoplay, swipe mobile e links diretos.

== Description ==

Exibe as galerias mais recentes do ML Gallery Pro em um carousel horizontal com:

* multi carousel por perfil
* seleção por álbum, galerias específicas ou todas
* resolvedor de link via runtime real do ML Gallery Pro
* data apenas pela máscara XX-XX-XXXX no título
* opção de exibir ou ocultar a data
* layout administrativo padrão ML com licenciamento funcional
* shortcode: [ml_carousel_gallery]

== Installation ==

1. Instale e ative ML Gallery Pro primeiro.
2. Instale este plugin.
3. Configure em ML Carousel.
4. Use o shortcode [ml_carousel_gallery] em qualquer página.

== Changelog ==

= 1.10.12 =
* Corrige invalidação indevida de transients durante renderização pública.
* Reduz consultas e escritas ao banco em páginas com carrosséis.
* Adiciona leitura de cache com reconstrução controlada e lock anti-concorrência.
* Preserva integração com ML Gallery Pro para capas, títulos e dados de galerias.
* Mantém compatibilidade com shortcodes existentes e páginas Nicepage.
* Evita falhas fatais em cenários de cache ausente ou dependência indisponível.

= 1.10.9 =
* Corrigida a ordenação do carrossel quando a origem é um álbum.
* A origem por álbum agora segue a ordem manual salva pelo ML Gallery Pro em wp_mlgp_album_items.sort_order ASC, id ASC.
* Mantidos links, preview, multi carousel, autoplay e updater GitHub sem alterações funcionais adicionais.

= 1.10.3 =
* Ordenação decrescente unificada: álbuns e galerias nas listas admin agora ordenam por created_at DESC, id DESC (mais recente primeiro) com COALESCE para valores nulos.
* Itens recém-adicionados aparecem sempre em primeiro nas listas de álbuns e galerias do painel.
* Frontend carousel já usava ordering decrescente — confirmado sem regressão.

= 1.10.2 =
* Novas galerias aparecem imediatamente no carousel: cache de listagem reduzido de 15 min para 60 s, garantindo que uma galeria recém-criada apareça no máximo em 1 minuto mesmo sem hook de invalidação.
* Invalidação automática ampliada: adicionados hooks mlgp_gallery_created, mlgp_gallery_status_changed, mlgp_gallery_published, mlgp_after_save_gallery, mlgp_after_create_gallery, mlgp_after_delete_gallery, transition_post_status e admin_post_mlcgp_save_settings.
* Ordenação com fallback seguro: COALESCE(NULLIF(created_at,""),"1970-01-01") DESC, id DESC garante que galerias sem created_at válido ainda apareçam na ordem correta.

= 1.10.1 =
* Corrige get_cover_url(): agora consulta mlgp_gallery_items WHERE id = cover_item_id antes de cair no fallback de primeiro item visível. O carousel passa a exibir a capa real selecionada no ML Gallery Pro em vez da primeira foto da galeria.

= 1.10.0 =
* Nova aba "Itens": exibe uma seção por carousel com preview de capa, título parseado (linhas separadas), data e link de cada galeria.
* Botão "Atualizar capas" por carousel: invalida cache de listagem e covers via AJAX, forçando releitura imediata das capas atuais do ML Gallery Pro.
* get_admin_preview_items() agora aceita profile_id para servir dados reais de cada carousel individualmente.
* AJAX handler mlcgp_refresh_covers adicionado em Plugin.php com capability check e nonce validation.
* admin.js estendido com handler AJAX para o botão de refresh com feedback via toast (sucesso/erro).
* Nonce localizado via wp_localize_script no enqueue_assets().

= 1.9.5 =
* Corrige atualização de capa: get_cover_url() agora relê cover_attachment_id e cover_item_id diretamente do banco a cada render, garantindo que a capa nova apareça imediatamente mesmo com o cache de listagem ativo.
* Adiciona hooks mlgp_gallery_cover_changed, mlgp_cover_saved e mlgp_gallery_updated para invalidação de cache ao trocar capa.
* Expande invalidate_cache_on_attachment_change() para vigiar todas as meta keys de capa do ML Gallery Pro (mlgp_cover_attachment_id, mlgp_cover_item_id, _mlgp_cover e variantes).

= 1.9.4 =
* Corrige process_title(): corta o título na primeira máscara DD-MM-YYYY encontrada, ignorando tudo após a data (créditos, fotógrafo, etc.).
* Corrige get_display_date(): retorna a primeira DD-MM-YYYY encontrada em qualquer posição do título.
* Título multi-linha: separadores ' - ' antes da data são renderizados como linhas separadas no card (frontend e preview admin).
* Adiciona CSS .mlcgp-card__title-line e .mlcgp-preview-title-line para suporte visual a múltiplas linhas no card.

= 1.9.3 =
* Corrige process_title(): regex agora remove apenas o sufixo exato ' - DD-MM-YYYY' no final do título (separador espaço-hífen-espaço obrigatório). Títulos sem esse separador exato não são alterados.
* Corrige get_display_date(): regex agora extrai apenas a data do sufixo ' - DD-MM-YYYY' no final do título. Datas em outras posições do título são ignoradas.

= 1.9.2 =
* Corrige cache da listagem de galerias: TRANSIENT_KEY era definida mas nunca usada em get/set — agora a listagem é cacheada por 15 min com chave por parâmetros de consulta.
* Corrige invalidação de cache de URLs de galeria e álbum: prefixos no invalidate_cache estavam incorretos (faltava 'v190_') — URLs obsoletas nunca eram limpas.
* Corrige mutação de superglobals ($_GET/$_REQUEST) no resolvedor de URL: envolve apply_filters('the_content') em try/finally para garantir restauração mesmo em caso de exceção.
* Limita renderizações de conteúdo no resolvedor de URL a 5 candidatos por álbum (era até 25) para evitar timeout em sites com muitos posts.
* Refatora License::get_runtime_config() para usar a instância de Settings injetada via construtor, eliminando instanciação duplicada.
* Atualiza comentário interno do JS para v1.9.2.

= 1.9.1 =
* Remove o cache de listagem das galerias para refletir troca de capa sem esperar transient antigo.
* Adiciona invalidação extra de cache em mudanças de attachment/meta.
* Adiciona cache-buster leve na URL da capa para forçar refresh visual quando a imagem de capa muda.

= 1.9.0 =
* Reescreve o resolvedor de links para usar o runtime real do front do ML Gallery Pro.
* Testa automaticamente as páginas públicas candidatas e valida a galeria renderizada antes de gerar a URL.
* Elimina fallback quebrado na raiz do site quando o álbum é recriado ou muda de página.


= 1.7.8 =
* Corrige a estrutura HTML do preview e posiciona as duas setas dentro do carrossel no mesmo eixo.

= 1.7.7 =
* Correção estrutural do preview para manter as duas setas dentro do carrossel.
* Seta direita reposicionada para o mesmo eixo vertical e lateral da seta esquerda.
* Navegação do preview mantida com alinhamento simétrico no admin.


= 1.7.0 =
* Adicionado preview em tempo real no admin para card, texto, data, overlay e quantidade visível.
* Preview com alternância Desktop, Tablet e Mobile sem salvar.
* Mantida a base visual ML, mesma instalação e mesmo slug.


= 1.6.1 =
* Removida a aba Prompt Base do admin.
* Interface administrativa limpa, sem bloco de prompt exposto.
* Ajustes visuais para eliminar fundos escuros/preto e reforçar a estética clean da base ML.

= 1.6.0 =
* Admin consolidado na base oficial ML.
* Licenciamento padrão ML funcional centralizado.
* Product ID, endpoint e textos comerciais centralizados nas configurações.
* Data no card mantida apenas pela máscara no título, sem fallback inventado.
* Mesmo slug, mesma instalação e update real.
