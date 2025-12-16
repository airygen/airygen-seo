<?php
/**
 * Generate localized topic-cluster article content for all multisite locales.
 *
 * Usage:
 * wp eval-file /var/www/html/wp-content/plugins/airygen-seo/scripts/generate_topic_cluster_content.php --allow-root
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
$l1_ids   = array( 20 );

/**
 * Split CSV long-tail into trimmed array.
 *
 * @return array<int, string>
 */
function ag_tc_split_long_tail( string $value ): array {
	$parts = array_map( 'trim', explode( ',', $value ) );
	$parts = array_values(
		array_filter(
			$parts,
			static function ( $v ) {
				return '' !== $v;
			}
		)
	);

	return array_slice( $parts, 0, 3 );
}

/**
 * Locale copy bank for budget-traveler voice.
 *
 * @return array<string, string>
 */
function ag_tc_locale_copy( string $locale ): array {
	$key = strtolower( (string) preg_replace( '/[_-].*/', '', $locale ) );

	switch ( $key ) {
		case 'zh':
			return array(
				'lead'        => '這篇從旅人的角度出發，重點是花得精準、玩得有效率，不追求行程塞滿，而是避免踩雷與浪費。',
				'plan_h2'     => '先把行程骨架定好，再決定要花錢的地方',
				'plan_p'      => '我自己的做法是先抓每天的移動半徑，再決定景點數量。只要跨區移動，就把緩衝時間算進去，省下臨時改票與加價成本。',
				'budget_h2'   => '精打細算的預算分配',
				'budget_p'    => '先固定三個大項：交通、住宿、門票，再把餐費與購物當成浮動。這樣即使某天超支，也能在後面幾天拉回整體預算。',
				'check_h2'    => '出發前檢查清單',
				'check_p'     => '確認付款條件、取消規則、入場時段與替代方案。把可退款與不可退款分開看，臨時變動時才不會慌。',
				'close'       => '最後提醒：把交通、住宿、票券三件事先鎖定，再把每天行程留一點彈性，整趟旅程會省錢又省心。',
				'list_intro'  => '以下三個長尾關鍵詞可以直接當成你查資料與比價時的搜尋方向：',
				'focus_mid'   => '中段規劃時，我會再次對照 %s，確保路線、成本和時間配置一致。',
				'focus_end'   => '收尾時再回到 %s 這個核心，檢查是否每一天都能在預算內完成。',
				'pad'         => '若還有空檔，建議先把隔日交通與集合點截圖存離線，遇到臨時改動時更不容易增加額外成本。',
				'ops_h2'      => '實戰操作重點',
				'ops_p'       => '以「%1$s」為主軸，先完成 %2$s 的比價，再安排 %3$s 的替代方案，最後檢查預算與時間是否一致。',
			);
		case 'ja':
			return array(
				'lead'        => 'このガイドは旅行者目線で、費用対効果を最優先に組み立てています。詰め込みよりも、無駄な出費を減らす設計です。',
				'plan_h2'     => '先に旅程の骨組みを作り、予算投入ポイントを決める',
				'plan_p'      => 'まず1日の移動半径を決めてから観光数を入れると、移動ロスが減ります。乗り換えや待ち時間を見込むだけで追加費用を避けやすくなります。',
				'budget_h2'   => 'コスパ重視の予算配分',
				'budget_p'    => '交通・宿・チケットの3項目を固定費として先に確保し、食事や買い物を変動費にすると全体管理がしやすくなります。',
				'check_h2'    => '出発前チェック',
				'check_p'     => 'キャンセル条件、入場時間、代替案を先に整理しておくと、予定変更時の損失を最小化できます。',
				'close'       => '最後に、移動・宿泊・チケットを先に固め、1日ごとに余白を残すと、予算を守りつつ満足度の高い旅になります。',
				'list_intro'  => '調査と比較で使えるロングテールは次の3つです。',
				'focus_mid'   => '中盤では %s を軸に、移動・費用・時間配分を再チェックします。',
				'focus_end'   => '締めとして %s を基準に、日ごとの実行可能性を確認してください。',
				'pad'         => '余裕があれば翌日の移動ルートをオフライン保存し、急な変更でも追加コストを抑えましょう。',
				'ops_h2'      => '実務で使う確認ポイント',
				'ops_p'       => '「%1$s」を軸に、まず %2$s を比較し、次に %3$s の代替案を用意して、最後に予算と時間の整合を確認します。',
			);
		case 'ko':
			return array(
				'lead'        => '이 글은 여행자 관점에서 가성비를 우선해 구성했습니다. 빡빡한 일정보다 불필요한 지출을 줄이는 방식입니다.',
				'plan_h2'     => '먼저 동선 뼈대를 짜고, 돈 쓸 지점을 정하기',
				'plan_p'      => '하루 이동 반경을 먼저 정하면 일정이 안정적입니다. 환승과 대기 시간을 예산처럼 관리하면 추가 비용을 줄일 수 있습니다.',
				'budget_h2'   => '가성비 중심 예산 배분',
				'budget_p'    => '교통·숙소·입장권을 고정비로 먼저 확보하고, 식비·쇼핑은 변동비로 관리하면 전체 예산을 통제하기 쉽습니다.',
				'check_h2'    => '출발 전 체크 포인트',
				'check_p'     => '취소 규정, 입장 시간, 대체 플랜을 미리 정리해 두면 일정이 흔들려도 손실이 작습니다.',
				'close'       => '마지막으로 교통·숙소·티켓을 먼저 확정하고, 하루 일정에 여유를 남기면 비용과 만족도를 함께 잡을 수 있습니다.',
				'list_intro'  => '아래 3개의 롱테일 키워드는 비교 검색에 바로 쓸 수 있습니다.',
				'focus_mid'   => '중간 점검에서는 %s 기준으로 동선·비용·시간 균형을 다시 맞춥니다.',
				'focus_end'   => '마무리 단계에서 %s 중심으로 하루 실행 가능성을 확인하세요.',
				'pad'         => '여유가 있다면 다음 날 이동 경로를 오프라인으로 저장해 돌발 상황에서 비용을 줄이세요.',
				'ops_h2'      => '실전 운영 포인트',
				'ops_p'       => '\"%1$s\"를 중심으로 %2$s부터 비교하고, %3$s 대안을 준비한 뒤 예산과 시간을 마지막에 맞춥니다.',
			);
		case 'ru':
			return array(
				'lead'        => 'Материал написан с позиции путешественника: меньше лишних трат, больше практической пользы и управляемый темп поездки.',
				'plan_h2'     => 'Сначала каркас маршрута, потом траты',
				'plan_p'      => 'Сначала определите радиус перемещений на день, затем количество точек. Это снижает транспортные потери и непредвиденные расходы.',
				'budget_h2'   => 'Как распределять бюджет рационально',
				'budget_p'    => 'Фиксируйте транспорт, жильё и билеты как базовые статьи. Питание и покупки оставляйте как гибкую часть бюджета.',
				'check_h2'    => 'Проверка перед выездом',
				'check_p'     => 'Проверьте условия отмены, окна входа и резервные варианты. Так проще избежать потерь при изменениях.',
				'close'       => 'Сначала зафиксируйте транспорт, проживание и билеты, а в каждый день оставьте небольшой буфер по времени и деньгам.',
				'list_intro'  => 'Три long-tail запроса для практического поиска и сравнения:',
				'focus_mid'   => 'На этапе уточнения снова проверьте план через %s.',
				'focus_end'   => 'В финале вернитесь к %s и проверьте реалистичность каждого дня.',
				'pad'         => 'Дополнительно сохраните маршруты офлайн: это снижает риск лишних расходов при сбоях связи.',
				'ops_h2'      => 'Практические шаги',
				'ops_p'       => 'Опираясь на «%1$s», сначала сравните %2$s, затем подготовьте резерв для %3$s и проверьте баланс бюджета и времени.',
			);
		case 'pt':
			return array(
				'lead'        => 'Este guia foi escrito na perspetiva de viajante: gastar com critério, evitar desperdício e manter o roteiro executável.',
				'plan_h2'     => 'Definir a estrutura antes de gastar',
				'plan_p'      => 'Primeiro define-se o raio de deslocação por dia, depois o número de pontos. Isso reduz perdas de tempo e custos imprevistos.',
				'budget_h2'   => 'Distribuição inteligente do orçamento',
				'budget_p'    => 'Transporte, alojamento e bilhetes entram como custos fixos. Refeições e compras ficam como variável ajustável.',
				'check_h2'    => 'Checklist antes da partida',
				'check_p'     => 'Confirma regras de cancelamento, janelas de entrada e opções de reserva para reduzir perdas em alterações de plano.',
				'close'       => 'Com transporte, estadia e bilhetes resolvidos antes, a viagem fica mais previsível e com melhor controlo de custos.',
				'list_intro'  => 'Três long-tail úteis para pesquisa e comparação:',
				'focus_mid'   => 'No meio do planeamento, volto a validar tudo com %s.',
				'focus_end'   => 'No fecho, uso %s como critério final de viabilidade diária.',
				'pad'         => 'Se possível, guarda rotas offline para evitar custos extras em mudanças de última hora.',
				'ops_h2'      => 'Pontos práticos de execução',
				'ops_p'       => 'Com \"%1$s\" como eixo, compara primeiro %2$s, prepara alternativa para %3$s e valida no fim orçamento e tempo.',
			);
		case 'fr':
			return array(
				'lead'        => 'Ce guide adopte un angle voyageur: optimiser chaque dépense et garder un itinéraire réaliste, sans surcharge inutile.',
				'plan_h2'     => 'Poser l’ossature avant les dépenses',
				'plan_p'      => 'Définissez d’abord le rayon de déplacement quotidien, puis le nombre de visites. Cela limite les pertes de temps et les frais imprévus.',
				'budget_h2'   => 'Répartition budget orientée efficacité',
				'budget_p'    => 'Transport, hébergement et billets forment la base fixe. Les repas et achats restent ajustables selon la journée.',
				'check_h2'    => 'Vérifications avant départ',
				'check_p'     => 'Vérifiez les conditions d’annulation, les créneaux d’entrée et les alternatives pour réduire les risques financiers.',
				'close'       => 'En verrouillant transport, hébergement et billets en amont, vous gagnez en maîtrise budgétaire et en sérénité.',
				'list_intro'  => 'Trois requêtes long-tail pertinentes pour la recherche :',
				'focus_mid'   => 'En phase d’ajustement, je revalide le plan avec %s.',
				'focus_end'   => 'En conclusion, je reviens à %s pour vérifier la faisabilité jour par jour.',
				'pad'         => 'Pensez à conserver vos trajets hors ligne pour limiter les coûts liés aux changements de dernière minute.',
				'ops_h2'      => 'Points opérationnels',
				'ops_p'       => 'Avec «%1$s» comme axe, comparez d’abord %2$s, préparez une alternative pour %3$s, puis validez budget et temps.',
			);
		case 'de':
			return array(
				'lead'        => 'Dieser Beitrag ist aus Reisenden-Perspektive geschrieben: budgetbewusst, pragmatisch und auf umsetzbare Tagesplanung ausgerichtet.',
				'plan_h2'     => 'Erst Struktur, dann Ausgaben',
				'plan_p'      => 'Bestimmen Sie zuerst den täglichen Bewegungsradius, danach die Anzahl der Stops. Das reduziert Zeitverlust und Zusatzkosten.',
				'budget_h2'   => 'Budget mit klaren Prioritäten',
				'budget_p'    => 'Verkehr, Unterkunft und Tickets als fixe Blöcke planen. Essen und Einkäufe bleiben flexibel steuerbar.',
				'check_h2'    => 'Checkliste vor Abreise',
				'check_p'     => 'Storno-Regeln, Einlassfenster und Alternativen prüfen, um bei Änderungen keine unnötigen Kosten zu haben.',
				'close'       => 'Wenn Verkehr, Unterkunft und Tickets früh feststehen, bleibt die Reise planbar und finanziell stabil.',
				'list_intro'  => 'Drei Long-Tail-Suchphrasen für Vergleich und Recherche:',
				'focus_mid'   => 'In der Feinplanung prüfe ich alles erneut mit %s.',
				'focus_end'   => 'Zum Abschluss dient %s als letzter Machbarkeits-Check.',
				'pad'         => 'Speichern Sie Routen offline, damit kurzfristige Änderungen keine Zusatzkosten auslösen.',
				'ops_h2'      => 'Operative Kernpunkte',
				'ops_p'       => 'Mit „%1$s“ als Leitfaden zuerst %2$s vergleichen, dann für %3$s Alternativen planen und am Ende Budget sowie Zeit prüfen.',
			);
		case 'it':
			return array(
				'lead'        => 'Guida scritta da viaggiatore per viaggiatori: meno sprechi, più controllo dei costi e itinerario davvero sostenibile.',
				'plan_h2'     => 'Prima la struttura, poi la spesa',
				'plan_p'      => 'Definisci prima il raggio di spostamento giornaliero e solo dopo il numero di tappe: così riduci tempi morti e costi extra.',
				'budget_h2'   => 'Ripartizione budget orientata al risparmio utile',
				'budget_p'    => 'Trasporti, alloggio e biglietti vanno fissati come blocchi principali. Cibo e shopping restano flessibili.',
				'check_h2'    => 'Checklist prima della partenza',
				'check_p'     => 'Controlla politiche di cancellazione, finestre di ingresso e piani alternativi per evitare perdite economiche.',
				'close'       => 'Con trasporti, alloggi e ticket definiti in anticipo, il viaggio resta più sereno e con spesa sotto controllo.',
				'list_intro'  => 'Tre long-tail da usare subito per ricerca e confronto:',
				'focus_mid'   => 'Durante la revisione centrale, riallineo tutto su %s.',
				'focus_end'   => 'In chiusura torno a %s per verificare la fattibilità giorno per giorno.',
				'pad'         => 'Salva anche i percorsi offline: aiuta a limitare costi imprevisti in caso di cambi rapidi.',
				'ops_h2'      => 'Punti operativi',
				'ops_p'       => 'Con \"%1$s\" come guida, confronta prima %2$s, prepara un piano alternativo per %3$s e verifica infine budget e tempi.',
			);
		case 'es':
			return array(
				'lead'        => 'Esta guía está escrita con enfoque viajero: gastar con criterio, evitar sobrecostes y mantener un plan realmente ejecutable.',
				'plan_h2'     => 'Primero estructura, luego gasto',
				'plan_p'      => 'Define primero el radio de movimiento diario y después la cantidad de visitas. Así reduces tiempos muertos y costes imprevistos.',
				'budget_h2'   => 'Distribución inteligente del presupuesto',
				'budget_p'    => 'Transporte, alojamiento y entradas van como base fija. Comida y compras se ajustan como parte variable.',
				'check_h2'    => 'Checklist antes de salir',
				'check_p'     => 'Revisa cancelaciones, franjas de acceso y alternativas para minimizar pérdidas si el plan cambia.',
				'close'       => 'Con transporte, alojamiento y entradas cerrados antes, el viaje queda más estable y fácil de controlar.',
				'list_intro'  => 'Tres long-tail útiles para investigar y comparar mejor:',
				'focus_mid'   => 'En la parte media del plan, vuelvo a validar todo con %s.',
				'focus_end'   => 'Al cerrar, uso %s como comprobación final de viabilidad diaria.',
				'pad'         => 'Guardar rutas sin conexión ayuda a evitar gastos extra cuando hay cambios de última hora.',
				'ops_h2'      => 'Puntos operativos',
				'ops_p'       => 'Con \"%1$s\" como eje, compara primero %2$s, prepara alternativa para %3$s y valida al final presupuesto y tiempo.',
			);
		default:
			return array(
				'lead'        => 'This guide is written from a traveler perspective: spend carefully, avoid waste, and keep the plan practical.',
				'plan_h2'     => 'Set structure first, spending second',
				'plan_p'      => 'Define your daily movement radius first, then decide how many stops fit. This reduces wasted transit time and surprise costs.',
				'budget_h2'   => 'Budget split that actually works',
				'budget_p'    => 'Treat transport, accommodation, and tickets as fixed cost blocks. Keep food and shopping as adjustable variables.',
				'check_h2'    => 'Pre-trip checklist',
				'check_p'     => 'Confirm cancellation terms, entry windows, and backup options before departure to limit financial risk.',
				'close'       => 'Lock transport, stays, and tickets first, then keep each day flexible enough to absorb real-world changes.',
				'list_intro'  => 'Use these three long-tail phrases for practical comparison research:',
				'focus_mid'   => 'In mid-planning, I realign decisions around %s.',
				'focus_end'   => 'At the end, I validate every day again against %s.',
				'pad'         => 'Store routes offline as a backup to avoid extra costs when plans change suddenly.',
				'ops_h2'      => 'Operational checklist',
				'ops_p'       => 'Using "%1$s" as the anchor, compare %2$s first, prepare a fallback for %3$s, then validate budget and timing.',
			);
	}
}

