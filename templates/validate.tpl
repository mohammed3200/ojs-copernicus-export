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
		{translate key="plugins.importexport.copernicus.selectIssue.long"}
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
			{/literal}
		</style>

		<h2>{translate key="plugins.importexport.copernicus.validate"} - {translate key="common.results"}</h2>

		<div class="cont">
			<h3>{translate key="plugins.importexport.copernicus.validationResults"}</h3>
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
					<strong>{translate key="common.success"}</strong> {translate key="plugins.importexport.copernicus.xmlValid"}
				</div>
			{/if}
		</div>

		<div>
			<h3>{translate key="plugins.importexport.copernicus.generatedXml"}</h3>
			<pre>{foreach from=$xml_lines item=line key=i}<span id="{$i+1}">{$line}</span>{/foreach}</pre>
		</div>

		<div class="app__formAction">
			<a href="{url page="management" op="importexport" path="CopernicusExportPlugin"}" class="button">
				{translate key="common.back"}
			</a>
		</div>
	</div>
{/block}