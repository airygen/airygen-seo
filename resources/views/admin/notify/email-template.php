<?php
/**
 * Notify email HTML template.
 *
 * @package Airygen
 *
 * @var array<string,string> $data Template payload.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$airygen_subject_esc = isset( $data['subject'] ) ? $data['subject'] : '';
$airygen_brand_esc   = isset( $data['brand'] ) ? $data['brand'] : '';
$airygen_site_esc    = isset( $data['site'] ) ? $data['site'] : '';
$airygen_content     = isset( $data['content'] ) ? $data['content'] : '';
?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="UTF-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<title><?php echo esc_html( $airygen_subject_esc ); ?></title>
</head>
<body style="margin:0;padding:0;background:#f8fafc;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#0f172a;">
	<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#f8fafc;padding:24px 12px;">
		<tr>
			<td align="center">
				<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:640px;background:#ffffff;border:1px solid #e2e8f0;">
					<tr>
						<td style="padding:20px 24px;background-color:#333333;color:#ffffff;">
							<div style="font-size:12px;letter-spacing:0.04em;text-transform:uppercase;opacity:0.9;">Airygen SEO</div>
							<div style="margin-top:6px;font-size:22px;font-weight:700;line-height:1.3;"><?php echo esc_html( $airygen_subject_esc ); ?></div>
						</td>
					</tr>
					<tr>
						<td style="padding:20px 24px;">
							<div style="font-size:16px;line-height:1.7;color:#334155;"><?php echo $airygen_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Content is sanitized before injection. ?></div>
						</td>
					</tr>
					<tr>
						<td style="padding:14px 24px;border-top:1px solid #e2e8f0;background:#f8fafc;color:#64748b;font-size:13px;line-height:1.6;">
							<?php
							echo wp_kses_post(
								sprintf(
									/* translators: 1: brand name, 2: site name. */
									__( 'Sent by %1$s via %2$s.', 'airygen-seo' ),
									esc_html( $airygen_brand_esc ),
									esc_html( $airygen_site_esc )
								)
							);
							?>
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
</body>
</html>
