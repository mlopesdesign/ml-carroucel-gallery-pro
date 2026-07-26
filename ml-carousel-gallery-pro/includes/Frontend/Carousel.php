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

	/**
	 * Cache namespace. The numeric suffix is bumped whenever the cache payload
	 * shape changes so old rows are never reused after a plugin update.
	 */
	const TRANSIENT_KEY = 'mlcgp_galleries_cache_v11013';

	/**
	 * Option holding the incremental cache generation. Bumping it invalidates
	 * every cached entry at once, including entries stored in a persistent
	 * object cache (Redis/Memcached) that cannot be enumerated or deleted by
	 * an SQL LIKE query.
	 */
	const CACHE_VERSION_OPTION = 'mlcgp_cache_version';

	/** @var Settings */
	private $settings;

	/** @var array<string,string> */
	private $cache_lock_tokens = [];

	/** @var int|null */
	private $cache_version = null;

	/** @var array<int,string> Per-request cover URL memo. */
	private $cover_url_memo = [];

	/** @var array<int,\WP_Post>|null Per-request public post pool. */
	private $public_post_pool = null;

	/**
	 * Re-entrancy guard. While the link resolver renders other pages through
	 * the_content, this carousel must never render itself again.
	 *
	 * @var bool
	 */
	private static $is_resolving = false;

	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	public function hooks(): void {
		add_shortcode( 'ml_carousel_gallery', [ $this, 'render_shortcode' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'register_assets' ] );

		// WordPress post lifecycle — covers any MLGP galleries stored as posts.
		add_action( 'save_post', [ $this, 'invalidate_cache_on_post_save' ] );
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
		add_action( 'mlgp_after_items_stored',      [ $this, 'invalidate_cache' ] );
		add_action( 'mlgp_album_saved',             [ $this, 'invalidate_cache' ] );
		add_action( 'mlgp_album_updated',           [ $this, 'invalidate_cache' ] );
		add_action( 'mlgp_album_items_updated',     [ $this, 'invalidate_cache' ] );

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
		// Re-entrancy guard: the link resolver renders other pages through
		// the_content. If one of those pages also holds this shortcode we must
		// not recurse into a second (potentially infinite) carousel render.
		if ( self::$is_resolving ) {
			return '';
		}

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

	/* ---------------------------------------------------------------------
	 * Gallery data
	 * ------------------------------------------------------------------ */

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

		$cached = $this->cache_get( $cache_key );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		$lock_key = $cache_key . '_lock';

		if ( ! $this->acquire_cache_rebuild_lock( $lock_key ) ) {
			$stale = $this->cache_get( $cache_key, true );

			if ( is_array( $stale ) ) {
				return $stale;
			}

			// No cache at all and another request holds the lock. Returning an
			// empty carousel would show "nenhuma galeria" to a real visitor, so
			// run the (single, indexed) query directly without writing cache.
			try {
				$rows = $this->query_galleries_from_mlgp( $limit, $source, $album_id, $gallery_ids );
			} catch ( \Throwable $e ) {
				$rows = [];
			}

			return is_array( $rows ) ? $rows : [];
		}

		try {
			$rows = $this->query_galleries_from_mlgp( $limit, $source, $album_id, $gallery_ids );

			if ( ! is_array( $rows ) ) {
				$rows = [];
			}

			$this->cache_set( $cache_key, $rows, 5 * MINUTE_IN_SECONDS );

			return $rows;
		} catch ( \Throwable $e ) {
			return [];
		} finally {
			$this->release_cache_rebuild_lock( $lock_key );
		}
	}

	/**
	 * @param array<string,mixed> $args
	 */
	private function get_galleries_cache_key( int $limit, array $args ): string {
		$payload = [
			'limit'       => max( 1, $limit ),
			'source'      => (string) ( $args['source'] ?? 'all' ),
			'album_id'    => absint( $args['album_id'] ?? 0 ),
			'gallery_ids' => isset( $args['gallery_ids'] ) && is_array( $args['gallery_ids'] ) ? array_values( array_map( 'absint', $args['gallery_ids'] ) ) : [],
		];

		sort( $payload['gallery_ids'] );

		return self::TRANSIENT_KEY . '_' . $this->get_cache_version() . '_' . md5( wp_json_encode( $payload ) ?: serialize( $payload ) );
	}

	/**
	 * Reads gallery rows for the requested source.
	 *
	 * source=all        → latest published galleries (g.id DESC).
	 * source=album      → galleries of one album, in the manual ML Gallery Pro order.
	 * source=galleries  → an explicit gallery ID list.
	 *
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

		// ML Gallery Pro does not reliably fill/refresh created_at, so the
		// primary key is the only trustworthy creation order. Ordering by
		// created_at pushed every gallery with an empty date to the end.
		$order_sql = 'g.id DESC';
		$rows      = [];

		if ( 'album' === $source && $album_id > 0 ) {
			return $this->get_album_gallery_rows_in_mlgp_order( $album_id, $limit );
		}

		if ( 'galleries' === $source && ! empty( $gallery_ids ) ) {
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

			return is_array( $rows ) ? $rows : [];
		}

		// source=all — the newest published galleries, period. This result must
		// never be replaced by an album listing: doing so pinned the carousel to
		// whatever album happened to contain the newest gallery.
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

		return is_array( $rows ) ? $rows : [];
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

	/* ---------------------------------------------------------------------
	 * Cache layer
	 * ------------------------------------------------------------------ */

	private function get_cache_version(): int {
		if ( null === $this->cache_version ) {
			$this->cache_version = max( 1, (int) get_option( self::CACHE_VERSION_OPTION, 1 ) );
		}

		return $this->cache_version;
	}

	/**
	 * Reads a cache entry.
	 *
	 * With a persistent object cache the standard transient API is used, because
	 * transients never reach the options table there. Without one, the option row
	 * is read directly: get_transient() deletes expired rows, and public shortcode
	 * rendering must never execute delete_option()/DELETE cleanup work.
	 *
	 * @return mixed False when missing.
	 */
	private function cache_get( string $cache_key, bool $allow_stale = false ) {
		if ( function_exists( 'wp_using_ext_object_cache' ) && wp_using_ext_object_cache() ) {
			return get_transient( $cache_key );
		}

		$timeout = get_option( '_transient_timeout_' . $cache_key );

		if ( ! $allow_stale && false !== $timeout && (int) $timeout > 0 && (int) $timeout < time() ) {
			return false;
		}

		return get_option( '_transient_' . $cache_key, false );
	}

	/**
	 * @param mixed $value
	 */
	private function cache_set( string $cache_key, $value, int $ttl ): void {
		set_transient( $cache_key, $value, max( 60, $ttl ) );
	}

	private function acquire_cache_rebuild_lock( string $lock_key ): bool {
		if ( function_exists( 'wp_using_ext_object_cache' ) && wp_using_ext_object_cache() ) {
			if ( false !== get_transient( $lock_key ) ) {
				return false;
			}

			$token = wp_generate_uuid4();
			set_transient( $lock_key, $token, 20 );
			$this->cache_lock_tokens[ $lock_key ] = $token;

			return true;
		}

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
		$token = $this->cache_lock_tokens[ $lock_key ] ?? '';

		if ( '' === $token ) {
			unset( $this->cache_lock_tokens[ $lock_key ] );

			return;
		}

		if ( function_exists( 'wp_using_ext_object_cache' ) && wp_using_ext_object_cache() ) {
			if ( get_transient( $lock_key ) === $token ) {
				delete_transient( $lock_key );
			}

			unset( $this->cache_lock_tokens[ $lock_key ] );

			return;
		}

		$option_name = '_transient_' . $lock_key;

		if ( get_option( $option_name ) === $token ) {
			update_option( $option_name, '', false );
			update_option( '_transient_timeout_' . $lock_key, 1, false );
		}

		unset( $this->cache_lock_tokens[ $lock_key ] );
	}

	/**
	 * Invalidates every carousel cache entry.
	 *
	 * The generation counter is the authoritative mechanism: it works with and
	 * without a persistent object cache. The SQL purge only garbage-collects
	 * abandoned option rows on installs without an object cache.
	 */
	public function invalidate_cache(): void {
		$version = $this->get_cache_version() + 1;

		update_option( self::CACHE_VERSION_OPTION, $version, false );

		$this->cache_version    = $version;
		$this->cover_url_memo   = [];
		$this->public_post_pool = null;

		if ( function_exists( 'wp_using_ext_object_cache' ) && wp_using_ext_object_cache() ) {
			return;
		}

		global $wpdb;

		$like         = $wpdb->esc_like( '_transient_mlcgp_' ) . '%';
		$timeout_like = $wpdb->esc_like( '_transient_timeout_mlcgp_' ) . '%';

		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $like ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $timeout_like ) );
	}

	/**
	 * Cache invalidation for save_post, skipping autosaves and revisions.
	 *
	 * @param int|string $post_id Post ID.
	 */
	public function invalidate_cache_on_post_save( $post_id ): void {
		$post_id = absint( $post_id );

		if ( $post_id <= 0 ) {
			return;
		}

		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		$this->invalidate_cache();
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
		unset( $meta_id, $object_id, $meta_value );

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

	/* ---------------------------------------------------------------------
	 * Link resolver — never break this behavior.
	 * ------------------------------------------------------------------ */

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

		$cache_key = 'mlcgp_gurl_' . $this->get_cache_version() . '_' . $gallery_id;
		$cached    = $this->cache_get( $cache_key );

		if ( is_string( $cached ) ) {
			return $cached;
		}

		$album_ids = $this->get_related_album_ids_for_gallery( $gallery_id );

		if ( empty( $album_ids ) ) {
			// Negative result is cached too: without this, every page view
			// re-ran the full resolver for every unresolved gallery.
			$this->cache_set( $cache_key, '', 5 * MINUTE_IN_SECONDS );

			return '';
		}

		$resolved_url = $this->resolve_gallery_url_via_runtime_render( $gallery_id, $gallery, $album_ids );

		if ( '' !== $resolved_url ) {
			$this->cache_set( $cache_key, $resolved_url, 15 * MINUTE_IN_SECONDS );

			return $resolved_url;
		}

		$this->cache_set( $cache_key, '', 5 * MINUTE_IN_SECONDS );

		return '';
	}

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

		$previous_state      = self::$is_resolving;
		self::$is_resolving  = true;

		try {
			foreach ( $album_ids as $album_id ) {
				$album_id = absint( $album_id );
				if ( $album_id <= 0 ) {
					continue;
				}

				$candidates = $this->get_runtime_candidate_posts_for_album( $album_id, $gallery_title, $gallery_slug );

				// Cap full content renders to 5 candidates per album to prevent timeout.
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
		} finally {
			self::$is_resolving = $previous_state;
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

		// Superglobals are always restored, even if the render throws.
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

			if ( '' !== $album_title && false !== strpos( strtolower( (string) $post->post_title ), strtolower( $album_title ) ) ) {
				return $post;
			}

			if ( '' !== $album_slug && false !== strpos( strtolower( (string) $post->post_name ), strtolower( $album_slug ) ) ) {
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

		// Never render a page that embeds this carousel: it would re-enter the
		// shortcode while resolving links.
		if ( function_exists( 'has_shortcode' ) && has_shortcode( $post_content, 'ml_carousel_gallery' ) ) {
			return '';
		}

		$previous_post   = $GLOBALS['post'] ?? null;
		$GLOBALS['post'] = $post;
		setup_postdata( $post );

		try {
			$html = (string) apply_filters( 'the_content', $post_content );
		} catch ( \Throwable $e ) {
			$html = '';
		} finally {
			if ( $previous_post instanceof \WP_Post ) {
				$GLOBALS['post'] = $previous_post;
				setup_postdata( $previous_post );
			} else {
				wp_reset_postdata();
				unset( $GLOBALS['post'] );
			}
		}

		return $html;
	}

	/**
	 * @param array<int,string> $needles
	 * @return array<int,\WP_Post>
	 */
	private function get_public_post_candidates( array $needles ): array {
		$pool = $this->get_public_post_pool();

		if ( empty( $pool ) ) {
			return [];
		}

		$scored = [];

		foreach ( $pool as $post ) {
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}

			$haystack = strtolower( implode( ' ', array_filter( [ (string) $post->post_title, (string) $post->post_name, (string) $post->post_content ] ) ) );
			$score    = 0;

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
				$scored[] = [
					'score' => $score,
					'post'  => $post,
				];
			}
		}

		usort(
			$scored,
			static function ( array $a, array $b ): int {
				return $b['score'] <=> $a['score'];
			}
		);

		return array_map(
			static function ( array $row ) {
				return $row['post'];
			},
			array_slice( $scored, 0, 25 )
		);
	}

	/**
	 * The candidate query is identical for every gallery in a render, so it runs
	 * once per request instead of once per gallery.
	 *
	 * @return array<int,\WP_Post>
	 */
	private function get_public_post_pool(): array {
		if ( is_array( $this->public_post_pool ) ) {
			return $this->public_post_pool;
		}

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

		$this->public_post_pool = ( ! empty( $query->posts ) && is_array( $query->posts ) ) ? $query->posts : [];

		return $this->public_post_pool;
	}

	private function normalize_title_for_match( string $value ): string {
		$value = remove_accents( wp_strip_all_tags( $value ) );
		$value = strtolower( (string) preg_replace( '/\s+/', ' ', $value ) );

		return trim( $value );
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

	private function build_album_view_key( int $root_album_id ): string {
		return sanitize_key( 'mlgp_album_view_' . absint( $root_album_id ) );
	}

	/* ---------------------------------------------------------------------
	 * Covers
	 * ------------------------------------------------------------------ */

	/**
	 * @param array<string, mixed> $gallery
	 */
	private function get_cover_url( array $gallery ): string {
		$gallery_id = (int) ( $gallery['id'] ?? 0 );

		if ( $gallery_id > 0 && isset( $this->cover_url_memo[ $gallery_id ] ) ) {
			return $this->cover_url_memo[ $gallery_id ];
		}

		$url = $this->resolve_cover_url( $gallery );

		if ( $gallery_id > 0 ) {
			$this->cover_url_memo[ $gallery_id ] = $url;
		}

		return $url;
	}

	/**
	 * @param array<string, mixed> $gallery
	 */
	private function resolve_cover_url( array $gallery ): string {
		global $wpdb;

		$gallery_id = (int) ( $gallery['id'] ?? 0 );

		// Always re-read cover_attachment_id and cover_item_id fresh from the DB so a cover
		// change in ML Gallery Pro is reflected immediately, even when the gallery list row
		// is still cached with the previous values.
		if ( $gallery_id > 0 ) {
			$galleries_table = $wpdb->prefix . 'mlgp_galleries';
			$fresh           = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT cover_attachment_id, cover_item_id FROM {$galleries_table} WHERE id = %d LIMIT 1",
					$gallery_id
				),
				ARRAY_A
			);
			if ( is_array( $fresh ) ) {
				$gallery['cover_attachment_id'] = $fresh['cover_attachment_id'] ?? ( $gallery['cover_attachment_id'] ?? 0 );
				$gallery['cover_item_id']       = $fresh['cover_item_id'] ?? ( $gallery['cover_item_id'] ?? 0 );
			}
		}

		$attachment_id = (int) ( $gallery['cover_attachment_id'] ?? 0 );

		if ( $attachment_id > 0 ) {
			$src = wp_get_attachment_image_src( $attachment_id, 'large' );
			if ( is_array( $src ) && ! empty( $src[0] ) ) {
				return $this->append_cover_cache_buster( (string) $src[0], $gallery );
			}
		}

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

		if ( $gallery_id <= 0 ) {
			return '';
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
	 * Adds a light cache-buster so updated cover selections refresh immediately in the carousel.
	 *
	 * @param array<string,mixed> $gallery
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

	/* ---------------------------------------------------------------------
	 * Title / date
	 * ------------------------------------------------------------------ */

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
		$csv = (string) preg_replace( '/[^0-9,]/', '', $csv );

		if ( '' === trim( $csv, ',' ) ) {
			return [];
		}

		return array_values( array_unique( array_filter( array_map( 'absint', explode( ',', trim( $csv, ',' ) ) ) ) ) );
	}

	/* ---------------------------------------------------------------------
	 * Admin preview
	 * ------------------------------------------------------------------ */

	/**
	 * Returns real gallery data for the admin preview using the same source logic as the shortcode.
	 *
	 * @param string $profile_id Optional profile ID. Defaults to 'home' then first profile.
	 * @return array<int, array<string, string>>
	 */
	public function get_admin_preview_items( string $profile_id = '' ): array {
		$s       = $this->settings->get();
		$profile = null;

		if ( method_exists( $this->settings, 'get_profile' ) ) {
			$lookup  = '' !== $profile_id ? $profile_id : 'home';
			$profile = $this->settings->get_profile( $lookup );
		}

		if ( ! is_array( $profile ) || empty( $profile ) ) {
			$profiles = isset( $s['profiles'] ) && is_array( $s['profiles'] ) ? array_values( $s['profiles'] ) : [];
			$profile  = ! empty( $profiles ) && is_array( $profiles[0] ) ? $profiles[0] : [];
		}

		$limit       = 4;
		$source      = 'all';
		$album_id    = 0;
		$gallery_ids = [];

		if ( is_array( $profile ) && ! empty( $profile ) ) {
			$limit       = max( 4, (int) ( $profile['limit'] ?? $limit ) );
			$source      = (string) ( $profile['source'] ?? 'all' );
			$album_id    = absint( $profile['album_id'] ?? 0 );
			$gallery_ids = $this->parse_gallery_ids( (string) ( $profile['gallery_ids'] ?? '' ) );
		}

		$rows = $this->get_galleries(
			$limit,
			[
				'source'      => $source,
				'album_id'    => $album_id,
				'gallery_ids' => $gallery_ids,
			]
		);

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

	/* ---------------------------------------------------------------------
	 * Assets
	 * ------------------------------------------------------------------ */

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
}
