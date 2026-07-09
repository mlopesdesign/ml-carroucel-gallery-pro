<?php
namespace MLCarouselGalleryPro\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Settings {

	const OPTION_KEY = 'mlcgp_settings';

	public function hooks(): void {
		add_action( 'admin_init', [ $this, 'register_settings' ] );
	}

	public function add_menu(): void {}

	public function enqueue_assets( string $hook ): void {}

	public function register_settings(): void {
		register_setting(
			'mlcgp_settings_group',
			self::OPTION_KEY,
			[ $this, 'sanitize' ]
		);
	}

	/**
	 * @param mixed $value
	 */
	private function sanitize_visible_count( $value ): float {
		$value = is_numeric( $value ) ? (float) $value : 3.0;
		$value = max( 1.0, min( 6.0, $value ) );
		return round( $value * 2 ) / 2;
	}

	/**
	 * @param array<int|string, mixed> $profiles
	 * @return array<int, array<string, mixed>>
	 */
	private function sanitize_profiles( array $profiles ): array {
		$clean = [];

		foreach ( $profiles as $profile ) {
			if ( ! is_array( $profile ) ) {
				continue;
			}

			$id = sanitize_title( (string) ( $profile['id'] ?? '' ) );
			if ( '' === $id ) {
				continue;
			}

			$source = (string) ( $profile['source'] ?? 'all' );
			if ( ! in_array( $source, [ 'all', 'album', 'galleries' ], true ) ) {
				$source = 'all';
			}

			$raw_gallery_ids = $profile['gallery_ids'] ?? '';
			if ( is_array( $raw_gallery_ids ) ) {
				$gallery_ids = implode( ',', array_values( array_filter( array_map( 'absint', $raw_gallery_ids ) ) ) );
			} else {
				$gallery_ids = preg_replace( '/[^0-9,]/', '', (string) $raw_gallery_ids );
			}

			$clean[] = [
				'id'          => $id,
				'label'       => sanitize_text_field( (string) ( $profile['label'] ?? $id ) ),
				'source'      => $source,
				'album_id'    => absint( $profile['album_id'] ?? 0 ),
				'gallery_ids' => trim( (string) $gallery_ids, ',' ),
				'limit'       => max( 1, min( 24, absint( $profile['limit'] ?? 6 ) ) ),
			];
		}

		return array_values( $clean );
	}

