<?php
/**
 * Desinstalacao do ML Carousel Gallery Pro.
 *
 * Remove somente dados descartaveis (caches e transients com prefixo mlcgp_).
 * Configuracoes (mlcgp_settings) e licenca (mlcgp_license_state) sao preservadas
 * de proposito: o cliente pode reinstalar sem perder ajustes nem reativar a licenca.
 *
 * @package MLCarouselGalleryPro
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Limpa caches do site atual.
 */
function mlcgp_uninstall_clear_cache() {
	global $wpdb;

	$patterns = [
		$wpdb->esc_like( '_transient_mlcgp_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_mlcgp_' ) . '%',
		$wpdb->esc_like( '_site_transient_mlcgp_' ) . '%',
		$wpdb->esc_like( '_site_transient_timeout_mlcgp_' ) . '%',
	];
	foreach ( $patterns as $pattern ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $pattern ) );
	}

	// Com cache persistente (Redis/Memcached) as entradas nao podem ser listadas; avancar a
	// geracao invalida todas de uma vez e evita servir cache velho numa reinstalacao.
	update_option( 'mlcgp_cache_version', max( 1, (int) get_option( 'mlcgp_cache_version', 1 ) ) + 1, false );
}

if ( is_multisite() ) {
	global $wpdb;
	foreach ( get_sites( [ 'fields' => 'ids', 'number' => 0 ] ) as $mlcgp_site_id ) {
		switch_to_blog( (int) $mlcgp_site_id );
		mlcgp_uninstall_clear_cache();
		restore_current_blog();
	}
	foreach ( [ '_site_transient_mlcgp_', '_site_transient_timeout_mlcgp_' ] as $mlcgp_prefix ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE %s", $wpdb->esc_like( $mlcgp_prefix ) . '%' ) );
	}
} else {
	mlcgp_uninstall_clear_cache();
}
