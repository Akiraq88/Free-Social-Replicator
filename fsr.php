<?php
/**
 * Plugin Name: Free Social Replicator
 * Plugin URI:  https://github.com/free-social-replicator/free-social-replicator
 * Description: Publica automáticamente cada nota nueva en Facebook e Instagram con una imagen con título, resumen corto y enlace mediante la API oficial de Meta Graph.
 * Version:     1.0.0
 * Author:      Free Social Replicator
 * Requires PHP: 7.4
 * Text Domain: free-social-replicator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'FSR_VERSION', '1.0.0' );
define( 'FSR_DIR', plugin_dir_path( __FILE__ ) );
define( 'FSR_URL', plugin_dir_url( __FILE__ ) );

require_once FSR_DIR . 'includes/image.php';

/* ======================================================================
 * Ajustes
 * ====================================================================== */

function fsr_defaults() {
	return array(
		'dry_run'   => '1',      // Modo prueba: genera la imagen y registra, pero no publica nada.
		'fb_on'     => '1',
		'ig_on'     => '1',
		'page_id'   => '',
		'ig_id'     => '',
		'token'     => '',
		'api_ver'   => 'v25.0',
		'delay'     => 3,        // Minutos de espera tras publicar la nota.
		'max_age'   => 24,       // No publica notas cuya fecha sea más vieja que estas horas.
		'fb_mode'   => 'photo',  // photo = imagen con título + texto; link = enlace con tarjeta.
		'img_fmt'   => 'portrait',
		'tpl_fb'    => "{titulo}\n\n{resumen}\n\nLeé la nota completa 👉 {enlace}",
		'tpl_ig'    => "{titulo}\n\n{resumen}\n\n👉 Nota completa: enlace en la bio\n\n{hashtags}",
		'hashtags'  => '#Noticias #Actualidad',
		'try_font'  => '1',
	);
}

function fsr_opts() {
	$o = get_option( 'fsr_opts', array() );
	return wp_parse_args( is_array( $o ) ? $o : array(), fsr_defaults() );
}

/* ======================================================================
 * Registro de actividad y avisos
 * ====================================================================== */

function fsr_scrub( $s ) {
	$o = fsr_opts();
	if ( ! empty( $o['token'] ) ) {
		$s = str_replace( $o['token'], '[token]', (string) $s );
	}
	return preg_replace( '/access_token=[^&\s"]+/i', 'access_token=[token]', (string) $s );
}

function fsr_log( $post_id, $network, $status, $msg ) {
	$log = get_option( 'fsr_log', array() );
	if ( ! is_array( $log ) ) {
		$log = array();
	}
	array_unshift(
		$log,
		array(
			't' => time(),
			'p' => (int) $post_id,
			'n' => sanitize_key( $network ),
			's' => sanitize_key( $status ),
			'm' => mb_substr( fsr_scrub( $msg ), 0, 400 ),
		)
	);
	update_option( 'fsr_log', array_slice( $log, 0, 60 ), false );
}

function fsr_notice( $type, $msg ) {
	set_transient( 'fsr_notice_' . get_current_user_id(), array( sanitize_key( $type ), $msg ), 60 );
}

add_action( 'admin_notices', 'fsr_show_notice' );
function fsr_show_notice() {
	$n = get_transient( 'fsr_notice_' . get_current_user_id() );
	if ( ! $n || ! is_array( $n ) ) {
		return;
	}
	delete_transient( 'fsr_notice_' . get_current_user_id() );
	printf( '<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr( $n[0] ), esc_html( $n[1] ) );
}

/* ======================================================================
 * Textos: resumen y pie de cada red
 * ====================================================================== */

function fsr_summary( $post, $max = 220 ) {
	$t = has_excerpt( $post ) ? $post->post_excerpt : $post->post_content;
	$t = strip_shortcodes( $t );
	$t = preg_replace( '/<\/(p|h[1-6]|div|li|blockquote)>/i', '$0 ', $t );
	$t = wp_strip_all_tags( $t );
	$t = html_entity_decode( $t, ENT_QUOTES, 'UTF-8' );
	$t = trim( preg_replace( '/\s+/u', ' ', $t ) );
	if ( mb_strlen( $t ) <= $max ) {
		return $t;
	}
	$cut = mb_substr( $t, 0, $max );
	$pos = max( (int) mb_strrpos( $cut, '. ' ), (int) mb_strrpos( $cut, '? ' ), (int) mb_strrpos( $cut, '! ' ) );
	if ( $pos > $max * 0.5 ) {
		return mb_substr( $cut, 0, $pos + 1 );
	}
	$pos = mb_strrpos( $cut, ' ' );
	if ( $pos ) {
		$cut = mb_substr( $cut, 0, $pos );
	}
	return rtrim( $cut, ' ,;:-' ) . '…';
}

function fsr_caption( $post, $tpl, $o ) {
	$cats = get_the_category( $post->ID );
	$cat  = $cats ? $cats[0]->name : '';
	$map  = array(
		'{titulo}'    => html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ),
		'{resumen}'   => fsr_summary( $post ),
		'{enlace}'    => get_permalink( $post ),
		'{categoria}' => $cat,
		'{hashtags}'  => $o['hashtags'],
	);
	$s = strtr( $tpl, $map );
	$s = preg_replace( "/\n{3,}/", "\n\n", trim( str_replace( "\r", '', $s ) ) );
	return mb_substr( $s, 0, 2200 );
}

