<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0"
	xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
	xmlns:sitemap="http://www.sitemaps.org/schemas/sitemap/0.9"
	exclude-result-prefixes="sitemap">
	<xsl:output method="html" version="1.0" encoding="UTF-8" indent="yes" />
	<xsl:template match="/">
		<html xmlns="http://www.w3.org/1999/xhtml">
			<head>
				<title>Sitemap</title>
				<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
				<style type="text/css">
					body {
						font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol", "Noto Color Emoji";
						color: #444;
					}
					a {
						color: #0669f7;
					}
					table {
						border: none;
						border-collapse: collapse;
						width: 100%;
					}
					th {
						text-align: left;
						padding: 15px;
						border-bottom: 2px solid #d0d0d0;
						font-size: 1rem;
					}
					td {
						padding: 15px;
						border-bottom: 1px solid #d0d0d0;
						font-size: 0.95rem;
					}
					tr:hover td {
						background: #f5f5f5;
					}
					@media (max-width: 768px) {
						td, th {
							display: block;
						}
						th {
							border-bottom: none;
							padding-bottom: 5px;
						}
					}
				</style>
			</head>
			<body>
				<h1>Sitemap</h1>
				<p>This sitemap contains URLs to your content.</p>
				<table>
					<thead>
						<tr>
							<th>URL</th>
							<th>Last Modified</th>
						</tr>
					</thead>
					<tbody>
						<xsl:for-each select="sitemap:urlset/sitemap:url">
							<tr>
								<td>
									<a href="{sitemap:loc}">
										<xsl:value-of select="sitemap:loc"/>
									</a>
								</td>
								<td>
									<xsl:value-of select="sitemap:lastmod"/>
								</td>
							</tr>
						</xsl:for-each>
					</tbody>
				</table>
			</body>
		</html>
	</xsl:template>
</xsl:stylesheet>
