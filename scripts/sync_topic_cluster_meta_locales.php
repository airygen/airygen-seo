<?php
/**
 * Sync localized Topic Cluster metadata for multisite locales.
 *
 * Usage:
 * wp eval-file /var/www/html/wp-content/plugins/airygen-seo/scripts/sync_topic_cluster_meta_locales.php --allow-root
 */

use Airygen\Support\Meta\PostData;

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

$locales = array(
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

$map = array(
	'en_US' => array(
		20 => array(
			'title'     => 'Japan Independent Travel Guide: Plan, Transit, Hotels, and Budget',
			'focus'     => 'Japan independent travel',
			'long_tail' => array( 'Japan travel guide for first timers', 'Japan self-guided trip checklist', 'Japan independent travel tips' ),
		),
		22 => array(
			'title'     => 'Japan Itinerary Planning: Pre-Trip Checklist and Route Setup',
			'focus'     => 'Japan trip planning',
			'long_tail' => array( 'Japan itinerary 7 days', 'Japan travel planning checklist', 'How to plan a Japan itinerary' ),
		),
		23 => array(
			'title'     => 'Japan Transportation Guide: JR Pass, IC Cards, and Rail Tickets',
			'focus'     => 'Japan transport pass',
			'long_tail' => array( 'JR Pass vs regional pass', 'Japan train pass comparison', 'Best transport cards in Japan' ),
		),
		26 => array(
			'title'     => 'Japan Hotel and Ticket Booking: Common Pitfalls and How to Avoid Them',
			'focus'     => 'Japan hotel booking',
			'long_tail' => array( 'Japan hotel booking tips', 'Japan flight and hotel booking guide', 'Japan trip booking mistakes' ),
		),
		32 => array(
			'title'     => 'Where to Stay in Japan: One Base or Multi-City Hotel Strategy',
			'focus'     => 'Japan accommodation strategy',
			'long_tail' => array( 'Best areas to stay in Japan', 'Japan one hotel base itinerary', 'Japan multi-city hotel plan' ),
		),
		42 => array(
			'title'     => 'City Transit in Japan: Subway Day Pass, Bus, and Walking Balance',
			'focus'     => 'Japan subway day pass',
			'long_tail' => array( 'Best subway day pass in Japan', 'Japan city transport planning', 'How to use buses and subways in Japan' ),
		),
		50 => array(
			'title'     => 'Japan Travel Risk Guide: Typhoon Delays, Lost Items, Medical and Insurance',
			'focus'     => 'Japan travel insurance',
			'long_tail' => array( 'Best travel insurance for Japan', 'Japan typhoon travel contingency', 'Emergency medical help in Japan for tourists' ),
		),
		28 => array(
			'title'     => 'How Many Days in Japan: Trip Length, Pace, and Distance Planning',
			'focus'     => 'how many days in Japan',
			'long_tail' => array( 'How long should I stay in Japan', 'Japan itinerary by trip length', 'Japan travel pace planning' ),
		),
		36 => array(
			'title'     => 'Is JR Pass Worth It? Cost Formula, Use Cases, and Common Mistakes',
			'focus'     => 'is JR Pass worth it',
			'long_tail' => array( 'When JR Pass is worth buying', 'JR Pass calculator guide', 'Best routes for JR Pass' ),
		),
		38 => array(
			'title'     => 'Suica vs PASMO vs ICOCA: Which IC Card Should You Choose?',
			'focus'     => 'Suica vs PASMO',
			'long_tail' => array( 'Suica PASMO ICOCA differences', 'Best IC card for Japan travel', 'How to refund Suica or PASMO' ),
		),
		40 => array(
			'title'     => 'How to Reserve Shinkansen Seats: Reserved vs Non-Reserved and App Booking',
			'focus'     => 'shinkansen reservation',
			'long_tail' => array( 'Shinkansen reserved seat booking', 'Reserved vs non-reserved shinkansen', 'Book Shinkansen tickets online' ),
		),
		48 => array(
			'title'     => 'How to Book Popular Tickets in Japan: Disney, USJ, and Exhibitions',
			'focus'     => 'Japan Disney ticket booking',
			'long_tail' => array( 'Tokyo Disney ticket reservation', 'USJ ticket booking guide', 'Japan attraction ticket booking tips' ),
		),
		44 => array(
			'title'     => 'Japan Travel Budget Breakdown: Hotels, Transit, Food, and Activities',
			'focus'     => 'Japan travel budget',
			'long_tail' => array( 'Japan trip cost estimate', 'Japan budget planning guide', 'How to save money traveling in Japan' ),
		),
		46 => array(
			'title'     => 'Japan Hotel Booking Tips: Cancellation Rules, Discounts, and Peak Season Strategy',
			'focus'     => 'Japan hotel booking tips',
			'long_tail' => array( 'Japan peak season hotel booking', 'Japan hotel cancellation policy tips', 'Best time to book hotels in Japan' ),
		),
		34 => array(
			'title'     => 'Japan Sightseeing Itinerary: Mix Popular Spots, Hidden Gems, and Rain Plans',
			'focus'     => 'Japan itinerary planning',
			'long_tail' => array( 'Japan day-by-day sightseeing plan', 'Rainy day alternatives in Japan', 'How to structure a Japan trip route' ),
		),
		30 => array(
			'title'     => 'Best Japan Routes: Kanto, Kansai, Kyushu, or Hokkaido?',
			'focus'     => 'Japan route recommendation',
			'long_tail' => array( 'Kanto vs Kansai itinerary', 'Kyushu or Hokkaido trip planning', 'Best Japan route for first-time visitors' ),
		),
	),
	'ja'    => array(
		20 => array(
			'title'     => '日本自由旅行ガイド：行程・交通・宿・予算をまとめて解説',
			'focus'     => '日本 自由旅行',
			'long_tail' => array( '日本自由旅行 初心者', '日本自由旅行 持ち物 チェック', '日本自由旅行 注意点' ),
		),
		22 => array(
			'title'     => '日本旅行の計画方法：出発前準備と旅程づくり',
			'focus'     => '日本旅行 計画',
			'long_tail' => array( '日本旅行 7日間 モデルコース', '日本旅行 計画 立て方', '日本旅行 事前準備 チェックリスト' ),
		),
		23 => array(
			'title'     => '日本の交通攻略：JRパス・ICカード・乗車券の選び方',
			'focus'     => '日本 交通 パス',
			'long_tail' => array( 'JRパス 地域パス 比較', '日本 交通系ICカード おすすめ', '日本 電車 乗り方 観光' ),
		),
		26 => array(
			'title'     => '日本のホテル・チケット予約で失敗しないためのポイント',
			'focus'     => '日本 ホテル 予約',
			'long_tail' => array( '日本 ホテル予約 コツ', '日本 航空券 ホテル 予約方法', '日本旅行 予約 失敗 あるある' ),
		),
		32 => array(
			'title'     => '日本旅行の宿選び：1都市滞在か周遊かを比較',
			'focus'     => '日本 旅行 宿泊',
			'long_tail' => array( '日本旅行 どこに泊まる', '日本 連泊 おすすめ エリア', '日本 周遊 宿泊 計画' ),
		),
		42 => array(
			'title'     => '日本の市内移動：地下鉄1日券・バス・徒歩バランスの組み方',
			'focus'     => '日本 地下鉄 一日券',
			'long_tail' => array( '地下鉄1日券 お得な使い方', '日本 観光 移動手段 比較', '日本 バス 地下鉄 使い分け' ),
		),
		50 => array(
			'title'     => '日本旅行リスク対策：台風・紛失・医療・保険の備え',
			'focus'     => '日本 旅行 保険',
			'long_tail' => array( '日本旅行保険 おすすめ', '日本 台風 旅行 対応', '日本旅行 病院 受診方法' ),
		),
		28 => array(
			'title'     => '日本旅行は何日必要？日数と移動ペースの決め方',
			'focus'     => '日本旅行 何日',
			'long_tail' => array( '日本旅行 日数 目安', '日本旅行 4泊5日 モデルコース', '日本旅行 スケジュール 組み方' ),
		),
		36 => array(
			'title'     => 'JRパスは本当にお得？計算方法と判断基準',
			'focus'     => 'JRパス お得',
			'long_tail' => array( 'JRパス 買うべき人', 'JRパス 料金 シミュレーション', 'JRパス おすすめ ルート' ),
		),
		38 => array(
			'title'     => 'Suica・PASMO・ICOCA比較：旅行者はどれを選ぶべき？',
			'focus'     => 'Suica PASMO 違い',
			'long_tail' => array( 'Suica PASMO ICOCA 比較', '日本 交通ICカード 選び方', 'Suica PASMO 払い戻し' ),
		),
		40 => array(
			'title'     => '新幹線の予約方法：指定席・自由席・アプリ購入の流れ',
			'focus'     => '新幹線 予約',
			'long_tail' => array( '新幹線 指定席 予約方法', '新幹線 自由席 指定席 違い', '新幹線 ネット予約 やり方' ),
		),
		48 => array(
			'title'     => '人気チケット予約ガイド：ディズニー・USJ・展示会',
			'focus'     => '日本 ディズニー チケット',
			'long_tail' => array( 'ディズニーチケット 予約方法', 'USJ チケット 購入 タイミング', '日本 展示会 チケット 取り方' ),
		),
		44 => array(
			'title'     => '日本旅行の予算内訳：宿・交通・食費・体験費を見える化',
			'focus'     => '日本旅行 予算',
			'long_tail' => array( '日本旅行 費用 シミュレーション', '日本旅行 予算 立て方', '日本旅行 節約術' ),
		),
		46 => array(
			'title'     => '日本ホテル予約のコツ：キャンセル規定と繁忙期対策',
			'focus'     => '日本 ホテル 予約 コツ',
			'long_tail' => array( '日本 ホテル 早割 予約', '日本 ホテル キャンセル料', '繁忙期 日本 ホテル 取り方' ),
		),
		34 => array(
			'title'     => '日本観光の行程づくり：人気スポットと雨の日プランを両立',
			'focus'     => '日本 旅行 ルート',
			'long_tail' => array( '日本観光 モデルコース', '日本旅行 雨の日 観光', '日本旅行 効率的な回り方' ),
		),
		30 => array(
			'title'     => '日本旅行のルート比較：関東・関西・九州・北海道',
			'focus'     => '日本 旅行 おすすめ ルート',
			'long_tail' => array( '関東 関西 どっち 旅行', '九州 北海道 旅行 比較', '初めての日本旅行 おすすめエリア' ),
		),
	),
	'ko_KR' => array(
		20 => array(
			'title'     => '일본 자유여행 가이드: 일정·교통·숙소·예산 한 번에 정리',
			'focus'     => '일본 자유여행',
			'long_tail' => array( '일본 자유여행 초보 가이드', '일본 자유여행 준비물 체크리스트', '일본 자유여행 주의사항' ),
		),
		22 => array(
			'title'     => '일본 여행 일정 짜는 법: 출발 전 준비와 동선 설계',
			'focus'     => '일본 여행 일정',
			'long_tail' => array( '일본 7일 여행 코스', '일본 여행 계획 세우기', '일본 여행 준비 체크리스트' ),
		),
		23 => array(
			'title'     => '일본 교통 공략: JR 패스·IC카드·철도권 비교',
			'focus'     => '일본 교통 패스',
			'long_tail' => array( 'JR 패스 지역패스 비교', '일본 교통카드 추천', '일본 기차 타는 법' ),
		),
		26 => array(
			'title'     => '일본 호텔·티켓 예약 실수 줄이는 방법',
			'focus'     => '일본 호텔 예약',
			'long_tail' => array( '일본 호텔 예약 팁', '일본 항공권 숙소 예약 순서', '일본 여행 예약 실수' ),
		),
		32 => array(
			'title'     => '일본 숙소 선택 가이드: 한 곳 연박 vs 지역 이동',
			'focus'     => '일본 자유여행 숙소',
			'long_tail' => array( '일본 어디에 숙소 잡을까', '일본 연박 추천 지역', '일본 다도시 숙소 계획' ),
		),
		42 => array(
			'title'     => '일본 시내 이동 최적화: 지하철 1일권·버스·도보 비율',
			'focus'     => '일본 지하철 1일권',
			'long_tail' => array( '일본 지하철 1일권 추천', '일본 시내 교통 동선 짜기', '일본 버스 지하철 이용법' ),
		),
		50 => array(
			'title'     => '일본 여행 리스크 관리: 태풍·분실·의료·보험 대응',
			'focus'     => '일본 여행자 보험',
			'long_tail' => array( '일본 여행자 보험 추천', '일본 태풍 여행 대처', '일본 여행 중 병원 이용' ),
		),
		28 => array(
			'title'     => '일본 여행 며칠이 적당할까? 일정 길이와 속도 설정',
			'focus'     => '일본 자유여행 며칠',
			'long_tail' => array( '일본 여행 일수 추천', '일본 4박5일 코스', '일본 여행 일정 템포' ),
		),
		36 => array(
			'title'     => 'JR 패스, 진짜 이득일까? 계산법과 판단 기준',
			'focus'     => 'JR 패스 가치',
			'long_tail' => array( 'JR 패스 사야 하는 경우', 'JR 패스 비용 계산', 'JR 패스 추천 노선' ),
		),
		38 => array(
			'title'     => 'Suica·PASMO·ICOCA 비교: 어떤 교통카드가 맞을까?',
			'focus'     => 'Suica PASMO 차이',
			'long_tail' => array( 'Suica PASMO ICOCA 비교', '일본 교통카드 선택법', 'Suica PASMO 환불 방법' ),
		),
		40 => array(
			'title'     => '신칸센 예약 방법: 지정석·자유석·앱 예매',
			'focus'     => '신칸센 예약',
			'long_tail' => array( '신칸센 지정석 예약', '신칸센 자유석 차이', '신칸센 온라인 예매' ),
		),
		48 => array(
			'title'     => '일본 인기 티켓 예약: 디즈니·USJ·전시',
			'focus'     => '일본 디즈니 티켓 예약',
			'long_tail' => array( '도쿄 디즈니 티켓 예매', 'USJ 티켓 예약 방법', '일본 명소 입장권 예매' ),
		),
		44 => array(
			'title'     => '일본 자유여행 예산 짜기: 숙소·교통·식비·입장권',
			'focus'     => '일본 자유여행 예산',
			'long_tail' => array( '일본 여행 비용 계산', '일본 예산 계획 세우기', '일본 여행 절약 팁' ),
		),
		46 => array(
			'title'     => '일본 호텔 예약 전략: 취소 규정·성수기 대응',
			'focus'     => '일본 호텔 예약 팁',
			'long_tail' => array( '일본 성수기 호텔 예약', '일본 호텔 취소 규정', '일본 호텔 언제 예약할까' ),
		),
		34 => array(
			'title'     => '일본 관광 일정 구성: 인기·비인기 코스와 우천 플랜',
			'focus'     => '일본 여행 코스 짜기',
			'long_tail' => array( '일본 여행 코스 추천', '일본 여행 비 오는 날 일정', '일본 동선 효율적으로 짜기' ),
		),
		30 => array(
			'title'     => '일본 여행 루트 추천: 간토·간사이·규슈·홋카이도 비교',
			'focus'     => '일본 여행 루트 추천',
			'long_tail' => array( '간토 간사이 여행 비교', '규슈 홋카이도 여행 선택', '일본 첫 여행 지역 추천' ),
		),
	),
	'ru_RU' => array(
		20 => array(
			'title'     => 'Самостоятельное путешествие по Японии: маршрут, транспорт, жильё и бюджет',
			'focus'     => 'самостоятельное путешествие по Японии',
			'long_tail' => array( 'Япония самостоятельно с чего начать', 'чеклист поездки в Японию', 'советы для самостоятельной поездки в Японию' ),
		),
		22 => array(
			'title'     => 'Планирование поездки в Японию: подготовка и маршрут',
			'focus'     => 'план поездки в Японию',
			'long_tail' => array( 'маршрут по Японии на 7 дней', 'как спланировать поездку в Японию', 'подготовка к поездке в Японию' ),
		),
		23 => array(
			'title'     => 'Транспорт в Японии: JR Pass, IC-карты и билеты',
			'focus'     => 'транспортные проездные Япония',
			'long_tail' => array( 'JR Pass или региональные проездные', 'сравнение транспортных карт Японии', 'как пользоваться поездами в Японии' ),
		),
		26 => array(
			'title'     => 'Бронирование отелей и билетов в Японии: как избежать ошибок',
			'focus'     => 'бронирование отелей Япония',
			'long_tail' => array( 'советы по бронированию отелей в Японии', 'как бронировать перелет и отель в Японии', 'ошибки при планировании поездки в Японию' ),
		),
		32 => array(
			'title'     => 'Где жить в Японии: один базовый город или переезды',
			'focus'     => 'проживание в Японии самостоятельно',
			'long_tail' => array( 'лучшие районы для проживания в Японии', 'Япония поездка с одной базой', 'план проживания по городам Японии' ),
		),
		42 => array(
			'title'     => 'Передвижение по городу в Японии: метро, автобус и пешком',
			'focus'     => 'проездной метро Япония',
			'long_tail' => array( 'дневной проездной метро в Японии', 'план городского транспорта в Японии', 'как совмещать автобус и метро в Японии' ),
		),
		50 => array(
			'title'     => 'Риски в поездке по Японии: тайфун, потеря вещей, медицина, страховка',
			'focus'     => 'страховка для поездки в Японию',
			'long_tail' => array( 'какую страховку выбрать для Японии', 'что делать при тайфуне в Японии', 'медицинская помощь туристу в Японии' ),
		),
		28 => array(
			'title'     => 'Сколько дней нужно на Японию: темп и длина маршрута',
			'focus'     => 'сколько дней в Японии',
			'long_tail' => array( 'на сколько ехать в Японию впервые', 'маршрут по Японии на 5 дней', 'план поездки по Японии по дням' ),
		),
		36 => array(
			'title'     => 'Стоит ли покупать JR Pass: расчёт и критерии',
			'focus'     => 'стоит ли JR Pass',
			'long_tail' => array( 'когда выгодно покупать JR Pass', 'как рассчитать окупаемость JR Pass', 'лучшие маршруты для JR Pass' ),
		),
		38 => array(
			'title'     => 'Suica, PASMO или ICOCA: какую карту выбрать туристу',
			'focus'     => 'разница Suica PASMO',
			'long_tail' => array( 'Suica PASMO ICOCA сравнение', 'лучшая транспортная карта в Японии', 'как вернуть Suica или PASMO' ),
		),
		40 => array(
			'title'     => 'Как бронировать синкансэн: резервируемые и нерезервируемые места',
			'focus'     => 'бронирование синкансэн',
			'long_tail' => array( 'как забронировать место в синкансэне', 'разница reserved и non-reserved shinkansen', 'покупка билетов на синкансэн онлайн' ),
		),
		48 => array(
			'title'     => 'Бронирование популярных билетов в Японии: Disney, USJ и выставки',
			'focus'     => 'билеты Tokyo Disney бронирование',
			'long_tail' => array( 'как купить билеты в Tokyo Disney', 'бронирование билетов USJ', 'билеты на достопримечательности Японии' ),
		),
		44 => array(
			'title'     => 'Бюджет поездки в Японию: жильё, транспорт, еда и развлечения',
			'focus'     => 'бюджет поездки в Японию',
			'long_tail' => array( 'сколько стоит поездка в Японию', 'как рассчитать бюджет поездки в Японию', 'как сэкономить в Японии туристу' ),
		),
		46 => array(
			'title'     => 'Бронирование отелей в Японии: отмена, скидки и высокий сезон',
			'focus'     => 'советы по бронированию отелей Япония',
			'long_tail' => array( 'отели в Японии в высокий сезон', 'условия отмены отелей в Японии', 'когда лучше бронировать отели в Японии' ),
		),
		34 => array(
			'title'     => 'План достопримечательностей в Японии: популярные места и план на дождь',
			'focus'     => 'маршрут по Японии',
			'long_tail' => array( 'маршрут по Японии для туриста', 'чем заняться в Японии в дождь', 'как грамотно составить маршрут по Японии' ),
		),
		30 => array(
			'title'     => 'Как выбрать маршрут по Японии: Канто, Кансай, Кюсю или Хоккайдо',
			'focus'     => 'маршрут по Японии рекомендации',
			'long_tail' => array( 'Канто или Кансай что выбрать', 'Кюсю или Хоккайдо для путешествия', 'лучший маршрут по Японии для первого раза' ),
		),
	),
	'pt_PT' => array(
		20 => array(
			'title'     => 'Viagem independente ao Japão: roteiro, transporte, alojamento e orçamento',
			'focus'     => 'viagem independente ao Japão',
			'long_tail' => array( 'guia Japão para primeira viagem', 'checklist de viagem ao Japão', 'dicas para viajar ao Japão por conta própria' ),
		),
		22 => array(
			'title'     => 'Como planear uma viagem ao Japão: preparação e roteiro',
			'focus'     => 'planeamento de viagem Japão',
			'long_tail' => array( 'roteiro Japão 7 dias', 'como montar roteiro para o Japão', 'preparação antes de viajar para o Japão' ),
		),
		23 => array(
			'title'     => 'Transporte no Japão: JR Pass, cartões IC e bilhetes',
			'focus'     => 'passes de transporte Japão',
			'long_tail' => array( 'JR Pass vs passes regionais', 'comparação de cartões de transporte no Japão', 'como usar comboios no Japão' ),
		),
		26 => array(
			'title'     => 'Reservas de hotel e bilhetes no Japão: erros comuns e como evitar',
			'focus'     => 'reservas de hotel Japão',
			'long_tail' => array( 'dicas de reserva de hotel no Japão', 'como reservar voo e hotel para o Japão', 'erros ao planear viagem ao Japão' ),
		),
		32 => array(
			'title'     => 'Onde ficar no Japão: base única ou várias cidades',
			'focus'     => 'alojamento Japão viagem',
			'long_tail' => array( 'melhores zonas para ficar no Japão', 'viagem ao Japão com hotel base', 'plano de alojamento por cidades no Japão' ),
		),
		42 => array(
			'title'     => 'Mobilidade urbana no Japão: passe diário de metro, autocarro e caminhada',
			'focus'     => 'passe diário metro Japão',
			'long_tail' => array( 'melhor passe diário no Japão', 'planeamento de transportes urbanos no Japão', 'como combinar metro e autocarro no Japão' ),
		),
		50 => array(
			'title'     => 'Riscos na viagem ao Japão: tufão, perda, saúde e seguro',
			'focus'     => 'seguro de viagem Japão',
			'long_tail' => array( 'melhor seguro para viagem ao Japão', 'o que fazer em caso de tufão no Japão', 'assistência médica para turistas no Japão' ),
		),
		28 => array(
			'title'     => 'Quantos dias ficar no Japão: duração ideal e ritmo do roteiro',
			'focus'     => 'quantos dias no Japão',
			'long_tail' => array( 'quantos dias para primeira viagem ao Japão', 'roteiro Japão 5 dias', 'planeamento de ritmo de viagem no Japão' ),
		),
		36 => array(
			'title'     => 'JR Pass vale a pena? Como calcular e decidir',
			'focus'     => 'JR Pass vale a pena',
			'long_tail' => array( 'quando compensa comprar JR Pass', 'simulador de custo JR Pass', 'melhores rotas para usar JR Pass' ),
		),
		38 => array(
			'title'     => 'Suica, PASMO ou ICOCA: qual cartão escolher?',
			'focus'     => 'diferença Suica PASMO',
			'long_tail' => array( 'comparação Suica PASMO ICOCA', 'melhor cartão de transporte no Japão', 'como pedir reembolso do Suica' ),
		),
		40 => array(
			'title'     => 'Reserva de Shinkansen: lugares reservados, não reservados e app',
			'focus'     => 'reserva Shinkansen',
			'long_tail' => array( 'como reservar lugar no Shinkansen', 'diferença entre lugar reservado e não reservado', 'comprar bilhete de Shinkansen online' ),
		),
		48 => array(
			'title'     => 'Reservar bilhetes populares no Japão: Disney, USJ e eventos',
			'focus'     => 'bilhete Disney Japão reserva',
			'long_tail' => array( 'reserva de bilhetes Tokyo Disney', 'como comprar bilhete da USJ', 'bilhetes para atrações no Japão' ),
		),
		44 => array(
			'title'     => 'Orçamento de viagem ao Japão: alojamento, transporte, comida e atividades',
			'focus'     => 'orçamento viagem Japão',
			'long_tail' => array( 'custo de viagem ao Japão', 'como planear orçamento para o Japão', 'dicas para poupar numa viagem ao Japão' ),
		),
		46 => array(
			'title'     => 'Estratégia para reservar hotéis no Japão: cancelamento, descontos e época alta',
			'focus'     => 'dicas reserva hotel Japão',
			'long_tail' => array( 'reservar hotel no Japão em época alta', 'política de cancelamento de hotéis no Japão', 'quando reservar hotéis no Japão' ),
		),
		34 => array(
			'title'     => 'Planeamento de atrações no Japão: pontos populares e plano para chuva',
			'focus'     => 'itinerário Japão',
			'long_tail' => array( 'roteiro de atrações no Japão', 'o que fazer no Japão em dia de chuva', 'como organizar o itinerário no Japão' ),
		),
		30 => array(
			'title'     => 'Rota ideal no Japão: Kanto, Kansai, Kyushu ou Hokkaido?',
			'focus'     => 'rota Japão recomendada',
			'long_tail' => array( 'Kanto ou Kansai para viajar', 'Kyushu ou Hokkaido qual escolher', 'melhor rota no Japão para primeira viagem' ),
		),
	),
	'fr_FR' => array(
		20 => array(
			'title'     => 'Voyage libre au Japon : itinéraire, transport, hébergement et budget',
			'focus'     => 'voyage libre au Japon',
			'long_tail' => array( 'guide Japon premier voyage', 'check-list voyage au Japon', 'conseils pour voyager au Japon en autonomie' ),
		),
		22 => array(
			'title'     => 'Planifier un voyage au Japon : préparation et construction d’itinéraire',
			'focus'     => 'itinéraire voyage Japon',
			'long_tail' => array( 'itinéraire Japon 7 jours', 'comment planifier un voyage au Japon', 'préparer son voyage au Japon' ),
		),
		23 => array(
			'title'     => 'Transports au Japon : JR Pass, cartes IC et billets',
			'focus'     => 'pass transport Japon',
			'long_tail' => array( 'JR Pass ou pass régional', 'comparatif cartes de transport Japon', 'comment prendre le train au Japon' ),
		),
		26 => array(
			'title'     => 'Réserver hôtels et billets au Japon : erreurs à éviter',
			'focus'     => 'réservation hôtel Japon',
			'long_tail' => array( 'conseils réservation hôtel Japon', 'réserver vol et hôtel pour le Japon', 'erreurs fréquentes voyage Japon' ),
		),
		32 => array(
			'title'     => 'Où dormir au Japon : base unique ou multi-villes ?',
			'focus'     => 'hébergement Japon voyage',
			'long_tail' => array( 'meilleurs quartiers où loger au Japon', 'voyage au Japon avec un seul hôtel base', 'plan d’hébergement multi-villes Japon' ),
		),
		42 => array(
			'title'     => 'Déplacements urbains au Japon : pass métro, bus et marche',
			'focus'     => 'pass métro journée Japon',
			'long_tail' => array( 'meilleur pass métro au Japon', 'organiser ses déplacements en ville au Japon', 'utiliser bus et métro au Japon' ),
		),
		50 => array(
			'title'     => 'Gestion des risques au Japon : typhon, perte, santé et assurance',
			'focus'     => 'assurance voyage Japon',
			'long_tail' => array( 'meilleure assurance pour le Japon', 'voyager au Japon pendant un typhon', 'soins médicaux au Japon pour touristes' ),
		),
		28 => array(
			'title'     => 'Combien de jours pour le Japon ? Durée et rythme de voyage',
			'focus'     => 'combien de jours au Japon',
			'long_tail' => array( 'durée idéale premier voyage Japon', 'itinéraire Japon 5 jours', 'rythme de voyage au Japon' ),
		),
		36 => array(
			'title'     => 'JR Pass : est-ce rentable ? Méthode de calcul et cas pratiques',
			'focus'     => 'JR Pass rentable',
			'long_tail' => array( 'quand acheter JR Pass', 'calcul rentabilité JR Pass', 'meilleurs trajets pour JR Pass' ),
		),
		38 => array(
			'title'     => 'Suica, PASMO ou ICOCA : quelle carte choisir ?',
			'focus'     => 'différence Suica PASMO',
			'long_tail' => array( 'comparatif Suica PASMO ICOCA', 'meilleure carte transport Japon', 'remboursement carte Suica ou PASMO' ),
		),
		40 => array(
			'title'     => 'Réserver le Shinkansen : places réservées, non réservées et appli',
			'focus'     => 'réservation Shinkansen',
			'long_tail' => array( 'réserver une place Shinkansen', 'différence place réservée non réservée Shinkansen', 'acheter billet Shinkansen en ligne' ),
		),
		48 => array(
			'title'     => 'Réserver les billets populaires au Japon : Disney, USJ et expositions',
			'focus'     => 'réservation billet Disney Japon',
			'long_tail' => array( 'billet Tokyo Disney réservation', 'comment réserver billet USJ', 'billets attractions Japon' ),
		),
		44 => array(
			'title'     => 'Budget voyage Japon : hébergement, transport, repas et activités',
			'focus'     => 'budget voyage Japon',
			'long_tail' => array( 'coût d’un voyage au Japon', 'planifier budget voyage Japon', 'astuces pour économiser au Japon' ),
		),
		46 => array(
			'title'     => 'Réservation d’hôtel au Japon : annulation, remises et haute saison',
			'focus'     => 'astuces réservation hôtel Japon',
			'long_tail' => array( 'réserver hôtel Japon haute saison', 'politique d’annulation hôtel Japon', 'quand réserver son hôtel au Japon' ),
		),
		34 => array(
			'title'     => 'Planifier ses visites au Japon : spots incontournables et plan pluie',
			'focus'     => 'organiser itinéraire Japon',
			'long_tail' => array( 'itinéraire visites Japon', 'que faire au Japon quand il pleut', 'optimiser son parcours au Japon' ),
		),
		30 => array(
			'title'     => 'Quelle route choisir au Japon : Kanto, Kansai, Kyushu ou Hokkaido ?',
			'focus'     => 'itinéraire Japon recommandé',
			'long_tail' => array( 'Kanto ou Kansai pour un voyage', 'Kyushu ou Hokkaido que choisir', 'meilleur itinéraire Japon premier voyage' ),
		),
	),
	'de_DE' => array(
		20 => array(
			'title'     => 'Japan Individualreise: Route, Verkehr, Unterkunft und Budget im Überblick',
			'focus'     => 'Japan Individualreise',
			'long_tail' => array( 'Japan Reise für Anfänger', 'Checkliste Japanreise', 'Tipps für Individualreise Japan' ),
		),
		22 => array(
			'title'     => 'Japan Reiseplanung: Vorbereitung und Routenaufbau',
			'focus'     => 'Japan Reiseplanung',
			'long_tail' => array( 'Japan Reiseroute 7 Tage', 'Japan Reise richtig planen', 'Vorbereitung für Japanreise' ),
		),
		23 => array(
			'title'     => 'Transport in Japan: JR Pass, IC-Karten und Tickets',
			'focus'     => 'Japan Verkehrspässe',
			'long_tail' => array( 'JR Pass oder Regionalpass', 'Vergleich japanischer IC-Karten', 'Zugfahren in Japan als Tourist' ),
		),
		26 => array(
			'title'     => 'Hotel- und Ticketbuchung in Japan: typische Fehler vermeiden',
			'focus'     => 'Japan Hotel buchen',
			'long_tail' => array( 'Hotelbuchung Japan Tipps', 'Flug und Hotel Japan buchen', 'Fehler bei Japan Reisebuchung' ),
		),
		32 => array(
			'title'     => 'Unterkunft in Japan: ein Standort oder mehrere Städte?',
			'focus'     => 'Japan Unterkunft Strategie',
			'long_tail' => array( 'wo übernachten in Japan', 'Japan Reise mit einer Basis', 'Japan Rundreise Unterkunft planen' ),
		),
		42 => array(
			'title'     => 'Stadtverkehr in Japan: U-Bahn-Tagespass, Bus und zu Fuß',
			'focus'     => 'Japan U-Bahn Tagespass',
			'long_tail' => array( 'bester U-Bahn Tagespass Japan', 'Stadtverkehr Japan planen', 'Bus und U-Bahn in Japan nutzen' ),
		),
		50 => array(
			'title'     => 'Risikomanagement für Japanreisen: Taifun, Verlust, Medizin, Versicherung',
			'focus'     => 'Japan Reiseversicherung',
			'long_tail' => array( 'beste Reiseversicherung für Japan', 'Japanreise bei Taifun', 'medizinische Hilfe in Japan für Touristen' ),
		),
		28 => array(
			'title'     => 'Wie viele Tage für Japan? Reisedauer und Tempo richtig wählen',
			'focus'     => 'wie viele Tage Japan',
			'long_tail' => array( 'Japan Erstreise Dauer', 'Japan Route 5 Tage', 'Japan Reise Tempo planen' ),
		),
		36 => array(
			'title'     => 'Lohnt sich der JR Pass wirklich? Rechenmodell und Entscheidung',
			'focus'     => 'lohnt sich JR Pass',
			'long_tail' => array( 'wann lohnt sich JR Pass', 'JR Pass Kosten berechnen', 'beste JR Pass Strecken' ),
		),
		38 => array(
			'title'     => 'Suica, PASMO oder ICOCA: Welche Karte ist die richtige?',
			'focus'     => 'Suica PASMO Unterschied',
			'long_tail' => array( 'Suica PASMO ICOCA Vergleich', 'beste Verkehrskarte Japan', 'Suica Rückerstattung' ),
		),
		40 => array(
			'title'     => 'Shinkansen reservieren: reservierte vs. freie Plätze und Online-Buchung',
			'focus'     => 'Shinkansen Reservierung',
			'long_tail' => array( 'Shinkansen Sitzplatz reservieren', 'Unterschied freie und reservierte Plätze Shinkansen', 'Shinkansen Ticket online kaufen' ),
		),
		48 => array(
			'title'     => 'Beliebte Tickets in Japan buchen: Disney, USJ und Ausstellungen',
			'focus'     => 'Japan Disney Ticket Reservierung',
			'long_tail' => array( 'Tokyo Disney Ticket buchen', 'USJ Ticket Reservierung', 'Tickets für Sehenswürdigkeiten in Japan' ),
		),
		44 => array(
			'title'     => 'Japan Reisebudget: Unterkunft, Verkehr, Essen und Aktivitäten',
			'focus'     => 'Japan Reisebudget',
			'long_tail' => array( 'Kosten einer Japanreise', 'Budget für Japanreise planen', 'Japan günstig reisen Tipps' ),
		),
		46 => array(
			'title'     => 'Hotels in Japan buchen: Storno, Rabatte und Hochsaison',
			'focus'     => 'Japan Hotelbuchung Tipps',
			'long_tail' => array( 'Hotel in Japan Hochsaison buchen', 'Stornobedingungen Hotel Japan', 'wann Hotel in Japan buchen' ),
		),
		34 => array(
			'title'     => 'Sehenswürdigkeiten in Japan planen: Highlights plus Regen-Alternative',
			'focus'     => 'Japan Reise Route planen',
			'long_tail' => array( 'Japan Sehenswürdigkeiten Route', 'Japan bei Regen Aktivitäten', 'Japan Reiseverlauf optimieren' ),
		),
		30 => array(
			'title'     => 'Japan Route wählen: Kanto, Kansai, Kyushu oder Hokkaido?',
			'focus'     => 'Japan Reiseroute Empfehlung',
			'long_tail' => array( 'Kanto oder Kansai Reise', 'Kyushu oder Hokkaido Reise', 'beste Japan Route für Erstbesucher' ),
		),
	),
	'it_IT' => array(
		20 => array(
			'title'     => 'Viaggio fai-da-te in Giappone: itinerario, trasporti, alloggio e budget',
			'focus'     => 'viaggio fai-da-te in Giappone',
			'long_tail' => array( 'Giappone viaggio fai da te prima volta', 'checklist viaggio Giappone', 'consigli viaggio Giappone in autonomia' ),
		),
		22 => array(
			'title'     => 'Come pianificare un viaggio in Giappone: preparazione e itinerario',
			'focus'     => 'pianificare viaggio Giappone',
			'long_tail' => array( 'itinerario Giappone 7 giorni', 'come organizzare viaggio in Giappone', 'preparazione viaggio Giappone' ),
		),
		23 => array(
			'title'     => 'Trasporti in Giappone: JR Pass, carte IC e biglietti',
			'focus'     => 'pass trasporti Giappone',
			'long_tail' => array( 'JR Pass o pass regionali', 'confronto carte trasporti Giappone', 'come usare i treni in Giappone' ),
		),
		26 => array(
			'title'     => 'Prenotare hotel e biglietti in Giappone: errori da evitare',
			'focus'     => 'prenotazione hotel Giappone',
			'long_tail' => array( 'consigli prenotazione hotel Giappone', 'come prenotare volo e hotel Giappone', 'errori prenotazione viaggio Giappone' ),
		),
		32 => array(
			'title'     => 'Dove alloggiare in Giappone: base unica o più città?',
			'focus'     => 'alloggio Giappone viaggio',
			'long_tail' => array( 'dove dormire in Giappone', 'Giappone viaggio con base unica', 'pianificare alloggi in più città in Giappone' ),
		),
		42 => array(
			'title'     => 'Mobilità urbana in Giappone: pass metro giornaliero, bus e camminata',
			'focus'     => 'abbonamento metro giornaliero Giappone',
			'long_tail' => array( 'miglior pass metro Giappone', 'pianificare trasporti urbani in Giappone', 'usare bus e metro in Giappone' ),
		),
		50 => array(
			'title'     => 'Rischi di viaggio in Giappone: tifoni, smarrimenti, salute e assicurazione',
			'focus'     => 'assicurazione viaggio Giappone',
			'long_tail' => array( 'migliore assicurazione per Giappone', 'come gestire tifone in viaggio in Giappone', 'assistenza medica in Giappone per turisti' ),
		),
		28 => array(
			'title'     => 'Quanti giorni in Giappone? Durata ideale e ritmo del viaggio',
			'focus'     => 'quanti giorni in Giappone',
			'long_tail' => array( 'durata viaggio Giappone prima volta', 'itinerario Giappone 5 giorni', 'ritmo ideale viaggio in Giappone' ),
		),
		36 => array(
			'title'     => 'JR Pass conviene davvero? Calcolo e criteri di scelta',
			'focus'     => 'JR Pass conviene',
			'long_tail' => array( 'quando conviene comprare JR Pass', 'calcolo costo JR Pass', 'migliori tratte per JR Pass' ),
		),
		38 => array(
			'title'     => 'Suica, PASMO o ICOCA: quale carta scegliere?',
			'focus'     => 'differenza Suica PASMO',
			'long_tail' => array( 'confronto Suica PASMO ICOCA', 'migliore carta trasporti Giappone', 'rimborso Suica PASMO' ),
		),
		40 => array(
			'title'     => 'Prenotare lo Shinkansen: posti riservati, non riservati e app',
			'focus'     => 'prenotazione Shinkansen',
			'long_tail' => array( 'come prenotare posto Shinkansen', 'differenza posti riservati Shinkansen', 'comprare biglietti Shinkansen online' ),
		),
		48 => array(
			'title'     => 'Prenotare biglietti popolari in Giappone: Disney, USJ ed eventi',
			'focus'     => 'prenotazione biglietti Disney Giappone',
			'long_tail' => array( 'biglietti Tokyo Disney prenotazione', 'come prenotare biglietti USJ', 'biglietti attrazioni Giappone' ),
		),
		44 => array(
			'title'     => 'Budget viaggio in Giappone: alloggio, trasporti, cibo e attività',
			'focus'     => 'budget viaggio Giappone',
			'long_tail' => array( 'costo viaggio in Giappone', 'come pianificare budget Giappone', 'risparmiare durante viaggio in Giappone' ),
		),
		46 => array(
			'title'     => 'Strategia hotel in Giappone: cancellazioni, sconti e alta stagione',
			'focus'     => 'consigli prenotazione hotel Giappone',
			'long_tail' => array( 'prenotare hotel in Giappone alta stagione', 'politica cancellazione hotel Giappone', 'quando prenotare hotel in Giappone' ),
		),
		34 => array(
			'title'     => 'Pianificare le attrazioni in Giappone: mete famose e piano pioggia',
			'focus'     => 'itinerario Giappone',
			'long_tail' => array( 'itinerario attrazioni Giappone', 'cosa fare in Giappone quando piove', 'come ottimizzare percorso in Giappone' ),
		),
		30 => array(
			'title'     => 'Quale rotta scegliere in Giappone: Kanto, Kansai, Kyushu o Hokkaido?',
			'focus'     => 'itinerario Giappone consigliato',
			'long_tail' => array( 'Kanto o Kansai viaggio', 'Kyushu o Hokkaido quale scegliere', 'miglior itinerario Giappone prima volta' ),
		),
	),
	'es_ES' => array(
		20 => array(
			'title'     => 'Viaje libre a Japón: ruta, transporte, alojamiento y presupuesto',
			'focus'     => 'viaje libre a Japón',
			'long_tail' => array( 'Japón por libre primera vez', 'checklist viaje a Japón', 'consejos para viajar por libre a Japón' ),
		),
		22 => array(
			'title'     => 'Cómo planificar un viaje a Japón: preparación e itinerario',
			'focus'     => 'planificar viaje Japón',
			'long_tail' => array( 'itinerario Japón 7 días', 'cómo organizar un viaje a Japón', 'preparativos antes de viajar a Japón' ),
		),
		23 => array(
			'title'     => 'Transporte en Japón: JR Pass, tarjetas IC y billetes',
			'focus'     => 'pases de transporte Japón',
			'long_tail' => array( 'JR Pass o pases regionales', 'comparativa tarjetas de transporte Japón', 'cómo moverse en tren por Japón' ),
		),
		26 => array(
			'title'     => 'Reserva de hoteles y entradas en Japón: errores que debes evitar',
			'focus'     => 'reservar hotel Japón',
			'long_tail' => array( 'consejos para reservar hotel en Japón', 'cómo reservar vuelo y hotel para Japón', 'errores al planear viaje a Japón' ),
		),
		32 => array(
			'title'     => 'Dónde alojarse en Japón: base única o varias ciudades',
			'focus'     => 'alojamiento Japón viaje',
			'long_tail' => array( 'mejores zonas para alojarse en Japón', 'viaje a Japón con hotel base', 'plan de alojamiento por ciudades en Japón' ),
		),
		42 => array(
			'title'     => 'Movilidad urbana en Japón: pase de metro diario, bus y caminata',
			'focus'     => 'pase diario metro Japón',
			'long_tail' => array( 'mejor pase de metro en Japón', 'plan de transporte urbano en Japón', 'cómo combinar bus y metro en Japón' ),
		),
		50 => array(
			'title'     => 'Riesgos en Japón: tifón, pérdidas, salud y seguro de viaje',
			'focus'     => 'seguro de viaje Japón',
			'long_tail' => array( 'mejor seguro para viajar a Japón', 'qué hacer si hay tifón en Japón', 'atención médica para turistas en Japón' ),
		),
		28 => array(
			'title'     => '¿Cuántos días ir a Japón? Duración ideal y ritmo del viaje',
			'focus'     => 'cuántos días en Japón',
			'long_tail' => array( 'cuántos días para viajar a Japón por primera vez', 'itinerario Japón 5 días', 'ritmo recomendado de viaje en Japón' ),
		),
		36 => array(
			'title'     => '¿Vale la pena el JR Pass? Cómo calcularlo y decidir',
			'focus'     => 'JR Pass vale la pena',
			'long_tail' => array( 'cuándo conviene comprar JR Pass', 'cómo calcular JR Pass', 'mejores rutas para JR Pass' ),
		),
		38 => array(
			'title'     => 'Suica, PASMO o ICOCA: ¿qué tarjeta conviene más?',
			'focus'     => 'diferencia Suica PASMO',
			'long_tail' => array( 'comparativa Suica PASMO ICOCA', 'mejor tarjeta de transporte en Japón', 'reembolso de Suica o PASMO' ),
		),
		40 => array(
			'title'     => 'Cómo reservar Shinkansen: asientos reservados, libres y app',
			'focus'     => 'reservar Shinkansen',
			'long_tail' => array( 'reserva de asiento en Shinkansen', 'diferencia asiento reservado y libre Shinkansen', 'comprar billete Shinkansen online' ),
		),
		48 => array(
			'title'     => 'Reserva de entradas populares en Japón: Disney, USJ y exposiciones',
			'focus'     => 'reservación boletos Disney Japón',
			'long_tail' => array( 'entradas Tokyo Disney reserva', 'cómo reservar entradas USJ', 'entradas para atracciones en Japón' ),
		),
		44 => array(
			'title'     => 'Presupuesto para Japón: alojamiento, transporte, comida y actividades',
			'focus'     => 'presupuesto viaje Japón',
			'long_tail' => array( 'cuánto cuesta viajar a Japón', 'cómo planificar presupuesto Japón', 'ahorrar dinero en viaje a Japón' ),
		),
		46 => array(
			'title'     => 'Estrategia para reservar hotel en Japón: cancelaciones y temporada alta',
			'focus'     => 'consejos reservar hotel Japón',
			'long_tail' => array( 'reservar hotel Japón temporada alta', 'política de cancelación hotel Japón', 'cuándo reservar hotel en Japón' ),
		),
		34 => array(
			'title'     => 'Cómo organizar visitas en Japón: lugares clave y plan para lluvia',
			'focus'     => 'plan de itinerario Japón',
			'long_tail' => array( 'itinerario de lugares en Japón', 'qué hacer en Japón cuando llueve', 'cómo optimizar ruta en Japón' ),
		),
		30 => array(
			'title'     => 'Qué ruta elegir en Japón: Kanto, Kansai, Kyushu o Hokkaido',
			'focus'     => 'ruta recomendada Japón',
			'long_tail' => array( 'Kanto o Kansai para viajar', 'Kyushu o Hokkaido cuál elegir', 'mejor ruta por Japón para primer viaje' ),
		),
	),
);

$updated = array();
$source_posts = array();

switch_to_blog( 1 );
foreach ( array( 20, 22, 23, 26, 32, 42, 50, 28, 36, 38, 40, 48, 44, 46, 34, 30 ) as $source_post_id ) {
	$post = get_post( (int) $source_post_id );
	if ( $post && 'post' === $post->post_type ) {
		$source_posts[ (int) $source_post_id ] = array(
			'post_author'           => (int) $post->post_author,
			'post_content'          => (string) $post->post_content,
			'post_excerpt'          => (string) $post->post_excerpt,
			'post_status'           => (string) $post->post_status,
			'post_date'             => (string) $post->post_date,
			'post_date_gmt'         => (string) $post->post_date_gmt,
			'post_modified'         => (string) $post->post_modified,
			'post_modified_gmt'     => (string) $post->post_modified_gmt,
			'comment_status'        => (string) $post->comment_status,
			'ping_status'           => (string) $post->ping_status,
			'post_password'         => (string) $post->post_password,
			'menu_order'            => (int) $post->menu_order,
			'post_mime_type'        => (string) $post->post_mime_type,
			'comment_count'         => (int) $post->comment_count,
		);
	}
}
restore_current_blog();

foreach ( $locales as $blog_id => $locale ) {
	if ( ! isset( $map[ $locale ] ) || ! is_array( $map[ $locale ] ) ) {
		continue;
	}

	switch_to_blog( $blog_id );

	foreach ( $map[ $locale ] as $post_id => $item ) {
		$post = get_post( (int) $post_id );
		if ( ( ! $post || 'post' !== $post->post_type ) && isset( $source_posts[ (int) $post_id ] ) ) {
			$source = $source_posts[ (int) $post_id ];
			$new_id = wp_insert_post(
				array(
					'import_id'            => (int) $post_id,
					'post_type'            => 'post',
					'post_status'          => $source['post_status'],
					'post_author'          => $source['post_author'],
					'post_content'         => $source['post_content'],
					'post_excerpt'         => $source['post_excerpt'],
					'post_date'            => $source['post_date'],
					'post_date_gmt'        => $source['post_date_gmt'],
					'post_modified'        => $source['post_modified'],
					'post_modified_gmt'    => $source['post_modified_gmt'],
					'comment_status'       => $source['comment_status'],
					'ping_status'          => $source['ping_status'],
					'post_password'        => $source['post_password'],
					'menu_order'           => $source['menu_order'],
					'post_mime_type'       => $source['post_mime_type'],
					'comment_count'        => $source['comment_count'],
					'post_title'           => $source['post_content'] ? (string) $item['title'] : (string) $item['title'],
				),
				true
			);

			if ( is_wp_error( $new_id ) ) {
				continue;
			}

			$post = get_post( (int) $post_id );
		}

		if ( ! $post || 'post' !== $post->post_type ) {
			continue;
		}

		wp_update_post(
			array(
				'ID'         => (int) $post_id,
				'post_title' => (string) $item['title'],
			)
		);

		PostData::save(
			(int) $post_id,
			array(
				'focusKeyphrase' => (string) $item['focus'],
				'focusLongTail'  => implode( ', ', $item['long_tail'] ),
			)
		);

		$updated[] = sprintf( 'blog:%d locale:%s post:%d', (int) $blog_id, (string) $locale, (int) $post_id );
	}

	restore_current_blog();
}

echo sprintf( "Updated %d post records.\n", count( $updated ) );
echo implode( "\n", $updated ) . "\n";