/* ======================================================================
 * Imagen con título y directorio seguro
 * ====================================================================== */

function fsr_upload_dir() {
	$u   = wp_upload_dir();
	$dir = trailingslashit( $u['basedir'] ) . 'fsr';
	if ( ! is_dir( $dir ) ) {
		wp_mkdir_p( $dir );
		@file_put_contents( $dir . '/index.php', "<?php\n// Silence is golden.\n" );
		// Prevenir ejecución de scripts PHP arbitrarios en la carpeta de uploads
		$htaccess = "<FilesMatch \"\\.(php|phtml|php3|php4|php5|php7|phps)$\">\nDeny from all\n</FilesMatch>\n";
		@file_put_contents( $dir . '/.htaccess', $htaccess );
	}
	return array( $dir, trailingslashit( $u['baseurl'] ) . 'fsr' );
}

/* Josefin Sans se descarga una vez desde Google Fonts; si no se puede, se usa Poppins Bold incluida. */
function fsr_font_title() {
	$fallback = FSR_DIR . 'assets/fonts/Poppins-Bold.ttf';
	$o        = fsr_opts();
	if ( empty( $o['try_font'] ) ) {
		return $fallback;
	}
	list( $dir ) = fsr_upload_dir();
	$file        = $dir . '/JosefinSans-Bold.ttf';
	if ( is_readable( $file ) && filesize( $file ) > 10000 ) {
		return $file;
	}
	if ( get_transient( 'fsr_font_fail' ) ) {
		return $fallback;
	}
	$res = wp_remote_get( 'https://fonts.googleapis.com/css?family=Josefin+Sans:700', array( 'timeout' => 8, 'sslverify' => true ) );
	if ( ! is_wp_error( $res ) && 200 === (int) wp_remote_retrieve_response_code( $res ) && preg_match( '/url\((https:[^)]+\.ttf)\)/', wp_remote_retrieve_body( $res ), $m ) ) {
		$f = wp_remote_get( $m[1], array( 'timeout' => 15, 'sslverify' => true ) );
		if ( ! is_wp_error( $f ) && 200 === (int) wp_remote_retrieve_response_code( $f ) ) {
			$body = wp_remote_retrieve_body( $f );
			$sig  = substr( $body, 0, 4 );
			if ( strlen( $body ) > 10000 && ( "\x00\x01\x00\x00" === $sig || 'OTTO' === $sig || 'true' === $sig ) ) {
				file_put_contents( $file, $body );
				return $file;
			}
		}
	}
	set_transient( 'fsr_font_fail', 1, 12 * HOUR_IN_SECONDS );
	return $fallback;
}

function fsr_logo_path() {
	foreach ( array( get_theme_mod( 'custom_logo' ), get_option( 'site_icon' ) ) as $id ) {
		if ( $id ) {
			$p = get_attached_file( (int) $id );
			if ( $p && is_readable( $p ) ) {
				return $p;
			}
		}
	}
	return '';
}

/**
 * @return array|WP_Error array( 'path' =>, 'url' => )
 */
function fsr_make_image( $post_id ) {
	$post = get_post( $post_id );
	if ( ! $post ) {
		return new WP_Error( 'fsr_nopost', 'La nota no existe.' );
	}
	$o           = fsr_opts();
	$thumb_id    = get_post_thumbnail_id( $post_id );
	$photo       = $thumb_id ? get_attached_file( $thumb_id ) : '';
	$photo       = ( $photo && is_readable( $photo ) ) ? $photo : '';
	$cats        = get_the_category( $post_id );
	$title       = html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' );
	$h           = ( 'square' === $o['img_fmt'] ) ? 1080 : 1350;
	list( $dir, $url ) = fsr_upload_dir();
	$hash        = substr( md5( $title . '|' . $thumb_id . '|' . $o['img_fmt'] . '|' . ( $cats ? $cats[0]->name : '' ) . '|' . FSR_VERSION ), 0, 10 );
	$name        = 'post-' . (int) $post_id . '-' . $hash . '.jpg';
	$out         = $dir . '/' . $name;

	if ( ! is_readable( $out ) ) {
		foreach ( (array) glob( $dir . '/post-' . (int) $post_id . '-*.jpg' ) as $old ) {
			@unlink( $old );
		}
		if ( function_exists( 'wp_raise_memory_limit' ) ) {
			wp_raise_memory_limit( 'image' );
		}
		$r = fsr_render(
			array(
				'out'        => $out,
				'w'          => 1080,
				'h'          => $h,
				'photo'      => $photo,
				'logo'       => fsr_logo_path(),
				'title'      => $title,
				'category'   => $cats ? $cats[0]->name : '',
				'footer'     => (string) wp_parse_url( home_url(), PHP_URL_HOST ),
				'font_title' => fsr_font_title(),
				'font_label' => FSR_DIR . 'assets/fonts/Poppins-Medium.ttf',
			)
		);
		if ( true !== $r ) {
			return new WP_Error( 'fsr_img', $r );
		}
	}
	return array( 'path' => $out, 'url' => $url . '/' . $name );
}

