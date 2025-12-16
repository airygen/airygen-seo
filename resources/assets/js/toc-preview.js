(function () {
	var root = document.querySelector('.airygen-toc-preview__content');
	if (!root) {
		return;
	}
	var styleNode = document.getElementById('airygen-toc-live-styles');
	if (!styleNode) {
		styleNode = document.createElement('style');
		styleNode.id = 'airygen-toc-live-styles';
		document.head.appendChild(styleNode);
	}
	function norm(settings) {
		var s = settings || {};
		s.style = s.style || {};
		return s;
	}
	function slugify(text) {
		return text
			.toLowerCase()
			.replace(/[^a-z0-9\s-]/g, '')
			.replace(/\s+/g, '-')
			.replace(/-+/g, '-')
			.replace(/^-|-$/g, '');
	}
		function applyStyles(settings) {
			var st = settings.style || {};
			var bodyContainer = st.body_container || {};
			var headerContainer = st.header_container || {};
			var headerTitle = st.header_title || {};
			var headerTitleStyle = headerTitle.font_style || {};
			var borderStyle = st.border_style || 'solid';
			var borderColor = st.border_color || '#e2e8f0';
			var borderRadius = Number.isFinite(Number(st.border_radius)) ? Number(st.border_radius) : 0;
			var bodyBorderWidthTop = Number.isFinite(Number(bodyContainer.border_width_top)) ? Number(bodyContainer.border_width_top) : 1;
			var bodyBorderWidthRight = Number.isFinite(Number(bodyContainer.border_width_right)) ? Number(bodyContainer.border_width_right) : 1;
			var bodyBorderWidthBottom = Number.isFinite(Number(bodyContainer.border_width_bottom)) ? Number(bodyContainer.border_width_bottom) : 1;
			var bodyBorderWidthLeft = Number.isFinite(Number(bodyContainer.border_width_left)) ? Number(bodyContainer.border_width_left) : 1;
			var bodyPaddingTop = Number.isFinite(Number(bodyContainer.padding_top)) ? Number(bodyContainer.padding_top) : 16;
			var bodyPaddingRight = Number.isFinite(Number(bodyContainer.padding_right)) ? Number(bodyContainer.padding_right) : 16;
			var bodyPaddingBottom = Number.isFinite(Number(bodyContainer.padding_bottom)) ? Number(bodyContainer.padding_bottom) : 16;
			var bodyPaddingLeft = Number.isFinite(Number(bodyContainer.padding_left)) ? Number(bodyContainer.padding_left) : 16;
			var bodyMarginTop = Number.isFinite(Number(bodyContainer.margin_top)) ? Number(bodyContainer.margin_top) : 0;
			var bodyMarginRight = Number.isFinite(Number(bodyContainer.margin_right)) ? Number(bodyContainer.margin_right) : 0;
			var bodyMarginBottom = Number.isFinite(Number(bodyContainer.margin_bottom)) ? Number(bodyContainer.margin_bottom) : 0;
			var bodyMarginLeft = Number.isFinite(Number(bodyContainer.margin_left)) ? Number(bodyContainer.margin_left) : 0;
			var tocPadding = Number.isFinite(Number(st.toc_padding)) ? Number(st.toc_padding) : 12;
			var linkColor = st.link_color || '#2563eb';
			var linkSize = Number.isFinite(Number(st.link_size)) ? Number(st.link_size) : 14;
			var fontStyle = st.font_style || {};
			var bold = !!fontStyle.bold;
			var italic = !!fontStyle.italic;
			var underline = !!fontStyle.underline;
			var bgColor = st.bg_color || '#ffffff';
			var maxWidth = Number.isFinite(Number(st.max_width)) ? Number(st.max_width) : 80;
			var numbered = !!settings.add_numbers;
			var collapsible = !!settings.collapse_on_load;
				var css = '.airygen-toc{margin:' + bodyMarginTop + 'px ' + bodyMarginRight + 'px ' + bodyMarginBottom + 'px ' + bodyMarginLeft + 'px;';
				css += 'padding:' + bodyPaddingTop + 'px ' + bodyPaddingRight + 'px ' + bodyPaddingBottom + 'px ' + bodyPaddingLeft + 'px;';
				css += 'border-width:' + bodyBorderWidthTop + 'px ' + bodyBorderWidthRight + 'px ' + bodyBorderWidthBottom + 'px ' + bodyBorderWidthLeft + 'px;';
				css += 'border-style:' + borderStyle + ';border-color:' + borderColor + ';background:' + bgColor + ';max-width:' + maxWidth + '%;}';
				css += '.airygen-toc{border-radius:' + borderRadius + 'px;}';
				var headerPaddingTop = Number.isFinite(Number(headerContainer.padding_top)) ? Number(headerContainer.padding_top) : 0;
				var headerPaddingRight = Number.isFinite(Number(headerContainer.padding_right)) ? Number(headerContainer.padding_right) : 0;
				var headerPaddingBottom = Number.isFinite(Number(headerContainer.padding_bottom)) ? Number(headerContainer.padding_bottom) : 0;
				var headerPaddingLeft = Number.isFinite(Number(headerContainer.padding_left)) ? Number(headerContainer.padding_left) : 0;
				var headerMarginTop = Number.isFinite(Number(headerContainer.margin_top)) ? Number(headerContainer.margin_top) : 0;
				var headerMarginRight = Number.isFinite(Number(headerContainer.margin_right)) ? Number(headerContainer.margin_right) : 0;
				var headerMarginBottom = Number.isFinite(Number(headerContainer.margin_bottom)) ? Number(headerContainer.margin_bottom) : 12;
				var headerMarginLeft = Number.isFinite(Number(headerContainer.margin_left)) ? Number(headerContainer.margin_left) : 0;
				var headerBorderWidthTop = Number.isFinite(Number(headerContainer.border_width_top)) ? Number(headerContainer.border_width_top) : 0;
				var headerBorderWidthRight = Number.isFinite(Number(headerContainer.border_width_right)) ? Number(headerContainer.border_width_right) : 0;
				var headerBorderWidthBottom = Number.isFinite(Number(headerContainer.border_width_bottom)) ? Number(headerContainer.border_width_bottom) : 0;
				var headerBorderWidthLeft = Number.isFinite(Number(headerContainer.border_width_left)) ? Number(headerContainer.border_width_left) : 0;
				var headerBorderRadius = Number.isFinite(Number(headerContainer.border_radius)) ? Number(headerContainer.border_radius) : 0;
				var headerBorderStyle = headerContainer.border_style || 'solid';
				var headerBorderColor = headerContainer.border_color || '#e2e8f0';
				var headerBg = headerContainer.bg_color || 'transparent';
				var headerColor = headerTitle.color || '#0f172a';
				var headerFontSize = Number.isFinite(Number(headerTitle.font_size)) ? Number(headerTitle.font_size) : 18;
				css += '.airygen-toc-header{display:block;';
					css += 'margin:' + headerMarginTop + 'px ' + headerMarginRight + 'px ' + headerMarginBottom + 'px ' + headerMarginLeft + 'px;';
					css += 'padding:' + headerPaddingTop + 'px ' + headerPaddingRight + 'px ' + headerPaddingBottom + 'px ' + headerPaddingLeft + 'px;';
					css += 'border-width:' + Math.max(0, Math.min(8, headerBorderWidthTop)) + 'px ' + Math.max(0, Math.min(8, headerBorderWidthRight)) + 'px ' + Math.max(0, Math.min(8, headerBorderWidthBottom)) + 'px ' + Math.max(0, Math.min(8, headerBorderWidthLeft)) + 'px;';
					css += 'border-style:' + headerBorderStyle + ';';
					css += 'border-color:' + headerBorderColor + ';';
				css += 'border-radius:' + Math.max(0, Math.min(48, headerBorderRadius)) + 'px;';
				css += 'background:' + headerBg + ';';
				css += 'color:' + headerColor + ';';
				css += 'font-size:' + Math.max(10, Math.min(40, headerFontSize)) + 'px;';
				css += 'font-weight:' + (headerTitleStyle.bold ? '700' : '400') + ';';
				css += 'font-style:' + (headerTitleStyle.italic ? 'italic' : 'normal') + ';';
				css += 'text-decoration:' + (headerTitleStyle.underline ? 'underline' : 'none') + ';';
				css += '}';
				css += ' .airygen-toc__list,.airygen-toc__sublist{margin:0;padding-left:' + tocPadding + 'px;}';
			css += ' .airygen-toc__item{margin:0.25rem 0;}';
			css += ' .airygen-toc__link{text-decoration:none;color:' + linkColor + ';font-size:' + linkSize + 'px;font-weight:' + (bold ? '700' : '400') + ';font-style:' + (italic ? 'italic' : 'normal') + ';}';
			if (underline) {
				css += '.airygen-toc__link{text-decoration:underline;text-underline-offset:3px;}';
				css += '.airygen-toc__link:hover{text-decoration-thickness:2px;}';
			}
			if (!numbered) {
				css += '.airygen-toc__list,.airygen-toc__sublist{list-style:none;}';
			}
			if (numbered) {
				css += '.airygen-toc__list{list-style:decimal;}';
				css += '.airygen-toc__sublist{list-style:lower-alpha;}';
				css += '.airygen-toc__sublist .airygen-toc__sublist{list-style:lower-roman;}';
			}
			if (collapsible) {
				css += '.airygen-toc-header{cursor:pointer;}';
			}
			var preset = (st.preset || 'minimal').toLowerCase();
			if ('card' === preset) {
				css += '.airygen-toc--preset-card{background:#f8fafc;border-radius:12px;box-shadow:0 1px 2px rgba(15,23,42,0.08);}';
			}
			if ('soft' === preset) {
				css += '.airygen-toc--preset-soft{background:#f1f5f9;border-radius:10px;border-color:#cbd5f5;}';
			}
			if ('accent' === preset) {
				var bodyBorderMax = Math.max(
					bodyBorderWidthTop,
					bodyBorderWidthRight,
					bodyBorderWidthBottom,
					bodyBorderWidthLeft
				);
				var accentBorder = Math.max(3, bodyBorderMax) + 'px ' + borderStyle + ' #0ea5e9';
				css += '.airygen-toc--preset-accent{border:' + accentBorder + ';border-left-width:' + Math.max(4, bodyBorderMax) + 'px;}';
			}
			if ('compact' === preset) {
				css += '.airygen-toc--preset-compact{font-size:0.95em;}';
				css += '.airygen-toc--preset-compact .airygen-toc__item{margin:0.2rem 0;}';
			}
			if ('underline' === preset) {
				css += '.airygen-toc--preset-underline .airygen-toc__link{text-decoration:underline;text-underline-offset:3px;}';
				css += '.airygen-toc--preset-underline .airygen-toc__link:hover{text-decoration-thickness:2px;}';
			}
			if (settings.smooth_scroll) {
				document.documentElement.style.scrollBehavior = 'smooth';
			} else {
				document.documentElement.style.scrollBehavior = '';
			}
			styleNode.textContent = css;
		}
		function buildToc(settings) {
			var existing = root.querySelector('.airygen-toc');
			if (existing) {
				var detailsWrapper = existing.closest('.airygen-toc-collapsible');
				if (detailsWrapper) {
					detailsWrapper.remove();
				}
				existing.remove();
			}
			var existingHeader = root.querySelector('.airygen-toc-header');
			if (existingHeader) {
				existingHeader.remove();
			}
			var levels = Array.isArray(settings.levels) ? settings.levels : [2, 3];
			levels = levels
				.map(function (l) {
					return Number(l);
				})
				.filter(function (l) {
					return l >= 2 && l <= 6;
				});
			if (!levels.length) {
				levels = [2, 3];
			}
			var minHeadings = Number.isFinite(Number(settings.min_headings))
				? Number(settings.min_headings)
				: 3;
			var exclude = (settings.exclude_headings || '')
				.split(',')
				.map(function (s) {
					return s.trim();
				})
				.filter(Boolean);
			var headings = [].slice.call(root.querySelectorAll('h2,h3,h4,h5,h6')).filter(function (node) {
				var level = Number(node.tagName.replace('H', ''));
				if (levels.indexOf(level) === -1) {
					return false;
				}
				var text = node.textContent || '';
				for (var i = 0; i < exclude.length; i++) {
					if (text.toLowerCase().indexOf(exclude[i].toLowerCase()) !== -1) {
						return false;
					}
				}
				return true;
			});
			if (headings.length < minHeadings) {
				return;
			}
			var prefix = settings.anchor_prefix || 'toc-';
			var used = {};
			headings.forEach(function (node) {
				if (!node.id) {
					var base = slugify(node.textContent || 'heading');
					if (!base) {
						base = 'section';
					}
					var id = prefix + base;
					var i = 2;
					while (used[id] || document.getElementById(id)) {
						id = prefix + base + '-' + i;
						i += 1;
					}
					node.id = id;
					used[id] = true;
				}
			});
			var list = document.createElement('ol');
			list.className = 'airygen-toc__list';
			var stack = [{ level: levels[0], list: list }];
			var current = list;
			headings.forEach(function (node) {
				var level = Number(node.tagName.replace('H', ''));
				var text = node.textContent || '';
				while (stack.length > 1 && level < stack[stack.length - 1].level) {
					stack.pop();
				}
				current = stack[stack.length - 1].list;
				if (level > stack[stack.length - 1].level) {
					var last = current.lastElementChild;
					if (!last) {
						last = document.createElement('li');
						last.className = 'airygen-toc__item';
						current.appendChild(last);
					}
					var sub = document.createElement('ol');
					sub.className = 'airygen-toc__sublist';
					last.appendChild(sub);
					stack.push({ level: level, list: sub });
					current = sub;
				}
				var item = document.createElement('li');
				item.className = 'airygen-toc__item';
				var link = document.createElement('a');
				link.className = 'airygen-toc__link';
				link.href = '#' + node.id;
				link.textContent = text;
				item.appendChild(link);
				current.appendChild(item);
			});
			var classes = ['airygen-toc'];
			if (settings.style && settings.style.preset) {
				classes.push('airygen-toc--preset-' + settings.style.preset);
			}
			if (settings.add_numbers) {
				classes.push('airygen-toc--numbered');
			}
			if (settings.collapse_on_load) {
				classes.push('airygen-toc--collapsed');
			}
			var titleEnabled = settings.title_enabled !== false;
			var title = settings.title || 'Table of contents';
			var st = settings.style || {};
			var borderStyle = st.border_style || 'solid';
			var borderColor = st.border_color || '#e2e8f0';
			var borderRadius = Number.isFinite(Number(st.border_radius)) ? Number(st.border_radius) : 0;
			var bodyContainer = st.body_container || {};
			var bodyBorderWidthTop = Number.isFinite(Number(bodyContainer.border_width_top)) ? Number(bodyContainer.border_width_top) : 1;
			var bodyBorderWidthRight = Number.isFinite(Number(bodyContainer.border_width_right)) ? Number(bodyContainer.border_width_right) : 1;
			var bodyBorderWidthBottom = Number.isFinite(Number(bodyContainer.border_width_bottom)) ? Number(bodyContainer.border_width_bottom) : 1;
			var bodyBorderWidthLeft = Number.isFinite(Number(bodyContainer.border_width_left)) ? Number(bodyContainer.border_width_left) : 1;
			var bodyPaddingTop = Number.isFinite(Number(bodyContainer.padding_top)) ? Number(bodyContainer.padding_top) : 16;
			var bodyPaddingRight = Number.isFinite(Number(bodyContainer.padding_right)) ? Number(bodyContainer.padding_right) : 16;
			var bodyPaddingBottom = Number.isFinite(Number(bodyContainer.padding_bottom)) ? Number(bodyContainer.padding_bottom) : 16;
			var bodyPaddingLeft = Number.isFinite(Number(bodyContainer.padding_left)) ? Number(bodyContainer.padding_left) : 16;
			var bodyMarginTop = Number.isFinite(Number(bodyContainer.margin_top)) ? Number(bodyContainer.margin_top) : 0;
			var bodyMarginRight = Number.isFinite(Number(bodyContainer.margin_right)) ? Number(bodyContainer.margin_right) : 0;
			var bodyMarginBottom = Number.isFinite(Number(bodyContainer.margin_bottom)) ? Number(bodyContainer.margin_bottom) : 0;
			var bodyMarginLeft = Number.isFinite(Number(bodyContainer.margin_left)) ? Number(bodyContainer.margin_left) : 0;
			var bgColor = st.bg_color || '#ffffff';
			var maxWidth = Number.isFinite(Number(st.max_width)) ? Number(st.max_width) : 80;
			var collapsible = !!settings.collapse_on_load;
			var tocNode;
			tocNode = document.createElement('div');
			tocNode.className = classes.join(' ');
			var navNode = document.createElement('nav');
			navNode.className = 'airygen-toc__nav';
			navNode.setAttribute('aria-label', 'Table of contents');
			navNode.appendChild(list);
			tocNode.appendChild(navNode);
			var headingNode = null;
			if (titleEnabled) {
				headingNode = document.createElement('div');
				headingNode.className = 'airygen-toc-header';
				headingNode.textContent = title;
			}
			tocNode.style.borderStyle = borderStyle;
			tocNode.style.borderColor = borderColor;
			tocNode.style.borderWidth = bodyBorderWidthTop + 'px ' + bodyBorderWidthRight + 'px ' + bodyBorderWidthBottom + 'px ' + bodyBorderWidthLeft + 'px';
			tocNode.style.borderRadius = borderRadius + 'px';
			tocNode.style.padding = bodyPaddingTop + 'px ' + bodyPaddingRight + 'px ' + bodyPaddingBottom + 'px ' + bodyPaddingLeft + 'px';
			tocNode.style.margin = bodyMarginTop + 'px ' + bodyMarginRight + 'px ' + bodyMarginBottom + 'px ' + bodyMarginLeft + 'px';
			tocNode.style.background = bgColor;
			tocNode.style.maxWidth = maxWidth + '%';
			tocNode.style.fontSize = '';
			var insertionNode = tocNode;
			if (collapsible) {
				var details = document.createElement('details');
				details.className = 'airygen-toc-collapsible';
				var summary = document.createElement('summary');
				summary.className = 'airygen-toc-header';
				if (titleEnabled) {
					summary.textContent = title;
				} else {
					summary.setAttribute('aria-label', 'Table of contents');
				}
				details.appendChild(summary);
				details.appendChild(tocNode);
				insertionNode = details;
			}
			var position = settings.position || 'after-first-paragraph';
			if (position === 'before-content') {
				if (headingNode && !collapsible) {
					root.insertBefore(headingNode, root.firstChild);
				}
				root.insertBefore(insertionNode, root.firstChild);
			} else if (position === 'after-content') {
				if (headingNode && !collapsible) {
					root.appendChild(headingNode);
				}
				root.appendChild(insertionNode);
			} else {
				var firstP = root.querySelector('p');
				if (firstP && firstP.parentNode === root) {
					if (headingNode && !collapsible) {
						firstP.insertAdjacentElement('afterend', headingNode);
						headingNode.insertAdjacentElement('afterend', insertionNode);
					} else {
						firstP.insertAdjacentElement('afterend', insertionNode);
					}
				} else {
					if (headingNode && !collapsible) {
						root.insertBefore(headingNode, root.firstChild);
					}
					root.insertBefore(insertionNode, headingNode && !collapsible ? headingNode.nextSibling : root.firstChild);
				}
			}
		}
		function handleMessage(event) {
			if (event.origin !== window.location.origin) {
				return;
			}
			var data = event.data || {};
			if (!data || data.type !== 'airygenTocPreview') {
				return;
			}
			var settings = norm(data.settings || {});
			applyStyles(settings);
			buildToc(settings);
		}
		window.addEventListener('message', handleMessage);
})();
