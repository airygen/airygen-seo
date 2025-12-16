import type { ModuleKey } from './modules';

export type RestModuleSettings = Partial<Record<ModuleKey, boolean | number | string>>;

export type RawOgSettings = {
	enabled?: unknown;
	default_image_id?: unknown;
	default_image_url?: unknown;
	image_width?: unknown;
	image_height?: unknown;
	fb_app_id?: unknown;
	fb_admins?: unknown;
	publisher_url?: unknown;
	domain_verification?: unknown;
};

export type RawTwitterSettings = {
	enabled?: unknown;
	card_type?: unknown;
	site_handle?: unknown;
	creator_handle?: unknown;
	inherit_og_image?: unknown;
	default_image_id?: unknown;
	default_image_url?: unknown;
};

export type RawImageSeoAttributeSettings = {
	enabled?: unknown;
	format?: unknown;
};

export type RawImageSeoSettings = {
	alt?: RawImageSeoAttributeSettings;
	title?: RawImageSeoAttributeSettings;
	separator?: unknown;
	custom_tokens?: {
		custom_1?: unknown;
		custom_2?: unknown;
		custom_3?: unknown;
	};
};

export type RawSocialSettings = {
	og?: RawOgSettings;
	twitter?: RawTwitterSettings;
};

export type RawSchemaVisibility = {
	organization?: unknown;
	website?: unknown;
	breadcrumb?: unknown;
	article?: unknown;
};

export type RawSchemaSettings = {
	organization_name?: unknown;
	organization_type?: unknown;
	organization_logo_id?: unknown;
	organization_logo_url?: unknown;
	article_type?: unknown;
	post_type_defaults?: Record<string, unknown>;
	article_show_author?: unknown;
	article_only_post?: unknown;
	visibility?: RawSchemaVisibility;
};

export type RawBreadcrumbsSettings = {
	enabled?: unknown;
	manual_output_enabled?: unknown;
	auto_injection_enabled?: unknown;
	injection_position?: unknown;
	separator?: unknown;
	prefix?: unknown;
	home?: {
		display?: unknown;
		label?: unknown;
		url?: unknown;
	};
	labels?: {
		archive?: unknown;
		search?: unknown;
		error?: unknown;
	};
	display?: {
		showCurrent?: unknown;
		showAncestors?: unknown;
		showBlog?: unknown;
		showPagination?: unknown;
		hideTaxonomy?: unknown;
	};
	style?: {
		fontSize?: unknown;
		textColor?: unknown;
		linkColor?: unknown;
		underlineLinks?: unknown;
		borderWidth?: unknown;
		borderColor?: unknown;
		padding?: unknown;
		bgColor?: unknown;
	};
};

export type RawTocSettings = {
	enabled?: unknown;
	manual_output_enabled?: unknown;
	auto_injection_enabled?: unknown;
	post_types?: unknown;
	levels?: unknown;
	position?: unknown;
	title_enabled?: unknown;
	title?: unknown;
	title_level?: unknown;
	min_headings?: unknown;
	smooth_scroll?: unknown;
	anchor_prefix?: unknown;
	add_numbers?: unknown;
	exclude_headings?: unknown;
	collapse_on_load?: unknown;
	output_mode?: unknown;
	style?: {
		preset?: unknown;
		border_style?: unknown;
		border_color?: unknown;
		border_width?: unknown;
		border_radius?: unknown;
		nav_padding?: unknown;
		toc_padding?: unknown;
		link_color?: unknown;
		link_size?: unknown;
		font_style?: {
			bold?: unknown;
			italic?: unknown;
			underline?: unknown;
		};
		bg_color?: unknown;
		header_container?: unknown;
		header_title?: unknown;
	};
};

export type RawRobotsSettings = {
	default_directive?: unknown;
	additional_rules?: unknown;
	enable_default_meta?: unknown;
	suppress_default_meta?: unknown;
};

export type RawHreflangEntry = {
	code?: unknown;
	url?: unknown;
};

export type RawHreflangSettings = {
	manual_map?: RawHreflangEntry[] | Record<string, unknown>;
	include_x_default?: unknown;
};

export type RawSitemapSettings = {
	enabled_post_types?: unknown;
	enabled_taxonomies?: unknown;
	exclude_empty_taxonomies?: unknown;
	items_per_page?: unknown;
};

