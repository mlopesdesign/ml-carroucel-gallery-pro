<?php
namespace MLCarouselGalleryPro\Admin;

use MLCarouselGalleryPro\Frontend\Carousel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AdminUI {
	public function render( Settings $settings_manager, License $license_manager, Carousel $carousel, string $active_tab, string $toast_message = '', string $toast_type = 'success' ): void {
		$settings        = $settings_manager->get();
		$profiles        = is_array( $settings['profiles'] ?? null ) ? $settings['profiles'] : [];
		$album_options   = $settings_manager->get_album_options_public();
		$gallery_options = $settings_manager->get_gallery_options_public();
		$license_state   = $license_manager->get_state();
		$license_config  = $license_manager->get_runtime_config();
		$preview_items   = $carousel->get_admin_preview_items();
		$tabs            = [ 'dashboard' => 'Dashboard', 'operation' => 'Operação', 'settings' => 'Configurações', 'items' => 'Itens', 'license' => 'Licença' ];
		if ( ! isset( $tabs[ $active_tab ] ) ) {
			$active_tab = 'dashboard';
		}
		?>
		<div class="wrap mlpb-admin-wrap mlcgp-admin-wrap" data-toast-message="<?php echo esc_attr( $toast_message ); ?>" data-toast-type="<?php echo esc_attr( $toast_type ); ?>">
			<div id="mlpb-toast-area" aria-live="polite" aria-atomic="true"></div>

			<section class="mlpb-hero">
				<div class="mlpb-hero-brand">
					<div class="mlpb-hero-mark"><img src="<?php echo esc_url( Helpers::logo_url() ); ?>" alt="ML Lopes Design"></div>
					<div class="mlpb-hero-copy">
						<span class="mlpb-hero-eyebrow">ML Lopes Design · Carousel Suite</span>
						<h1>ML Carousel Gallery Pro</h1>
						<p class="mlpb-intro">Carousel automático de galerias do ML Gallery Pro com link nativo correto, multi profiles, controle real de cards, preview em tempo real e base visual oficial ML preservada.</p>
					</div>
				</div>
				<div class="mlpb-hero-meta">
					<span class="mlpb-version-badge">v<?php echo esc_html( MLCGP_VERSION ); ?></span>
					<div class="mlpb-hero-tags">
						<span class="mlpb-chip">Mesmo slug</span>
						<span class="mlpb-chip">Multi carousel</span>
						<span class="mlpb-chip"><?php echo esc_html( $license_manager->get_plan_label( (string) $license_state['plan'] ) ); ?></span>
					</div>
				</div>
			</section>

			<nav class="mlpb-tab-nav" aria-label="Navegação do plugin">
				<?php foreach ( $tabs as $tab_key => $tab_label ) : ?>
					<button type="button" class="mlpb-tab-button <?php echo $active_tab === $tab_key ? 'is-active' : ''; ?>" data-tab-target="mlpb-tab-<?php echo esc_attr( $tab_key ); ?>"><?php echo esc_html( $tab_label ); ?></button>
				<?php endforeach; ?>
			</nav>

			<section id="mlpb-tab-dashboard" class="mlpb-tab-panel <?php echo 'dashboard' === $active_tab ? 'is-active' : ''; ?>" <?php echo 'dashboard' === $active_tab ? '' : 'hidden'; ?>>
				<div class="mlpb-summary-grid">
					<article class="mlpb-summary-card"><span class="mlpb-summary-label">Shortcode base</span><strong>[ml_carousel_gallery]</strong><small>Base global do carousel.</small></article>
					<article class="mlpb-summary-card"><span class="mlpb-summary-label">Profiles ativos</span><strong><?php echo esc_html( (string) count( $profiles ) ); ?></strong><small>Shortcodes múltiplos por ID.</small></article>
					<article class="mlpb-summary-card"><span class="mlpb-summary-label">Data no card</span><strong><?php echo ! empty( $settings['show_date'] ) ? 'Ativa' : 'Oculta'; ?></strong><small>Somente por máscara no título.</small></article>
					<article class="mlpb-summary-card"><span class="mlpb-summary-label">Licença</span><strong><?php echo esc_html( $license_manager->get_status_label( (string) $license_state['status'] ) ); ?></strong><small><?php echo esc_html( $license_manager->get_plan_label( (string) $license_state['plan'] ) ); ?></small></article>
				</div>

				<div class="mlpb-grid">
					<article class="mlpb-card">
						<div class="mlpb-card-header"><div><h2>Estado consolidado</h2><p class="mlpb-muted">Base visual ML restaurada sem quebrar o motor já validado.</p></div></div>
						<ul class="mlpb-list">
							<li>Links nativos por álbum raiz preservados.</li>
							<li>Consulta continua em <code>wp_mlgp_galleries</code> com status publicado.</li>
							<li>Multi carousel por todas, álbum ou galerias específicas.</li>
							<li>Data do card somente por máscara <code>XX-XX-XXXX</code> no título.</li>
							<li>Sem fallback inventado quando o título não contém data.</li>
						</ul>
						<div class="mlpb-note">A opção de exibir data agora apenas mostra ou oculta a data extraída do título. Se o título não tiver data, o card fica sem data.</div>
					</article>
					<article class="mlpb-card">
						<div class="mlpb-card-header"><div><h2>Núcleo comercial</h2><p class="mlpb-muted">Product ID, endpoint e textos centralizados.</p></div></div>
						<ul class="mlpb-list">
							<li><strong>Product ID:</strong> <?php echo esc_html( (string) $license_config['product_id'] ); ?></li>
							<li><strong>Endpoint:</strong> <?php echo '' !== (string) $license_config['endpoint'] ? esc_html( (string) $license_config['endpoint'] ) : 'Não configurado'; ?></li>
							<li><strong>Item:</strong> <?php echo esc_html( (string) $license_config['item_name'] ); ?></li>
							<li><strong>Status:</strong> <?php echo esc_html( $license_manager->get_status_label( (string) $license_state['status'] ) ); ?></li>
						</ul>
					</article>
				</div>
			</section>

			<section id="mlpb-tab-operation" class="mlpb-tab-panel <?php echo 'operation' === $active_tab ? 'is-active' : ''; ?>" <?php echo 'operation' === $active_tab ? '' : 'hidden'; ?>>
				<div class="mlpb-grid">
					<article class="mlpb-card">
						<div class="mlpb-card-header"><div><h2>Shortcodes e origem</h2><p class="mlpb-muted">Operação do motor sem remendos.</p></div></div>
						<div class="mlpb-codeblock">[ml_carousel_gallery]
[ml_carousel_gallery id="home"]</div>
						<ul class="mlpb-list" style="margin-top:16px;">
							<li>Origem por todas as galerias recentes.</li>
							<li>Origem por álbum específico.</li>
							<li>Origem por galerias específicas.</li>
							<li>Links nativos no formato <code>/{slug-do-album}/?mlgp_album_view_{id}=gallery-{gid}</code>.</li>
						</ul>
					</article>
					<article class="mlpb-card">
						<div class="mlpb-card-header"><div><h2>Regras críticas</h2><p class="mlpb-muted">Pontos fixos do plugin.</p></div></div>
						<ul class="mlpb-list">
							<li>Mesmo slug, mesma pasta e mesmo arquivo principal.</li>
							<li>Sem alterar ML Gallery Pro.</li>
							<li>Sem fallback para home.</li>
							<li>Sem inventar data quando o título não contém a máscara.</li>
							<li>Sem plugin paralelo.</li>
						</ul>
					</article>
				</div>
			</section>

			<section id="mlpb-tab-settings" class="mlpb-tab-panel <?php echo 'settings' === $active_tab ? 'is-active' : ''; ?>" <?php echo 'settings' === $active_tab ? '' : 'hidden'; ?>>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="mlcgp_save_settings">
					<?php wp_nonce_field( 'mlcgp_save_settings' ); ?>
					<div class="mlcgp-settings-grid">
						<article class="mlpb-card">
							<div class="mlpb-card-header"><div><h2>Exibição global</h2><p class="mlpb-muted">Configuração visual aplicada aos carrosseis.</p></div></div>
							<div class="mlpb-form-grid">
								<div class="mlpb-field"><label>Número padrão de galerias</label><input type="number" name="mlcgp_settings[limit]" value="<?php echo esc_attr( (string) $settings['limit'] ); ?>" min="1" max="24"></div>
								<div class="mlpb-field"><label>Velocidade do autoplay (ms)</label><input type="number" name="mlcgp_settings[speed]" value="<?php echo esc_attr( (string) $settings['speed'] ); ?>" min="1000" max="10000" step="500"></div>
							</div>
							<div class="mlcgp-switch-grid">
								<label class="mlcgp-switch-box"><input type="checkbox" name="mlcgp_settings[new_tab]" value="1" <?php checked( 1, $settings['new_tab'] ); ?>><span>Abrir galeria em nova aba</span></label>
								<label class="mlcgp-switch-box"><input type="checkbox" name="mlcgp_settings[autoplay]" value="1" <?php checked( 1, $settings['autoplay'] ); ?>><span>Ativar autoplay</span></label>
								<label class="mlcgp-switch-box"><input type="checkbox" name="mlcgp_settings[show_date]" value="1" <?php checked( 1, $settings['show_date'] ); ?>><span>Exibir data no card</span></label>
								<label class="mlcgp-switch-box"><input type="checkbox" name="mlcgp_settings[center_mode]" value="1" <?php checked( 1, $settings['center_mode'] ); ?>><span>Modo centralizado</span></label>
							</div>
						</article>

						<article class="mlpb-card">
							<div class="mlpb-card-header"><div><h2>Layout dos cards</h2><p class="mlpb-muted">Simetria corrigida com grid limpo e consistente.</p></div></div>
							<div class="mlpb-form-grid mlcgp-form-grid-3">
								<div class="mlpb-field"><label>Espaçamento entre cards (px)</label><input type="number" name="mlcgp_settings[card_gap]" value="<?php echo esc_attr( (string) $settings['card_gap'] ); ?>" min="0" max="80"></div>
								<div class="mlpb-field"><label>Largura do card (px)</label><input type="number" name="mlcgp_settings[card_width]" value="<?php echo esc_attr( (string) $settings['card_width'] ); ?>" min="0" max="1200"></div>
								<div class="mlpb-field"><label>Altura do card (px)</label><input type="number" name="mlcgp_settings[card_height]" value="<?php echo esc_attr( (string) $settings['card_height'] ); ?>" min="0" max="800"></div>
								<div class="mlpb-field"><label>Cards no desktop</label><input type="number" name="mlcgp_settings[visible_desktop]" value="<?php echo esc_attr( (string) $settings['visible_desktop'] ); ?>" min="1" max="6" step="0.5"></div>
								<div class="mlpb-field"><label>Cards no tablet</label><input type="number" name="mlcgp_settings[visible_tablet]" value="<?php echo esc_attr( (string) $settings['visible_tablet'] ); ?>" min="1" max="4" step="0.5"></div>
								<div class="mlpb-field"><label>Cards no mobile</label><input type="number" name="mlcgp_settings[visible_mobile]" value="<?php echo esc_attr( (string) $settings['visible_mobile'] ); ?>" min="1" max="2" step="0.5"></div>
							</div>
						</article>

						<article class="mlpb-card">
							<div class="mlpb-card-header"><div><h2>Texto sobre a imagem</h2><p class="mlpb-muted">Posição, alinhamento e overlay.</p></div></div>
							<div class="mlpb-form-grid">
								<div class="mlpb-field"><label>Posição vertical</label><select name="mlcgp_settings[text_position]"><option value="top" <?php selected( 'top', $settings['text_position'] ); ?>>Superior</option><option value="center" <?php selected( 'center', $settings['text_position'] ); ?>>Centro</option><option value="bottom" <?php selected( 'bottom', $settings['text_position'] ); ?>>Inferior</option></select></div>
								<div class="mlpb-field"><label>Alinhamento horizontal</label><select name="mlcgp_settings[text_align]"><option value="left" <?php selected( 'left', $settings['text_align'] ); ?>>Esquerda</option><option value="center" <?php selected( 'center', $settings['text_align'] ); ?>>Centro</option><option value="right" <?php selected( 'right', $settings['text_align'] ); ?>>Direita</option></select></div>
							</div>
							<div class="mlpb-field"><label>Opacidade do overlay (%)</label><input type="range" id="mlcgp_overlay_opacity" name="mlcgp_settings[overlay_opacity]" value="<?php echo esc_attr( (string) $settings['overlay_opacity'] ); ?>" min="0" max="100"><div class="mlcgp-range-row"><strong id="mlcgp_overlay_value"><?php echo esc_html( (string) $settings['overlay_opacity'] ); ?>%</strong></div></div>
						</article>

						<article class="mlpb-card mlcgp-full-span">
							<div class="mlpb-card-header"><div><h2>Preview em tempo real</h2><p class="mlpb-muted">Veja na hora altura, largura, respiro, texto, data e quantidade visível antes de salvar.</p></div></div>
							<div class="mlcgp-preview-shell">
								<div class="mlcgp-preview-toolbar">
									<div class="mlcgp-preview-devices" role="tablist" aria-label="Preview responsivo">
										<button type="button" class="mlcgp-preview-device is-active" data-device="desktop">Desktop</button>
										<button type="button" class="mlcgp-preview-device" data-device="tablet">Tablet</button>
										<button type="button" class="mlcgp-preview-device" data-device="mobile">Mobile</button>
									</div>
									<div class="mlcgp-preview-readout" id="mlcgp-preview-readout">Desktop · 3.5 cards</div>
								</div>

								<div class="mlcgp-preview-stage device-desktop" id="mlcgp-preview-stage">
									<div
										class="mlcgp-preview-wrapper mlcgp-text-<?php echo esc_attr( (string) $settings['text_position'] ); ?> mlcgp-align-<?php echo esc_attr( (string) $settings['text_align'] ); ?><?php echo ! empty( $settings['center_mode'] ) ? ' mlcgp-center-mode' : ''; ?>"
										id="mlcgp-preview-wrapper"
										style="--mlcgp-gap:<?php echo esc_attr( (string) $settings['card_gap'] ); ?>px;--mlcgp-card-width:<?php echo esc_attr( (string) $settings['card_width'] ); ?>px;--mlcgp-card-height:<?php echo esc_attr( number_format( (float) $settings['card_height'], 0, '.', '' ) ); ?>px;--mlcgp-overlay-opacity:<?php echo esc_attr( number_format( ( (int) $settings['overlay_opacity'] ) / 100, 2, '.', '' ) ); ?>;--mlcgp-visible-desktop:<?php echo esc_attr( rtrim( rtrim( number_format( (float) $settings['visible_desktop'], 1, '.', '' ), '0' ), '.' ) ); ?>;--mlcgp-visible-tablet:<?php echo esc_attr( rtrim( rtrim( number_format( (float) $settings['visible_tablet'], 1, '.', '' ), '0' ), '.' ) ); ?>;--mlcgp-visible-mobile:<?php echo esc_attr( rtrim( rtrim( number_format( (float) $settings['visible_mobile'], 1, '.', '' ), '0' ), '.' ) ); ?>;"
									>
										<div class="mlcgp-preview-track">
										<?php
										$preview_pool = ! empty( $preview_items ) ? array_values( $preview_items ) : [];
										if ( empty( $preview_pool ) ) {
											$preview_pool = [
												[ 'title' => 'GALERIA', 'date' => '', 'image' => '', 'link' => '' ],
											];
										}
										for ( $preview_index = 0; $preview_index < 5; $preview_index++ ) :
											$preview_item  = $preview_pool[ $preview_index % count( $preview_pool ) ];
											$image_url     = is_array( $preview_item ) ? (string) ( $preview_item['image'] ?? '' ) : '';
											$link_url      = is_array( $preview_item ) ? (string) ( $preview_item['link'] ?? '' ) : '';
											$title_text    = is_array( $preview_item ) ? (string) ( $preview_item['title'] ?? 'GALERIA' ) : 'GALERIA';
											$date_text     = is_array( $preview_item ) ? (string) ( $preview_item['date'] ?? '' ) : '';
											$image_classes = 'mlcgp-preview-image';
											if ( '' === $image_url ) {
												$image_classes .= ' mlcgp-preview-image-fallback mlcgp-preview-image-' . (string) ( ( $preview_index % 4 ) + 1 );
											}
											?>
											<div class="mlcgp-preview-slide<?php echo 0 === $preview_index ? ' is-center' : ''; ?>">
												<a class="mlcgp-preview-card" href="<?php echo esc_url( '' !== $link_url ? $link_url : '#' ); ?>" target="_blank" rel="noopener noreferrer">
													<div class="<?php echo esc_attr( $image_classes ); ?>"<?php echo '' !== $image_url ? ' style="background-image:url(' . esc_url( $image_url ) . ');"' : ''; ?>></div>
													<div class="mlcgp-preview-overlay"></div>
													<div class="mlcgp-preview-text">
														<span class="mlcgp-preview-title"><?php
															$preview_title_lines = array_filter( array_map( 'trim', explode( ' - ', $title_text ) ) );
															foreach ( $preview_title_lines as $preview_line ) {
																echo '<span class="mlcgp-preview-title-line">' . esc_html( $preview_line ) . '</span>';
															}
														?></span>
														<span class="mlcgp-preview-date<?php echo ( ! empty( $settings['show_date'] ) && '' !== $date_text ) ? '' : ' is-hidden'; ?>"><?php echo esc_html( $date_text ); ?></span>
													</div>
												</a>
											</div>
										<?php endfor; ?>
										</div>
									</div>
								</div>
						</article>


						<article class="mlpb-card mlcgp-full-span">
							<div class="mlpb-card-header"><div><h2>Multi carousel</h2><p class="mlpb-muted">Profiles com origem por álbum, galerias específicas ou todas as recentes.</p></div></div>
							<div id="mlcgp-profiles" class="mlcgp-profiles">
								<?php foreach ( $profiles as $index => $profile ) : ?>
									<?php $this->render_profile_row( (int) $index, (array) $profile, false, $album_options, $gallery_options ); ?>
								<?php endforeach; ?>
							</div>
							<div class="mlpb-actions"><button type="button" class="button button-secondary mlcgp-add-profile">Adicionar carrossel</button></div>
							<script type="text/template" id="tmpl-mlcgp-profile-row"><?php $this->render_profile_row( 9999, [ 'id' => '', 'label' => '', 'source' => 'all', 'album_id' => 0, 'gallery_ids' => '', 'limit' => 6 ], true, $album_options, $gallery_options ); ?></script>
						</article>
					</div>
					<div class="mlpb-actions" style="margin-top:18px;"><button type="submit" class="button button-primary">Salvar configurações</button></div>
				</form>
			</section>

			<section id="mlpb-tab-items" class="mlpb-tab-panel <?php echo 'items' === $active_tab ? 'is-active' : ''; ?>" <?php echo 'items' === $active_tab ? '' : 'hidden'; ?>>
				<?php if ( empty( $profiles ) ) : ?>
					<div class="mlpb-note">Nenhum carousel configurado. Crie um carousel na aba Configurações primeiro.</div>
				<?php else : ?>
					<?php foreach ( $profiles as $profile ) :
						if ( ! is_array( $profile ) ) { continue; }
						$p_id    = (string) ( $profile['id'] ?? '' );
						$p_label = (string) ( $profile['label'] ?? $p_id );
						$items   = $carousel->get_admin_preview_items( $p_id );
						?>
						<article class="mlpb-card" style="margin-bottom:24px;">
							<div class="mlpb-card-header">
								<div>
									<h2><?php echo esc_html( '' !== $p_label ? $p_label : $p_id ); ?></h2>
									<p class="mlpb-muted"><code>[ml_carousel_gallery id="<?php echo esc_attr( $p_id ); ?>"]</code></p>
								</div>
								<div>
									<button
										type="button"
										class="button button-secondary mlcgp-refresh-covers"
										data-profile="<?php echo esc_attr( $p_id ); ?>"
										data-nonce="<?php echo esc_attr( wp_create_nonce( 'mlcgp_refresh_covers' ) ); ?>"
									>Atualizar capas</button>
								</div>
							</div>

							<?php if ( empty( $items ) ) : ?>
								<p class="mlpb-muted">Nenhuma galeria encontrada para este carousel.</p>
							<?php else : ?>
								<div class="mlcgp-items-grid">
									<?php foreach ( $items as $item ) :
										$img   = (string) ( $item['image'] ?? '' );
										$title = (string) ( $item['title'] ?? '' );
										$date  = (string) ( $item['date'] ?? '' );
										$link  = (string) ( $item['link'] ?? '' );
										$title_lines = array_filter( array_map( 'trim', explode( ' - ', $title ) ) );
										?>
										<div class="mlcgp-item-card">
											<div class="mlcgp-item-thumb" style="<?php echo '' !== $img ? 'background-image:url(' . esc_url( $img ) . ');' : ''; ?>">
												<?php if ( '' === $img ) : ?><span class="mlcgp-item-thumb__placeholder">?</span><?php endif; ?>
											</div>
											<div class="mlcgp-item-meta">
												<span class="mlcgp-item-title">
													<?php foreach ( $title_lines as $line ) : ?>
														<span><?php echo esc_html( $line ); ?></span>
													<?php endforeach; ?>
													<?php if ( empty( $title_lines ) ) : ?><span class="mlpb-muted">Sem título</span><?php endif; ?>
												</span>
												<?php if ( '' !== $date ) : ?>
													<span class="mlcgp-item-date"><?php echo esc_html( $date ); ?></span>
												<?php endif; ?>
												<?php if ( '' !== $link ) : ?>
													<a class="mlcgp-item-link" href="<?php echo esc_url( $link ); ?>" target="_blank" rel="noopener noreferrer">Ver galeria ↗</a>
												<?php endif; ?>
											</div>
										</div>
									<?php endforeach; ?>
								</div>
							<?php endif; ?>
						</article>
					<?php endforeach; ?>
				<?php endif; ?>
			</section>

			<section id="mlpb-tab-license" class="mlpb-tab-panel <?php echo 'license' === $active_tab ? 'is-active' : ''; ?>" <?php echo 'license' === $active_tab ? '' : 'hidden'; ?>>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="mlcgp_save_license">
					<?php wp_nonce_field( 'mlcgp_save_license' ); ?>
					<div class="mlpb-grid">
						<article class="mlpb-card">
							<div class="mlpb-card-header"><div><h2><?php echo esc_html( (string) $license_config['cta_title'] ); ?></h2><p class="mlpb-muted"><?php echo esc_html( (string) $license_config['cta_text'] ); ?></p></div></div>
							<div class="mlpb-form-grid">
								<div class="mlpb-field"><label>Serial</label><input type="text" name="mlcgp_license[serial]" value="<?php echo esc_attr( (string) $license_state['serial'] ); ?>"></div>
								<div class="mlpb-field"><label>E-mail</label><input type="text" name="mlcgp_license[email]" value="<?php echo esc_attr( (string) $license_state['email'] ); ?>"></div>
								<div class="mlpb-field"><label>Status</label><select name="mlcgp_license[status]"><option value="inactive" <?php selected( 'inactive', $license_state['status'] ); ?>>Inativo</option><option value="active" <?php selected( 'active', $license_state['status'] ); ?>>Ativo</option><option value="expired" <?php selected( 'expired', $license_state['status'] ); ?>>Expirado</option><option value="blocked" <?php selected( 'blocked', $license_state['status'] ); ?>>Bloqueado</option></select></div>
								<div class="mlpb-field"><label>Plano</label><select name="mlcgp_license[plan]"><option value="free" <?php selected( 'free', $license_state['plan'] ); ?>>Free</option><option value="trial" <?php selected( 'trial', $license_state['plan'] ); ?>>Trial</option><option value="full" <?php selected( 'full', $license_state['plan'] ); ?>>Full</option><option value="lifetime" <?php selected( 'lifetime', $license_state['plan'] ); ?>>Lifetime</option></select></div>
							</div>
							<div class="mlpb-field"><label>Expira em</label><input type="text" name="mlcgp_license[expires_at]" value="<?php echo esc_attr( (string) $license_state['expires_at'] ); ?>" placeholder="YYYY-MM-DD"></div>
						</article>
						<article class="mlpb-card">
							<div class="mlpb-card-header"><div><h2>Status atual</h2><p class="mlpb-muted">Persistência real do estado comercial.</p></div></div>
							<ul class="mlpb-list">
								<li><strong>Status:</strong> <?php echo esc_html( $license_manager->get_status_label( (string) $license_state['status'] ) ); ?></li>
								<li><strong>Plano:</strong> <?php echo esc_html( $license_manager->get_plan_label( (string) $license_state['plan'] ) ); ?></li>
								<li><strong>Última checagem:</strong> <?php echo esc_html( (string) $license_state['last_check'] ); ?></li>
								<li><strong>Instância:</strong> <code><?php echo esc_html( (string) $license_state['instance_id'] ); ?></code></li>
							</ul>
							<div class="mlpb-note"><?php echo esc_html( (string) $license_state['message'] ); ?></div>
						</article>
						<article class="mlpb-card mlcgp-full-span">
							<div class="mlpb-card-header"><div><h2>Núcleo de licença</h2><p class="mlpb-muted">Configuração centralizada para comercialização, mantida apenas nesta aba.</p></div></div>
							<div class="mlpb-form-grid">
								<div class="mlpb-field"><label>Product ID</label><input type="text" name="mlcgp_settings[license_product_id]" value="<?php echo esc_attr( (string) ( $settings['license_product_id'] ?? '' ) ); ?>"></div>
								<div class="mlpb-field"><label>Endpoint</label><input type="url" name="mlcgp_settings[license_endpoint]" value="<?php echo esc_attr( (string) ( $settings['license_endpoint'] ?? '' ) ); ?>"></div>
								<div class="mlpb-field"><label>Nome do item</label><input type="text" name="mlcgp_settings[license_item_name]" value="<?php echo esc_attr( (string) ( $settings['license_item_name'] ?? '' ) ); ?>"></div>
								<div class="mlpb-field"><label>Seller</label><input type="text" name="mlcgp_settings[license_seller_name]" value="<?php echo esc_attr( (string) ( $settings['license_seller_name'] ?? '' ) ); ?>"></div>
							</div>
							<div class="mlpb-field"><label>Título comercial</label><input type="text" name="mlcgp_settings[license_cta_title]" value="<?php echo esc_attr( (string) ( $settings['license_cta_title'] ?? '' ) ); ?>"></div>
							<div class="mlpb-field"><label>Texto comercial</label><textarea name="mlcgp_settings[license_cta_text]"><?php echo esc_textarea( (string) ( $settings['license_cta_text'] ?? '' ) ); ?></textarea></div>
						</article>

					</div>
			</section>

		</div>
		<?php
	}

	private function render_profile_row( int $index, array $profile, bool $template, array $album_options, array $gallery_options ): void {
		$row_index = $template ? '{{index}}' : (string) $index;
		$id        = (string) ( $profile['id'] ?? '' );
		$label     = (string) ( $profile['label'] ?? '' );
		$source    = (string) ( $profile['source'] ?? 'all' );
		$album_id  = (int) ( $profile['album_id'] ?? 0 );
		$limit     = (int) ( $profile['limit'] ?? 6 );
		$ids_csv   = (string) ( $profile['gallery_ids'] ?? '' );
		$ids       = array_values( array_filter( array_map( 'absint', explode( ',', $ids_csv ) ) ) );
		?>
		<div class="mlcgp-profile-card" data-index="<?php echo esc_attr( $row_index ); ?>">
			<div class="mlcgp-profile-card__head">
				<strong><?php echo esc_html( '' !== $label ? $label : 'Novo carrossel' ); ?></strong>
				<button type="button" class="button-link-delete mlcgp-remove-profile">Remover</button>
			</div>
			<div class="mlpb-form-grid mlcgp-form-grid-3">
				<div class="mlpb-field"><label>ID do carrossel</label><input type="text" name="mlcgp_settings[profiles][<?php echo esc_attr( $row_index ); ?>][id]" value="<?php echo esc_attr( $id ); ?>" placeholder="home"></div>
				<div class="mlpb-field"><label>Rótulo interno</label><input type="text" name="mlcgp_settings[profiles][<?php echo esc_attr( $row_index ); ?>][label]" value="<?php echo esc_attr( $label ); ?>" placeholder="Home"></div>
				<div class="mlpb-field"><label>Quantidade</label><input type="number" name="mlcgp_settings[profiles][<?php echo esc_attr( $row_index ); ?>][limit]" value="<?php echo esc_attr( (string) $limit ); ?>" min="1" max="24"></div>
			</div>
			<div class="mlpb-field"><label>Origem</label><select class="mlcgp-profile-source" name="mlcgp_settings[profiles][<?php echo esc_attr( $row_index ); ?>][source]"><option value="all" <?php selected( 'all', $source ); ?>>Todas as galerias recentes</option><option value="album" <?php selected( 'album', $source ); ?>>Álbum específico</option><option value="galleries" <?php selected( 'galleries', $source ); ?>>Galerias específicas</option></select></div>
			<div class="mlpb-form-grid">
				<div class="mlpb-field mlcgp-source-album<?php echo 'album' === $source ? '' : ' is-hidden'; ?>"><label>Álbum</label><select name="mlcgp_settings[profiles][<?php echo esc_attr( $row_index ); ?>][album_id]"><option value="0">Selecione um álbum</option><?php foreach ( $album_options as $option_id => $option_label ) : ?><option value="<?php echo esc_attr( (string) $option_id ); ?>" <?php selected( $album_id, (int) $option_id ); ?>><?php echo esc_html( $option_label ); ?></option><?php endforeach; ?></select></div>
				<div class="mlpb-field mlcgp-source-galleries<?php echo 'galleries' === $source ? '' : ' is-hidden'; ?>"><label>Galerias</label><select class="mlcgp-multi-select" name="mlcgp_settings[profiles][<?php echo esc_attr( $row_index ); ?>][gallery_ids][]" multiple size="8"><?php foreach ( $gallery_options as $option_id => $option_label ) : ?><option value="<?php echo esc_attr( (string) $option_id ); ?>" <?php echo in_array( (int) $option_id, $ids, true ) ? 'selected' : ''; ?>><?php echo esc_html( $option_label ); ?></option><?php endforeach; ?></select></div>
			</div>
			<div class="mlcgp-profile-shortcode"><code>[ml_carousel_gallery id="<?php echo esc_html( '' !== $id ? $id : 'seu-id' ); ?>"]</code></div>
		</div>
		<?php
	}
}