/* ======================================================================
 * API de Meta (Graph API)
 * ====================================================================== */

function fsr_graph( $method, $path, $params = array() ) {
	$o   = fsr_opts();
	$url = 'https://graph.facebook.com/' . rawurlencode( $o['api_ver'] ) . '/' . ltrim( $path, '/' );
	
	// Cabecera de Autorización Bearer para evitar exponer el token en la query string de la URL
	$headers = array(
		'Accept' => 'application/json',
	);
	if ( ! empty( $o['token'] ) ) {
		$headers['Authorization'] = 'Bearer ' . $o['token'];
	}

	$args = array(
		'timeout'   => ( 'POST' === $method ) ? 25 : 15,
		'sslverify' => true,
		'headers'   => $headers,
	);

	if ( 'GET' === $method ) {
		$res = wp_remote_get( add_query_arg( $params, $url ), $args );
	} else {
		// En POST pasamos params en body; mantenemos access_token en body por compatibilidad con endpoints específicos de fotos
		$params['access_token'] = $o['token'];
		$args['body']           = $params;
		$res                    = wp_remote_post( $url, $args );
	}

	if ( is_wp_error( $res ) ) {
		return new WP_Error( 'fsr_http', $res->get_error_message(), array( 'fatal' => false ) );
	}
	$code = (int) wp_remote_retrieve_response_code( $res );
	$j    = json_decode( wp_remote_retrieve_body( $res ), true );
	if ( $code >= 200 && $code < 300 && is_array( $j ) && empty( $j['error'] ) ) {
		return $j;
	}
	$msg   = isset( $j['error']['message'] ) ? $j['error']['message'] : 'Respuesta HTTP ' . $code;
	$ecode = isset( $j['error']['code'] ) ? (int) $j['error']['code'] : 0;
	// Token vencido o inválido (190), permisos (10, 200-299) y parámetros inválidos (100): reintentar no sirve.
	$fatal = in_array( $ecode, array( 190, 10, 100, 102 ), true ) || ( $ecode >= 200 && $ecode < 300 );
	return new WP_Error( 'fsr_api', $msg . ( $ecode ? ' (código ' . $ecode . ')' : '' ), array( 'fatal' => $fatal ) );
}

function fsr_post_fb( $post, $img, $o ) {
	$caption = fsr_caption( $post, $o['tpl_fb'], $o );
	if ( 'link' === $o['fb_mode'] ) {
		$r = fsr_graph( 'POST', $o['page_id'] . '/feed', array( 'message' => $caption, 'link' => get_permalink( $post ) ) );
	} else {
		$r = fsr_graph( 'POST', $o['page_id'] . '/photos', array( 'url' => $img['url'], 'message' => $caption, 'published' => 'true' ) );
	}
	if ( is_wp_error( $r ) ) {
		return $r;
	}
	return isset( $r['post_id'] ) ? $r['post_id'] : ( isset( $r['id'] ) ? $r['id'] : 'ok' );
}

function fsr_post_ig( $post, $img, $o ) {
	$pid = $post->ID;
	$cid = get_post_meta( $pid, '_fsr_ig_container', true );
	if ( ! $cid ) {
		$r = fsr_graph( 'POST', $o['ig_id'] . '/media', array( 'image_url' => $img['url'], 'caption' => fsr_caption( $post, $o['tpl_ig'], $o ) ) );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		$cid = $r['id'];
		update_post_meta( $pid, '_fsr_ig_container', $cid );
	}
	
	// Polling optimizado para no exceder timeouts de Hostinger / LiteSpeed
	$ready = false;
	for ( $i = 0; $i < 4; $i++ ) {
		$s = fsr_graph( 'GET', $cid, array( 'fields' => 'status_code' ) );
		if ( is_wp_error( $s ) ) {
			return $s;
		}
		$status = isset( $s['status_code'] ) ? $s['status_code'] : '';
		if ( 'FINISHED' === $status ) {
			$ready = true;
			break;
		}
		if ( 'ERROR' === $status || 'EXPIRED' === $status ) {
			delete_post_meta( $pid, '_fsr_ig_container' );
			return new WP_Error( 'fsr_ig', 'Instagram no aceptó la imagen (estado ' . $status . ').', array( 'fatal' => false ) );
		}
		sleep( 2 );
	}
	if ( ! $ready ) {
		// No se descarta el contenedor, se programa reintento en el próximo ciclo
		return new WP_Error( 'fsr_ig', 'Instagram continúa procesando la imagen. Se reintentará en breve.', array( 'fatal' => false ) );
	}
	$r = fsr_graph( 'POST', $o['ig_id'] . '/media_publish', array( 'creation_id' => $cid ) );
	if ( is_wp_error( $r ) ) {
		return $r;
	}
	delete_post_meta( $pid, '_fsr_ig_container' );
	return $r['id'];
}

