{**
 * plugins/importexport/copernicus/templates/issues.tpl
 *
 * OJS 3.5.0.1 compatible version
 *}
{extends file="layouts/backend.tpl"}

{block name="page"}
	<h1 class="app__pageHeading">
		{translate key="plugins.importexport.copernicus.selectIssue.long"}
	</h1>

	<div class="app__contentPanel">
		{if $issues|@count}
			<table class="pkpTable">
				<thead>
					<tr>
						<th>{translate key="issue.issue"}</th>
						<th>{translate key="editor.issues.published"}</th>
						<th>{translate key="editor.issues.numArticles"}</th>
						<th class="text-right">{translate key="common.action"}</th>
					</tr>
				</thead>
				<tbody>
					{foreach from=$issues item=issue}
						<tr>
							<td>
								<a href="{url page="issue" op="view" path=$issue->getId()}">
									{$issue->getIssueIdentification()|escape}
								</a>
							</td>
							<td>
								{if $issue->getDatePublished()}
									{$issue->getDatePublished()|date_format:$dateFormatShort}
								{else}
									–
								{/if}
							</td>
							<td>
								{$issue->getNumArticles()|escape}
							</td>
							<td class="text-right">
								<!-- Validate button - shows validation results -->
								<a href="{url op="validateIssue" issueId=$issue->getId()}" class="button button-secondary">
									{translate key="plugins.importexport.copernicus.validate"}
								</a>
								<!-- Export button - downloads XML -->
								<a href="{url op="exportIssue" issueId=$issue->getId()}" class="button button-secondary">
									{translate key="common.export"}
								</a>
							</td>
						</tr>
					{/foreach}
				</tbody>
			</table>
		{else}
			<p class="app__notice">{translate key="issue.noIssues"}</p>
		{/if}
	</div>
{/block}