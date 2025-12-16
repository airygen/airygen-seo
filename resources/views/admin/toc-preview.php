<?php
/**
 * Table of contents preview markup.
 *
 * @package Airygen\Modules\TableOfContents\Views
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$airygen_content = isset( $data['content'] ) ? (string) $data['content'] : '';
$airygen_styles  = isset( $data['styles'] ) && is_array( $data['styles'] ) ? $data['styles'] : array();
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php
	foreach ( $airygen_styles as $airygen_style_index => $airygen_style_url ) {
		wp_enqueue_style( 'airygen-toc-preview-' . $airygen_style_index, $airygen_style_url, array(), AIRYGEN_VERSION );
	}

	wp_enqueue_script(
		'airygen-toc-preview',
		plugins_url( 'resources/assets/js/toc-preview.js', AIRYGEN_PLUGIN_FILE ),
		array(),
		AIRYGEN_VERSION,
		true
	);

	wp_head();
	?>
</head>
<body class="airygen-toc-preview">
	<main class="airygen-toc-preview__content entry-content">
		<?php echo wp_kses_post( $airygen_content ); ?>
	</main>
<?php wp_footer(); ?>
</body>
</html>