/* ======================================================================
 * Cuándo publicar (Hooks de publicación)
 * ====================================================================== */

add_action( 'transition_post_status', 'fsr_on_transition', 10, 3 );
function fsr_on_transition( $new, $old, $post ) {
	if ( 'publish' !== $new || 'publish' === $old || 'post' !== $post->post_type || wp_is_post_revision( $post->ID ) || defined( 'WP_IMPORTING' ) ) {
		return;
	}
	$o = fsr_opts();
	if ( get_post_meta( $post->ID, '_fsr_skip', true ) ) {
		return;
	}
	$age = time() - strtotime( $post->post_date_gmt . ' UTC' );
	if ( $age > (int) $o['max_age'] * HOUR_IN_SECONDS ) {
		return;
	}
	if ( ! wp_next_scheduled( 'fsr_run', array( $post->ID, 1 ) ) ) {
		wp_schedule_single_event( time() + max( 1, (int) $o['delay'] ) * MINUTE_IN_SECONDS, 'fsr_run', array( $post->ID, 1 ) );
	}
}

add_action( 'fsr_run', 'fsr_run', 10, 2 );
function fsr_run( $post_id, $attempt = 1 ) {
	$post = get_post( $post_id );
	if ( ! $post || 'publish' !== $post->post_status || 'post' !== $post->post_type ) {
		return;
	}
	if ( get_post_meta( $post_id, '_fsr_skip', true ) ) {
		return;
	}
	$lock = 'fsr_lock_' . (int) $post_id;
	if ( get_transient( $lock ) ) {
		return;
	}
	set_transient( $lock, 1, 3 * MINUTE_IN_SECONDS );

	$o   = fsr_opts();
	$img = fsr_make_image( $post_id );
	if ( is_wp_error( $img ) ) {
		fsr_log( $post_id, 'img', 'error', $img->get_error_message() );
		update_post_meta( $post_id, '_fsr_status', 'Error al generar la imagen' );
		delete_transient( $lock );
		if ( $attempt < 3 ) {
			wp_schedule_single_event( time() + 300 * $attempt, 'fsr_run', array( $post_id, $attempt + 1 ) );
		}
		return;
	}

	$retry   = false;
	$summary = array();
	foreach ( array( 'fb' => 'Facebook', 'ig' => 'Instagram' ) as $net => $label ) {
		if ( empty( $o[ $net . '_on' ] ) ) {
			continue;
		}
		if ( get_post_meta( $post_id, '_fsr_' . $net, true ) ) {
			$summary[] = $label . ': publicado';
			continue;
		}
		if ( ! empty( $o['dry_run'] ) ) {
			fsr_log( $post_id, $net, 'dry', 'Modo prueba: se habría publicado en ' . $label . '. Imagen: ' . $img['url'] );
			$summary[] = $label . ': prueba (no se publicó)';
			continue;
		}
		$id_ok = 'fb' === $net ? ! empty( $o['page_id'] ) : ! empty( $o['ig_id'] );
		if ( empty( $o['token'] ) || ! $id_ok ) {
			fsr_log( $post_id, $net, 'error', 'Faltan el token o el ID de ' . $label . ' en los ajustes.' );
			$summary[] = $label . ': faltan datos';
			continue;
		}
		$r = 'fb' === $net ? fsr_post_fb( $post, $img, $o ) : fsr_post_ig( $post, $img, $o );
		if ( is_wp_error( $r ) ) {
			$d = $r->get_error_data();
			fsr_log( $post_id, $net, 'error', $r->get_error_message() . ( empty( $d['fatal'] ) ? '' : ' (no se reintenta)' ) );
			$summary[] = $label . ': error';
			if ( empty( $d['fatal'] ) ) {
				$retry = true;
			}
		} else {
			update_post_meta( $post_id, '_fsr_' . $net, $r );
			fsr_log( $post_id, $net, 'ok', 'Publicado en ' . $label . ' (' . $r . ').' );
			$summary[] = $label . ': publicado';
		}
	}
	update_post_meta( $post_id, '_fsr_status', implode( ' · ', $summary ) . ' — ' . wp_date( 'd/m H:i' ) );
	delete_transient( $lock );
	if ( $retry && $attempt < 3 ) {
		wp_schedule_single_event( time() + 300 * $attempt, 'fsr_run', array( $post_id, $attempt + 1 ) );
	}
}

