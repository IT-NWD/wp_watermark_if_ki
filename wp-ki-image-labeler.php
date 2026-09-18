<?php
/**
 * Plugin Name: WP KI-Badge Plugin
 * Description: Kennzeichnet KI-generierte Bilder im Medien-Manager und optional im Frontend.
 * Version: 0.6.0
 * Author: IT-NWD
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * License: GPL-2.0-or-later
 * Text Domain: wp-ki-image-labeler
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WKI_VERSION', '0.6.0' );
define( 'WKI_FILE', __FILE__ );
define( 'WKI_DIR', plugin_dir_path( __FILE__ ) );

final class WKI_Plugin {
	private static $instance;
	private $option_name = 'wki_settings';

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_filter( 'attachment_fields_to_edit', array( $this, 'media_field' ), 10, 2 );
		add_filter( 'attachment_fields_to_save', array( $this, 'save_media_field' ), 10, 2 );
		add_filter( 'manage_media_columns', array( $this, 'media_column' ) );
		add_action( 'manage_media_custom_column', array( $this, 'media_column_value' ), 10, 2 );
		add_action( 'add_attachment', array( $this, 'auto_detect_attachment' ) );
		add_action( 'edit_attachment', array( $this, 'auto_detect_attachment' ) );
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_filter( 'wp_get_attachment_image_attributes', array( $this, 'image_attributes' ), 10, 3 );
		add_shortcode( 'wki_ai_background', array( $this, 'background_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_style' ) );
		add_action( 'template_redirect', array( $this, 'start_frontend_buffer' ), 1 );
	}

	private function defaults() {
		return array(
			'auto_detect' => true,
			'frontend_badge' => true,
			'badge_text' => 'KI-generiert',
			'show_icon' => true,
			'logo_size' => 16,
			'logo_hover_size' => 30,
			'badge_position' => 'bottom-left',
			'badge_font_size' => 12,
			'badge_padding' => 4,
			'badge_opacity' => 78,
			'font_family' => 'inherit',
			'font_weight' => 600,
			'text_color' => '#FFFFFF',
			'icon_variant' => 'white-transparent',
			'keywords' => "ai-generated\nartificial intelligence\ndall-e\ndalle\nmidjourney\nstable diffusion\nadobe firefly\ngenerative fill\ncomfyui",
		);
	}

	private function settings() {
		return array_replace( $this->defaults(), get_option( $this->option_name, array() ) );
	}

	private function is_editor_context() {
		if ( function_exists( 'fusion_is_builder_frame' ) && fusion_is_builder_frame() ) {
			return true;
		}
		if ( function_exists( 'fusion_is_preview_frame' ) && fusion_is_preview_frame() ) {
			return true;
		}
		if ( function_exists( 'is_preview_only' ) && is_preview_only() ) {
			return true;
		}
		if ( has_filter( 'fusion_builder_live_request' ) && apply_filters( 'fusion_builder_live_request', false ) ) {
			return true;
		}
		if ( isset( $_POST['model'] ) && is_user_logged_in() && current_user_can( 'edit_posts' ) ) {
			return true;
		}
		$editor_parameters = array( 'fb-edit', 'fb_live_editor', 'fusion_builder', 'fusion_builder_live', 'fusion-builder' );
		foreach ( $editor_parameters as $parameter ) {
			if ( isset( $_GET[ $parameter ] ) || isset( $_POST[ $parameter ] ) ) {
				return true;
			}
		}
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		if ( preg_match( '/(?:fb-edit|fb_live_editor|fusion_builder|fusion-builder)/i', $request_uri ) ) {
			return true;
		}
		return (bool) apply_filters( 'wki_is_editor_context', false );
	}

	private function available_fonts() {
		$fonts = array( 'inherit' => 'Theme/Avada', 'Arial, sans-serif' => 'Arial', 'Helvetica, sans-serif' => 'Helvetica', 'Verdana, sans-serif' => 'Verdana', 'Georgia, serif' => 'Georgia' );
		$avada = get_option( 'fusion_options', array() );
		$collect = function ( $value, $key = '' ) use ( &$collect, &$fonts ) {
			if ( is_array( $value ) ) {
				foreach ( $value as $child_key => $child_value ) {
					$collect( $child_value, (string) $child_key );
				}
				return;
			}
			$key = strtolower( (string) $key );
			if ( false === strpos( $key, 'font-family' ) && false === strpos( $key, 'font_family' ) ) {
				return;
			}
			$font = trim( (string) $value );
			if ( preg_match( '/^[\p{L}\d][\p{L}\d _-]*$/u', $font ) && 'inherit' !== strtolower( $font ) ) {
				$fonts[ $font . ', sans-serif' ] = $font;
			}
		};
		$collect( $avada );
		return $fonts;
	}

	public function register_settings() {
		register_setting( 'wki_settings_group', $this->option_name, array( $this, 'sanitize_settings' ) );
	}

	public function sanitize_settings( $input ) {
		$current = $this->settings();
		$allowed_fonts = array_keys( $this->available_fonts() );
		$allowed_weights = array( 300, 400, 500, 600, 700 );
		$font_weight = absint( $input['font_weight'] ?? $current['font_weight'] );
		return array(
			'auto_detect' => ! empty( $input['auto_detect'] ),
			'frontend_badge' => ! empty( $input['frontend_badge'] ),
			'badge_text' => sanitize_text_field( $input['badge_text'] ?? $current['badge_text'] ),
			'show_icon' => ! empty( $input['show_icon'] ),
			'logo_size' => max( 8, min( 48, absint( $input['logo_size'] ?? $current['logo_size'] ) ) ),
			'logo_hover_size' => max( 12, min( 96, absint( $input['logo_hover_size'] ?? $current['logo_hover_size'] ) ) ),
			'badge_position' => in_array( $input['badge_position'] ?? $current['badge_position'], array( 'bottom-left', 'bottom-right', 'top-left', 'top-right' ), true ) ? $input['badge_position'] : $current['badge_position'],
			'badge_font_size' => max( 9, min( 24, absint( $input['badge_font_size'] ?? $current['badge_font_size'] ) ) ),
			'badge_padding' => max( 2, min( 20, absint( $input['badge_padding'] ?? $current['badge_padding'] ) ) ),
			'badge_opacity' => max( 0, min( 100, absint( $input['badge_opacity'] ?? $current['badge_opacity'] ) ) ),
			'font_family' => in_array( $input['font_family'] ?? $current['font_family'], $allowed_fonts, true ) ? $input['font_family'] : $current['font_family'],
			'font_weight' => in_array( $font_weight, $allowed_weights, true ) ? $font_weight : $current['font_weight'],
			'text_color' => $this->sanitize_color( $input['text_color'] ?? $current['text_color'], $current['text_color'] ),
			'icon_variant' => in_array( $input['icon_variant'] ?? $current['icon_variant'], array( 'auto', 'auto-transparent', 'black', 'black-transparent', 'white', 'white-transparent' ), true ) ? $input['icon_variant'] : $current['icon_variant'],
			'keywords' => sanitize_textarea_field( $input['keywords'] ?? $current['keywords'] ),
		);
	}

	private function sanitize_color( $color, $fallback ) {
		return preg_match( '/^#[0-9a-fA-F]{6}$/', $color ) ? strtoupper( $color ) : $fallback;
	}

	public function media_field( $form_fields, $post ) {
		$form_fields['wki_is_ai'] = array(
			'label' => 'KI-Bild',
			'input' => 'html',
			'html' => sprintf( '<label><input type="checkbox" name="attachments[%1$d][wki_is_ai]" value="1" %2$s> Als KI-generiertes Bild kennzeichnen</label><p class="description">Manuelle Kennzeichnungen haben Vorrang vor der automatischen Metadatenerkennung.</p>', $post->ID, checked( $this->is_ai( $post->ID ), true, false ) ),
		);
		$form_fields['wki_ai_type'] = array(
			'label' => 'KI-Inhaltstyp',
			'input' => 'html',
			'html' => sprintf( '<select name="attachments[%1$d][wki_ai_type]"><option value="generated" %2$s>Vollständig KI-generiert</option><option value="modified" %3$s>Teilweise KI-modifiziert</option><option value="basic" %4$s>Grundlegendes KI-Symbol</option></select><p class="description">Orientiert sich an den EU-Icons zur Kennzeichnung von KI-generierten Inhalten.</p>', $post->ID, selected( $this->attachment_type( $post->ID ), 'generated', false ), selected( $this->attachment_type( $post->ID ), 'modified', false ), selected( $this->attachment_type( $post->ID ), 'basic', false ) ),
		);
		return $form_fields;
	}

	public function save_media_field( $post, $attachment ) {
		if ( ! array_key_exists( 'wki_is_ai', $attachment ) && ! array_key_exists( 'wki_ai_type', $attachment ) ) {
			return $post;
		}
		if ( isset( $attachment['wki_is_ai'] ) ) {
			$type = sanitize_key( $attachment['wki_ai_type'] ?? 'generated' );
			$type = in_array( $type, array( 'generated', 'modified', 'basic' ), true ) ? $type : 'generated';
			update_post_meta( $post['ID'], '_wki_is_ai', '1' );
			update_post_meta( $post['ID'], '_wki_ai_source', 'manual' );
			update_post_meta( $post['ID'], '_wki_ai_type', $type );
			if ( '' === trim( (string) get_post_meta( $post['ID'], '_wp_attachment_image_alt', true ) ) ) {
				update_post_meta( $post['ID'], '_wp_attachment_image_alt', $this->default_alt_text( $type ) );
			}
		} else {
			update_post_meta( $post['ID'], '_wki_is_ai', '0' );
			update_post_meta( $post['ID'], '_wki_ai_source', 'manual' );
		}
		return $post;
	}

	private function default_alt_text( $type ) {
		$labels = array( 'generated' => 'KI-generiertes Bild', 'modified' => 'Teilweise KI-modifiziertes Bild', 'basic' => 'KI-gekennzeichnetes Bild' );
		return $labels[ $type ] ?? $labels['generated'];
	}

	public function media_column( $columns ) {
		$columns['wki_ai'] = 'KI-Bild';
		return $columns;
	}

	public function media_column_value( $column, $post_id ) {
		if ( 'wki_ai' === $column ) {
			echo $this->is_ai( $post_id ) ? '<span aria-label="KI-generiert">Ja</span>' : '—';
		}
	}

	public function auto_detect_attachment( $attachment_id ) {
		$settings = $this->settings();
		if ( ! $settings['auto_detect'] || 'manual' === get_post_meta( $attachment_id, '_wki_ai_source', true ) ) {
			return;
		}
		$file = get_attached_file( $attachment_id );
		$metadata = $file ? wp_read_image_metadata( $file ) : array();
		$xmp = $file ? $this->read_xmp( $file ) : '';
		$haystack = strtolower( wp_json_encode( $metadata ) . ' ' . $xmp );
		$keywords = preg_split( '/\r\n|\r|\n/', $settings['keywords'] );
		$matched = array();
		foreach ( $keywords as $keyword ) {
			$keyword = trim( $keyword );
			if ( '' !== $keyword && false !== strpos( $haystack, strtolower( $keyword ) ) ) {
				$matched[] = $keyword;
			}
		}
		if ( $matched ) {
			update_post_meta( $attachment_id, '_wki_is_ai', '1' );
			update_post_meta( $attachment_id, '_wki_ai_source', 'metadata' );
			if ( ! get_post_meta( $attachment_id, '_wki_ai_type', true ) ) {
				update_post_meta( $attachment_id, '_wki_ai_type', 'generated' );
			}
			update_post_meta( $attachment_id, '_wki_ai_matches', $matched );
		}
	}

	private function read_xmp( $file ) {
		if ( ! is_readable( $file ) || filesize( $file ) > 25 * 1024 * 1024 ) {
			return '';
		}
		$contents = file_get_contents( $file );
		if ( false === $contents ) {
			return '';
		}
		$start = strpos( $contents, '<x:xmpmeta' );
		$end = strpos( $contents, '</x:xmpmeta>', $start );
		return false !== $start && false !== $end ? substr( $contents, $start, $end - $start + 12 ) : '';
	}

	private function is_ai( $attachment_id ) {
		return '1' === (string) get_post_meta( $attachment_id, '_wki_is_ai', true );
	}

	private function attachment_type( $attachment_id ) {
		$type = get_post_meta( $attachment_id, '_wki_ai_type', true );
		return in_array( $type, array( 'generated', 'modified', 'basic' ), true ) ? $type : 'generated';
	}

	private function resolved_icon_variant( $attachment_id ) {
		$settings = $this->settings();
		$variant = $settings['icon_variant'];
		if ( ! in_array( $variant, array( 'auto', 'auto-transparent' ), true ) ) {
			return $variant;
		}
		$color = $this->automatic_icon_color( $attachment_id, $settings['badge_position'] );
		return $color . ( 'auto-transparent' === $variant ? '-transparent' : '' );
	}

	private function automatic_icon_color( $attachment_id, $position ) {
		$file = get_attached_file( $attachment_id );
		if ( ! $file || ! is_readable( $file ) || ! wp_attachment_is_image( $attachment_id ) ) {
			return 'white';
		}
		$signature = md5( $file . '|' . (string) filemtime( $file ) . '|' . (string) filesize( $file ) );
		$cache = get_post_meta( $attachment_id, '_wki_auto_icon_contrast', true );
		if ( is_array( $cache ) && isset( $cache[ $position ]['signature'], $cache[ $position ]['color'] ) && hash_equals( $signature, $cache[ $position ]['signature'] ) ) {
			return in_array( $cache[ $position ]['color'], array( 'black', 'white' ), true ) ? $cache[ $position ]['color'] : 'white';
		}
		$luminance = $this->image_region_luminance( $file, $position );
		$color = null !== $luminance && $luminance > 0.179 ? 'black' : 'white';
		$cache = is_array( $cache ) ? $cache : array();
		$cache[ $position ] = array(
			'signature' => $signature,
			'color' => $color,
			'luminance' => null === $luminance ? null : round( $luminance, 4 ),
		);
		update_post_meta( $attachment_id, '_wki_auto_icon_contrast', $cache );
		return $color;
	}

	private function image_region_luminance( $file, $position ) {
		if ( class_exists( 'Imagick' ) ) {
			$luminance = $this->imagick_region_luminance( $file, $position );
			if ( null !== $luminance ) {
				return $luminance;
			}
		}
		return $this->gd_region_luminance( $file, $position );
	}

	private function sample_region( $width, $height, $position ) {
		$sample_width = max( 1, (int) round( $width * 0.35 ) );
		$sample_height = max( 1, (int) round( $height * 0.25 ) );
		$is_right = false !== strpos( $position, 'right' );
		$is_bottom = false !== strpos( $position, 'bottom' );
		return array(
			$is_right ? max( 0, $width - $sample_width ) : 0,
			$is_bottom ? max( 0, $height - $sample_height ) : 0,
			$sample_width,
			$sample_height,
		);
	}

	private function imagick_region_luminance( $file, $position ) {
		$image = null;
		try {
			$image = new Imagick( $file . '[0]' );
			if ( method_exists( $image, 'autoOrient' ) ) {
				$image->autoOrient();
			} elseif ( method_exists( $image, 'autoOrientImage' ) ) {
				$image->autoOrientImage();
			}
			$width = $image->getImageWidth();
			$height = $image->getImageHeight();
			if ( $width < 1 || $height < 1 ) {
				return null;
			}
			list( $x, $y, $sample_width, $sample_height ) = $this->sample_region( $width, $height, $position );
			$image->cropImage( $sample_width, $sample_height, $x, $y );
			if ( $image->getImageAlphaChannel() ) {
				$background = new Imagick();
				$background->newImage( $sample_width, $sample_height, new ImagickPixel( 'white' ) );
				$background->compositeImage( $image, Imagick::COMPOSITE_OVER, 0, 0 );
				$image->clear();
				$image = $background;
			}
			$image->thumbnailImage( 64, 64, true );
			$pixels = $image->exportImagePixels( 0, 0, $image->getImageWidth(), $image->getImageHeight(), 'RGB', Imagick::PIXEL_CHAR );
			return $this->pixel_luminance_average( $pixels );
		} catch ( Throwable $exception ) {
			return null;
		} finally {
			if ( $image instanceof Imagick ) {
				$image->clear();
				$image->destroy();
			}
		}
	}

	private function gd_region_luminance( $file, $position ) {
		if ( ! function_exists( 'gd_info' ) || ! function_exists( 'imagecreatefromstring' ) || filesize( $file ) > 50 * 1024 * 1024 ) {
			return null;
		}
		$contents = file_get_contents( $file );
		if ( false === $contents ) {
			return null;
		}
		$source = @imagecreatefromstring( $contents );
		if ( false === $source ) {
			return null;
		}
		$width = imagesx( $source );
		$height = imagesy( $source );
		list( $x, $y, $sample_width, $sample_height ) = $this->sample_region( $width, $height, $position );
		$target_width = min( 64, $sample_width );
		$target_height = min( 64, $sample_height );
		$sample = imagecreatetruecolor( $target_width, $target_height );
		$white = imagecolorallocate( $sample, 255, 255, 255 );
		imagefill( $sample, 0, 0, $white );
		imagealphablending( $sample, true );
		imagecopyresampled( $sample, $source, 0, 0, $x, $y, $target_width, $target_height, $sample_width, $sample_height );
		$pixels = array();
		for ( $pixel_y = 0; $pixel_y < $target_height; $pixel_y++ ) {
			for ( $pixel_x = 0; $pixel_x < $target_width; $pixel_x++ ) {
				$rgb = imagecolorat( $sample, $pixel_x, $pixel_y );
				$pixels[] = ( $rgb >> 16 ) & 0xFF;
				$pixels[] = ( $rgb >> 8 ) & 0xFF;
				$pixels[] = $rgb & 0xFF;
			}
		}
		imagedestroy( $sample );
		imagedestroy( $source );
		return $this->pixel_luminance_average( $pixels );
	}

	private function pixel_luminance_average( $pixels ) {
		$count = count( $pixels );
		if ( $count < 3 ) {
			return null;
		}
		$total = 0.0;
		$samples = 0;
		for ( $index = 0; $index + 2 < $count; $index += 3 ) {
			$red = $this->linear_color_channel( $pixels[ $index ] / 255 );
			$green = $this->linear_color_channel( $pixels[ $index + 1 ] / 255 );
			$blue = $this->linear_color_channel( $pixels[ $index + 2 ] / 255 );
			$total += 0.2126 * $red + 0.7152 * $green + 0.0722 * $blue;
			$samples++;
		}
		return $samples ? $total / $samples : null;
	}

	private function linear_color_channel( $channel ) {
		return $channel <= 0.04045 ? $channel / 12.92 : pow( ( $channel + 0.055 ) / 1.055, 2.4 );
	}

	private function icon_url( $attachment_id ) {
		$variant = $this->resolved_icon_variant( $attachment_id );
		return plugins_url( 'assets/eu-icons/ai-basic-' . $variant . '.svg', WKI_FILE );
	}

	private function type_icon_url( $attachment_id ) {
		$type = $this->attachment_type( $attachment_id );
		$variant = $this->resolved_icon_variant( $attachment_id );
		return plugins_url( 'assets/eu-icons/ai-' . $type . '-' . $variant . '.svg', WKI_FILE );
	}

	public function image_attributes( $attr, $attachment, $size ) {
		if ( ! $this->is_editor_context() && $this->is_ai( $attachment->ID ) ) {
			$attr['class'] = trim( ( $attr['class'] ?? '' ) . ' wki-ai-image' );
			$attr['data-wki-ai'] = 'true';
			$attr['data-wki-attachment'] = (string) $attachment->ID;
		}
		return $attr;
	}

	public function frontend_badge( $html, $attachment_id, $size, $icon, $attr ) {
		if ( $this->is_editor_context() || ! $this->settings()['frontend_badge'] || ! $this->is_ai( $attachment_id ) ) {
			return $html;
		}
		$this->enqueue_frontend_style();
		return '<span class="wki-ai-wrap">' . $html . $this->badge_markup( $attachment_id ) . '</span>';
	}

	public function start_frontend_buffer() {
		if ( $this->is_editor_context() || is_admin() || wp_doing_ajax() || ! $this->settings()['frontend_badge'] ) {
			return;
		}
		ob_start( array( $this, 'filter_frontend_html' ) );
	}

	public function filter_frontend_html( $html ) {
		if ( $this->is_editor_context() ) {
			return $html;
		}
		$protected = array();
		$html = preg_replace_callback( '/<(script|style)\\b[^>]*>.*?<\\/\\1>/is', function ( $match ) use ( &$protected ) {
			$key = '<!-- WKI-PROTECTED-' . count( $protected ) . ' -->';
			$protected[ $key ] = $match[0];
			return $key;
		}, $html );
		$html = preg_replace_callback( '/<img\\b[^>]*>/i', function ( $match ) {
			$attachment_id = 0;
			if ( preg_match( '/\\bwp-image-(\\d+)\\b/i', $match[0], $id_match ) ) {
				$attachment_id = absint( $id_match[1] );
			} elseif ( preg_match( "/\\bdata-wki-attachment=[\"'](\\d+)[\"']/i", $match[0], $id_match ) ) {
				$attachment_id = absint( $id_match[1] );
			}
			if ( ! $this->is_ai( $attachment_id ) ) {
				return $match[0];
			}
			return '<span class="wki-ai-wrap">' . $match[0] . $this->badge_markup( $attachment_id ) . '</span>';
		}, $html );
		$html = preg_replace_callback( '/<[a-z][^>]*\\bdata-bg(?:-url)?=["\']([^"\']+)["\'][^>]*>/i', function ( $match ) {
			$attachment_id = $this->attachment_id_from_url( html_entity_decode( $match[1] ) );
			if ( ! $this->is_ai( $attachment_id ) ) {
				return $match[0];
			}
			$tag = preg_replace( '/\\sclass=["\']([^"\']*)["\']/i', ' class="$1 wki-ai-background"', $match[0], 1, $class_count );
			if ( ! $class_count ) {
				$tag = preg_replace( '/^(<[a-z]+)/i', '$1 class="wki-ai-background"', $match[0] );
			}
			return $tag . $this->badge_markup( $attachment_id );
		}, $html );
		return strtr( $html, $protected );
	}

	private function attachment_id_from_url( $url ) {
		$attachment_id = attachment_url_to_postid( $url );
		if ( $attachment_id ) {
			return $attachment_id;
		}
		$parts = wp_parse_url( $url );
		if ( empty( $parts['path'] ) ) {
			return 0;
		}
		$path = preg_replace( '/-\\d+x\\d+(?=\\.[^.]+$)/', '', $parts['path'] );
		if ( $path === $parts['path'] ) {
			return 0;
		}
		$original_url = ( ! empty( $parts['scheme'] ) ? $parts['scheme'] . '://' : 'https://' ) . $parts['host'] . $path;
		return attachment_url_to_postid( $original_url );
	}

	public function enqueue_frontend_style() {
		if ( $this->is_editor_context() ) {
			return;
		}
		if ( ! wp_style_is( 'wki-frontend', 'registered' ) ) {
			wp_register_style( 'wki-frontend', plugins_url( 'assets/frontend.css', WKI_FILE ), array(), WKI_VERSION );
		}
		wp_enqueue_style( 'wki-frontend' );
	}

	private function badge_markup( $attachment_id ) {
		$settings = $this->settings();
		$style = sprintf( '--wki-badge-font-family:%s;--wki-badge-font-size:%dpx;--wki-badge-font-weight:%d;--wki-badge-padding:%dpx;--wki-badge-opacity:%d%%;--wki-badge-text-color:%s;--wki-badge-logo-size:%dpx;--wki-badge-logo-hover-size:%dpx;', esc_attr( $settings['font_family'] ), absint( $settings['badge_font_size'] ), absint( $settings['font_weight'] ), absint( $settings['badge_padding'] ), absint( $settings['badge_opacity'] ), esc_attr( $settings['text_color'] ), absint( $settings['logo_size'] ), absint( $settings['logo_hover_size'] ) );
		$label = esc_attr( $settings['badge_text'] );
		$icon = $settings['show_icon'] ? '<span class="wki-ai-logo" aria-hidden="true"><img class="wki-ai-icon wki-ai-icon--general" src="' . esc_url( $this->icon_url( $attachment_id ) ) . '" alt=""><img class="wki-ai-icon wki-ai-icon--specific" src="' . esc_url( $this->type_icon_url( $attachment_id ) ) . '" alt=""></span>' : '';
		return '<span class="wki-ai-badge wki-ai-badge--' . esc_attr( $settings['badge_position'] ) . '" style="' . esc_attr( $style ) . '" role="img" aria-label="' . $label . '">' . $icon . esc_html( $settings['badge_text'] ) . '</span>';
	}

	public function background_shortcode( $atts ) {
		if ( $this->is_editor_context() ) {
			return '';
		}
		$atts = shortcode_atts( array( 'image_id' => 0, 'class' => '', 'height' => '' ), $atts, 'wki_ai_background' );
		$attachment_id = absint( $atts['image_id'] );
		$url = $attachment_id ? wp_get_attachment_image_url( $attachment_id, 'full' ) : '';
		if ( ! $url || ! $this->is_ai( $attachment_id ) ) {
			return '';
		}
		$this->enqueue_frontend_style();
		$classes = trim( 'wki-ai-background ' . sanitize_html_class( $atts['class'] ) );
		$height = '' !== $atts['height'] ? 'min-height:' . esc_attr( preg_replace( '/[^0-9.%a-zA-Z -]/', '', $atts['height'] ) ) . ';' : '';
		$style = 'background-image:url("' . esc_url( $url ) . '");' . $height;
		return '<span class="' . esc_attr( $classes ) . '" style="' . esc_attr( $style ) . '">' . $this->badge_markup( $attachment_id ) . '</span>';
	}

	public function admin_menu() {
		add_menu_page( 'WP KI-Badge Plugin', 'KI-Badge', 'manage_options', 'wki-settings', array( $this, 'settings_page' ), 'dashicons-format-image', 58 );
	}

	public function settings_page() {
		$settings = $this->settings();
		?>
		<div class="wrap"><h1>WP KI-Badge Plugin</h1>
		<p>Manuelle Kennzeichnungen werden im Medien-Manager am jeweiligen Bild gesetzt. Die automatische Erkennung durchsucht Bild-Metadaten, überschreibt aber keine manuelle Entscheidung.</p>
		<form method="post" action="options.php">
			<?php settings_fields( 'wki_settings_group' ); ?>
			<table class="form-table">
				<tr><th scope="row">Automatische Erkennung</th><td><label><input type="checkbox" name="wki_settings[auto_detect]" value="1" <?php checked( $settings['auto_detect'] ); ?>> Metadaten beim Hochladen und Bearbeiten prüfen</label><p class="description">Geprüft werden WordPress-Bildmetadaten sowie eingebettete XMP-Daten. Die Erkennung ist heuristisch und ersetzt keine redaktionelle Prüfung.</p></td></tr>
				<tr><th scope="row"><label for="wki-keywords">Suchbegriffe</label></th><td><textarea class="large-text code" id="wki-keywords" name="wki_settings[keywords]" rows="8"><?php echo esc_textarea( $settings['keywords'] ); ?></textarea><p class="description">Ein Begriff pro Zeile, zum Beispiel „AI generated“, „Midjourney“ oder „Stable Diffusion“.</p></td></tr>
				<tr><th scope="row">Frontend-Badge</th><td><label><input type="checkbox" name="wki_settings[frontend_badge]" value="1" <?php checked( $settings['frontend_badge'] ); ?>> KI-Hinweis auf gekennzeichneten Bildern anzeigen</label></td></tr>
				<tr><th scope="row"><label for="wki-badge-text">Badge-Text</label></th><td><input class="regular-text" id="wki-badge-text" name="wki_settings[badge_text]" value="<?php echo esc_attr( $settings['badge_text'] ); ?>"></td></tr>
				<tr><th scope="row">AI-Logo</th><td><label><input type="checkbox" name="wki_settings[show_icon]" value="1" <?php checked( $settings['show_icon'] ); ?>> Kleines AI-Logo anzeigen</label><p class="description">Optional. Der Text bleibt auch ohne Logo sichtbar.</p></td></tr>
				<tr><th scope="row"><label for="wki-logo-size">Logo-Größe</label></th><td><input type="number" min="8" max="48" id="wki-logo-size" name="wki_settings[logo_size]" value="<?php echo esc_attr( $settings['logo_size'] ); ?>"> px <p class="description">Beim Überfahren des Badges wird das gewählte EU-Symbol angezeigt.</p></td></tr>
				<tr><th scope="row"><label for="wki-logo-hover-size">Logo-Höhe bei Hover</label></th><td><input type="number" min="12" max="96" id="wki-logo-hover-size" name="wki_settings[logo_hover_size]" value="<?php echo esc_attr( $settings['logo_hover_size'] ); ?>"> px <p class="description">Bestimmt die sichtbare Höhe des breiten EU-Typ-Logos.</p></td></tr>
				<tr><th scope="row"><label for="wki-badge-position">Badge-Position</label></th><td><select id="wki-badge-position" name="wki_settings[badge_position]"><option value="bottom-left" <?php selected( $settings['badge_position'], 'bottom-left' ); ?>>Unten links</option><option value="bottom-right" <?php selected( $settings['badge_position'], 'bottom-right' ); ?>>Unten rechts</option><option value="top-left" <?php selected( $settings['badge_position'], 'top-left' ); ?>>Oben links</option><option value="top-right" <?php selected( $settings['badge_position'], 'top-right' ); ?>>Oben rechts</option></select></td></tr>
				<tr><th scope="row"><label for="wki-badge-font-size">Schriftgröße</label></th><td><input type="number" min="9" max="24" id="wki-badge-font-size" name="wki_settings[badge_font_size]" value="<?php echo esc_attr( $settings['badge_font_size'] ); ?>"> px</td></tr>
				<tr><th scope="row"><label for="wki-badge-padding">Innenabstand</label></th><td><input type="number" min="2" max="20" id="wki-badge-padding" name="wki_settings[badge_padding]" value="<?php echo esc_attr( $settings['badge_padding'] ); ?>"> px</td></tr>
				<tr><th scope="row"><label for="wki-badge-opacity">Transparenz</label></th><td><input type="number" min="0" max="100" id="wki-badge-opacity" name="wki_settings[badge_opacity]" value="<?php echo esc_attr( $settings['badge_opacity'] ); ?>"> % Deckkraft <p class="description">0 % = unsichtbar, 100 % = vollständig deckend.</p></td></tr>
				<?php $font_options = $this->available_fonts(); ?><tr><th scope="row"><label for="wki-font-family">Schriftfamilie</label></th><td><select id="wki-font-family" name="wki_settings[font_family]"><?php foreach ( $font_options as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $settings['font_family'], $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select><p class="description">Avada-Schriftarten werden automatisch aus den Theme-Einstellungen übernommen.</p></td></tr>
				<tr><th scope="row"><label for="wki-font-weight">Schriftstärke</label></th><td><select id="wki-font-weight" name="wki_settings[font_weight]"><?php foreach ( array( 300 => 'Leicht', 400 => 'Normal', 500 => 'Medium', 600 => 'Halbfett', 700 => 'Fett' ) as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $settings['font_weight'], $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></td></tr>
				<tr><th scope="row"><label for="wki-text-color">Schriftfarbe</label></th><td><input id="wki-text-color" type="color" name="wki_settings[text_color]" value="<?php echo esc_attr( $settings['text_color'] ); ?>"></td></tr>
				<tr><th scope="row"><label for="wki-icon-variant">EU-Icon-Variante</label></th><td><select id="wki-icon-variant" name="wki_settings[icon_variant]"><option value="auto" <?php selected( $settings['icon_variant'], 'auto' ); ?>>Auto – optimaler Kontrast</option><option value="auto-transparent" <?php selected( $settings['icon_variant'], 'auto-transparent' ); ?>>Auto – optimaler Kontrast, transparent</option><option value="black" <?php selected( $settings['icon_variant'], 'black' ); ?>>Schwarz</option><option value="black-transparent" <?php selected( $settings['icon_variant'], 'black-transparent' ); ?>>Schwarz, transparent</option><option value="white" <?php selected( $settings['icon_variant'], 'white' ); ?>>Weiß</option><option value="white-transparent" <?php selected( $settings['icon_variant'], 'white-transparent' ); ?>>Weiß, transparent</option></select><p class="description">Auto untersucht lokal den Bildbereich an der gewählten Badge-Position und verwendet das kontrastreichere schwarze oder weiße EU-Symbol. Das Ergebnis wird pro Bild und Position zwischengespeichert.</p></td></tr>
			</table>
			<?php submit_button( 'Einstellungen speichern' ); ?>
		</form></div>
		<?php
	}
}

WKI_Plugin::instance();
