(function () {
	var root = document.querySelector('.airygen-faq-preview__content');
	if (!root) {
		return;
	}
	var styleNode = document.getElementById('airygen-faq-live-styles');
	if (!styleNode) {
		styleNode = document.createElement('style');
		styleNode.id = 'airygen-faq-live-styles';
		document.head.appendChild(styleNode);
	}
	function darken(hex, factor) {
		if (!hex || hex[0] !== '#' || (hex.length !== 7 && hex.length !== 4)) {
			return hex;
		}
		var full = hex.length === 4
			? '#' + hex[1] + hex[1] + hex[2] + hex[2] + hex[3] + hex[3]
			: hex;
		var r = parseInt(full.slice(1, 3), 16);
		var g = parseInt(full.slice(3, 5), 16);
		var b = parseInt(full.slice(5, 7), 16);
		if (Number.isNaN(r) || Number.isNaN(g) || Number.isNaN(b)) {
			return hex;
		}
		r = Math.max(0, Math.min(255, Math.round(r * factor)));
		g = Math.max(0, Math.min(255, Math.round(g * factor)));
		b = Math.max(0, Math.min(255, Math.round(b * factor)));
		return '#' + r.toString(16).padStart(2, '0') +
			g.toString(16).padStart(2, '0') +
			b.toString(16).padStart(2, '0');
	}
	var plusIcon =
		'<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">' +
		'<g transform="scale(3.65)">' +
		'<path d="M5.27582 3.60906H3.60979V5.27508H3.05445V3.60906H1.38843V3.05372H3.05445V1.3877H3.60979V3.05372H5.27582V3.60906Z" fill="currentColor"/>' +
		'</g>' +
		'</svg>';
	var minusIcon =
		'<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">' +
		'<g transform="scale(3.65)">' +
		'<path d="M5.27557 3.61003H1.38818V3.05469H5.27557V3.61003Z" fill="currentColor"/>' +
		'</g>' +
		'</svg>';

			function applyStyles(settings) {
				var st = settings.style || {};
				var headerContainer = st.header_container || st.headerContainer || {};
				var headerTitle = st.header_title || st.headerTitle || {};
				var headerTitleStyle = headerTitle.font_style || headerTitle.fontStyle || {};
				if (settings.collapse_enabled && settings.collapse_style) {
					st = settings.collapse_style;
				}
			var showBorder = !!(st.show_border || st.showBorder);
			var borderStyle = st.border_style || st.borderStyle || 'solid';
			var borderColor = st.border_color || st.borderColor || '#e2e8f0';
			var questionBg = st.question_bg_color || st.questionBgColor || '#f8fafc';
			var questionBorder = st.question_border_color || st.questionBorderColor || '#cbd5e1';
			var questionBorderStyle = st.question_border_style || st.questionBorderStyle || 'solid';
			var questionText = st.question_text_color || st.questionTextColor || '#0f172a';
			var questionFontSize = Number.isFinite(Number(st.question_font_size))
				? Number(st.question_font_size)
				: Number.isFinite(Number(st.questionFontSize))
					? Number(st.questionFontSize)
					: 15;
			var questionPadding = Number.isFinite(Number(st.question_padding))
				? Number(st.question_padding)
				: Number.isFinite(Number(st.questionPadding))
					? Number(st.questionPadding)
					: 18;
			var answerBg = st.answer_bg_color || st.answerBgColor || '#f5f5f5';
			var answerText = st.answer_text_color || st.answerTextColor || '#475569';
			var answerFontSize = Number.isFinite(Number(st.answer_font_size))
				? Number(st.answer_font_size)
				: Number.isFinite(Number(st.answerFontSize))
					? Number(st.answerFontSize)
					: 14;
			var answerPadding = Number.isFinite(Number(st.answer_padding))
				? Number(st.answer_padding)
				: Number.isFinite(Number(st.answerPadding))
					? Number(st.answerPadding)
					: 16;
			var toggleBg = st.toggle_bg_color || st.toggleBgColor || '#ffffff';
			var toggleIcon = st.toggle_icon_color || st.toggleIconColor || '#2563eb';
			var toggleBorder = st.toggle_border_color || st.toggleBorderColor || '#cbd5f5';
			var toggleBorderWidth = Number.isFinite(Number(st.toggle_border_width))
				? Number(st.toggle_border_width)
				: Number.isFinite(Number(st.toggleBorderWidth))
					? Number(st.toggleBorderWidth)
					: 1;
			var borderWidth = Number.isFinite(Number(st.border_width))
				? Number(st.border_width)
				: Number.isFinite(Number(st.borderWidth))
					? Number(st.borderWidth)
					: 1;
			var faqPadding = Number.isFinite(Number(st.faq_padding))
				? Number(st.faq_padding)
				: Number.isFinite(Number(st.faqPadding))
					? Number(st.faqPadding)
					: 16;
			var bgColor = st.bg_color || st.bgColor || '#ffffff';
			var border = showBorder ? borderWidth + 'px ' + borderStyle + ' ' + borderColor : 'none';
			var hoverBg = darken(questionBg, 0.9);
				var headerBorderStyle = headerContainer.border_style || headerContainer.borderStyle || 'solid';
				var headerBorderRadius = Number.isFinite(Number(headerContainer.border_radius))
					? Number(headerContainer.border_radius)
					: Number.isFinite(Number(headerContainer.borderRadius))
						? Number(headerContainer.borderRadius)
						: 0;
				var headerColor = headerTitle.color || '#0f172a';
				var headerFontSize = Number.isFinite(Number(headerTitle.font_size))
					? Number(headerTitle.font_size)
					: Number.isFinite(Number(headerTitle.fontSize))
						? Number(headerTitle.fontSize)
						: 18;
				var headerPaddingTop = Number.isFinite(Number(headerContainer.padding_top))
					? Number(headerContainer.padding_top)
					: Number.isFinite(Number(headerContainer.paddings && headerContainer.paddings.top))
						? Number(headerContainer.paddings.top)
						: 0;
				var headerPaddingRight = Number.isFinite(Number(headerContainer.padding_right))
					? Number(headerContainer.padding_right)
					: Number.isFinite(Number(headerContainer.paddings && headerContainer.paddings.right))
						? Number(headerContainer.paddings.right)
						: 0;
				var headerPaddingBottom = Number.isFinite(Number(headerContainer.padding_bottom))
					? Number(headerContainer.padding_bottom)
					: Number.isFinite(Number(headerContainer.paddings && headerContainer.paddings.bottom))
						? Number(headerContainer.paddings.bottom)
						: 0;
				var headerPaddingLeft = Number.isFinite(Number(headerContainer.padding_left))
					? Number(headerContainer.padding_left)
					: Number.isFinite(Number(headerContainer.paddings && headerContainer.paddings.left))
						? Number(headerContainer.paddings.left)
						: 0;
				var headerMarginTop = Number.isFinite(Number(headerContainer.margin_top))
					? Number(headerContainer.margin_top)
					: Number.isFinite(Number(headerContainer.margins && headerContainer.margins.top))
						? Number(headerContainer.margins.top)
						: 0;
				var headerMarginRight = Number.isFinite(Number(headerContainer.margin_right))
					? Number(headerContainer.margin_right)
					: Number.isFinite(Number(headerContainer.margins && headerContainer.margins.right))
						? Number(headerContainer.margins.right)
						: 0;
				var headerMarginBottom = Number.isFinite(Number(headerContainer.margin_bottom))
					? Number(headerContainer.margin_bottom)
					: Number.isFinite(Number(headerContainer.margins && headerContainer.margins.bottom))
						? Number(headerContainer.margins.bottom)
						: 12;
				var headerMarginLeft = Number.isFinite(Number(headerContainer.margin_left))
					? Number(headerContainer.margin_left)
					: Number.isFinite(Number(headerContainer.margins && headerContainer.margins.left))
						? Number(headerContainer.margins.left)
						: 0;
				var headerBorderWidthTop = Number.isFinite(Number(headerContainer.border_width_top))
					? Number(headerContainer.border_width_top)
					: Number.isFinite(Number(headerContainer.borderWidths && headerContainer.borderWidths.top))
						? Number(headerContainer.borderWidths.top)
						: 0;
				var headerBorderWidthRight = Number.isFinite(Number(headerContainer.border_width_right))
					? Number(headerContainer.border_width_right)
					: Number.isFinite(Number(headerContainer.borderWidths && headerContainer.borderWidths.right))
						? Number(headerContainer.borderWidths.right)
						: 0;
				var headerBorderWidthBottom = Number.isFinite(Number(headerContainer.border_width_bottom))
					? Number(headerContainer.border_width_bottom)
					: Number.isFinite(Number(headerContainer.borderWidths && headerContainer.borderWidths.bottom))
						? Number(headerContainer.borderWidths.bottom)
						: 0;
				var headerBorderWidthLeft = Number.isFinite(Number(headerContainer.border_width_left))
					? Number(headerContainer.border_width_left)
					: Number.isFinite(Number(headerContainer.borderWidths && headerContainer.borderWidths.left))
						? Number(headerContainer.borderWidths.left)
						: 0;
				var headerBorderColor = headerContainer.border_color || headerContainer.borderColor || '#e2e8f0';
				var headerBgColor = headerContainer.bg_color || headerContainer.bgColor || 'transparent';
				var css = '.airygen-faq{padding:' + faqPadding + 'px;border:' + border + ';background:' + bgColor + ';border-radius:8px;--airygen-faq-question-bg:' + questionBg + ';--airygen-faq-answer-bg:' + answerBg + ';--airygen-faq-question-hover:' + hoverBg + ';}';
				css += [
					'.airygen-faq__section-title{',
					'display:block;',
					'margin:' + headerMarginTop + 'px ' + headerMarginRight + 'px ' + headerMarginBottom + 'px ' + headerMarginLeft + 'px;',
					'padding:' + headerPaddingTop + 'px ' + headerPaddingRight + 'px ' + headerPaddingBottom + 'px ' + headerPaddingLeft + 'px;',
					'border-width:' + headerBorderWidthTop + 'px ' + headerBorderWidthRight + 'px ' + headerBorderWidthBottom + 'px ' + headerBorderWidthLeft + 'px;',
					'border-style:' + headerBorderStyle + ';',
					'border-color:' + headerBorderColor + ';',
					'border-radius:' + headerBorderRadius + 'px;',
					'background:' + headerBgColor + ';',
					'color:' + headerColor + ';',
					'font-size:' + headerFontSize + 'px;',
					'font-weight:' + (headerTitleStyle.bold ? '700' : '400') + ';',
					'font-style:' + (headerTitleStyle.italic ? 'italic' : 'normal') + ';',
					'text-decoration:' + (headerTitleStyle.underline ? 'underline' : 'none') + ';',
					'}',
				].join('');
				css += '.airygen-faq__list-item{margin:0 0 1rem 0;}';
				css += '.airygen-faq__list-item:last-child{margin:0;}';
			if (!settings.collapse_enabled) {
				css += '.airygen-faq__question{color:' + questionText + ';font-size:' + questionFontSize + 'px;font-weight:600;line-height:1.4;}';
				css += '.airygen-faq__answer{color:' + answerText + ';font-size:' + answerFontSize + 'px;line-height:1.5rem;}';
			}
			var gapValue = Number.isFinite(Number(st.question_gap))
				? Number(st.question_gap)
				: Number.isFinite(Number(st.questionGap))
					? Number(st.questionGap)
					: 12;
			css += '.airygen-faq--collapsible{display:flex;flex-direction:column;gap:' + gapValue + 'px;padding:0;background:transparent;border:none;}';
			css += '.airygen-faq--collapsible .airygen-faq__collapse-item{background:#f5f5f5;border-radius:6px;overflow:hidden;margin:0;}';
			if (settings.collapse_enabled) {
				css += '.airygen-faq__collapse-item{border:1px ' + questionBorderStyle + ' ' + questionBorder + ';}';
			}
			css += '.airygen-faq__header{display:flex;align-items:center;justify-content:space-between;gap:12px;text-align:left;padding:' + questionPadding + 'px 24px;background:var(--airygen-faq-question-bg,#f8fafc);color:' + questionText + ';cursor:pointer;transition:background 0.2s;border:none;}';
			css += '.airygen-faq__header:hover{background:var(--airygen-faq-question-hover,#e2e8f0);}';
			css += '.airygen-faq__header:focus-visible{outline:2px solid rgba(15, 23, 42, 0.35);outline-offset:2px;}';
			css += '.airygen-faq__header .airygen-faq__title{font-size:' + questionFontSize + 'px;font-weight:600;color:inherit;line-height:1.4;}';
			css += '.airygen-faq__toggle{display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;flex-shrink:0;border-radius:999px;background:' + toggleBg + ';color:' + toggleIcon + ';border:' + toggleBorderWidth + 'px solid ' + toggleBorder + ';}';
			css += '.airygen-faq__content{max-height:0;overflow:hidden;background:var(--airygen-faq-answer-bg,#f5f5f5);color:' + answerText + ';padding:0 24px;transition:max-height 0.3s ease;font-size:' + answerFontSize + 'px;}';
			css += '.airygen-faq__content.is-open{padding:' + answerPadding + 'px 24px;}';
			styleNode.textContent = css;
		}
		function buildItem(item) {
			if (item.querySelector('.airygen-faq__header')) {
				return;
			}
			var question = item.querySelector('.airygen-faq__question');
			var answer = item.querySelector('.airygen-faq__answer');
			if (!question || !answer) {
				return;
			}
			var header = document.createElement('div');
			header.className = 'airygen-faq__header';
			header.setAttribute('role', 'button');
			header.setAttribute('tabindex', '0');
			header.setAttribute('aria-expanded', 'false');
			var title = document.createElement('span');
			title.className = 'airygen-faq__title';
			title.textContent = question.textContent || '';
			var toggle = document.createElement('span');
			toggle.className = 'airygen-faq__toggle';
			toggle.setAttribute('aria-hidden', 'true');
			toggle.innerHTML = plusIcon;
			header.appendChild(title);
			header.appendChild(toggle);
			question.remove();
			item.insertBefore(header, answer);
			item.classList.remove('airygen-faq__list-item');
			item.classList.remove('airygen-faq__collapse-item');
			item.classList.add('airygen-faq__collapse-item');
			item.dataset.airygenFaqItem = '1';
			answer.classList.add('airygen-faq__content');
			if (!answer.id) {
				answer.id = 'airygen-faq-content-' + Math.random().toString(36).slice(2);
			}
			header.setAttribute('aria-controls', answer.id);
		}
		function setExpanded(item, expanded) {
			var header = item.querySelector('.airygen-faq__header');
			var content = item.querySelector('.airygen-faq__content');
			if (!header || !content) {
				var items = container.querySelectorAll('.airygen-faq__collapse-item');
				items.forEach(function(item) {
					item.classList.remove('airygen-faq__collapse-item');
					item.classList.add('airygen-faq__list-item');
				});
				return;
			}
			header.setAttribute('aria-expanded', expanded ? 'true' : 'false');
			var toggle = header.querySelector('.airygen-faq__toggle');
			if (toggle) {
				toggle.innerHTML = expanded ? minusIcon : plusIcon;
			}
			content.style.display = 'block';
			if (expanded) {
				content.style.maxHeight = content.scrollHeight + 'px';
				content.classList.add('is-open');
			} else {
				content.style.maxHeight = '0px';
				content.classList.remove('is-open');
			}
		}
		function extractTexts(item) {
			var titleNode = item.querySelector('.airygen-faq__title');
			var questionNode = item.querySelector('.airygen-faq__question');
			var contentNode = item.querySelector('.airygen-faq__content');
			var answerNode = item.querySelector('.airygen-faq__answer');
			var questionText = titleNode
				? titleNode.textContent
				: questionNode
					? questionNode.textContent
					: '';
			var answerText = contentNode
				? contentNode.textContent
				: answerNode
					? answerNode.textContent
					: '';
			return {
				question: questionText || '',
				answer: answerText || '',
			};
		}
		function renderListItem(item) {
			var text = extractTexts(item);
			item.classList.remove('airygen-faq__collapse-item');
			item.classList.add('airygen-faq__list-item');
			item.innerHTML =
				'<div class="airygen-faq__question"></div><div class="airygen-faq__answer"></div>';
			var q = item.querySelector('.airygen-faq__question');
			var a = item.querySelector('.airygen-faq__answer');
			if (q) {
				q.textContent = text.question;
			}
			if (a) {
				a.textContent = text.answer;
			}
		}
		function renderCollapseItem(item) {
			var text = extractTexts(item);
			item.classList.remove('airygen-faq__list-item');
			item.classList.add('airygen-faq__collapse-item');
			item.innerHTML =
				'<div class="airygen-faq__header" role="button" tabindex="0" aria-expanded="false">' +
				'<span class="airygen-faq__title"></span>' +
				'<span class="airygen-faq__toggle" aria-hidden="true"></span>' +
				'</div>' +
				'<div class="airygen-faq__content"></div>';
			var header = item.querySelector('.airygen-faq__header');
			var title = item.querySelector('.airygen-faq__title');
			var toggle = item.querySelector('.airygen-faq__toggle');
			var content = item.querySelector('.airygen-faq__content');
			if (title) {
				title.textContent = text.question;
			}
			if (content) {
				content.textContent = text.answer;
				if (!content.id) {
					content.id = 'airygen-faq-content-' + Math.random().toString(36).slice(2);
				}
			}
			if (header && content) {
				header.setAttribute('aria-controls', content.id);
			}
			if (toggle) {
				toggle.innerHTML = plusIcon;
			}
			item.dataset.airygenFaqItem = '1';
		}
		function applyCollapse(settings) {
			var collapsible = !!settings.collapse_enabled;
			var container = root.querySelector('.airygen-faq');
			if (!container) {
				return;
			}
			var itemNodes = Array.prototype.slice.call(container.children || []);
			itemNodes.forEach(function(item) {
				if (item && item.nodeType === 1) {
					item.dataset.airygenFaqItem = '1';
				}
			});
			var allItems = container.querySelectorAll('[data-airygen-faq-item]');
			if (!collapsible) {
				container.classList.remove('airygen-faq--collapsible');
				allItems.forEach(function(item) {
					renderListItem(item);
				});
				var headers = container.querySelectorAll('.airygen-faq__header');
				headers.forEach(function(header) {
					header.style.display = 'block';
					var toggle = header.querySelector('.airygen-faq__toggle');
					if (toggle) {
						toggle.style.display = 'none';
					}
				});
				var answers = container.querySelectorAll('.airygen-faq__content');
				answers.forEach(function(answer) {
					answer.style.display = 'block';
					answer.style.maxHeight = 'none';
					answer.classList.remove('is-open');
				});
				return;
			}
			container.classList.add('airygen-faq--collapsible');
			container.dataset.airygenKeepOpen =
				settings.collapse_style && settings.collapse_style.keep_open ? '1' : '0';
			if (settings.keep_open) {
				container.dataset.airygenKeepOpen = '1';
			}
			allItems.forEach(function(item) {
				renderCollapseItem(item);
			});
			items = container.querySelectorAll('.airygen-faq__collapse-item');
			items.forEach(function(item) {
				setExpanded(item, false);
			});
			var headers = container.querySelectorAll('.airygen-faq__header');
			headers.forEach(function(header) {
				if (header.dataset.airygenBound === '1') {
					return;
				}
				header.dataset.airygenBound = '1';
				header.addEventListener('click', function() {
					var item = header.closest('.airygen-faq__collapse-item');
					if (!item) {
						return;
					}
					var wrap = header.closest('.airygen-faq');
					var keepOpen = wrap && wrap.dataset.airygenKeepOpen === '1';
					var expanded = header.getAttribute('aria-expanded') === 'true';
					if (!keepOpen && !expanded) {
						var openItems = wrap ? wrap.querySelectorAll('.airygen-faq__collapse-item') : [];
						openItems.forEach(function(other) {
							if (other !== item) {
								setExpanded(other, false);
							}
						});
					}
					setExpanded(item, !expanded);
				});
			});
		}
		function updatePreset(preset) {
			var container = root.querySelector('.airygen-faq');
			if (!container) {
				return;
			}
			container.className = container.className.replace(/airygen-faq--preset-[a-z-]+/g, '').trim();
			if (preset) {
				container.classList.add('airygen-faq--preset-' + preset);
			}
		}
		function applyTitle(settings) {
			var enabled = settings.title_enabled !== false;
			var text = settings.title_text || 'Frequently Asked Questions';
			var level = settings.title_level || 'h3';
			if (['h2', 'h3', 'h4'].indexOf(level) === -1) {
				level = 'h3';
			}
			var existing = document.getElementById('airygen-faq-title');
			if (!enabled || !text) {
				if (existing) {
					existing.parentNode.removeChild(existing);
				}
				return;
			}
			if (!existing || existing.tagName.toLowerCase() !== level) {
				if (existing) {
					existing.parentNode.removeChild(existing);
				}
				existing = document.createElement(level);
				existing.id = 'airygen-faq-title';
					existing.className = 'airygen-faq__section-title';
				root.insertBefore(existing, root.firstChild);
			}
			existing.textContent = text;
		}
		window.addEventListener('message', function (event) {
			if (!event || !event.data || event.data.type !== 'airygenFaqPreview') {
				return;
			}
			var settings = event.data.settings || {};
			var activePreset = settings.style && settings.style.preset ? settings.style.preset : 'minimal';
			if (settings.collapse_enabled && settings.collapse_style && settings.collapse_style.preset) {
				activePreset = settings.collapse_style.preset;
			}
			applyStyles(settings);
			applyCollapse(settings);
			applyTitle(settings);
			updatePreset(activePreset);
		});
		var initData = window.airygenFaqPreviewData || {};
		applyStyles({ style: initData.style || {} });
		applyCollapse({ collapse_enabled: false });
		applyTitle({
			title_enabled: initData.title_enabled,
			title_text: initData.title_text,
			title_level: initData.title_level
		});
		updatePreset(initData.preset || 'minimal');
})();