/* ======================================================================
 * Acciones del administrador
 * ====================================================================== */

function fsr_back( $default = '' ) {
	$to = wp_get_referer();
	wp_safe_redirect( $to ? $to : ( $default ? $default : admin_url( 'options-general.php?page=fsr' ) ) );
	exit;
}

/* Publicar ahora / reintentar (solo las redes que faltan). */
add_action( 'admin_post_fsr_now', 'fsr_admin_now' );
function fsr_admin_now() {
	$pid = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;
	if ( ! $pid || ! current_user_can( 'edit_post', $pid ) ) {
		wp_die( esc_html__( 'No autorizado.', 'free-social-replicator' ) );
	}
	check_admin_referer( 'fsr_now_' . $pid );
	delete_transient( 'fsr_lock_' . $pid );
	fsr_run( $pid, 1 );
	fsr_notice( 'info', 'Listo. Estado: ' . get_post_meta( $pid, '_fsr_status', true ) );
	fsr_back();
}

/* Olvidar publicaciones previas (para poder volver a publicar la nota). */
add_action( 'admin_post_fsr_reset', 'fsr_admin_reset' );
function fsr_admin_reset() {
	$pid = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;
	if ( ! $pid || ! current_user_can( 'edit_post', $pid ) ) {
		wp_die( esc_html__( 'No autorizado.', 'free-social-replicator' ) );
	}
	check_admin_referer( 'fsr_reset_' . $pid );
	$keys = array( '_fsr_fb', '_fsr_ig', '_fsr_ig_container', '_fsr_status' );
	foreach ( $keys as $k ) {
		delete_post_meta( $pid, $k );
	}
	fsr_notice( 'success', 'Se borró el registro de publicaciones de esta nota. Ya se puede volver a publicar.' );
	fsr_back();
}

/* Vista previa de la imagen (sin publicar nada). */
add_action( 'admin_post_fsr_preview', 'fsr_admin_preview' );
function fsr_admin_preview() {
	$pid = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;
	if ( ! $pid || ! current_user_can( 'edit_post', $pid ) ) {
		wp_die( esc_html__( 'No autorizado.', 'free-social-replicator' ) );
	}
	check_admin_referer( 'fsr_preview_' . $pid );
	$img = fsr_make_image( $pid );
	if ( is_wp_error( $img ) ) {
		wp_die( esc_html( $img->get_error_message() ) );
	}
	nocache_headers();
	header( 'Content-Type: image/jpeg' );
	header( 'X-Content-Type-Options: nosniff' );
	readfile( $img['path'] );
	exit;
}

/* Guardar ajustes. */
add_action( 'admin_post_fsr_save', 'fsr_admin_save' );
function fsr_admin_save() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'No autorizado.', 'free-social-replicator' ) );
	}
	check_admin_referer( 'fsr_save' );
	$old = fsr_opts();
	$p   = wp_unslash( $_POST );
	$new = $old;
	$new['dry_run']  = empty( $p['dry_run'] ) ? '0' : '1';
	$new['fb_on']    = empty( $p['fb_on'] ) ? '0' : '1';
	$new['ig_on']    = empty( $p['ig_on'] ) ? '0' : '1';
	$new['try_font'] = empty( $p['try_font'] ) ? '0' : '1';
	$new['page_id']  = preg_replace( '/[^0-9]/', '', isset( $p['page_id'] ) ? $p['page_id'] : '' );
	$new['ig_id']    = preg_replace( '/[^0-9]/', '', isset( $p['ig_id'] ) ? $p['ig_id'] : '' );
	
	if ( isset( $p['token'] ) ) {
		$sanitized_token = sanitize_text_field( $p['token'] );
		if ( '' !== trim( $sanitized_token ) ) {
			$new['token'] = preg_replace( '/\s+/', '', $sanitized_token );
		}
	}
	if ( ! empty( $p['clear_token'] ) ) {
		$new['token'] = '';
	}
	
	$api_ver_input   = isset( $p['api_ver'] ) ? sanitize_text_field( $p['api_ver'] ) : '';
	$new['api_ver']  = preg_match( '/^v\d{1,3}\.\d$/', $api_ver_input ) ? $api_ver_input : $old['api_ver'];
	$new['delay']    = max( 1, min( 120, (int) ( isset( $p['delay'] ) ? $p['delay'] : 3 ) ) );
	$new['max_age']  = max( 1, min( 168, (int) ( isset( $p['max_age'] ) ? $p['max_age'] : 24 ) ) );
	$new['fb_mode']  = ( isset( $p['fb_mode'] ) && 'link' === $p['fb_mode'] ) ? 'link' : 'photo';
	$new['img_fmt']  = ( isset( $p['img_fmt'] ) && 'square' === $p['img_fmt'] ) ? 'square' : 'portrait';
	$new['tpl_fb']   = sanitize_textarea_field( isset( $p['tpl_fb'] ) ? $p['tpl_fb'] : $old['tpl_fb'] );
	$new['tpl_ig']   = sanitize_textarea_field( isset( $p['tpl_ig'] ) ? $p['tpl_ig'] : $old['tpl_ig'] );
	$new['hashtags'] = sanitize_text_field( isset( $p['hashtags'] ) ? $p['hashtags'] : $old['hashtags'] );
	
	update_option( 'fsr_opts', $new, false );
	fsr_notice( 'success', 'Ajustes guardados.' );
	fsr_back();
}

