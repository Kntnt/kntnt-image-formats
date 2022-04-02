<?php

/**
 * @wordpress-plugin
 * Plugin Name:       Kntnt Image Formats
 * Plugin URI:        https://www.kntnt.com/
 * Description:       Provides a set of image formats including 'thumbnail', 'medium', 'medium_large', and 'large'.
 * Version:           1.1.4
 * Author:            Thomas Barregren
 * Author URI:        https://www.kntnt.com/
 * License:           GPL-3.0+
 * License URI:       http://www.gnu.org/licenses/gpl-3.0.txt
 */

namespace Kntnt\Image_Formats;

defined( 'ABSPATH' ) && new Plugin;

class Plugin {

	private static array $built_in_sizes = [ 'thumbnail', 'medium', 'medium_large', 'large' ];

	private static function default_image_formats(): array {
		return [
			'thumbnail'     => [
				'name'   => __( 'Thumbnail', 'kntnt-image-formats' ),
				'width'  => 150,
				'height' => 150,
				'crop'   => true,
			],
			'xx_small'      => [
				'name'   => __( 'XX-Small', 'kntnt-image-formats' ),
				'width'  => 225,
				'height' => 9999,
				'crop'   => false,
			],
			'medium'        => [
				'name'   => __( 'X-Small', 'kntnt-image-formats' ),
				'width'  => 300,
				'height' => 9999,
				'crop'   => false,
			],
			'medium_small'  => [
				'name'   => __( 'Small', 'kntnt-image-formats' ),
				'width'  => 450,
				'height' => 9999,
				'crop'   => false,
			],
			'medium_medium' => [
				'name'   => __( 'Medium', 'kntnt-image-formats' ),
				'width'  => 600,
				'height' => 9999,
				'crop'   => false,
			],
			'medium_large'  => [
				'name'   => __( 'Large', 'kntnt-image-formats' ),
				'width'  => 900,
				'height' => 9999,
				'crop'   => false,
			],
			'large'         => [
				'name'   => __( 'X-Large', 'kntnt-image-formats' ),
				'width'  => 1200,
				'height' => 9999,
				'crop'   => false,
			],
			'xx_large'      => [
				'name'   => __( 'XX-Large', 'kntnt-image-formats' ),
				'width'  => 1920,
				'height' => 9999,
				'crop'   => false,
			],
			'small_banner'  => [
				'name'   => __( 'Small banner', 'kntnt-image-formats' ),
				'width'  => 1920,
				'height' => 300,
				'crop'   => true,
			],
			'medium_banner' => [
				'name'   => __( 'Medium banner', 'kntnt-image-formats' ),
				'width'  => 1920,
				'height' => 600,
				'crop'   => true,
			],
			'large_banner'  => [
				'name'   => __( 'Large banner', 'kntnt-image-formats' ),
				'width'  => 1920,
				'height' => 1200,
				'crop'   => true,
			],
		];
	}

	private array $names = [];

	public function __construct() {
		add_action( 'plugins_loaded', [ $this, 'run' ] );
	}

	public function run() {

		// Add image formats
		$this->setup_image_formats();

		// Replace WordPress image_resize_dimensions(), which calculated resize
		// dimensions for use in WP_Image_Editor, with our own implementation
		// that crops with bleed. That is, it behaves like the CSS property
		// `object-fit: cover`, while WordPress behaves like the CSS property
		// `object-fit: contain`.
		add_filter( 'image_resize_dimensions', [ $this, 'image_resize_dimensions' ], 10, 6 );

		// Modify the list of image sizes that are available to administrators in Media Library
		add_filter( 'image_size_names_choose', [ $this, 'update_ui' ], 9999 );

		// Modify the media setting page
		add_filter( 'all_admin_notices', [ $this, 'media_options' ], 10, 1 );

	}

	public function setup_image_formats() {
		$image_formats = apply_filters( 'kntnt-image-formats', self::default_image_formats() );
		foreach ( $image_formats as $slug => $format ) {
			$this->set_image_size( $slug, $format['width'], $format['height'], $format['crop'], $format['name'] );
		}
	}

	/** @noinspection PhpUnusedParameterInspection */
	public function image_resize_dimensions( $payload, $src_w, $src_h, $dst_w, $dst_h, $crop ): ?array {

		if ( ! $crop ) {
			return null;
		}

		$scale_factor = max( $dst_w / $src_w, $dst_h / $src_h );

		$crop_w = round( $dst_w / $scale_factor );
		$crop_h = round( $dst_h / $scale_factor );

		$src_x = floor( ( $src_w - $crop_w ) / 2 );
		$src_y = floor( ( $src_h - $crop_h ) / 2 );

		return [ 0, 0, (int) $src_x, (int) $src_y, (int) $dst_w, (int) $dst_h, (int) $crop_w, (int) $crop_h ];

	}

	public function update_ui( $sizes ): array {

		// Remove all previously defined images sizes that is overridden by ImageSizeBuilder.
		$sizes = array_diff_key( $sizes, $this->names );

		// Remove all images sizes with an empty name.
		$names = array_filter( $this->names );

		// Return all image sizes defined by this class and the leftovers.
		return array_merge( $names, $sizes );

	}

	public function media_options() {
		$screen = get_current_screen();
		if ( isset( $screen ) && 'options-media' == $screen->base ) {
			ob_start( function ( $content ) {
				$image_sizes = __( 'Image sizes' );
				$re          = "/<h2[^>]+>$image_sizes.*?(?=<h2)/s";
				return preg_replace( $re, '', $content, 1 );
			} );
			add_action( 'in_admin_footer', 'ob_end_flush', 10, 0 );
		}
	}

	private function set_image_size( $slug, $width, $height, $crop, $name ) {

		// Store the name in order
		$this->names[ $slug ] = $name;

		// Update the image size
		add_image_size( $slug, $width, $height, $crop );

		// Update the options for the built-in image sizes
		if ( in_array( $slug, self::$built_in_sizes ) ) {
			update_option( $slug . '_size_w', $width );
			update_option( $slug . '_size_h', $height );
			if ( $slug == 'thumbnail' ) {
				update_option( $slug . '_size_crop', $crop );
			}
		}

	}

}