	/**
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>
	 */
	public function sanitize( array $input ): array {
		$defaults = self::defaults();

		return [
			'limit'               => max( 1, min( 24, absint( $input['limit'] ?? $defaults['limit'] ) ) ),
			'autoplay'            => ! empty( $input['autoplay'] ) ? 1 : 0,
			'speed'               => max( 1000, min( 10000, absint( $input['speed'] ?? $defaults['speed'] ) ) ),
			'text_position'       => in_array( $input['text_position'] ?? '', [ 'top', 'center', 'bottom' ], true ) ? (string) $input['text_position'] : 'bottom',
			'text_align'          => in_array( $input['text_align'] ?? '', [ 'left', 'center', 'right' ], true ) ? (string) $input['text_align'] : 'center',
			'new_tab'             => ! empty( $input['new_tab'] ) ? 1 : 0,
			'show_date'           => ! empty( $input['show_date'] ) ? 1 : 0,
			'card_gap'            => max( 0, min( 80, absint( $input['card_gap'] ?? $defaults['card_gap'] ) ) ),
			'card_width'          => max( 0, min( 1200, absint( $input['card_width'] ?? $defaults['card_width'] ) ) ),
			'card_height'         => max( 0, min( 800, absint( $input['card_height'] ?? $defaults['card_height'] ) ) ),
			'center_mode'         => ! empty( $input['center_mode'] ) ? 1 : 0,
			'overlay_opacity'     => max( 0, min( 100, absint( $input['overlay_opacity'] ?? $defaults['overlay_opacity'] ) ) ),
			'visible_desktop'     => $this->sanitize_visible_count( $input['visible_desktop'] ?? $defaults['visible_desktop'] ),
			'visible_tablet'      => $this->sanitize_visible_count( $input['visible_tablet'] ?? $defaults['visible_tablet'] ),
			'visible_mobile'      => $this->sanitize_visible_count( $input['visible_mobile'] ?? $defaults['visible_mobile'] ),
			'license_product_id'  => sanitize_text_field( (string) ( $input['license_product_id'] ?? $defaults['license_product_id'] ) ),
			'license_endpoint'    => esc_url_raw( (string) ( $input['license_endpoint'] ?? $defaults['license_endpoint'] ) ),
			'license_item_name'   => sanitize_text_field( (string) ( $input['license_item_name'] ?? $defaults['license_item_name'] ) ),
			'license_seller_name' => sanitize_text_field( (string) ( $input['license_seller_name'] ?? $defaults['license_seller_name'] ) ),
			'license_cta_title'   => sanitize_text_field( (string) ( $input['license_cta_title'] ?? $defaults['license_cta_title'] ) ),
			'license_cta_text'    => sanitize_textarea_field( (string) ( $input['license_cta_text'] ?? $defaults['license_cta_text'] ) ),
			'profiles'            => $this->sanitize_profiles( (array) ( $input['profiles'] ?? $defaults['profiles'] ) ),
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return [
			'limit'               => 6,
			'autoplay'            => 1,
			'speed'               => 4000,
			'text_position'       => 'bottom',
			'text_align'          => 'center',
			'new_tab'             => 0,
			'show_date'           => 1,
			'card_gap'            => 10,
			'card_width'          => 0,
			'card_height'         => 280,
			'center_mode'         => 0,
			'overlay_opacity'     => 55,
			'visible_desktop'     => 3.5,
			'visible_tablet'      => 2.0,
			'visible_mobile'      => 1.0,
			'license_product_id'  => 'ml-carousel-gallery-pro',
			'license_endpoint'    => '',
			'license_item_name'   => 'ML Carousel Gallery Pro',
			'license_seller_name' => 'ML Lopes Design',
			'license_cta_title'   => 'Licenciamento padrão ML ativo',
			'license_cta_text'    => 'Estados free, trial, full e lifetime prontos para comercialização.',
			'profiles'            => [
				[
					'id'          => 'home',
					'label'       => 'Home',
					'source'      => 'all',
					'album_id'    => 0,
					'gallery_ids' => '',
					'limit'       => 12,
				],
			],
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	public function get(): array {
		return wp_parse_args( (array) get_option( self::OPTION_KEY, [] ), self::defaults() );
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function get_profile( string $id ): ?array {
		$id       = sanitize_title( $id );
		$settings = $this->get();

		if ( empty( $settings['profiles'] ) || ! is_array( $settings['profiles'] ) ) {
			return null;
		}

		foreach ( $settings['profiles'] as $profile ) {
			if ( is_array( $profile ) && sanitize_title( (string) ( $profile['id'] ?? '' ) ) === $id ) {
				return $profile;
			}
		}

		return null;
	}

	/**
	 * @return array<int, string>
	 */
	private function get_album_options(): array {
		global $wpdb;

		$table  = $wpdb->prefix . 'mlgp_albums';
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );

		if ( $exists !== $table ) {
			return [];
		}

		$rows = $wpdb->get_results( "SELECT id, title, slug FROM {$table} ORDER BY COALESCE(NULLIF(created_at, ''), '1970-01-01') DESC, id DESC", ARRAY_A );
		if ( ! is_array( $rows ) ) {
			return [];
		}

		$options = [];
		foreach ( $rows as $row ) {
			$id = absint( $row['id'] ?? 0 );
			if ( $id <= 0 ) {
				continue;
			}
			$title = trim( (string) ( $row['title'] ?? '' ) );
			$slug  = trim( (string) ( $row['slug'] ?? '' ) );
			$label = '' !== $title ? $title : $slug;
			if ( '' === $label ) {
				$label = 'Álbum #' . $id;
			}
			$options[ $id ] = $label . ' (#' . $id . ')';
		}

		return $options;
	}

	/**
	 * @return array<int, string>
	 */
	private function get_gallery_options(): array {
		global $wpdb;

		$table  = $wpdb->prefix . 'mlgp_galleries';
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( $exists !== $table ) {
			return [];
		}

		$rows = $wpdb->get_results( "SELECT id, title, slug FROM {$table} WHERE status IN ('publish','published') ORDER BY COALESCE(NULLIF(created_at, ''), '1970-01-01') DESC, id DESC LIMIT 500", ARRAY_A );
		if ( ! is_array( $rows ) ) {
			return [];
		}

		$options = [];
		foreach ( $rows as $row ) {
			$id = absint( $row['id'] ?? 0 );
			if ( $id <= 0 ) {
				continue;
			}
			$title = trim( (string) ( $row['title'] ?? '' ) );
			$slug  = trim( (string) ( $row['slug'] ?? '' ) );
			$label = '' !== $title ? $title : $slug;
			if ( '' === $label ) {
				$label = 'Galeria #' . $id;
			}
			$options[ $id ] = $label . ' (#' . $id . ')';
		}

		return $options;
	}

	/**
	 * @return array<int, string>
	 */
	public function get_album_options_public(): array {
		return $this->get_album_options();
	}

	/**
	 * @return array<int, string>
	 */
	public function get_gallery_options_public(): array {
		return $this->get_gallery_options();
	}

	public function render_page(): void {}
}