export type RawRssFeedSignatureSettings = {
	enabled?: unknown;
	before_content?: unknown;
	after_content?: unknown;
};

export type RawSiteVerificationSettings = {
	google?: unknown;
	bing?: unknown;
	yandex?: unknown;
	baidu?: unknown;
	pinterest?: unknown;
};

export type RawCodeSnippet = {
	id?: unknown;
	enabled?: unknown;
	description?: unknown;
	code?: unknown;
	placement?: unknown;
};

export type RawCodeSnippetManagerSettings = {
	snippets?: RawCodeSnippet[];
};

export type RawRedirectRule = Record<string, unknown>;

export type RawRedirectSettings = {
	rules?: RawRedirectRule[];
};

export type RawInstantIndexingEngineSettings = {
	enabled?: unknown;
	endpoint?: unknown;
};

export type RawInstantIndexingSettings = {
	enabled?: unknown;
	auto_submit?: unknown;
	retry_cooldown_days?: unknown;
	key?: unknown;
	key_location?: unknown;
	max_events_per_day?: unknown;
	batch_size?: unknown;
	engines?: Record<string, RawInstantIndexingEngineSettings>;
	backfill?: {
		post_types?: unknown[];
	};
};

export type RawOnPageSeoSettings = {
	output?: {
		title?: unknown;
		description?: unknown;
		canonical?: unknown;
		robots?: unknown;
	};
	templates?: {
		global?: {
			title?: unknown;
			description?: unknown;
		};
		separator?: unknown;
		custom_tokens?: {
			custom_1?: unknown;
			custom_2?: unknown;
			custom_3?: unknown;
		};
		post_types?: Record<
			string,
			{
				title?: unknown;
				description?: unknown;
			}
		>;
	};
};

export type RawBrokenLinkCheckerSettings = {
	enabled?: boolean | number | string;
	enable_daily_alert?: boolean | number | string;
	check_interval_hours?: number | string;
	max_requests_per_run?: number | string;
	batch_delay_minutes?: number | string;
	log_retention_days?: number | string;
	connection_timeout_seconds?: number | string;
	operation_timeout_seconds?: number | string;
	treat_redirects_as_warning?: boolean | number | string;
	link_types?: unknown;
};

export type RawScoreCalculatorSettings = {
	rules?: Record<string, unknown>;
	post_types?: unknown;
	custom_rules?: Record<string, unknown>;
};

export type RawTopicClusterSettings = {
	manual_output_enabled?: unknown;
	auto_injection_enabled?: unknown;
	override_breadcrumbs?: unknown;
	override_wp_adjacent?: unknown;
	insert_position?: unknown;
	post_types?: unknown;
	title_enabled?: unknown;
	title_text?: unknown;
	relation_text_l1?: unknown;
	relation_text_l2?: unknown;
	relation_text_l3?: unknown;
	title_level?: unknown;
	style_type?: unknown;
	style?: {
		preset?: unknown;
		show_border?: unknown;
		border_style?: unknown;
		border_color?: unknown;
		border_width_top?: unknown;
		border_width_right?: unknown;
		border_width_bottom?: unknown;
		border_width_left?: unknown;
		border_radius?: unknown;
		padding_top?: unknown;
		padding_right?: unknown;
		padding_bottom?: unknown;
		padding_left?: unknown;
		margin_top?: unknown;
		margin_right?: unknown;
		margin_bottom?: unknown;
		margin_left?: unknown;
		bg_color?: unknown;
		item_text_color?: unknown;
		item_font_size?: unknown;
		item_bold?: unknown;
		item_italic?: unknown;
		item_underline?: unknown;
		item_list_style?: unknown;
		item_gap?: unknown;
		header_container?: unknown;
		header_title?: unknown;
	};
};

export type RawAuthorSeoSettings = {
	enabled?: unknown;
	noindex_author_archives?: unknown;
	title_template?: unknown;
	description_template?: unknown;
	separator?: unknown;
	custom_tokens?: unknown;
	social_profiles?: unknown;
};

export type RawTaxonomySeoSettings = {
	enabled?: unknown;
	enabled_taxonomies?: unknown;
	templates?: {
		global?: {
			title?: unknown;
			description?: unknown;
		};
		separator?: unknown;
		custom_tokens?: {
			custom_1?: unknown;
			custom_2?: unknown;
			custom_3?: unknown;
		};
	};
};

