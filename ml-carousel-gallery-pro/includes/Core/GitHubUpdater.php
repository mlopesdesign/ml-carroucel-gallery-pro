<?php
/**
 * GitHub updater for ML Carousel Gallery Pro.
 *
 * @package MLCarouselGalleryPro
 */

namespace MLCarouselGalleryPro\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles WordPress update checks against GitHub Releases.
 */
final class GitHubUpdater {
	/**
	 * Plugin basename.
	 *
	 * @var string
	 */
	private string $plugin_basename;

	/**
	 * Main plugin file path.
	 *
	 * @var string
	 */
	private string $plugin_file;

	/**
	 * Current plugin version.
	 *
	 * @var string
	 */
	private string $current_version;

	/**
	 * GitHub owner.
	 *
	 * @var string
	 */
	private string $owner;

	/**
	 * Candidate GitHub repository names.
	 *
	 * @var array<int,string>
	 */
	private array $repositories;

	/**
	 * Cached selected release payload.
	 *
	 * @var array<string,mixed>|null
	 */
	private ?array $latest_release = null;

	/**
	 * Cached selected repository name.
	 *
	 * @var string|null
	 */
	private ?string $selected_repository = null;

	/**
	 * Constructor.
	 *
	 * @param string            $plugin_file     Main plugin file.
	 * @param string            $current_version Current version.
	 * @param string            $owner           GitHub owner/org.
	 * @param array<int,string> $repositories    Candidate repository names.
	 */
	public function __construct( string $plugin_file, string $current_version, string $owner, array $repositories ) {
		$this->plugin_file      = $plugin_file;
		$this->plugin_basename  = plugin_basename( $plugin_file );
		$this->current_version  = $current_version;
		$this->owner            = sanitize_key( $owner );
		$this->repositories     = array_values( array_filter( array_map( 'sanitize_title', $repositories ) ) );
	}