/* Probar la conexión y detectar el Instagram vinculado a la página. */
add_action( 'admin_post_fsr_test', 'fsr_admin_test' );
function fsr_admin_test() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'No autorizado.', 'free-social-replicator' ) );
	}
	check_admin_referer( 'fsr_test' );
	$o = fsr_opts();
	if ( empty( $o['token'] ) || empty( $o['page_id'] ) ) {
		fsr_notice( 'error', 'Cargá primero el ID de la página y el token y guardá los ajustes.' );
		fsr_back();
	}
	$r = fsr_graph( 'GET', $o['page_id'], array( 'fields' => 'name,instagram_business_account{id,username}' ) );
	if ( is_wp_error( $r ) ) {
		fsr_notice( 'error', 'No se pudo conectar: ' . fsr_scrub( $r->get_error_message() ) );
		fsr_back();
	}
	$msg = 'Facebook conectado: página "' . ( isset( $r['name'] ) ? $r['name'] : '?' ) . '".';
	if ( ! empty( $r['instagram_business_account']['id'] ) ) {
		$ig = $r['instagram_business_account'];
		$msg .= ' Instagram vinculado: @' . ( isset( $ig['username'] ) ? $ig['username'] : '?' ) . ' (ID ' . $ig['id'] . ').';
		if ( $o['ig_id'] !== $ig['id'] ) {
			$o['ig_id'] = preg_replace( '/[^0-9]/', '', $ig['id'] );
			update_option( 'fsr_opts', $o, false );
			$msg .= ' El ID de Instagram se guardó solo.';
		}
	} else {
		$msg .= ' Esa página no tiene una cuenta de Instagram profesional vinculada.';
	}
	fsr_notice( 'success', $msg );
	fsr_back();
}

/* ======================================================================
 * Cuadro en la edición de la nota (Metabox)
 * ====================================================================== */

add_action( 'add_meta_boxes', 'fsr_add_metabox' );
function fsr_add_metabox() {
	add_meta_box( 'fsr_metabox', 'Free Social Replicator', 'fsr_metabox', 'post', 'side', 'default' );
}

function fsr_metabox( $post ) {
	wp_nonce_field( 'fsr_meta', 'fsr_meta_nonce' );
	$skip   = get_post_meta( $post->ID, '_fsr_skip', true );
	$status = get_post_meta( $post->ID, '_fsr_status', true );
	$o      = fsr_opts();
	echo '<p><label><input type="checkbox" name="fsr_skip" value="1" ' . checked( $skip, '1', false ) . '> No publicar esta nota en redes</label></p>';
	if ( ! empty( $o['dry_run'] ) ) {
		echo '<p style="color:#b45309"><strong>Modo prueba activo:</strong> no se publica nada de verdad.</p>';
	}
	if ( $status ) {
		echo '<p><strong>Estado:</strong> ' . esc_html( $status ) . '</p>';
	}
	if ( 'publish' === $post->post_status ) {
		$now   = wp_nonce_url( admin_url( 'admin-post.php?action=fsr_now&post=' . $post->ID ), 'fsr_now_' . $post->ID );
		$prev  = wp_nonce_url( admin_url( 'admin-post.php?action=fsr_preview&post=' . $post->ID ), 'fsr_preview_' . $post->ID );
		$reset = wp_nonce_url( admin_url( 'admin-post.php?action=fsr_reset&post=' . $post->ID ), 'fsr_reset_' . $post->ID );
		echo '<p><a class="button" href="' . esc_url( $now ) . '">Publicar ahora / reintentar</a></p>';
		echo '<p><a href="' . esc_url( $prev ) . '" target="_blank" rel="noopener">Ver la imagen que se publicaría</a></p>';
		echo '<p><a href="' . esc_url( $reset ) . '" onclick="return confirm(\'Se borra el registro de esta nota para poder volver a publicarla. Si ya está publicada, se duplicaría. ¿Seguir?\');">Olvidar publicaciones previas</a></p>';
	} else {
		echo '<p class="description">Al publicar la nota, se enviará a las redes unos minutos después (según los ajustes).</p>';
	}
}

