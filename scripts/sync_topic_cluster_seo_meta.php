<?php
/**
 * Sync SEO meta title/description for Topic Cluster posts across locales.
 *
 * Usage:
 * wp eval-file /var/www/html/wp-content/plugins/airygen-seo/scripts/sync_topic_cluster_seo_meta.php --allow-root
 */

use Airygen\Support\Meta\PostData;

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

$blog_locales = array(
	1  => 'zh_TW',
	2  => 'en_US',
	3  => 'ja',
	4  => 'ko_KR',
	5  => 'ru_RU',
	6  => 'pt_PT',
	7  => 'fr_FR',
	8  => 'de_DE',
	9  => 'it_IT',
	10 => 'es_ES',
);

$post_ids = array( 20, 22, 23, 26, 32, 42, 50, 28, 36, 38, 40, 48, 44, 46, 34, 30 );

/**
 * Truncate UTF-8 text to max chars.
 */
function ag_tc_trim_text( string $text, int $max_chars ): string {
	$text = trim( $text );
	if ( mb_strlen( $text ) <= $max_chars ) {
		return $text;
	}

	return rtrim( mb_substr( $text, 0, $max_chars - 1 ) ) . '…';
}

/**
 * Build localized SEO title.
 */
function ag_tc_build_title( string $locale, string $focus, string $post_title ): string {
	$locale_key = strtolower( (string) preg_replace( '/[_-].*/', '', $locale ) );

	$base = $post_title;
	if ( false === mb_stripos( $post_title, $focus ) ) {
		$base = $focus . ' | ' . $post_title;
	}

	$max = in_array( $locale_key, array( 'zh', 'ja', 'ko' ), true ) ? 36 : 60;
	return ag_tc_trim_text( $base, $max );
}

/**
 * Build localized SEO description (must include focus keyword).
 */
function ag_tc_build_description( string $locale, string $focus, string $post_title ): string {
	$locale_key = strtolower( (string) preg_replace( '/[_-].*/', '', $locale ) );

	switch ( $locale_key ) {
		case 'zh':
			$text = sprintf(
				'%s完整指南：%s。整理行程規劃、交通票券、住宿選擇與預算重點，協助快速完成日本自由行安排。',
				$focus,
				$post_title
			);
			return ag_tc_trim_text( $text, 90 );

		case 'ja':
			$text = sprintf(
				'%sの実用ガイド。%sを中心に、行程設計・交通パス・宿選び・予算管理の要点を短時間で把握できます。',
				$focus,
				$post_title
			);
			return ag_tc_trim_text( $text, 120 );

		case 'ko':
			$text = sprintf(
				'%s 가이드입니다. %s를 중심으로 일정 구성, 교통 패스, 숙소 선택, 예산 관리 핵심을 한 번에 정리합니다.',
				$focus,
				$post_title
			);
			return ag_tc_trim_text( $text, 120 );

		case 'ru':
			$text = sprintf(
				'%s: практическое руководство. Материал «%s» помогает быстро спланировать маршрут, транспорт, проживание и бюджет поездки по Японии.',
				$focus,
				$post_title
			);
			return ag_tc_trim_text( $text, 160 );

		case 'pt':
			$text = sprintf(
				'%s: guia prático. Em "%s", reunimos pontos-chave de roteiro, transportes, alojamento e orçamento para planear uma viagem ao Japão com clareza.',
				$focus,
				$post_title
			);
			return ag_tc_trim_text( $text, 160 );

		case 'fr':
			$text = sprintf(
				'%s : guide pratique. L’article "%s" résume l’itinéraire, les transports, l’hébergement et le budget pour préparer un voyage au Japon.',
				$focus,
				$post_title
			);
			return ag_tc_trim_text( $text, 160 );

		case 'de':
			$text = sprintf(
				'%s: Praxisleitfaden. Der Beitrag "%s" fasst Route, Transportpässe, Unterkunft und Budget kompakt zusammen, damit die Japanreise schnell planbar wird.',
				$focus,
				$post_title
			);
			return ag_tc_trim_text( $text, 160 );

		case 'it':
			$text = sprintf(
				'%s: guida pratica. In "%s" trovi una sintesi chiara su itinerario, trasporti, alloggio e budget per organizzare al meglio il viaggio in Giappone.',
				$focus,
				$post_title
			);
			return ag_tc_trim_text( $text, 160 );

		case 'es':
			$text = sprintf(
				'%s: guía práctica. El contenido "%s" resume itinerario, transporte, alojamiento y presupuesto para planificar un viaje a Japón de forma realista.',
				$focus,
				$post_title
			);
			return ag_tc_trim_text( $text, 160 );

		default:
			$text = sprintf(
				'%s guide: %s. Covers itinerary planning, transport passes, accommodation choices, and budget essentials for a practical Japan trip.',
				$focus,
				$post_title
			);
			return ag_tc_trim_text( $text, 160 );
	}
}

$updated = 0;

foreach ( $blog_locales as $blog_id => $locale ) {
	switch_to_blog( (int) $blog_id );

	foreach ( $post_ids as $post_id ) {
		$post = get_post( (int) $post_id );
		if ( ! $post || 'post' !== $post->post_type ) {
			continue;
		}

		$focus = trim( PostData::get_field( (int) $post_id, 'focusKeyphrase' ) );
		if ( '' === $focus ) {
			continue;
		}

		$title       = ag_tc_build_title( $locale, $focus, (string) $post->post_title );
		$description = ag_tc_build_description( $locale, $focus, (string) $post->post_title );

		PostData::save(
			(int) $post_id,
			array(
				'title'       => $title,
				'description' => $description,
			)
		);
		$updated++;
	}

	restore_current_blog();
}

echo sprintf( "Updated SEO meta for %d records.\n", $updated );
