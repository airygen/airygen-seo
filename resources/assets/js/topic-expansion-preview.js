(function () {
	var root = document.querySelector('.airygen-topic-preview__content');
	if (!root) {
		return;
	}
	var styleNode = document.getElementById('airygen-topic-expansion-live-styles');
	if (!styleNode) {
		styleNode = document.createElement('style');
		styleNode.id = 'airygen-topic-expansion-live-styles';
		document.head.appendChild(styleNode);
	}
		function applyStyles(settings) {
			var st = settings.style || {};
			var headerContainer = st.header_container || st.headerContainer || {};
			var headerTitle = st.header_title || st.headerTitle || {};
			var headerTitleStyle = headerTitle.font_style || headerTitle.fontStyle || {};
			var borderStyle = st.border_style || st.borderStyle || 'solid';
			var borderColor = st.border_color || st.borderColor || '#e2e8f0';
			var borderWidth = Number.isFinite(Number(st.border_width))
				? Number(st.border_width)
				: Number.isFinite(Number(st.borderWidth))
					? Number(st.borderWidth)
					: 1;
			var borderRadius = Number.isFinite(Number(st.border_radius))
				? Number(st.border_radius)
				: Number.isFinite(Number(st.borderRadius))
					? Number(st.borderRadius)
					: 8;
			var padding = Number.isFinite(Number(st.padding))
				? Number(st.padding)
				: 16;
			var bgColor = st.bg_color || st.bgColor || '#ffffff';
			var itemText = st.item_text_color || st.itemTextColor || '#0f172a';
			var itemSize = Number.isFinite(Number(st.item_font_size))
				? Number(st.item_font_size)
				: Number.isFinite(Number(st.itemFontSize))
					? Number(st.itemFontSize)
					: 16;
			var itemBold = st.item_bold === true || st.itemBold === true;
			var itemItalic = st.item_italic === true || st.itemItalic === true;
			var itemUnderline =
				st.item_underline === true ||
				st.itemUnderline === true ||
				st.underline_links === true ||
				st.underlineLinks === true;
			var itemListStyle = st.item_list_style || st.itemListStyle || 'none';
			if (['none', 'disc', 'decimal'].indexOf(itemListStyle) === -1) {
				itemListStyle = 'none';
			}
				var itemGap = Number.isFinite(Number(st.item_gap))
					? Number(st.item_gap)
					: Number.isFinite(Number(st.itemGap))
						? Number(st.itemGap)
						: 5;
				itemGap = Math.max(0, Math.min(20, itemGap));
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
				var border = borderWidth + 'px ' + borderStyle + ' ' + borderColor;
				var css = '.airygen-topic-expansion{padding:' + padding + 'px;border:' + border + ';background:' + bgColor + ';border-radius:' + borderRadius + 'px;}';
				css += '.airygen-topic-expansion__title{display:block;margin:' + headerMarginTop + 'px ' + headerMarginRight + 'px ' + headerMarginBottom + 'px ' + headerMarginLeft + 'px;padding:' + headerPaddingTop + 'px ' + headerPaddingRight + 'px ' + headerPaddingBottom + 'px ' + headerPaddingLeft + 'px;border-width:' + headerBorderWidthTop + 'px ' + headerBorderWidthRight + 'px ' + headerBorderWidthBottom + 'px ' + headerBorderWidthLeft + 'px;border-style:' + headerBorderStyle + ';border-color:' + headerBorderColor + ';border-radius:' + headerBorderRadius + 'px;background:' + headerBgColor + ';color:' + headerColor + ';font-size:' + headerFontSize + 'px;font-weight:' + (headerTitleStyle.bold ? '700' : '400') + ';font-style:' + (headerTitleStyle.italic ? 'italic' : 'normal') + ';text-decoration:' + (headerTitleStyle.underline ? 'underline' : 'none') + ';}';
				css += '.airygen-topic-expansion__list{display:flex;flex-direction:column;gap:' + itemGap + 'px;margin:0;list-style:' + itemListStyle + ';padding-left:' + (itemListStyle === 'none' ? '0' : '1.25rem') + ';}';
				css += '.airygen-topic-expansion__list-item{margin:0;}';
			css += '.airygen-topic-expansion__topic{color:' + itemText + ';font-size:' + itemSize + 'px;font-weight:' + (itemBold ? '700' : '400') + ';font-style:' + (itemItalic ? 'italic' : 'normal') + ';text-decoration:' + (itemUnderline ? 'underline' : 'none') + ';}';
			styleNode.textContent = css;
		}
		function updatePreset(preset) {
			var container = root.querySelector('.airygen-topic-expansion');
			if (!container) {
				return;
			}
			container.className = container.className.replace(/airygen-topic-expansion--preset-[a-z-]+/g, '').trim();
			if (preset) {
				container.classList.add('airygen-topic-expansion--preset-' + preset);
			}
		}
		function applyTitle(settings) {
			var enabled = settings.title_enabled !== false;
			var text = settings.title_text || 'Related Topics';
			var level = settings.title_level || 'h3';
			if (['h2', 'h3', 'h4'].indexOf(level) === -1) {
				level = 'h3';
			}
			var existing = document.getElementById('airygen-topic-expansion-title');
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
				existing.id = 'airygen-topic-expansion-title';
				existing.className = 'airygen-topic-expansion__title';
				root.insertBefore(existing, root.firstChild);
			}
			existing.textContent = text;
		}
		window.addEventListener('message', function (event) {
			if (!event || !event.data || event.data.type !== 'airygenTopicPreview') {
				return;
			}
			var settings = event.data.settings || {};
			var activePreset = settings.style && settings.style.preset ? settings.style.preset : 'minimal';
			applyStyles(settings);
			applyTitle(settings);
			updatePreset(activePreset);
		});
		var initData = window.airygenTopicPreviewData || {};
		applyStyles({ style: initData.style || {} });
		applyTitle({
			title_enabled: initData.title_enabled,
			title_text: initData.title_text,
			title_level: initData.title_level
		});
		updatePreset(initData.preset || 'minimal');
})();
