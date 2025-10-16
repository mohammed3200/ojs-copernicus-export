{**
 * plugins/importexport/copernicus/templates/validate.tpl
 *
 * Copyright (c) 2013-2015 Simon Fraser University Library
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
			pre, .cont {
				counter-reset: line;
				font-family: monospace;
				background-color: #fff;
				padding: 0.5em;
				border-radius: .25em;
				box-shadow: .1em .1em .5em rgba(0,0,0,.45);
				line-height: 0;
				margin-bottom: 2em;
			}
			pre span {
				counter-increment: line;
				display: block;
				line-height: 1.5rem;
				overflow: hidden;
			}
			pre span::before {
				content: counter(line);
				-webkit-user-select: none;
				display: inline-block;
				border-right: 1px solid #ddd;
				padding: 0 .5em;
				margin-right: .5em;
				color: #888;
				width: 2em;
				text-align: right;
			}
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
			.xml-container {
				border: 1px solid #ddd;
				border-radius: 4px;
				margin: 1em 0;
			}
			.xml-header {
				background-color: #f8f9fa;
				padding: 0.5em 1em;
				border-bottom: 1px solid #ddd;
				display: flex;
				justify-content: space-between;
				align-items: center;
			}
			.xml-content {
				max-height: 500px;
				overflow: auto;
				padding: 1em;
				background-color: #f8f9fa;
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
			.copy-btn:hover {
				background: #005a87;
			}
			.copy-btn.copied {
				background: #28a745;
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
			{/literal}
		</style>

		<script>
		function copyXmlToClipboard() {
			const xmlText = `{foreach from=$xml_lines item=line}{$line|replace:"`":"\\`"|replace:"'":"\\'"}\n{/foreach}`;
			
			navigator.clipboard.writeText(xmlText).then(function() {
				const btn = document.getElementById('copyXmlBtn');
				const originalText = btn.textContent;
				btn.textContent = 'Copied!';
				btn.classList.add('copied');
				
				setTimeout(function() {
					btn.textContent = originalText;
					btn.classList.remove('copied');
				}, 2000);
			}).catch(function(err) {
				console.error('Failed to copy XML: ', err);
				alert('Failed to copy XML to clipboard. Please select and copy manually.');
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
			<div class="xml-container">
				<div class="xml-header">
					<span><strong>XML Content</strong></span>
					<button id="copyXmlBtn" class="copy-btn" onclick="copyXmlToClipboard()">
						Copy XML to Clipboard
					</button>
				</div>
				<div class="xml-content">
					<pre>{foreach from=$xml_lines item=line key=i}<span id="{$i+1}">{$line}</span>{/foreach}</pre>
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