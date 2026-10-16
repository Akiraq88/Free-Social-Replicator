<?php
/**
 * Free Social Replicator (FSR)
 * Al borrar el plugin se eliminan sus ajustes, registros e imágenes temporales generadas.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Eliminar opciones del plugin FSR
delete_option( 'fsr_opts' );
delete_option( 'fsr_log' );
delete_transient( 'fsr_font_fail' );

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
