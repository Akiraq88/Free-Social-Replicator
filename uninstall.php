<?php
/**
 * Free Social Replicator (FSR)
 * Al borrar el plugin se eliminan sus ajustes, registros, metadatos e imágenes temporales generadas.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Eliminar opciones del plugin FSR
delete_option( 'fsr_opts' );
delete_option( 'fsr_log' );

// Eliminar metadatos asociados a las entradas en wp_postmeta
$meta_keys = array( '_fsr_fb', '_fsr_ig', '_fsr_status', '_fsr_skip', '_fsr_ig_container' );
foreach ( $meta_keys as $meta_key ) {
	delete_post_meta_by_key( $meta_key );
}

// Limpiar directorio de uploads de FSR
$u   = wp_upload_dir();
$dir = trailingslashit( $u['basedir'] ) . 'fsr';

if ( is_dir( $dir ) ) {
	foreach ( (array) glob( $dir . '/*' ) as $f ) {
		if ( is_file( $f ) ) {
			@unlink( $f );
		}
	}
	@rmdir( $dir );
}
