<?php
/**
 * Plugin Name: KI-Bildkennzeichnung
 * Description: Kennzeichnet KI-generierte Bilder im Medien-Manager und optional im Frontend.
 * Version: 0.1.1
 * Author: IT-NWD
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * License: GPL-2.0-or-later
 * Text Domain: wp-ki-image-labeler
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WKI_VERSION', '0.1.1' );
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
		add_filter( 'wp_get_attachment_image', array( $this, 'frontend_badge' ), 10, 5 );
		add_shortcode( 'wki_ai_background', array( $this, 'background_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_style' ) );
	}

	private function defaults() {
		return array(
			'auto_detect' => true,
			'frontend_badge' => false,
			'badge_text' => 'KI-generiertes Bild',
			'badge_position' => 'bottom-left',
			'badge_font_size' => 12,
			'badge_padding' => 7,
			'keywords' => "ai-generated\nartificial intelligence\ndall-e\ndalle\nmidjourney\nstable diffusion\nadobe firefly\ngenerative fill\ncomfyui",
		);
	}

	private function settings() {
		return array_replace( $this->defaults(), get_option( $this->option_name, array() ) );
	}

	public function register_settings() {
		register_setting( 'wki_settings_group', $this->option_name, array( $this, 'sanitize_settings' ) );
	}

	public function sanitize_settings( $input ) {
		$current = $this->settings();
		return array(
			'auto_detect' => ! empty( $input['auto_detect'] ),
			'frontend_badge' => ! empty( $input['frontend_badge'] ),
			'badge_text' => sanitize_text_field( $input['badge_text'] ?? $current['badge_text'] ),
			'badge_position' => in_array( $input['badge_position'] ?? $current['badge_position'], array( 'bottom-left', 'bottom-right', 'top-left', 'top-right' ), true ) ? $input['badge_position'] : $current['badge_position'],
			'badge_font_size' => max( 9, min( 24, absint( $input['badge_font_size'] ?? $current['badge_font_size'] ) ) ),
			'badge_padding' => max( 2, min( 20, absint( $input['badge_padding'] ?? $current['badge_padding'] ) ) ),
			'keywords' => sanitize_textarea_field( $input['keywords'] ?? $current['keywords'] ),
		);
	}

	public function media_field( $form_fields, $post ) {
		$form_fields['wki_is_ai'] = array(
			'label' => 'KI-Bild',
			'input' => 'html',
			'html' => sprintf( '<label><input type="checkbox" name="attachments[%1$d][wki_is_ai]" value="1" %2$s> Als KI-generiertes Bild kennzeichnen</label><p class="description">Manuelle Kennzeichnungen haben Vorrang vor der automatischen Metadatenerkennung.</p>', $post->ID, checked( $this->is_ai( $post->ID ), true, false ) ),
		);
		return $form_fields;
	}

	public function save_media_field( $post, $attachment ) {
		if ( isset( $attachment['wki_is_ai'] ) ) {
			update_post_meta( $post['ID'], '_wki_is_ai', '1' );
			update_post_meta( $post['ID'], '_wki_ai_source', 'manual' );
		} else {
			update_post_meta( $post['ID'], '_wki_is_ai', '0' );
			update_post_meta( $post['ID'], '_wki_ai_source', 'manual' );
		}
		return $post;
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

	public function image_attributes( $attr, $attachment, $size ) {
		if ( $this->is_ai( $attachment->ID ) ) {
			$attr['class'] = trim( ( $attr['class'] ?? '' ) . ' wki-ai-image' );
			$attr['data-wki-ai'] = 'true';
		}
		return $attr;
	}

	public function frontend_badge( $html, $attachment_id, $size, $icon, $attr ) {
		if ( ! $this->settings()['frontend_badge'] || ! $this->is_ai( $attachment_id ) ) {
			return $html;
		}
		$this->enqueue_frontend_style();
		return '<span class="wki-ai-wrap">' . $html . $this->badge_markup() . '</span>';
	}

	public function enqueue_frontend_style() {
		if ( ! wp_style_is( 'wki-frontend', 'registered' ) ) {
			wp_register_style( 'wki-frontend', plugins_url( 'assets/frontend.css', WKI_FILE ), array(), WKI_VERSION );
		}
		wp_enqueue_style( 'wki-frontend' );
	}

	private function badge_markup() {
		$settings = $this->settings();
		$style = sprintf( '--wki-badge-font-size:%dpx;--wki-badge-padding:%dpx;', absint( $settings['badge_font_size'] ), absint( $settings['badge_padding'] ) );
		return '<span class="wki-ai-badge wki-ai-badge--' . esc_attr( $settings['badge_position'] ) . '" style="' . esc_attr( $style ) . '">' . esc_html( $settings['badge_text'] ) . '</span>';
	}

	public function background_shortcode( $atts ) {
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
		return '<span class="' . esc_attr( $classes ) . '" style="' . esc_attr( $style ) . '">' . $this->badge_markup() . '</span>';
	}

	public function admin_menu() {
		add_options_page( 'KI-Bildkennzeichnung', 'KI-Bildkennzeichnung', 'manage_options', 'wki-settings', array( $this, 'settings_page' ) );
	}

	public function settings_page() {
		$settings = $this->settings();
		?>
		<div class="wrap"><h1>KI-Bildkennzeichnung</h1>
		<p>Manuelle Kennzeichnungen werden im Medien-Manager am jeweiligen Bild gesetzt. Die automatische Erkennung durchsucht Bild-Metadaten, überschreibt aber keine manuelle Entscheidung.</p>
		<form method="post" action="options.php">
			<?php settings_fields( 'wki_settings_group' ); ?>
			<table class="form-table">
				<tr><th scope="row">Automatische Erkennung</th><td><label><input type="checkbox" name="wki_settings[auto_detect]" value="1" <?php checked( $settings['auto_detect'] ); ?>> Metadaten beim Hochladen und Bearbeiten prüfen</label><p class="description">Geprüft werden WordPress-Bildmetadaten sowie eingebettete XMP-Daten. Die Erkennung ist heuristisch und ersetzt keine redaktionelle Prüfung.</p></td></tr>
				<tr><th scope="row"><label for="wki-keywords">Suchbegriffe</label></th><td><textarea class="large-text code" id="wki-keywords" name="wki_settings[keywords]" rows="8"><?php echo esc_textarea( $settings['keywords'] ); ?></textarea><p class="description">Ein Begriff pro Zeile, zum Beispiel „AI generated“, „Midjourney“ oder „Stable Diffusion“.</p></td></tr>
				<tr><th scope="row">Frontend-Badge</th><td><label><input type="checkbox" name="wki_settings[frontend_badge]" value="1" <?php checked( $settings['frontend_badge'] ); ?>> KI-Hinweis auf gekennzeichneten Bildern anzeigen</label></td></tr>
				<tr><th scope="row"><label for="wki-badge-text">Badge-Text</label></th><td><input class="regular-text" id="wki-badge-text" name="wki_settings[badge_text]" value="<?php echo esc_attr( $settings['badge_text'] ); ?>"></td></tr>
				<tr><th scope="row"><label for="wki-badge-position">Badge-Position</label></th><td><select id="wki-badge-position" name="wki_settings[badge_position]"><option value="bottom-left" <?php selected( $settings['badge_position'], 'bottom-left' ); ?>>Unten links</option><option value="bottom-right" <?php selected( $settings['badge_position'], 'bottom-right' ); ?>>Unten rechts</option><option value="top-left" <?php selected( $settings['badge_position'], 'top-left' ); ?>>Oben links</option><option value="top-right" <?php selected( $settings['badge_position'], 'top-right' ); ?>>Oben rechts</option></select></td></tr>
				<tr><th scope="row"><label for="wki-badge-font-size">Schriftgröße</label></th><td><input type="number" min="9" max="24" id="wki-badge-font-size" name="wki_settings[badge_font_size]" value="<?php echo esc_attr( $settings['badge_font_size'] ); ?>"> px</td></tr>
				<tr><th scope="row"><label for="wki-badge-padding">Innenabstand</label></th><td><input type="number" min="2" max="20" id="wki-badge-padding" name="wki_settings[badge_padding]" value="<?php echo esc_attr( $settings['badge_padding'] ); ?>"> px</td></tr>
			</table>
			<?php submit_button( 'Einstellungen speichern' ); ?>
		</form></div>
		<?php
	}
}

WKI_Plugin::instance();
