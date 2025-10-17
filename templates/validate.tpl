{**
 * plugins/importexport/copernicus/templates/validate.tpl
 *
 * Copyright (c) 2013-2015 Simon Fraser University
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Copernicus import/export plugin - XML validation results
 *}
{extends file="layouts/backend.tpl"}

{block name="page"}
	<h1 class="app__pageHeading">
		{translate key="plugins.importexport.copernicus.validationResults"}
	</h1>

	<div class="app__contentPanel">
		<style type="text/css">
			{literal}
			/* -- General formatting and line numbers (kept for accessibility) -- */
			.validation-warning {
				background-color: #fff3cd;
				color: #856404;
				border: 1px solid #ffeaa7;
				padding: 0.5em;
				margin: 0.5em 0;
				border-radius: 0.25em;
			}
			.validation-error {
				background-color: #f8d7da;
				color: #721c24;
				border: 1px solid #f5c6cb;
				padding: 0.5em;
				margin: 0.5em 0;
				border-radius: 0.25em;
			}
			.validation-fatal {
				background-color: #e2c3ff;
				color: #4a0080;
				border: 1px solid #d4a5ff;
				padding: 0.5em;
				margin: 0.5em 0;
				border-radius: 0.25em;
			}
			.validation-ok {
				background-color: #d4edda;
				color: #155724;
				border: 1px solid #c3e6cb;
				padding: 0.5em;
				margin: 0.5em 0;
				border-radius: 0.25em;
			}

			.results-section {
				margin-bottom: 2em;
				padding: 1em;
				background: white;
				border-radius: 4px;
				box-shadow: 0 1px 3px rgba(0,0,0,0.1);
			}
			.section-title {
				font-size: 1.2em;
				font-weight: bold;
				margin-bottom: 0.5em;
				color: #333;
				border-bottom: 2px solid #007ab3;
				padding-bottom: 0.3em;
			}

			/* -- XML clamp box: shows N lines and appends "..." -- */
			.xml-clamp {
				background:#f8f9fa;
				border:1px solid #ddd;
				padding:12px;
				border-radius:6px;
				font-family: monospace;
				font-size: 0.95rem;
				line-height: 1.45rem;

				white-space: pre-wrap;        /* preserve newlines, allow wrapping */
				overflow: hidden;
				position: relative;
				display: -webkit-box;
				-webkit-box-orient: vertical;

				/* default visible lines; change the number below as needed */
				-webkit-line-clamp: 12;
			}

			/* fallback for browsers without -webkit-line-clamp */
			@supports not ( -webkit-line-clamp: 1 ) {
				.xml-clamp {
					max-height: calc(1.45rem * 12); /* same number-of-lines * line-height */
					overflow: hidden;
				}
			}

			/* decorative ellipsis at the bottom-right */
			.xml-clamp::after {
				content: '...';
				position: absolute;
				right: 12px;
				bottom: 8px;
				background: linear-gradient(to right, rgba(248,249,250,0), #f8f9fa 40%);
				padding-left: 6px;
				color: #666;
				font-weight: 600;
				pointer-events: none;
			}

			.xml-header {
				display:flex;
				align-items:center;
				justify-content:space-between;
				margin-bottom:8px;
			}

			.copy-btn {
				background: #007ab3;
				color: white;
				border: none;
				padding: 0.4em 0.8em;
				border-radius: 3px;
				cursor: pointer;
				font-size: 0.9em;
			}
			.copy-btn:hover { background: #005a87; }
			.copy-btn.copied { background: #28a745; }
			{/literal}
		</style>

		<script>
		// copy the visible/full XML by reading the DOM node text.
		function copyXmlToClipboard() {
			const xmlNode = document.getElementById('xml');
			if (!xmlNode) return;
			const xmlText = xmlNode.innerText || xmlNode.textContent || '';
			navigator.clipboard.writeText(xmlText).then(function() {
				const btn = document.getElementById('copyXmlBtn');
				if (!btn) return;
				const originalText = btn.textContent;
				btn.textContent = 'Copied!';
				btn.classList.add('copied');
				setTimeout(function() {
					btn.textContent = originalText;
					btn.classList.remove('copied');
				}, 1400);
			}).catch(function(err) {
				console.error('Failed to copy XML: ', err);
				alert('{translate key="plugins.importexport.copernicus.copyFailed"|default:"Failed to copy XML to clipboard. Please select and copy manually."}');
			});
		}
		</script>

		<!-- Validation Results Section -->
		<div class="results-section">
			<div class="section-title">Validation Results</div>
			{if count($xml_errors) > 0}
				{foreach from=$xml_errors item=error}
					<div>
						{if $error->level == LIBXML_ERR_WARNING}
							<div class="validation-warning">
								<strong>Warning {$error->code}:</strong> {$error->message} at line {$error->line}
							</div>
						{elseif $error->level == LIBXML_ERR_ERROR}
							<div class="validation-error">
								<strong>Error {$error->code}:</strong> {$error->message} at line {$error->line}
							</div>
						{elseif $error->level == LIBXML_ERR_FATAL}
							<div class="validation-fatal">
								<strong>Fatal Error {$error->code}:</strong> {$error->message} at line {$error->line}
							</div>
						{/if}
					</div>
				{/foreach}
			{else}
				<div class="validation-ok">
					<strong>✓ Success:</strong> The XML is valid and complies with ICI standards.
				</div>
			{/if}
		</div>

		<!-- Generated XML Section -->
		<div class="results-section">
			<div class="section-title">Generated XML</div>

			<div class="xml-header">
				<span><strong>XML Content</strong></span>
				<button id="copyXmlBtn" class="copy-btn" onclick="copyXmlToClipboard()">
					Copy XML to Clipboard
				</button>
			</div>

			<div class="xml-content">
				<!-- render lines inside a clamp div (no show/hide button) -->
				<div id="xml" class="xml-clamp" role="region" aria-label="XML content">
					{foreach from=$xml_lines item=line key=i}
						<span id="{$i+1}">{$line}</span>
					{/foreach}
				</div>
			</div>
		</div>

		<div class="app__formAction">
			<a href="{url page="management" op="importexport" path="CopernicusExportPlugin"}" class="button">
				{translate key="common.back"}
			</a>
			{if count($xml_errors) == 0}
				<a href="?op=exportIssue&issueId={$issueId}" class="button button-primary" style="margin-left: 10px;">
					{translate key="common.export"} XML File
				</a>
			{/if}
		</div>
	</div>
{/block}