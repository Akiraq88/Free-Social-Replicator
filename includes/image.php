<?php
/**
 * Free Social Replicator (FSR)
 * Generador de imagen destacada para redes sociales (1080 px de ancho).
 * Función pura: no depende de WordPress, solo de GD con FreeType.
 */

if ( ! defined( 'ABSPATH' ) && ! defined( 'FSR_TESTING' ) ) {
	exit;
}

/**
 * Divide el texto en líneas que entren en $maxw píxeles.
 */
function fsr_wrap( $text, $font, $size, $maxw ) {
	$words = preg_split( '/\s+/u', trim( $text ) );
	$lines = array();
	$cur   = '';
	foreach ( $words as $wd ) {
		if ( '' === $wd ) {
			continue;
		}
		$try = '' === $cur ? $wd : $cur . ' ' . $wd;
		$bb  = imagettfbbox( $size, 0, $font, $try );
		$tw  = $bb ? ( $bb[2] - $bb[0] ) : 0;
		if ( $tw <= $maxw || '' === $cur ) {
			$cur = $try;
		} else {
			$lines[] = $cur;
			$cur     = $wd;
		}
	}
	if ( '' !== $cur ) {
		$lines[] = $cur;
	}
	return $lines;
}

/**
 * Carga una imagen (JPEG, PNG, WebP o GIF) o devuelve false si no se puede o es demasiado grande.
 */
function fsr_load_image( $path, $max_pixels = 36000000 ) {
	if ( '' === $path || ! is_readable( $path ) ) {
		return false;
	}
	$info = @getimagesize( $path );
	if ( ! $info || ( (int) $info[0] * (int) $info[1] ) > $max_pixels ) {
		return false;
	}
	$data = @file_get_contents( $path );
	if ( false === $data ) {
		return false;
	}
	$im = @imagecreatefromstring( $data );
	return $im ? $im : false;
}

/**
 * Dibuja la imagen y la guarda como JPEG.
 *
 * Claves de $a: out, w, h, photo, logo, title, category, footer, font_title, font_label.
 *
 * @return true|string true si salió bien; si no, el texto del error.
 */
function fsr_render( array $a ) {
	if ( ! function_exists( 'imagettftext' ) || ! function_exists( 'imagecreatetruecolor' ) ) {
		return 'El servidor no tiene GD con FreeType (imagettftext).';
	}
	$w = (int) $a['w'];
	$h = (int) $a['h'];
	if ( ! is_readable( $a['font_title'] ) || ! is_readable( $a['font_label'] ) ) {
		return 'No se encontró la tipografía.';
	}

	$im = imagecreatetruecolor( $w, $h );
	imagealphablending( $im, true );
	imagefill( $im, 0, 0, imagecolorallocate( $im, 21, 21, 25 ) );

	// Foto de fondo, recortada al centro (un poco hacia arriba, donde suelen estar las caras).
	$src = fsr_load_image( $a['photo'] );
	if ( $src ) {
		$sw    = imagesx( $src );
		$sh    = imagesy( $src );
		$scale = max( $w / $sw, $h / $sh );
		$cw    = $w / $scale;
		$ch    = $h / $scale;
		$sx    = ( $sw - $cw ) / 2;
		$sy    = ( $sh - $ch ) * 0.30;
		imagecopyresampled( $im, $src, 0, 0, (int) $sx, (int) $sy, $w, $h, (int) round( $cw ), (int) round( $ch ) );
		imagedestroy( $src );
	}

	// Degradado oscuro abajo (para leer el título) y suave arriba (para el logo).
	$y0 = (int) ( $h * 0.34 );
	for ( $y = $y0; $y < $h; $y++ ) {
		$t       = ( $y - $y0 ) / ( $h - $y0 );
		$opacity = min( 0.96, 0.97 * pow( $t, 1.12 ) );
		imageline( $im, 0, $y, $w, $y, imagecolorallocatealpha( $im, 11, 11, 13, (int) round( 127 * ( 1 - $opacity ) ) ) );
	}
	$top = 280;
	for ( $y = 0; $y < $top; $y++ ) {
		$opacity = 0.55 * ( 1 - $y / $top );
		imageline( $im, 0, $y, $w, $y, imagecolorallocatealpha( $im, 11, 11, 13, (int) round( 127 * ( 1 - $opacity ) ) ) );
	}

	$mx = 72;

	// Logo arriba a la izquierda.
	$logo = fsr_load_image( $a['logo'], 16000000 );
	if ( $logo ) {
		$lw = imagesx( $logo );
		$lh = imagesy( $logo );
		$th = 120;
		$tw = (int) round( $lw * ( $th / $lh ) );
		imagecopyresampled( $im, $logo, $mx, 56, 0, 0, $tw, $th, $lw, $lh );
		imagedestroy( $logo );
	}

	// Título: se achica la letra hasta que entre en 5 líneas (máximo 6 en el tamaño más chico).
	$maxw  = $w - 2 * $mx;
	$title = trim( preg_replace( '/\s+/u', ' ', $a['title'] ) );
	$lines = array();
	$size  = 78;
	foreach ( array( 78, 70, 62, 56, 50, 46 ) as $s ) {
		$size  = $s;
		$lines = fsr_wrap( $title, $a['font_title'], $s, $maxw );
		if ( count( $lines ) <= 5 ) {
			break;
		}
	}
	if ( count( $lines ) > 6 ) {
		$lines    = array_slice( $lines, 0, 6 );
		$lines[5] = rtrim( $lines[5], ' ,.;:-' ) . '…';
	}
	$line_h  = (int) round( $size * 1.28 );
	$y_end   = $h - 150;
	$y_top   = $y_end - count( $lines ) * $line_h;
	$white   = imagecolorallocate( $im, 242, 242, 244 );
	$shadow  = imagecolorallocatealpha( $im, 0, 0, 0, 80 );
	$y_base  = $y_top + $size;
	foreach ( $lines as $ln ) {
		imagettftext( $im, $size, 0, $mx + 2, $y_base + 2, $shadow, $a['font_title'], $ln );
		imagettftext( $im, $size, 0, $mx, $y_base, $white, $a['font_title'], $ln );
		$y_base += $line_h;
	}

	// Categoría (dorado) y barra de acento (rosa) sobre el título.
	$y_label = $y_top - 28;
	if ( '' !== trim( $a['category'] ) ) {
		$gold = imagecolorallocate( $im, 227, 177, 92 );
		imagettftext( $im, 28, 0, $mx, $y_label, $gold, $a['font_label'], mb_strtoupper( $a['category'], 'UTF-8' ) );
		$y_bar = $y_label - 28 - 26;
	} else {
		$y_bar = $y_top - 36;
	}
	imagefilledrectangle( $im, $mx, $y_bar, $mx + 96, $y_bar + 10, imagecolorallocate( $im, 255, 92, 147 ) );

	// Pie: dirección del sitio.
	$muted = imagecolorallocate( $im, 180, 180, 190 );
	imagettftext( $im, 28, 0, $mx, $h - 68, $muted, $a['font_label'], $a['footer'] );

	$ok = imagejpeg( $im, $a['out'], 90 );
	imagedestroy( $im );
	return $ok ? true : 'No se pudo guardar la imagen en el servidor.';
}
