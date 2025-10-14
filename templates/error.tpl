{**
 * plugins/importexport/copernicus/templates/error.tpl
 *}
{extends file="layouts/backend.tpl"}

{block name="page"}
    <h1 class="app__pageHeading">
        {translate key="plugins.importexport.copernicus.displayName"}
    </h1>

    <div class="app__contentPanel">
        <div class="alert alert-danger">
            <strong>Error:</strong> {$errorMessage}
        </div>
        
        <div class="app__formAction">
            <a href="{url page="management" op="importexport" path="CopernicusExportPlugin"}" class="button">
                {translate key="common.back"}
            </a>
        </div>
    </div>
{/block}