/**
 * Build article body with constraints:
 * - Focus keyphrase appears 3 times in dedicated paragraphs.
 * - Each long-tail appears once.
 * - L1 min chars 2200, others 1200.
 */
function ag_tc_build_content( string $locale, string $title, string $focus, array $long_tail, int $min_chars ): string {
	$copy = ag_tc_locale_copy( $locale );

	$long_tail = array_values(
		array_slice(
			array_filter(
				$long_tail,
				static function ( $v ) {
					return '' !== trim( (string) $v );
				}
			),
			0,
			3
		)
	);

	while ( count( $long_tail ) < 3 ) {
		$long_tail[] = $focus;
	}

	$left_first = ( crc32( $title ) % 2 ) === 0;

	$parts   = array();
	$parts[] = '<p>' . esc_html( $focus . '：' . $copy['lead'] ) . '</p>'; // Focus #1
	$parts[] = '<h2>' . esc_html( $copy['plan_h2'] ) . '</h2>';
	$parts[] = '<p>' . esc_html( $title . '。' . $copy['plan_p'] ) . '</p>';
	$parts[] = '<p>' . esc_html( sprintf( $copy['focus_mid'], $focus ) ) . '</p>'; // Focus #2

	if ( $left_first ) {
		$parts[] = '<h2>' . esc_html( $copy['budget_h2'] ) . '</h2>';
		$parts[] = '<p>' . esc_html( $copy['budget_p'] ) . '</p>';
		$parts[] = '<h2>' . esc_html( $copy['check_h2'] ) . '</h2>';
		$parts[] = '<p>' . esc_html( $copy['check_p'] ) . '</p>';
	} else {
		$parts[] = '<h2>' . esc_html( $copy['check_h2'] ) . '</h2>';
		$parts[] = '<p>' . esc_html( $copy['check_p'] ) . '</p>';
		$parts[] = '<h2>' . esc_html( $copy['budget_h2'] ) . '</h2>';
		$parts[] = '<p>' . esc_html( $copy['budget_p'] ) . '</p>';
	}

	$parts[] = '<h2>' . esc_html( $copy['ops_h2'] ) . '</h2>';
	$parts[] = '<p>' . esc_html( sprintf( $copy['ops_p'], $focus, $long_tail[0], $long_tail[1] ) ) . '</p>';

	$parts[] = '<p>' . esc_html( $copy['list_intro'] ) . '</p>';
	$parts[] = '<ul>';
	$parts[] = '<li>' . esc_html( $long_tail[0] ) . '</li>';
	$parts[] = '<li>' . esc_html( $long_tail[1] ) . '</li>';
	$parts[] = '<li>' . esc_html( $long_tail[2] ) . '</li>';
	$parts[] = '</ul>';
	$parts[] = '<p>' . esc_html( sprintf( $copy['focus_end'], $focus ) ) . '</p>'; // Focus #3
	$parts[] = '<p>' . esc_html( $copy['close'] ) . '</p>';

	$content = implode( "\n", $parts );

	// Pad to target length with additional practical tips.
	$guard = 0;
	while ( mb_strlen( wp_strip_all_tags( $content ) ) < $min_chars && $guard < 60 ) {
		$content .= "\n<p>" . esc_html( $copy['pad'] ) . '</p>';
		$guard++;
	}

	return $content;
}

$updated_rows = 0;

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

		$long_tail_raw = PostData::get_field( (int) $post_id, 'focusLongTail' );
		$long_tail     = ag_tc_split_long_tail( $long_tail_raw );
		$min_chars     = in_array( (int) $post_id, $l1_ids, true ) ? 2200 : 1200;

		$content = ag_tc_build_content(
			$locale,
			(string) $post->post_title,
			$focus,
			$long_tail,
			$min_chars
		);

		wp_update_post(
			array(
				'ID'           => (int) $post_id,
				'post_content' => $content,
			)
		);

		$updated_rows++;
	}

	restore_current_blog();
}

echo sprintf( "Updated post content rows: %d\n", $updated_rows );