add_action( 'save_post_post', 'fsr_save_metabox', 10, 1 );
function fsr_save_metabox( $post_id ) {
	if ( ! isset( $_POST['fsr_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fsr_meta_nonce'] ) ), 'fsr_meta' ) ) {
		return;
	}
	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	if ( empty( $_POST['fsr_skip'] ) ) {
		delete_post_meta( $post_id, '_fsr_skip' );
	} else {
		update_post_meta( $post_id, '_fsr_skip', '1' );
	}
}

/* Columna en la lista de entradas de WordPress. */
add_filter( 'manage_post_posts_columns', function ( $cols ) {
	$cols['fsr_social'] = 'Redes (FSR)';
	return $cols;
} );

add_action( 'manage_post_posts_custom_column', function ( $col, $post_id ) {
	if ( 'fsr_social' !== $col ) {
		return;
	}
	$fb = get_post_meta( $post_id, '_fsr_fb', true );
	$ig = get_post_meta( $post_id, '_fsr_ig', true );
	if ( get_post_meta( $post_id, '_fsr_skip', true ) ) {
		echo 'Omitida';
	} elseif ( $fb || $ig ) {
		echo ( $fb ? 'FB ✓ ' : '' ) . ( $ig ? 'IG ✓' : '' );
	} else {
		echo '—';
	}
}, 10, 2 );

/* ======================================================================
 * Página de ajustes
 * ====================================================================== */

add_action( 'admin_menu', 'fsr_register_admin_menu' );
function fsr_register_admin_menu() {
	add_options_page( 'Free Social Replicator', 'Social Replicator', 'manage_options', 'fsr', 'fsr_settings_page' );
}

