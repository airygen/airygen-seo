<?php
/**
 * Utility for rendering PHP templates with optional sanitization.
 *
 * @package Airygen\Support
 */

declare(strict_types=1);

namespace Airygen\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lightweight template helper used by legacy function wrappers.
 */
final class TemplateRenderer {

	private const TEMPLATE_BASE = __DIR__ . '/../../resources/views/';

	/**
	 * Render a template and echo the output.
	 *
	 * @param string        $relative_path Relative path within the views directory (without .php).
	 * @param array<mixed>  $data          Data passed into the template.
	 * @param callable|null $sanitizer     Optional sanitizer applied recursively to the data payload.
	 *
	 * @return void
	 */
	public static function render( string $relative_path, array $data = array(), ?callable $sanitizer = null ): void {
		echo self::get( $relative_path, $data, $sanitizer ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Render a template and return the output.
	 *
	 * @param string        $relative_path Relative path within the views directory (without .php).
	 * @param array<mixed>  $data          Data passed into the template.
	 * @param callable|null $sanitizer     Optional sanitizer applied recursively to the data payload.
	 *
	 * @return string
	 */
	public static function get( string $relative_path, array $data = array(), ?callable $sanitizer = null ): string {
		$template_path = self::resolve_path( $relative_path );

		if ( '' === $template_path || ! file_exists( $template_path ) ) {
			return '';
		}

		$template_data = null === $sanitizer ? $data : self::sanitize_recursive( $data, $sanitizer );

		ob_start();
		// Make $data available inside template scope.
		$data = $template_data;
		require $template_path;

		$output = ob_get_clean();
		if ( false === $output ) {
			return '';
		}

		return wp_kses( $output, self::allowed_tags() );
	}

	/**
	 * Default sanitizer used by legacy helpers.
	 *
	 * @return callable
	 */
	public static function default_sanitizer(): callable {
		return static function ( $value ) {
			if ( is_string( $value ) ) {
				return sanitize_text_field( $value );
			}

			return $value;
		};
	}

	/**
	 * Recursively apply sanitizer to the provided data.
	 *
	 * @param mixed    $value     Input value.
	 * @param callable $sanitizer Sanitizer callback.
	 *
	 * @return mixed
	 */
	public static function sanitize_recursive( $value, callable $sanitizer ) {
		if ( is_array( $value ) ) {
			foreach ( $value as $key => $inner_value ) {
				$value[ $key ] = self::sanitize_recursive( $inner_value, $sanitizer );
			}

			return $value;
		}

		return $sanitizer( $value );
	}

	/**
	 * Allowed tags used when filtering template output.
	 *
	 * @return array<string, array<string, array<empty, empty>>|array<string, array<string, empty>>>
	 */
	public static function allowed_tags(): array {
		return array(
			'a'        => array(
				'href'   => array(),
				'title'  => array(),
				'class'  => array(),
				'target' => array(),
				'rel'    => array(),
			),
			'br'       => array(),
			'em'       => array(),
			'strong'   => array(),
			'input'    => array(
				'type'         => array(),
				'name'         => array(),
				'value'        => array(),
				'id'           => array(),
				'checked'      => array(),
				'disabled'     => array(),
				'class'        => array(),
				'maxlength'    => array(),
				'placeholder'  => array(),
				'autocomplete' => array(),
			),
			'label'    => array(
				'for'   => array(),
				'class' => array(),
			),
			'form'     => array(
				'action' => array(),
				'method' => array(),
				'class'  => array(),
			),
			'textarea' => array(
				'name'        => array(),
				'id'          => array(),
				'rows'        => array(),
				'cols'        => array(),
				'class'       => array(),
				'placeholder' => array(),
			),
			'div'      => array(
				'class'       => array(),
				'id'          => array(),
				'role'        => array(),
				'data-panel'  => array(),
				'aria-modal'  => array(),
				'aria-hidden' => array(),
			),
			'span'     => array(
				'class' => array(),
				'id'    => array(),
			),
			'h1'       => array(),
			'h2'       => array(),
			'p'        => array(
				'class' => array(),
			),
			'select'   => array(
				'name'     => array(),
				'id'       => array(),
				'class'    => array(),
				'disabled' => array(),
			),
			'option'   => array(
				'value'    => array(),
				'selected' => array(),
			),
			'code'     => array(
				'class' => array(),
			),
			'pre'      => array(
				'class' => array(),
			),
			'ul'       => array(
				'class' => array(),
			),
			'ol'       => array(
				'class' => array(),
			),
			'li'       => array(
				'class' => array(),
			),
			'button'   => array(
				'class'      => array(),
				'type'       => array(),
				'id'         => array(),
				'aria-label' => array(),
				'data-tab'   => array(),
				'disabled'   => array(),
			),
			'h3'       => array(),
			'h4'       => array(),
			'meta'     => array(
				'property' => array(),
				'content'  => array(),
				'name'     => array(),
			),
			'table'    => array(
				'class' => array(),
				'role'  => array(),
			),
			'tr'       => array(
				'class' => array(),
			),
			'th'       => array(
				'scope' => array(),
				'class' => array(),
			),
			'td'       => array(
				'class' => array(),
			),
			'tbody'    => array(
				'class' => array(),
			),
			'thead'    => array(
				'class' => array(),
			),
		);
	}

	/**
	 * Resolve a template path from a relative identifier.
	 *
	 * @param string $relative_path Relative path without the .php extension.
	 *
	 * @return string
	 */
	private static function resolve_path( string $relative_path ): string {
		$relative_path = trim( $relative_path, '/' );
		if ( '' === $relative_path ) {
			return '';
		}

		return self::TEMPLATE_BASE . $relative_path . '.php';
	}
}