export type RawWooCommerceSeoSettings = {
	enabled?: unknown;
	enable_schema?: unknown;
	brand_attribute?: unknown;
	templates?: {
		product?: {
			title?: unknown;
			description?: unknown;
		};
		separator?: unknown;
		custom_tokens?: {
			custom_1?: unknown;
			custom_2?: unknown;
			custom_3?: unknown;
		};
	};
};

export type RawLocalSeoSettings = {
	enabled?: unknown;
	layout_template?: unknown;
	layout_show_card_border?: unknown;
	layout_card_padding?: unknown;
	layout_label_font_size?: unknown;
	layout_label_color?: unknown;
	layout_label_uppercase?: unknown;
	layout_label_bold?: unknown;
	layout_label_italic?: unknown;
	layout_value_font_size?: unknown;
	layout_value_color?: unknown;
	layout_title_font_size?: unknown;
	layout_card_background_color?: unknown;
	business_type?: unknown;
	business_name?: unknown;
	legal_name?: unknown;
	image_url?: unknown;
	logo_url?: unknown;
	phone?: unknown;
	price_range?: unknown;
	price_range_level?: unknown;
	price_range_custom?: unknown;
	rating_value?: unknown;
	review_count?: unknown;
	same_as_urls?: unknown;
	street_address?: unknown;
	city?: unknown;
	region?: unknown;
	postal_code?: unknown;
	country?: unknown;
	latitude?: unknown;
	longitude?: unknown;
	kml_in_sitemap?: unknown;
	opening_hours?: unknown;
	enable_geo_tags?: unknown;
	geo_region_code?: unknown;
	geo_placename?: unknown;
	map_zoom?: unknown;
	service_catalog_name?: unknown;
	service_catalog_items?: unknown;
	layout_order?: unknown;
	layout_grid?: unknown;
	footer_nap_layout_order?: unknown;
	footer_nap_enabled?: unknown;
	footer_nap_font_size?: unknown;
	footer_nap_text_color?: unknown;
	footer_nap_text_align?: unknown;
	footer_nap_first_item_bold?: unknown;
	footer_nap_margin_y?: unknown;
	footer_nap_gap?: unknown;
	footer_nap_container_width?: unknown;
	contact_auto_map_embed?: unknown;
	contact_detailed_opening_hours?: unknown;
	service_area_cities?: unknown;
	service_area_postal_codes?: unknown;
	service_area_radius_km?: unknown;
	vat_id?: unknown;
	vat_validate_checksum?: unknown;
	show_vat_in_footer?: unknown;
	click_to_call_enabled?: unknown;
	special_hours?: unknown;
	branches?: unknown;
};

export type RawRelatedPostsSettings = {
	enabled?: unknown;
	title_enabled?: unknown;
	title_text?: unknown;
	title_level?: unknown;
	template?: unknown;
	footer_columns?: unknown;
	block_order?: unknown;
	block_regions?: unknown;
	grid_container?: {
		border_width_top?: unknown;
		border_width_right?: unknown;
		border_width_bottom?: unknown;
		border_width_left?: unknown;
		border_radius?: unknown;
		border_style?: unknown;
		border_color?: unknown;
		bg_color?: unknown;
		padding_top?: unknown;
		padding_right?: unknown;
		padding_bottom?: unknown;
		padding_left?: unknown;
		gap?: unknown;
	};
	post_container?: {
		border_width_top?: unknown;
		border_width_right?: unknown;
		border_width_bottom?: unknown;
		border_width_left?: unknown;
		border_radius?: unknown;
		border_style?: unknown;
		border_color?: unknown;
		bg_color?: unknown;
		padding_top?: unknown;
		padding_right?: unknown;
		padding_bottom?: unknown;
		padding_left?: unknown;
		gap?: unknown;
	};
	header_container?: unknown;
	header_title?: unknown;
	featured_image_size?: unknown;
	featured_image_radius?: unknown;
	title_font_size?: unknown;
	title_color?: unknown;
	title_bold?: unknown;
	title_italic?: unknown;
	excerpt_font_size?: unknown;
	excerpt_color?: unknown;
	excerpt_max_chars?: unknown;
	excerpt_fade_mask?: unknown;
	excerpt_fade_color?: unknown;
	excerpt_mask_height?: unknown;
	author_font_size?: unknown;
	author_color?: unknown;
	author_bold?: unknown;
	author_italic?: unknown;
	auto_inject_enabled?: unknown;
	display_preset?: unknown;
	data_limit?: unknown;
	enabled_post_types?: unknown;
	insert_position?: unknown;
};