function fsr_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$o       = fsr_opts();
	$log     = get_option( 'fsr_log', array() );
	$gd      = function_exists( 'imagettftext' );
	$last    = get_posts( array( 'numberposts' => 1, 'post_status' => 'publish', 'post_type' => 'post' ) );
	$pending = 0;
	foreach ( (array) _get_cron_array() as $ts => $hooks ) {
		if ( isset( $hooks['fsr_run'] ) ) {
			$pending += count( $hooks['fsr_run'] );
		}
	}
	?>
	<div class="wrap">
		<h1>Free Social Replicator</h1>
		<?php if ( ! $gd ) : ?>
			<div class="notice notice-error"><p>Este servidor no tiene GD con FreeType, necesario para dibujar el título sobre la imagen. Solicitá a tu proveedor de hosting que lo active.</p></div>
		<?php endif; ?>
		<?php if ( ! empty( $o['dry_run'] ) ) : ?>
			<div class="notice notice-warning"><p><strong>Modo prueba activo.</strong> Las notas nuevas generan la imagen y quedan registradas abajo, pero no se publica nada en Facebook ni Instagram. Desactivalo cuando estés listo.</p></div>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="fsr_save">
			<?php wp_nonce_field( 'fsr_save' ); ?>
			<table class="form-table" role="presentation">
				<tr><th>Modo prueba</th><td><label><input type="checkbox" name="dry_run" value="1" <?php checked( $o['dry_run'], '1' ); ?>> No publicar de verdad (solo generar la imagen y registrar)</label></td></tr>
				<tr><th>Redes</th><td>
					<label><input type="checkbox" name="fb_on" value="1" <?php checked( $o['fb_on'], '1' ); ?>> Facebook</label>
					&nbsp; <label><input type="checkbox" name="ig_on" value="1" <?php checked( $o['ig_on'], '1' ); ?>> Instagram</label>
				</td></tr>
				<tr><th><label for="page_id">ID de la página de Facebook</label></th><td><input id="page_id" name="page_id" type="text" class="regular-text" value="<?php echo esc_attr( $o['page_id'] ); ?>"></td></tr>
				<tr><th><label for="ig_id">ID de Instagram (profesional)</label></th><td><input id="ig_id" name="ig_id" type="text" class="regular-text" value="<?php echo esc_attr( $o['ig_id'] ); ?>"><p class="description">Si lo dejás vacío, el botón "Probar conexión con Meta" lo detecta desde la página de Facebook vinculada.</p></td></tr>
				<tr><th><label for="token">Token de acceso de la página</label></th><td>
					<input id="token" name="token" type="password" class="regular-text" autocomplete="new-password" placeholder="<?php echo $o['token'] ? '(guardado; escribí uno nuevo para reemplazarlo)' : 'Pegá el token aquí'; ?>">
					<?php if ( $o['token'] ) : ?><label style="margin-left:8px"><input type="checkbox" name="clear_token" value="1"> Borrar el token guardado</label><?php endif; ?>
					<p class="description">Token de página de larga duración (Page Access Token de Meta). No se muestra una vez guardado ni se escribe en el registro.</p>
				</td></tr>
				<tr><th><label for="api_ver">Versión de la API</label></th><td><input id="api_ver" name="api_ver" type="text" class="small-text" value="<?php echo esc_attr( $o['api_ver'] ); ?>"> <span class="description">Ejemplo: v25.0</span></td></tr>
				<tr><th><label for="delay">Esperar antes de publicar</label></th><td><input id="delay" name="delay" type="number" min="1" max="120" class="small-text" value="<?php echo esc_attr( $o['delay'] ); ?>"> minutos <span class="description">(da tiempo a corregir la nota recién publicada)</span></td></tr>
				<tr><th><label for="max_age">Ignorar notas más viejas de</label></th><td><input id="max_age" name="max_age" type="number" min="1" max="168" class="small-text" value="<?php echo esc_attr( $o['max_age'] ); ?>"> horas <span class="description">(evita publicar notas antiguas por error)</span></td></tr>
				<tr><th>Formato de la imagen</th><td>
					<label><input type="radio" name="img_fmt" value="portrait" <?php checked( $o['img_fmt'], 'portrait' ); ?>> Vertical 4:5 (1080×1350)</label> &nbsp;
					<label><input type="radio" name="img_fmt" value="square" <?php checked( $o['img_fmt'], 'square' ); ?>> Cuadrada (1080×1080)</label>
				</td></tr>
				<tr><th>Facebook publica</th><td>
					<label><input type="radio" name="fb_mode" value="photo" <?php checked( $o['fb_mode'], 'photo' ); ?>> La imagen con título + texto y enlace</label><br>
					<label><input type="radio" name="fb_mode" value="link" <?php checked( $o['fb_mode'], 'link' ); ?>> Solo el enlace (Facebook arma la tarjeta; la imagen con título no se usa)</label>
				</td></tr>
				<tr><th><label for="tpl_fb">Texto de Facebook</label></th><td><textarea id="tpl_fb" name="tpl_fb" rows="5" class="large-text"><?php echo esc_textarea( $o['tpl_fb'] ); ?></textarea>
					<p class="description">Podés usar las variables: {titulo} {resumen} {enlace} {categoria} {hashtags}.</p></td></tr>
				<tr><th><label for="tpl_ig">Texto de Instagram</label></th><td><textarea id="tpl_ig" name="tpl_ig" rows="6" class="large-text"><?php echo esc_textarea( $o['tpl_ig'] ); ?></textarea>
					<p class="description">En Instagram los enlaces del texto no son clickeables; por eso se recomienda indicar "enlace en la bio".</p></td></tr>
				<tr><th><label for="hashtags">Hashtags por defecto</label></th><td><input id="hashtags" name="hashtags" type="text" class="large-text" value="<?php echo esc_attr( $o['hashtags'] ); ?>"></td></tr>
				<tr><th>Tipografía de título</th><td><label><input type="checkbox" name="try_font" value="1" <?php checked( $o['try_font'], '1' ); ?>> Intentar usar Josefin Sans (descarga automática desde Google Fonts); si no se puede, se usa Poppins (incluida)</label></td></tr>
			</table>
			<?php submit_button( 'Guardar ajustes' ); ?>
		</form>

		<h2>Herramientas</h2>
		<p>
			<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=fsr_test' ), 'fsr_test' ) ); ?>">Probar conexión con Meta</a>
			<?php if ( $last ) : ?>
				&nbsp;<a class="button" target="_blank" rel="noopener" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=fsr_preview&post=' . $last[0]->ID ), 'fsr_preview_' . $last[0]->ID ) ); ?>">Ver la imagen de la última nota</a>
			<?php endif; ?>
		</p>
		<p class="description">Envíos programados en espera: <?php echo (int) $pending; ?>. Si tu WordPress tiene WP-Cron desactivado (DISABLE_WP_CRON), asegurate de tener un Cron de sistema configurado en tu panel de hosting.</p>

		<h2>Actividad reciente</h2>
		<?php if ( empty( $log ) ) : ?>
			<p>Todavía no hay actividad registrada.</p>
		<?php else : ?>
			<table class="widefat striped" style="max-width:1000px">
				<thead><tr><th>Fecha</th><th>Nota</th><th>Red</th><th>Resultado</th><th>Detalle</th></tr></thead>
				<tbody>
				<?php foreach ( $log as $row ) : ?>
					<tr>
						<td><?php echo esc_html( wp_date( 'd/m H:i', $row['t'] ) ); ?></td>
						<td><?php echo $row['p'] ? '<a href="' . esc_url( get_edit_post_link( $row['p'] ) ) . '">#' . (int) $row['p'] . '</a>' : '—'; ?></td>
						<td><?php echo esc_html( strtoupper( $row['n'] ) ); ?></td>
						<td><?php echo esc_html( array( 'ok' => 'Publicado', 'error' => 'Error', 'dry' => 'Prueba' )[ $row['s'] ] ?? $row['s'] ); ?></td>
						<td><?php echo esc_html( $row['m'] ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
	<?php
}
