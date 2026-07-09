<?php
/**
 * Frontend carousel renderer.
 *
 * @package MLCarouselGalleryPro
 */

namespace MLCarouselGalleryPro\Frontend;

use MLCarouselGalleryPro\Admin\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Carousel {

	const TRANSIENT_KEY = 'mlcgp_galleries_cache_v192';

	/** @var Settings */
	private $settings;

	/** @var array<string,string> */
	private $cache_lock_tokens = [];

	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	public function hooks(): void {
		add_shortcode( 'ml_carousel_gallery', [ $this, 'render_shortcode' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'register_assets' ] );

		// WordPress post lifecycle — covers any MLGP galleries stored as posts.
		add_action( 'save_post', [ $this, 'invalidate_cache' ] );
		add_action( 'transition_post_status', [ $this, 'invalidate_cache' ] );

		// ML Gallery Pro gallery lifecycle hooks (all known variants).
		add_action( 'mlgp_gallery_saved',          [ $this, 'invalidate_cache' ] );
		add_action( 'mlgp_gallery_created',         [ $this, 'invalidate_cache' ] );
		add_action( 'mlgp_gallery_deleted',         [ $this, 'invalidate_cache' ] );
		add_action( 'mlgp_gallery_updated',         [ $this, 'invalidate_cache' ] );
		add_action( 'mlgp_gallery_status_changed',  [ $this, 'invalidate_cache' ] );
		add_action( 'mlgp_gallery_published',       [ $this, 'invalidate_cache' ] );
		add_action( 'mlgp_after_save_gallery',      [ $this, 'invalidate_cache' ] );
		add_action( 'mlgp_after_create_gallery',    [ $this, 'invalidate_cache' ] );
		add_action( 'mlgp_after_delete_gallery',    [ $this, 'invalidate_cache' ] );

		// Cover-specific hooks.
		add_action( 'mlgp_gallery_cover_changed',   [ $this, 'invalidate_cache' ] );
		add_action( 'mlgp_cover_saved',             [ $this, 'invalidate_cache' ] );

		// Attachment meta changes.
		add_action( 'added_post_meta',   [ $this, 'invalidate_cache_on_attachment_change' ], 10, 4 );
		add_action( 'updated_post_meta', [ $this, 'invalidate_cache_on_attachment_change' ], 10, 4 );
		add_action( 'deleted_post_meta', [ $this, 'invalidate_cache_on_attachment_change' ], 10, 4 );
		add_action( 'edit_attachment',   [ $this, 'invalidate_cache' ] );
		add_action( 'delete_attachment', [ $this, 'invalidate_cache' ] );

		// Plugin settings saved — profile changes can affect which galleries appear.
		add_action( 'admin_post_mlcgp_save_settings', [ $this, 'invalidate_cache' ], 1 );
	}

	/**
	 * Shortcode [ml_carousel_gallery] and [ml_carousel_gallery id="home"].
	 *
	 * @param array<string, mixed> $atts
	 */
	public function render_shortcode( $atts ): string {
		$atts        = shortcode_atts( [ 'id' => '' ], (array) $atts, 'ml_carousel_gallery' );
		$s           = $this->settings->get();
		$limit       = max( 1, (int) $s['limit'] );
		$source      = 'all';
		$album_id    = 0;
		$gallery_ids = [];

		if ( ! empty( $atts['id'] ) && method_exists( $this->settings, 'get_profile' ) ) {
			$profile = $this->settings->get_profile( (string) $atts['id'] );

			if ( is_array( $profile ) && ! empty( $profile ) ) {
				$limit       = max( 1, (int) ( $profile['limit'] ?? $limit ) );
				$source      = (string) ( $profile['source'] ?? 'all' );
				$album_id    = absint( $profile['album_id'] ?? 0 );
				$gallery_ids = $this->parse_gallery_ids( (string) ( $profile['gallery_ids'] ?? '' ) );
			}
		}

		$galleries = $this->get_galleries(
			$limit,
			[
				'source'      => $source,
				'album_id'    => $album_id,
				'gallery_ids' => $gallery_ids,
			]
		);

		if ( empty( $galleries ) ) {
			return '<p class="mlcgp-empty">' . esc_html__( 'Nenhuma galeria publicada encontrada.', 'ml-carousel-gallery-pro' ) . '</p>';
		}

		$this->enqueue_assets();

		$text_pos       = in_array( $s['text_position'], [ 'top', 'center', 'bottom' ], true ) ? $s['text_position'] : 'bottom';
		$text_align     = in_array( $s['text_align'], [ 'left', 'center', 'right' ], true ) ? $s['text_align'] : 'center';
		$target         = ! empty( $s['new_tab'] ) ? '_blank' : '_self';
		$autoplay       = ! empty( $s['autoplay'] ) ? 'true' : 'false';
		$speed          = (int) $s['speed'];
		$center         = ! empty( $s['center_mode'] ) ? 'true' : 'false';
		$gap            = (int) $s['card_gap'];
		$card_w         = (int) $s['card_width'];
		$card_h         = (int) $s['card_height'];
		$overlay        = (int) $s['overlay_opacity'];
		$visibleDesktop = max( 1, (float) ( $s['visible_desktop'] ?? 3.5 ) );
		$visibleTablet  = max( 1, (float) ( $s['visible_tablet'] ?? 2 ) );
		$visibleMobile  = max( 1, (float) ( $s['visible_mobile'] ?? 1 ) );

		$css_vars = sprintf(
			'--mlcgp-gap:%dpx;--mlcgp-overlay-opacity:%.2f;--mlcgp-visible-desktop:%s;--mlcgp-visible-tablet:%s;--mlcgp-visible-mobile:%s;',
			$gap,
			$overlay / 100,
			rtrim( rtrim( number_format( $visibleDesktop, 1, '.', '' ), '0' ), '.' ),
			rtrim( rtrim( number_format( $visibleTablet, 1, '.', '' ), '0' ), '.' ),
			rtrim( rtrim( number_format( $visibleMobile, 1, '.', '' ), '0' ), '.' )
		);

		if ( $card_w > 0 ) {
			$css_vars .= '--mlcgp-card-width:' . $card_w . 'px;';
		}
		if ( $card_h > 0 ) {
			$css_vars .= '--mlcgp-card-height:' . $card_h . 'px;';
		}

		$uid = 'mlcgp-' . wp_generate_uuid4();

		ob_start();
		?>
		<div
			class="mlcgp-wrapper<?php echo ! empty( $s['center_mode'] ) ? ' mlcgp-center-mode' : ''; ?>"
			id="<?php echo esc_attr( $uid ); ?>"
			style="<?php echo esc_attr( $css_vars ); ?>"
			data-autoplay="<?php echo esc_attr( $autoplay ); ?>"
			data-speed="<?php echo esc_attr( (string) $speed ); ?>"
			data-center="<?php echo esc_attr( $center ); ?>"
			data-visible-desktop="<?php echo esc_attr( (string) $visibleDesktop ); ?>"
			data-visible-tablet="<?php echo esc_attr( (string) $visibleTablet ); ?>"
			data-visible-mobile="<?php echo esc_attr( (string) $visibleMobile ); ?>"
			role="region"
			aria-label="<?php esc_attr_e( 'Carousel de galerias', 'ml-carousel-gallery-pro' ); ?>"
		>
			<div class="mlcgp-track-outer">
				<div class="mlcgp-track">
					<?php foreach ( $galleries as $gallery ) : ?>
						<?php
						$gallery_url   = $this->get_gallery_url( $gallery );
						$cover_url     = $this->get_cover_url( $gallery );
						$display_title = $this->process_title( (string) $gallery['title'] );
						$date_display  = ! empty( $s['show_date'] ) ? $this->get_display_date( (string) $gallery['title'] ) : '';
						?>
						<div class="mlcgp-slide">
							<a
								href="<?php echo esc_url( $gallery_url ?: '#' ); ?>"
								class="mlcgp-card mlcgp-text-<?php echo esc_attr( $text_pos ); ?> mlcgp-align-<?php echo esc_attr( $text_align ); ?>"
								target="<?php echo esc_attr( $target ); ?>"
								<?php echo '_blank' === $target ? 'rel="noopener noreferrer"' : ''; ?>
								aria-label="<?php echo esc_attr( $display_title ); ?>"
							>
								<?php if ( $cover_url ) : ?>
									<img class="mlcgp-card__img" src="<?php echo esc_url( $cover_url ); ?>" alt="<?php echo esc_attr( $display_title ); ?>" loading="lazy">
								<?php else : ?>
									<div class="mlcgp-card__placeholder"></div>
								<?php endif; ?>

								<div class="mlcgp-card__overlay"></div>

								<div class="mlcgp-card__text">
									<span class="mlcgp-card__title"><?php
										$title_lines = array_filter( array_map( 'trim', explode( ' - ', strtoupper( $display_title ) ) ) );
										foreach ( $title_lines as $line ) {
											echo '<span class="mlcgp-card__title-line">' . esc_html( $line ) . '</span>';
										}
									?></span>
									<?php if ( '' !== $date_display ) : ?>
										<span class="mlcgp-card__date"><?php echo esc_html( $date_display ); ?></span>
									<?php endif; ?>
								</div>
							</a>
						</div>
					<?php endforeach; ?>
				</div>
			</div>

			<button class="mlcgp-nav mlcgp-nav--prev" aria-label="<?php esc_attr_e( 'Anterior', 'ml-carousel-gallery-pro' ); ?>">
				<svg viewBox="0 0 24 24" focusable="false" aria-hidden="true"><path d="M14.5 5.5L8 12l6.5 6.5" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.4"/></svg>
			</button>

			<button class="mlcgp-nav mlcgp-nav--next" aria-label="<?php esc_attr_e( 'Próximo', 'ml-carousel-gallery-pro' ); ?>">
				<svg viewBox="0 0 24 24" focusable="false" aria-hidden="true"><path d="M9.5 5.5L16 12l-6.5 6.5" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.4"/></svg>
			</button>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * @param array<string,mixed> $args
	 * @return array<int, array<string,mixed>>
	 */
	private function get_galleries( int $limit, array $args = [] ): array {
		$limit       = max( 1, $limit );
		$source      = (string) ( $args['source'] ?? 'all' );
		$album_id    = absint( $args['album_id'] ?? 0 );
		$gallery_ids = isset( $args['gallery_ids'] ) && is_array( $args['gallery_ids'] ) ? array_values( array_filter( array_map( 'absint', $args['gallery_ids'] ) ) ) : [];

		$cache_key = $this->get_galleries_cache_key(
			$limit,
			[
				'source'      => $source,
				'album_id'    => $album_id,
				'gallery_ids' => $gallery_ids,
			]
		);

		$cached = $this->read_transient_without_public_delete( $cache_key );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		$lock_key = $cache_key . '_lock';

		if ( ! $this->acquire_cache_rebuild_lock( $lock_key ) ) {
			$stale = $this->read_transient_without_public_delete( $cache_key, true );

			return is_array( $stale ) ? $stale : [];
		}

		try {
			$rows = $this->query_galleries_from_mlgp( $limit, $source, $album_id, $gallery_ids );

			if ( ! is_array( $rows ) ) {
				$rows = [];
			}

			set_transient( $cache_key, $rows, 5 * MINUTE_IN_SECONDS );

			return $rows;
		} catch ( \Throwable $e ) {
			return [];
		} finally {
			$this->release_cache_rebuild_lock( $lock_key );
		}
	}

	private function get_galleries_cache_key( int $limit, array $args ): string {
		$payload = [
			'limit'       => max( 1, $limit ),
			'source'      => (string) ( $args['source'] ?? 'all' ),
			'album_id'    => absint( $args['album_id'] ?? 0 ),
			'gallery_ids' => isset( $args['gallery_ids'] ) && is_array( $args['gallery_ids'] ) ? array_values( array_map( 'absint', $args['gallery_ids'] ) ) : [],
		];

		sort( $payload['gallery_ids'] );

		return self::TRANSIENT_KEY . '_' . md5( wp_json_encode( $payload ) ?: serialize( $payload ) );
	}

	/**
	 * Reads a transient without calling get_transient(), because WordPress deletes expired
	 * transient rows inside get_transient(). Public shortcode rendering must not execute
	 * delete_option()/DELETE cleanup work. Explicit invalidation remains isolated in
	 * invalidate_cache() and admin/ML Gallery Pro mutation hooks.
	 *
	 * @return mixed
	 */
	private function read_transient_without_public_delete( string $cache_key, bool $allow_stale = false ) {
		$timeout = get_option( '_transient_timeout_' . $cache_key );

		if ( ! $allow_stale && false !== $timeout && (int) $timeout > 0 && (int) $timeout < time() ) {
			return false;
		}

		return get_option( '_transient_' . $cache_key, false );
	}

	private function acquire_cache_rebuild_lock( string $lock_key ): bool {
		$option_name  = '_transient_' . $lock_key;
		$timeout_name = '_transient_timeout_' . $lock_key;
		$now          = time();
		$expires      = $now + 20;
		$token        = function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : md5( $lock_key . microtime( true ) . wp_rand() );

		if ( add_option( $option_name, $token, '', 'no' ) ) {
			update_option( $timeout_name, $expires, false );
			$this->cache_lock_tokens[ $lock_key ] = $token;

			return true;
		}

		$timeout = (int) get_option( $timeout_name, 0 );

		if ( $timeout > 0 && $timeout < $now ) {
			update_option( $option_name, $token, false );
			update_option( $timeout_name, $expires, false );

			if ( get_option( $option_name ) === $token ) {
				$this->cache_lock_tokens[ $lock_key ] = $token;

				return true;
			}
		}

		return false;
	}

	private function release_cache_rebuild_lock( string $lock_key ): void {
		$option_name = '_transient_' . $lock_key;
		$token       = $this->cache_lock_tokens[ $lock_key ] ?? '';

		if ( '' !== $token && get_option( $option_name ) === $token ) {
			update_option( $option_name, '', false );
			update_option( '_transient_timeout_' . $lock_key, 1, false );
		}

		unset( $this->cache_lock_tokens[ $lock_key ] );
	}

	/**
	 * @param array<int,int> $gallery_ids
	 * @return array<int, array<string,mixed>>
	 */
	private function query_galleries_from_mlgp( int $limit, string $source, int $album_id, array $gallery_ids ): array {
		global $wpdb;

		$table        = $wpdb->prefix . 'mlgp_galleries';
		$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );

		if ( $table_exists !== $table ) {
			return [];
		}

		$status_sql = "g.status IN ('publish','published')";
		$order_sql  = 'COALESCE(NULLIF(g.created_at, ""), "1970-01-01") DESC, g.id DESC';
		$rows       = [];

		if ( 'album' === $source && $album_id > 0 ) {
			$rows = $this->get_album_gallery_rows_in_mlgp_order( $album_id, $limit );
		} elseif ( 'galleries' === $source && ! empty( $gallery_ids ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $gallery_ids ), '%d' ) );
			$params       = array_merge( $gallery_ids, [ $limit ] );
			$query        = $wpdb->prepare(
				"SELECT g.id, g.title, g.slug, g.cover_attachment_id, g.cover_item_id, g.created_at
				FROM {$table} g
				WHERE {$status_sql} AND g.id IN ({$placeholders})
				ORDER BY {$order_sql}
				LIMIT %d",
				$params
			);
			$rows = $wpdb->get_results( $query, ARRAY_A );
		} else {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT g.id, g.title, g.slug, g.cover_attachment_id, g.cover_item_id, g.created_at
					FROM {$table} g
					WHERE {$status_sql}
					ORDER BY {$order_sql}
					LIMIT %d",
					$limit
				),
				ARRAY_A
			);

			$album_ordered_rows = $this->maybe_get_album_ordered_rows_from_recent_rows( is_array( $rows ) ? $rows : [], $limit );
			if ( ! empty( $album_ordered_rows ) ) {
				$rows = $album_ordered_rows;
			}
		}

		return is_array( $rows ) ? $rows : [];
	}

	/**
	 * When the automatic carousel is fed by recent galleries, mirror the ML Gallery Pro
	 * album structure for the album that contains the newest gallery. ML Gallery Pro
	 * renders album items with wp_mlgp_album_items ORDER BY sort_order ASC, id ASC;
	 * the carousel must not override that manual order with created_at/id ordering.
	 *
	 * @param array<int,array<string,mixed>> $recent_rows Recent gallery rows.
	 * @return array<int,array<string,mixed>>
	 */
	private function maybe_get_album_ordered_rows_from_recent_rows( array $recent_rows, int $limit ): array {
		if ( empty( $recent_rows ) ) {
			return [];
		}

		$newest_gallery_id = 0;

		foreach ( $recent_rows as $row ) {
			$newest_gallery_id = is_array( $row ) ? absint( $row['id'] ?? 0 ) : 0;

			if ( $newest_gallery_id > 0 ) {
				break;
			}
		}

		if ( $newest_gallery_id <= 0 ) {
			return [];
		}

		global $wpdb;

		$album_items_table = $wpdb->prefix . 'mlgp_album_items';

		$album_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT album_id
				FROM {$album_items_table}
				WHERE item_type = %s AND item_id = %d
				ORDER BY id DESC
				LIMIT 1",
				'gallery',
				$newest_gallery_id
			)
		);

		if ( $album_id <= 0 ) {
			return [];
		}

		return $this->get_album_gallery_rows_in_mlgp_order( $album_id, $limit );
	}

	/**
	 * Returns gallery rows for one ML Gallery Pro album using the same manual order
	 * saved by ML Gallery Pro in wp_mlgp_album_items.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function get_album_gallery_rows_in_mlgp_order( int $album_id, int $limit ): array {
		global $wpdb;

		$album_id = absint( $album_id );
		$limit    = max( 1, absint( $limit ) );

		if ( $album_id <= 0 ) {
			return [];
		}

		$album_items_table = $wpdb->prefix . 'mlgp_album_items';
		$galleries_table   = $wpdb->prefix . 'mlgp_galleries';

		$album_items_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $album_items_table ) );
		$galleries_exists   = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $galleries_table ) );

		if ( $album_items_exists !== $album_items_table || $galleries_exists !== $galleries_table ) {
			return [];
		}

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT g.id, g.title, g.slug, g.cover_attachment_id, g.cover_item_id, g.created_at
				FROM {$album_items_table} ai
				INNER JOIN {$galleries_table} g ON g.id = ai.item_id
				WHERE ai.album_id = %d
				AND ai.item_type = %s
				AND g.status IN ('publish','published')
				ORDER BY ai.sort_order ASC, ai.id ASC
				LIMIT %d",
				$album_id,
				'gallery',
				$limit
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : [];
	}

	/**
	 * Builds the native ML Gallery Pro album-view URL.
	 *
	 * Pattern:
	 * /{album-slug}/?mlgp_album_view_{album_id}=gallery-{gallery_id}
	 *
	 * @param array<string, mixed> $gallery Gallery row.
	 */
	private function get_gallery_url( array $gallery ): string {
		$gallery_id = (int) ( $gallery['id'] ?? 0 );

		if ( $gallery_id <= 0 ) {
			return '';
		}

		$cache_key = 'mlcgp_v190_gallery_url_' . $gallery_id;
		$cached    = $this->read_transient_without_public_delete( $cache_key );

		if ( false !== $cached && is_string( $cached ) ) {
			return $cached;
		}

		$album_ids = $this->get_related_album_ids_for_gallery( $gallery_id );

		if ( empty( $album_ids ) ) {
			return '';
		}

		$resolved_url = $this->resolve_gallery_url_via_runtime_render( $gallery_id, $gallery, $album_ids );

		if ( '' !== $resolved_url ) {
			set_transient( $cache_key, $resolved_url, 15 * MINUTE_IN_SECONDS );
			return $resolved_url;
		}

		return '';
	}


	/**
	 * Try to reuse the exact public gallery URL rendered by ML Gallery Pro on the album page.
	 *
	 * This avoids inventing URLs and keeps the carousel aligned with the real front-end motor.
	 *
	 * @param int                 $gallery_id     Gallery ID.
	 * @param array<string,mixed> $gallery        Gallery row.
	 * @param int                 $root_album_id  Root album ID.
	 */

	/**
	 * Resolve the gallery URL by exercising the same front-end runtime behavior ML Gallery Pro uses.
	 *
	 * For each related album ID, we temporarily render candidate public pages with the exact
	 * query var `mlgp_album_view_{album_id}=gallery-{gallery_id}` and accept the first page whose
	 * rendered HTML clearly contains this gallery.
	 *
	 * @param int                 $gallery_id Gallery ID.
	 * @param array<string,mixed> $gallery Gallery row.
	 * @param array<int,int>      $album_ids Related album IDs (direct and ancestor/root chain).
	 */
	private function resolve_gallery_url_via_runtime_render( int $gallery_id, array $gallery, array $album_ids ): string {
		$gallery_title = isset( $gallery['title'] ) ? trim( wp_strip_all_tags( (string) $gallery['title'] ) ) : '';
		$gallery_slug  = isset( $gallery['slug'] ) ? sanitize_title( (string) $gallery['slug'] ) : '';
		$cover_url     = $this->get_cover_url( $gallery );

		foreach ( $album_ids as $album_id ) {
			$album_id = absint( $album_id );
			if ( $album_id <= 0 ) {
				continue;
			}

			$candidates = $this->get_runtime_candidate_posts_for_album( $album_id, $gallery_title, $gallery_slug );

			// Bug #4 fix: cap full content renders to 5 candidates per album to prevent timeout.
			$candidates = array_slice( $candidates, 0, 5 );

			foreach ( $candidates as $post ) {
				if ( ! $post instanceof \WP_Post ) {
					continue;
				}

				if ( $this->page_renders_gallery_for_album( $post, $album_id, $gallery_id, $gallery_title, $gallery_slug, $cover_url ) ) {
					$permalink = get_permalink( $post );
					if ( is_string( $permalink ) && '' !== $permalink ) {
						return add_query_arg( $this->build_album_view_key( $album_id ), 'gallery-' . $gallery_id, $permalink );
					}
				}
			}
		}

		return '';
	}

	/**
	 * @return array<int,\WP_Post>
	 */
	private function get_runtime_candidate_posts_for_album( int $album_id, string $gallery_title, string $gallery_slug ): array {
		$album_meta = $this->get_album_meta_by_id( $album_id );
		$album_post = $this->find_album_public_post( $album_id, $album_meta );
		$posts      = [];

		if ( $album_post instanceof \WP_Post ) {
			$posts[] = $album_post;
		}

		$needles = array_filter(
			array_unique(
				[
					'mlgp_album_view_' . $album_id,
					'album_id="' . $album_id . '"',
					"album_id='" . $album_id . "'",
					'album="' . $album_id . '"',
					"album='" . $album_id . "'",
					(string) ( $album_meta['title'] ?? '' ),
					(string) ( $album_meta['slug'] ?? '' ),
					$gallery_title,
					$gallery_slug,
					'mlgp',
					'nggallery',
				]
			)
		);

		foreach ( $this->get_public_post_candidates( $needles ) as $post ) {
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}
			$posts[ $post->ID ] = $post;
		}

		return array_values( $posts );
	}

	private function page_renders_gallery_for_album( \WP_Post $post, int $album_id, int $gallery_id, string $gallery_title, string $gallery_slug, string $cover_url ): bool {
		$query_key   = $this->build_album_view_key( $album_id );
		$query_value = 'gallery-' . $gallery_id;

		$previous_get     = $_GET[ $query_key ] ?? null;
		$previous_request = $_REQUEST[ $query_key ] ?? null;
		$had_get          = array_key_exists( $query_key, $_GET );
		$had_request      = array_key_exists( $query_key, $_REQUEST );

		$_GET[ $query_key ]     = $query_value;
		$_REQUEST[ $query_key ] = $query_value;

		// Bug #3 fix: use try/finally so superglobals are always restored even if an exception is thrown.
		try {
			$html = $this->render_public_post_content( $post );
		} finally {
			if ( $had_get ) {
				$_GET[ $query_key ] = $previous_get;
			} else {
				unset( $_GET[ $query_key ] );
			}

			if ( $had_request ) {
				$_REQUEST[ $query_key ] = $previous_request;
			} else {
				unset( $_REQUEST[ $query_key ] );
			}
		}

		if ( '' === $html ) {
			return false;
		}

		$checks = array_filter(
			[
				'gallery-' . $gallery_id,
				$gallery_slug,
				$this->normalize_title_for_match( $gallery_title ),
				$cover_url,
			]
		);

		$html_plain      = wp_strip_all_tags( $html );
		$normalized_html = $this->normalize_title_for_match( $html_plain );
		$html_lower      = strtolower( $html );

		foreach ( $checks as $check ) {
			$check = (string) $check;
			if ( '' === $check ) {
				continue;
			}

			if ( 0 === strpos( $check, 'http' ) ) {
				if ( false !== strpos( $html, $check ) ) {
					return true;
				}
				continue;
			}

			if ( false !== strpos( $html_lower, strtolower( $check ) ) || false !== strpos( $normalized_html, strtolower( $check ) ) ) {
				return true;
			}
		}

		return false;
	}

	private function resolve_gallery_url_from_site_render( int $gallery_id, array $gallery, int $root_album_id ): string {
		$post = $this->find_album_public_post( $root_album_id, $this->get_album_meta_by_id( $root_album_id ) );

		if ( ! $post instanceof \WP_Post ) {
			return '';
		}

		$html = $this->render_public_post_content( $post );

		if ( '' === $html ) {
			return '';
		}

		$gallery_token = 'gallery-' . $gallery_id;
		$gallery_title = isset( $gallery['title'] ) ? trim( wp_strip_all_tags( (string) $gallery['title'] ) ) : '';
		$gallery_slug  = isset( $gallery['slug'] ) ? sanitize_title( (string) $gallery['slug'] ) : '';

		if ( preg_match_all( '/href\s*=\s*(["\'])(.*?)\1/i', $html, $matches ) && ! empty( $matches[2] ) ) {
			$best_match = '';

			foreach ( $matches[2] as $raw_url ) {
				$url = $this->normalize_public_url( html_entity_decode( (string) $raw_url, ENT_QUOTES, 'UTF-8' ) );

				if ( '' === $url ) {
					continue;
				}

				if ( false !== strpos( $url, $gallery_token ) ) {
					return $url;
				}

				if ( '' !== $gallery_slug && false !== strpos( strtolower( $url ), strtolower( $gallery_slug ) ) && '' === $best_match ) {
					$best_match = $url;
				}
			}

			if ( '' !== $best_match ) {
				return $best_match;
			}
		}

		if ( '' !== $gallery_title ) {
			$normalized_title = $this->normalize_title_for_match( $gallery_title );

			if ( '' !== $normalized_title && preg_match_all( '/<a\b[^>]*href\s*=\s*(["\'])(.*?)\1[^>]*>(.*?)<\/a>/is', $html, $anchor_matches, PREG_SET_ORDER ) ) {
				foreach ( $anchor_matches as $anchor ) {
					$url  = $this->normalize_public_url( html_entity_decode( (string) $anchor[2], ENT_QUOTES, 'UTF-8' ) );
					$text = $this->normalize_title_for_match( wp_strip_all_tags( (string) $anchor[3] ) );

					if ( '' !== $url && '' !== $text && false !== strpos( $text, $normalized_title ) ) {
						return $url;
					}
				}
			}
		}

		return '';
	}

	/**
	 * @param array<string,string> $album_meta
	 */
	private function find_album_public_post( int $album_id, array $album_meta ): ?\WP_Post {
		foreach ( [ 'page_id', 'post_id', 'public_page_id' ] as $key ) {
			if ( ! empty( $album_meta[ $key ] ) ) {
				$post = get_post( (int) $album_meta[ $key ] );
				if ( $post instanceof \WP_Post && 'publish' === $post->post_status ) {
					return $post;
				}
			}
		}

		$album_title = isset( $album_meta['title'] ) ? trim( wp_strip_all_tags( (string) $album_meta['title'] ) ) : '';
		$album_slug  = isset( $album_meta['slug'] ) ? sanitize_title( (string) $album_meta['slug'] ) : '';
		$needles     = array_filter(
			array_unique(
				[
					'mlgp_album_view_' . $album_id,
					'album_id="' . $album_id . '"',
					"album_id='" . $album_id . "'",
					'album="' . $album_id . '"',
					"album='" . $album_id . "'",
					$album_title,
					$album_slug,
				]
			)
		);

		if ( empty( $needles ) ) {
			return null;
		}

		$query = new \WP_Query(
			[
				'post_type'              => 'any',
				'post_status'            => 'publish',
				'posts_per_page'         => 50,
				'ignore_sticky_posts'    => true,
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				's'                      => $album_title ?: $album_slug,
			]
		);

		if ( empty( $query->posts ) || ! is_array( $query->posts ) ) {
			return null;
		}

		$best_match = null;

		foreach ( $query->posts as $post ) {
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}

			$haystack = strtolower(
				implode(
					' ',
					array_filter(
						[
							(string) $post->post_title,
							(string) $post->post_name,
							(string) $post->post_content,
						]
					)
				)
			);

			$matched = false;
			foreach ( $needles as $needle ) {
				$needle = strtolower( trim( (string) $needle ) );
				if ( '' !== $needle && false !== strpos( $haystack, $needle ) ) {
					$matched = true;
					break;
				}
			}

			if ( ! $matched ) {
				continue;
			}

			if ( false !== strpos( strtolower( (string) $post->post_title ), strtolower( $album_title ) ) || false !== strpos( strtolower( (string) $post->post_name ), strtolower( $album_slug ) ) ) {
				return $post;
			}

			if ( null === $best_match ) {
				$best_match = $post;
			}
		}

		return $best_match;
	}

	private function render_public_post_content( \WP_Post $post ): string {
		$post_content = (string) $post->post_content;

		if ( '' === trim( $post_content ) ) {
			return '';
		}

		$previous_post = $GLOBALS['post'] ?? null;
		$GLOBALS['post'] = $post;
		setup_postdata( $post );

		$html = (string) apply_filters( 'the_content', $post_content );

		if ( $previous_post instanceof \WP_Post ) {
			$GLOBALS['post'] = $previous_post;
			setup_postdata( $previous_post );
		} else {
			wp_reset_postdata();
			unset( $GLOBALS['post'] );
		}

		return $html;
	}


	/**
	 * Search the rendered public content of published posts/pages and reuse the exact
	 * gallery URL already emitted by the real ML Gallery Pro front-end.
	 *
	 * This is intentionally dynamic so a deleted/recreated album is picked up without
	 * manual per-site adjustments.
	 *
	 * @param int                 $gallery_id    Gallery ID.
	 * @param array<string,mixed> $gallery       Gallery row.
	 * @param int                 $root_album_id Root album ID.
	 */
	private function find_rendered_gallery_url_across_public_posts( int $gallery_id, array $gallery, int $root_album_id ): string {
		$gallery_token = 'gallery-' . $gallery_id;
		$gallery_title = isset( $gallery['title'] ) ? trim( wp_strip_all_tags( (string) $gallery['title'] ) ) : '';
		$gallery_slug  = isset( $gallery['slug'] ) ? sanitize_title( (string) $gallery['slug'] ) : '';
		$needles       = array_filter( array_unique( [ $gallery_token, $gallery_title, $gallery_slug, 'mlgp_album_view_' . $root_album_id ] ) );

		$candidates = $this->get_public_post_candidates( $needles );

		foreach ( $candidates as $post ) {
			$html = $this->render_public_post_content( $post );

			if ( '' === $html ) {
				continue;
			}

			if ( preg_match_all( '/href\s*=\s*(["\'])(.*?)\1/i', $html, $matches ) && ! empty( $matches[2] ) ) {
				$best_match = '';

				foreach ( $matches[2] as $raw_url ) {
					$url = $this->normalize_public_url( html_entity_decode( (string) $raw_url, ENT_QUOTES, 'UTF-8' ) );

					if ( '' === $url ) {
						continue;
					}

					if ( false !== strpos( $url, $gallery_token ) ) {
						return $url;
					}

					if ( '' !== $gallery_slug && false !== strpos( strtolower( $url ), strtolower( $gallery_slug ) ) && '' === $best_match ) {
						$best_match = $url;
					}
				}

				if ( '' !== $best_match ) {
					return $best_match;
				}
			}
		}

		return '';
	}

	/**
	 * @param array<int,string> $needles
	 * @return array<int,\WP_Post>
	 */
	private function get_public_post_candidates( array $needles ): array {
		$post_types = get_post_types( [ 'public' => true ], 'names' );

		$query = new \WP_Query(
			[
				'post_type'              => array_values( $post_types ),
				'post_status'            => 'publish',
				'posts_per_page'         => 200,
				'ignore_sticky_posts'    => true,
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'orderby'                => 'modified',
				'order'                  => 'DESC',
			]
		);

		if ( empty( $query->posts ) || ! is_array( $query->posts ) ) {
			return [];
		}

		$scored = [];

		foreach ( $query->posts as $post ) {
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}

			$haystack = strtolower( implode( ' ', array_filter( [ (string) $post->post_title, (string) $post->post_name, (string) $post->post_content ] ) ) );
			$score = 0;

			foreach ( $needles as $needle ) {
				$needle = strtolower( trim( (string) $needle ) );
				if ( '' !== $needle && false !== strpos( $haystack, $needle ) ) {
					$score++;
				}
			}

			if ( false !== strpos( $haystack, 'mlgp' ) || false !== strpos( $haystack, 'nggallery' ) ) {
				$score += 2;
			}

			if ( $score > 0 ) {
				$scored[] = [ 'score' => $score, 'post' => $post ];
			}
		}

		usort(
			$scored,
			static function ( array $a, array $b ): int {
				return $b['score'] <=> $a['score'];
			}
		);

		return array_map( static function ( array $row ) { return $row['post']; }, array_slice( $scored, 0, 25 ) );
	}

	private function normalize_public_url( string $url ): string {
		$url = trim( $url );

		if ( '' === $url || '#' === $url ) {
			return '';
		}

		if ( 0 === strpos( $url, '//' ) ) {
			return is_ssl() ? 'https:' . $url : 'http:' . $url;
		}

		if ( 0 === strpos( $url, '/' ) ) {
			return home_url( $url );
		}

		if ( ! preg_match( '#^https?://#i', $url ) ) {
			return home_url( '/' . ltrim( $url, '/' ) );
		}

		return $url;
	}

	private function normalize_title_for_match( string $value ): string {
		$value = remove_accents( wp_strip_all_tags( $value ) );
		$value = strtolower( preg_replace( '/\s+/', ' ', $value ) );

		return trim( $value );
	}
	/**
	 * Resolves the real public base URL for a root album.
	 *
	 * Never assume the internal album slug is the public permalink.
	 * First try to locate a published page/post that references this album,
	 * then fall back to the internal slug only if nothing public is found.
	 */
	private function get_album_base_url_by_id( int $album_id ): string {
		global $wpdb;

		$album_id = absint( $album_id );

		if ( $album_id <= 0 ) {
			return '';
		}

		$cache_key = 'mlcgp_v190_album_base_' . $album_id;
		$cached    = $this->read_transient_without_public_delete( $cache_key );

		if ( false !== $cached && is_string( $cached ) ) {
			return $cached;
		}

		$album_meta = $this->get_album_meta_by_id( $album_id );

		if ( ! empty( $album_meta['public_url'] ) ) {
			set_transient( $cache_key, (string) $album_meta['public_url'], 15 * MINUTE_IN_SECONDS );
			return (string) $album_meta['public_url'];
		}

		$resolved = $this->resolve_album_permalink_from_content( $album_id, $album_meta );

		if ( '' !== $resolved ) {
			set_transient( $cache_key, $resolved, 15 * MINUTE_IN_SECONDS );
			return $resolved;
		}

		$slug = isset( $album_meta['slug'] ) ? trim( (string) $album_meta['slug'] ) : '';

		if ( '' === $slug ) {
			return '';
		}

		$url = home_url( '/' . ltrim( $slug, '/' ) . '/' );
		set_transient( $cache_key, $url, 15 * MINUTE_IN_SECONDS );

		return $url;
	}

	/**
	 * @return array<string,string>
	 */
	private function get_album_meta_by_id( int $album_id ): array {
		global $wpdb;

		$albums_table = $wpdb->prefix . 'mlgp_albums';
		$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $albums_table ) );

		if ( $table_exists !== $albums_table ) {
			return [];
		}

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$albums_table} WHERE id = %d LIMIT 1",
				$album_id
			),
			ARRAY_A
		);

		if ( ! is_array( $row ) || empty( $row ) ) {
			return [];
		}

		$meta = [
			'slug'       => isset( $row['slug'] ) ? (string) $row['slug'] : '',
			'title'      => isset( $row['title'] ) ? (string) $row['title'] : '',
			'public_url' => '',
		];

		foreach ( [ 'public_url', 'url', 'permalink' ] as $key ) {
			if ( ! empty( $row[ $key ] ) && is_string( $row[ $key ] ) ) {
				$meta['public_url'] = (string) $row[ $key ];
				break;
			}
		}

		foreach ( [ 'page_id', 'post_id', 'public_page_id' ] as $key ) {
			if ( ! empty( $row[ $key ] ) ) {
				$permalink = get_permalink( (int) $row[ $key ] );
				if ( is_string( $permalink ) && '' !== $permalink ) {
					$meta['public_url'] = $permalink;
					break;
				}
			}
		}

		return $meta;
	}

	/**
	 * @param array<string,string> $album_meta
	 */
	private function resolve_album_permalink_from_content( int $album_id, array $album_meta ): string {
		$post = $this->find_album_public_post( $album_id, $album_meta );

		if ( ! $post instanceof \WP_Post ) {
			return '';
		}

		$permalink = get_permalink( $post );

		return is_string( $permalink ) ? $permalink : '';
	}


	/**
	 * @return array<int,int>
	 */
	private function get_related_album_ids_for_gallery( int $gallery_id ): array {
		global $wpdb;

		$album_items_table = $wpdb->prefix . 'mlgp_album_items';
		$parents           = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT album_id FROM {$album_items_table} WHERE item_type = %s AND item_id = %d ORDER BY sort_order ASC, id ASC",
				'gallery',
				$gallery_id
			)
		);

		if ( ! is_array( $parents ) || empty( $parents ) ) {
			return [];
		}

		$all = [];

		foreach ( $parents as $album_id ) {
			$album_id = absint( $album_id );
			if ( $album_id <= 0 ) {
				continue;
			}
			$all[] = $album_id;
			$all   = array_merge( $all, $this->walk_album_chain( $album_id, [] ) );
		}

		$all = array_values( array_unique( array_filter( array_map( 'absint', $all ) ) ) );
		sort( $all );

		return $all;
	}

	/**
	 * @param array<int,int> $visited
	 * @return array<int,int>
	 */
	private function walk_album_chain( int $album_id, array $visited ): array {
		global $wpdb;

		if ( $album_id <= 0 || in_array( $album_id, $visited, true ) ) {
			return [];
		}

		$visited[]         = $album_id;
		$album_items_table = $wpdb->prefix . 'mlgp_album_items';
		$parents           = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT album_id FROM {$album_items_table} WHERE item_type = %s AND item_id = %d ORDER BY sort_order ASC, id ASC",
				'album',
				$album_id
			)
		);

		if ( ! is_array( $parents ) || empty( $parents ) ) {
			return [];
		}

		$chain = [];

		foreach ( $parents as $parent_id ) {
			$parent_id = absint( $parent_id );
			if ( $parent_id <= 0 ) {
				continue;
			}
			$chain[] = $parent_id;
			$chain   = array_merge( $chain, $this->walk_album_chain( $parent_id, $visited ) );
		}

		return $chain;
	}

	/**
	 * @return array<int, int>
	 */
	private function get_root_album_ids_for_gallery( int $gallery_id ): array {
		global $wpdb;

		$album_items_table = $wpdb->prefix . 'mlgp_album_items';

		$parents = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT album_id FROM {$album_items_table} WHERE item_type = %s AND item_id = %d ORDER BY sort_order ASC, id ASC",
				'gallery',
				$gallery_id
			)
		);

		if ( ! is_array( $parents ) || empty( $parents ) ) {
			return [];
		}

		$roots = [];

		foreach ( $parents as $album_id ) {
			$album_id = (int) $album_id;
			if ( $album_id <= 0 ) {
				continue;
			}
			$roots = array_merge( $roots, $this->walk_album_ancestors_to_roots( $album_id, [] ) );
		}

		$roots = array_values( array_unique( array_filter( array_map( 'absint', $roots ) ) ) );
		sort( $roots );

		return $roots;
	}

	/**
	 * @param array<int, int> $visited
	 * @return array<int, int>
	 */
	private function walk_album_ancestors_to_roots( int $album_id, array $visited ): array {
		global $wpdb;

		if ( $album_id <= 0 || in_array( $album_id, $visited, true ) ) {
			return [];
		}

		$visited[]         = $album_id;
		$album_items_table = $wpdb->prefix . 'mlgp_album_items';

		$parents = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT album_id FROM {$album_items_table} WHERE item_type = %s AND item_id = %d ORDER BY sort_order ASC, id ASC",
				'album',
				$album_id
			)
		);

		if ( ! is_array( $parents ) || empty( $parents ) ) {
			return [ $album_id ];
		}

		$roots = [];

		foreach ( $parents as $parent_id ) {
			$parent_id = (int) $parent_id;
			if ( $parent_id <= 0 ) {
				continue;
			}
			$roots = array_merge( $roots, $this->walk_album_ancestors_to_roots( $parent_id, $visited ) );
		}

		return $roots;
	}

	private function build_album_view_key( int $root_album_id ): string {
		return sanitize_key( 'mlgp_album_view_' . absint( $root_album_id ) );
	}

	/**
	 * @param array<string, mixed> $gallery
	 */
	private function get_cover_url( array $gallery ): string {
		$gallery_id = (int) ( $gallery['id'] ?? 0 );

		// Always re-read cover_attachment_id and cover_item_id fresh from the DB so a cover
		// change in ML Gallery Pro is reflected immediately, even when the gallery list row
		// is still cached with the previous values.
		if ( $gallery_id > 0 ) {
			global $wpdb;
			$galleries_table = $wpdb->prefix . 'mlgp_galleries';
			$fresh = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT cover_attachment_id, cover_item_id FROM {$galleries_table} WHERE id = %d LIMIT 1",
					$gallery_id
				),
				ARRAY_A
			);
			if ( is_array( $fresh ) ) {
				$gallery['cover_attachment_id'] = $fresh['cover_attachment_id'] ?? $gallery['cover_attachment_id'];
				$gallery['cover_item_id']       = $fresh['cover_item_id'] ?? $gallery['cover_item_id'];
			}
		}

		$attachment_id = (int) ( $gallery['cover_attachment_id'] ?? 0 );

		if ( $attachment_id > 0 ) {
			$src = wp_get_attachment_image_src( $attachment_id, 'large' );
			if ( is_array( $src ) && ! empty( $src[0] ) ) {
				return $this->append_cover_cache_buster( (string) $src[0], $gallery );
			}
		}

		global $wpdb;

		$items_table = $wpdb->prefix . 'mlgp_gallery_items';

		// Resolve image via cover_item_id — the primary key of the item the user
		// designated as cover inside ML Gallery Pro. Must be checked before the
		// first-item fallback, which ignores the user's cover selection entirely.
		$cover_item_id = (int) ( $gallery['cover_item_id'] ?? 0 );

		if ( $cover_item_id > 0 ) {
			$cover_row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT large_url, medium_url, file_url, attachment_id
					FROM {$items_table}
					WHERE id = %d
					LIMIT 1",
					$cover_item_id
				),
				ARRAY_A
			);

			if ( is_array( $cover_row ) ) {
				foreach ( [ 'large_url', 'medium_url', 'file_url' ] as $key ) {
					if ( ! empty( $cover_row[ $key ] ) ) {
						return $this->append_cover_cache_buster( (string) $cover_row[ $key ], $gallery );
					}
				}

				$cover_att_id = (int) ( $cover_row['attachment_id'] ?? 0 );
				if ( $cover_att_id > 0 ) {
					$src = wp_get_attachment_image_src( $cover_att_id, 'large' );
					if ( is_array( $src ) && ! empty( $src[0] ) ) {
						return $this->append_cover_cache_buster( (string) $src[0], $gallery );
					}
				}
			}
		}

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT large_url, medium_url, file_url, attachment_id
				FROM {$items_table}
				WHERE gallery_id = %d AND is_visible = 1
				ORDER BY sort_order ASC, id ASC
				LIMIT 1",
				$gallery_id
			),
			ARRAY_A
		);

		if ( ! is_array( $row ) ) {
			return '';
		}

		foreach ( [ 'large_url', 'medium_url', 'file_url' ] as $key ) {
			if ( ! empty( $row[ $key ] ) ) {
				return $this->append_cover_cache_buster( (string) $row[ $key ], $gallery );
			}
		}

		$att_id = (int) ( $row['attachment_id'] ?? 0 );
		if ( $att_id > 0 ) {
			$src = wp_get_attachment_image_src( $att_id, 'large' );
			if ( is_array( $src ) && ! empty( $src[0] ) ) {
				return $this->append_cover_cache_buster( (string) $src[0], $gallery );
			}
		}

		return '';
	}


	/**
	 * Bust carousel cache when attachment-related meta changes.
	 *
	 * @param int|string $meta_id    Meta row id.
	 * @param int|string $object_id  Post/attachment id.
	 * @param string     $meta_key   Meta key.
	 * @param mixed      $meta_value Meta value.
	 */
	public function invalidate_cache_on_attachment_change( $meta_id, $object_id, string $meta_key, $meta_value ): void {
		unset( $meta_id, $meta_value );

		$watched = [
			// WordPress core attachment meta.
			'_wp_attached_file',
			'_wp_attachment_metadata',
			'_thumbnail_id',
			// ML Gallery Pro cover meta keys (all known variants).
			'mlgp_cover_attachment_id',
			'mlgp_cover_item_id',
			'_mlgp_cover',
			'_mlgp_cover_attachment_id',
			'_mlgp_cover_item_id',
			'cover_attachment_id',
			'cover_item_id',
		];

		if ( in_array( $meta_key, $watched, true ) ) {
			$this->invalidate_cache();
		}
	}

	/**
	 * Adds a light cache-buster so updated cover selections refresh immediately in the carousel.
	 */
	private function append_cover_cache_buster( string $url, array $gallery ): string {
		if ( '' === $url ) {
			return '';
		}

		$parts = [
			(int) ( $gallery['cover_attachment_id'] ?? 0 ),
			(int) ( $gallery['cover_item_id'] ?? 0 ),
			(int) ( $gallery['id'] ?? 0 ),
		];

		$version = implode( '-', array_map( 'strval', $parts ) );

		return (string) add_query_arg( 'mlcgpv', rawurlencode( $version ), $url );
	}

	private function process_title( string $title ): string {
		$title = trim( wp_strip_all_tags( $title ) );

		// Cut at the first DD-MM-YYYY date mask (and its preceding ' - ' separator if present).
		$title = (string) preg_replace( '/\s*-?\s*\d{2}-\d{2}-\d{4}.*$/u', '', $title );

		// Trim trailing separator characters left after the cut.
		return trim( $title, " -\t\n\r\0\x0B" );
	}

	private function get_display_date( string $title ): string {
		$title = trim( wp_strip_all_tags( $title ) );

		// Return the first DD-MM-YYYY found anywhere in the title string.
		if ( preg_match( '/\b(\d{2}-\d{2}-\d{4})\b/', $title, $m ) ) {
			return $m[1];
		}

		return '';
	}

	/**
	 * @return array<int, int>
	 */
	private function parse_gallery_ids( string $csv ): array {
		$csv = preg_replace( '/[^0-9,]/', '', $csv );

		if ( ! is_string( $csv ) || '' === trim( $csv, ',' ) ) {
			return [];
		}

		return array_values( array_unique( array_filter( array_map( 'absint', explode( ',', trim( $csv, ',' ) ) ) ) ) );
	}


	/**
	 * Returns real gallery data for the admin preview using the same source logic as the shortcode.
	 *
	 * @param string $profile_id Optional profile ID. Defaults to 'home' then first profile.
	 * @return array<int, array<string, string>>
	 */
	public function get_admin_preview_items( string $profile_id = '' ): array {
		$s = $this->settings->get();
		$profile = null;

		if ( method_exists( $this->settings, 'get_profile' ) ) {
			$lookup = '' !== $profile_id ? $profile_id : 'home';
			$profile = $this->settings->get_profile( $lookup );
		}

		if ( ! is_array( $profile ) || empty( $profile ) ) {
			$profiles = isset( $s['profiles'] ) && is_array( $s['profiles'] ) ? array_values( $s['profiles'] ) : [];
			$profile  = ! empty( $profiles ) && is_array( $profiles[0] ) ? $profiles[0] : [];
		}

		$limit = 4;
		$source = 'all';
		$album_id = 0;
		$gallery_ids = [];

		if ( is_array( $profile ) && ! empty( $profile ) ) {
			$limit       = max( 4, (int) ( $profile['limit'] ?? $limit ) );
			$source      = (string) ( $profile['source'] ?? 'all' );
			$album_id    = absint( $profile['album_id'] ?? 0 );
			$gallery_ids = $this->parse_gallery_ids( (string) ( $profile['gallery_ids'] ?? '' ) );
		}

		$rows = $this->get_galleries( $limit, [
			'source'      => $source,
			'album_id'    => $album_id,
			'gallery_ids' => $gallery_ids,
		] );

		$items = [];

		foreach ( array_slice( $rows, 0, 5 ) as $gallery ) {
			$title = $this->process_title( (string) ( $gallery['title'] ?? '' ) );
			$link  = $this->get_gallery_url( $gallery );
			$image = $this->get_cover_url( $gallery );

			$items[] = [
				'title' => '' !== $title ? strtoupper( $title ) : 'GALERIA',
				'date'  => $this->get_display_date( (string) ( $gallery['title'] ?? '' ) ),
				'image' => $image,
				'link'  => $link,
			];
		}

		return $items;
	}

	public function register_assets(): void {
		wp_register_style(
			'mlcgp-carousel',
			MLCGP_URL . 'assets/css/carousel.css',
			[],
			MLCGP_VERSION
		);

		wp_register_script(
			'mlcgp-carousel',
			MLCGP_URL . 'assets/js/carousel.js',
			[],
			MLCGP_VERSION,
			true
		);
	}

	private function enqueue_assets(): void {
		wp_enqueue_style( 'mlcgp-carousel' );
		wp_enqueue_script( 'mlcgp-carousel' );
	}

	public function invalidate_cache(): void {
		global $wpdb;

		// Gallery list cache (TRANSIENT_KEY + per-query md5 suffix).
		$like = $wpdb->esc_like( '_transient_' . self::TRANSIENT_KEY ) . '%';
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $like ) );

		$timeout_like = $wpdb->esc_like( '_transient_timeout_' . self::TRANSIENT_KEY ) . '%';
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $timeout_like ) );

		// Bug #2 fix: prefixes must include the 'v190_' infix that matches set_transient() calls.
		$like_links = $wpdb->esc_like( '_transient_mlcgp_v190_gallery_url_' ) . '%';
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $like_links ) );
		$like_links_timeout = $wpdb->esc_like( '_transient_timeout_mlcgp_v190_gallery_url_' ) . '%';
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $like_links_timeout ) );

		$like_album = $wpdb->esc_like( '_transient_mlcgp_v190_album_base_' ) . '%';
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $like_album ) );
		$like_album_timeout = $wpdb->esc_like( '_transient_timeout_mlcgp_v190_album_base_' ) . '%';
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $like_album_timeout ) );
	}
}