export type RawNotFoundManagerSettings = {
	monitor_mode?: unknown;
	enable_daily_alert?: unknown;
	ignore_query_params?: unknown;
	log_limit?: unknown;
	retention_days?: unknown;
	exclude_patterns?: unknown;
	fallback_redirect_mode?: unknown;
	fallback_redirect_target?: unknown;
	fallback_redirect_code?: unknown;
};

export type RawNotifySettings = {
	enabled?: unknown;
	custom?: {
		visible_blocks?: unknown;
		hidden_blocks?: unknown;
	};
	message?: {
		subject?: unknown;
		intro?: unknown;
		footer?: unknown;
	};
	logs?: {
		retention_days?: unknown;
	};
	schedule?: {
		timezone?: unknown;
		time?: unknown;
	};
	channels?: {
		email?: {
			enabled?: unknown;
			recipients?: unknown;
			smtp?: {
				host?: unknown;
				port?: unknown;
				auth?: unknown;
				secure?: unknown;
				username?: unknown;
				password?: unknown;
				timeout?: unknown;
				fromEmail?: unknown;
				fromName?: unknown;
			};
		};
		telegram?: {
			enabled?: unknown;
			botToken?: unknown;
			chatId?: unknown;
			topicId?: unknown;
		};
		discord?: {
			enabled?: unknown;
			webhook?: unknown;
			username?: unknown;
			avatar?: unknown;
		};
		teams?: {
			enabled?: unknown;
			webhook?: unknown;
		};
	};
};

export type RawMarkdownForAgentsSettings = {
	enabled?: unknown;
	prompts_for_agents?: unknown;
	include_frontmatter?: unknown;
	post_types?: unknown;
};

export type RawLlmsTxtSettings = {
	enabled?: unknown;
	custom_declaration?: unknown;
	auto_section_title?: unknown;
	index_strategy?: unknown;
	auto_topic_cluster_groups?: unknown;
	use_markdown_links?: unknown;
	add_to_sitemap?: unknown;
	exclude_noindex?: unknown;
	exclude_password_protected?: unknown;
	min_word_count?: unknown;
	sections?: unknown;
	extensions?: unknown;
	post_types?: unknown;
};

export type RawSettingsPayload = {
	socialCards?: RawSocialSettings;
	schemaMarkup?: RawSchemaSettings;
	breadcrumbs?: RawBreadcrumbsSettings;
	robots?: RawRobotsSettings;
	hreflang?: RawHreflangSettings;
	sitemap?: RawSitemapSettings;
	codeSnippetManager?: RawCodeSnippetManagerSettings;
	siteVerification?: RawSiteVerificationSettings;
	rssFeedSignature?: RawRssFeedSignatureSettings;
	redirects?: RawRedirectSettings;
	modules?: RestModuleSettings;
	brokenLinkChecker?: RawBrokenLinkCheckerSettings;
	instantIndexing?: RawInstantIndexingSettings;
	onPageSeo?: RawOnPageSeoSettings;
	imageSeo?: RawImageSeoSettings;
	scoreCalculator?: RawScoreCalculatorSettings;
	toc?: RawTocSettings;
	topicCluster?: RawTopicClusterSettings;
	authorSeo?: RawAuthorSeoSettings;
	taxonomySeo?: RawTaxonomySeoSettings;
	wooCommerceSeo?: RawWooCommerceSeoSettings;
	localSeo?: RawLocalSeoSettings;
	relatedPosts?: RawRelatedPostsSettings;
	notFoundManager?: RawNotFoundManagerSettings;
	notify?: RawNotifySettings;
	markdownForAgents?: RawMarkdownForAgentsSettings;
	llmsTxt?: RawLlmsTxtSettings;
	moduleOrder?: unknown;
	panelOrder?: unknown;
	panelVisibility?: unknown;
	contentBlockOrder?: unknown;
	contentBlockGap?: unknown;
	contentBlockMarginTop?: unknown;
};