	/**
	 * Register updater hooks.
	 *
	 * @return void
	 */
	public function boot(): void {
		add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'filter_update_plugins' ] );
		add_filter( 'plugins_api', [ $this, 'filter_plugin_information' ], 20, 3 );
		add_filter( 'upgrader_post_install', [ $this, 'normalize_install_directory' ], 10, 3 );
	}

	/**
	 * Add update data to WordPress update transient.
	 *
	 * @param object|mixed $transient Update transient.
	 * @return object|mixed
	 */
	public function filter_update_plugins( $transient ) {
		if ( ! is_object( $transient ) ) {
			return $transient;
		}

		$release = $this->get_latest_release();
		if ( empty( $release ) ) {
			return $transient;
		}

		$remote_version = $this->normalize_version( (string) ( $release['tag_name'] ?? '' ) );
		if ( '' === $remote_version || ! version_compare( $remote_version, $this->current_version, '>' ) ) {
			return $transient;
		}

		$package = $this->get_release_zip_asset_url( $release, $remote_version );
		if ( '' === $package ) {
			return $transient;
		}

		$repository = $this->selected_repository ?: $this->get_primary_repository();
		$update     = (object) [
			'id'          => $this->plugin_basename,
			'slug'        => dirname( $this->plugin_basename ),
			'plugin'      => $this->plugin_basename,
			'new_version' => $remote_version,
			'url'         => $this->get_repository_url( $repository ),
			'package'     => $package,
			'tested'      => (string) ( $release['tested'] ?? '' ),
			'requires'    => '6.0',
			'requires_php'=> '7.4',
		];

		if ( ! isset( $transient->response ) || ! is_array( $transient->response ) ) {
			$transient->response = [];
		}

		$transient->response[ $this->plugin_basename ] = $update;

		return $transient;
	}

	/**
	 * Provide plugin details modal data.
	 *
	 * @param false|object|array $result Result.
	 * @param string             $action Action.
	 * @param object             $args   Args.
	 * @return false|object|array
	 */
	public function filter_plugin_information( $result, string $action, object $args ) {
		if ( 'plugin_information' !== $action ) {
			return $result;
		}

		if ( empty( $args->slug ) || dirname( $this->plugin_basename ) !== $args->slug ) {
			return $result;
		}

		$release = $this->get_latest_release();
		if ( empty( $release ) ) {
			return $result;
		}

		$version    = $this->normalize_version( (string) ( $release['tag_name'] ?? '' ) );
		$repository = $this->selected_repository ?: $this->get_primary_repository();
		$body       = isset( $release['body'] ) ? wp_kses_post( (string) $release['body'] ) : '';

		return (object) [
			'name'          => 'ML Carousel Gallery Pro',
			'slug'          => dirname( $this->plugin_basename ),
			'version'       => $version,
			'author'        => '<a href="https://mlopesdesign.com/">Mlopesdesign</a>',
			'homepage'      => $this->get_repository_url( $repository ),
			'requires'      => '6.0',
			'requires_php'  => '7.4',
			'tested'        => '',
			'download_link' => $this->get_release_zip_asset_url( $release, $version ),
			'sections'      => [
				'description' => 'Professional WordPress carousel plugin for ML Gallery Pro.',
				'changelog'   => $body ?: 'Release notes available on GitHub.',
			],
		];
	}

	/**
	 * Normalize GitHub zipball extraction folder to the real plugin folder when needed.
	 *
	 * @param bool|\WP_Error $response   Install response.
	 * @param array          $hook_extra Extra data.
	 * @param array          $result     Install result.
	 * @return bool|\WP_Error
	 */
	public function normalize_install_directory( $response, array $hook_extra, array $result ) {
		if ( empty( $hook_extra['plugin'] ) || $this->plugin_basename !== $hook_extra['plugin'] ) {
			return $response;
		}

		global $wp_filesystem;
		if ( ! $wp_filesystem || empty( $result['destination'] ) ) {
			return $response;
		}

		$plugin_dir_name = dirname( $this->plugin_basename );
		$target          = trailingslashit( WP_PLUGIN_DIR ) . $plugin_dir_name;
		$destination     = untrailingslashit( $result['destination'] );

		if ( basename( $destination ) === $plugin_dir_name ) {
			return $response;
		}

		if ( $wp_filesystem->exists( $target ) ) {
			$wp_filesystem->delete( $target, true );
		}

		$wp_filesystem->move( $destination, $target, true );

		return $response;
	}

	/**
	 * Get latest release across configured repositories.
	 *
	 * @return array<string,mixed>|null
	 */
	private function get_latest_release(): ?array {
		if ( null !== $this->latest_release ) {
			return $this->latest_release;
		}

		$cache_key = 'mlcgp_github_latest_release_' . md5( $this->owner . '|' . implode( ',', $this->repositories ) . '|' . $this->current_version );
		$cached    = get_site_transient( $cache_key );
		if ( is_array( $cached ) && ! empty( $cached['release'] ) && ! empty( $cached['repository'] ) ) {
			$this->latest_release       = $cached['release'];
			$this->selected_repository = (string) $cached['repository'];
			return $this->latest_release;
		}

		$best_release    = null;
		$best_repository = null;
		$best_version    = '';

		foreach ( $this->repositories as $repository ) {
			$release = $this->request_latest_release( $repository );
			if ( empty( $release ) || ! is_array( $release ) ) {
				continue;
			}

			$version = $this->normalize_version( (string) ( $release['tag_name'] ?? '' ) );
			if ( '' === $version ) {
				continue;
			}

			if ( null === $best_release || version_compare( $version, $best_version, '>' ) ) {
				$best_release    = $release;
				$best_repository = $repository;
				$best_version    = $version;
			}
		}

		if ( null !== $best_release && null !== $best_repository ) {
			$this->latest_release       = $best_release;
			$this->selected_repository = $best_repository;
			set_site_transient(
				$cache_key,
				[
					'release'    => $best_release,
					'repository' => $best_repository,
				],
				5 * MINUTE_IN_SECONDS
			);
		}

		return $this->latest_release;
	}

	/**
	 * Request latest release from GitHub.
	 *
	 * @param string $repository Repository name.
	 * @return array<string,mixed>|null
	 */
	private function request_latest_release( string $repository ): ?array {
		$url = sprintf(
			'https://api.github.com/repos/%s/%s/releases/latest',
			rawurlencode( $this->owner ),
			rawurlencode( $repository )
		);

		$response = wp_remote_get(
			$url,
			[
				'timeout' => 12,
				'headers' => [
					'Accept'     => 'application/vnd.github+json',
					'User-Agent' => 'ML-Carousel-Gallery-Pro/' . $this->current_version,
				],
			]
		);

		if ( is_wp_error( $response ) ) {
			return null;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return null;
		}

		$payload = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		return is_array( $payload ) ? $payload : null;
	}

	/**
	 * Get release asset URL for the plugin ZIP.
	 *
	 * @param array<string,mixed> $release Release payload.
	 * @param string              $version Version.
	 * @return string
	 */
	private function get_release_zip_asset_url( array $release, string $version ): string {
		$assets = isset( $release['assets'] ) && is_array( $release['assets'] ) ? $release['assets'] : [];
		$wanted = [
			'ml-carousel-gallery-pro-v' . $version . '.zip',
			'ml-carrousel-gallery-pro-v' . $version . '.zip',
			'ml-carroucel-gallery-pro-v' . $version . '.zip',
		];

		foreach ( $assets as $asset ) {
			if ( ! is_array( $asset ) ) {
				continue;
			}

			$name = (string) ( $asset['name'] ?? '' );
			$url  = (string) ( $asset['browser_download_url'] ?? '' );

			if ( '' === $url ) {
				continue;
			}

			if ( in_array( $name, $wanted, true ) ) {
				return esc_url_raw( $url );
			}
		}

		foreach ( $assets as $asset ) {
			if ( ! is_array( $asset ) ) {
				continue;
			}

			$name = (string) ( $asset['name'] ?? '' );
			$url  = (string) ( $asset['browser_download_url'] ?? '' );

			if ( '' !== $url && '.zip' === substr( strtolower( $name ), -4 ) ) {
				return esc_url_raw( $url );
			}
		}

		return '';
	}

	/**
	 * Normalize semantic version from tag.
	 *
	 * @param string $version Version/tag.
	 * @return string
	 */
	private function normalize_version( string $version ): string {
		$version = trim( strtolower( $version ) );
		$version = preg_replace( '/^ml-carr?ou?cel-gallery-pro[-_]?v?/', '', $version );
		$version = preg_replace( '/^ml-carousel-gallery-pro[-_]?v?/', '', $version );
		$version = preg_replace( '/^ml-carrousel-gallery-pro[-_]?v?/', '', $version );
		$version = ltrim( (string) $version, 'v' );
		return preg_match( '/^\d+(?:\.\d+){1,3}(?:[-+][a-z0-9.-]+)?$/', $version ) ? $version : '';
	}

	/**
	 * Get primary repository.
	 *
	 * @return string
	 */
	private function get_primary_repository(): string {
		return $this->repositories[0] ?? 'ml-carroucel-gallery-pro';
	}

	/**
	 * Get repository URL.
	 *
	 * @param string $repository Repository name.
	 * @return string
	 */
	private function get_repository_url( string $repository ): string {
		return sprintf( 'https://github.com/%s/%s', $this->owner, $repository );
	}
}
