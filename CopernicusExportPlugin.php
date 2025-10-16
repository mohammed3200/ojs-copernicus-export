<?php

/**
 * @file plugins/importexport/copernicus/CopernicusExportPlugin.php
 *
 * Copyright (c) 2018 Oleksii Vodka
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class CopernicusExportPlugin
 * @ingroup plugins_importexport_copernicus
 *
 * @brief Copernicus import/export plugin
 */

namespace APP\plugins\importexport\copernicus;

use PKP\plugins\ImportExportPlugin;
use APP\core\Application;
use APP\template\TemplateManager;
use APP\facades\Repo;

class CopernicusExportPlugin extends ImportExportPlugin
{
    /**
     * Called as a plugin is registered to the registry
     */
    public function register($category, $path, $mainContextId = NULL)
    {
        $success = parent::register($category, $path, $mainContextId);
        $this->addLocaleData();
        return $success;
    }

    /**
     * Get the name of this plugin
     * IMPORTANT: This must match the plugin folder name
     */
    public function getName()
    {
        return 'copernicus';
    }

    public function getDisplayName()
    {
        return __('plugins.importexport.copernicus.displayName');
    }

    public function getDescription()
    {
        return __('plugins.importexport.copernicus.description');
    }

    /**
     * Return the issue's context id in a backwards-compatible way.
     */
    private function getIssueContextId($issue)
    {
        if (!$issue) return null;

        $contextId = $issue->getData('contextId');
        if (!empty($contextId)) return (int)$contextId;

        $journalId = $issue->getData('journalId');
        if (!empty($journalId)) return (int)$journalId;

        if (method_exists($issue, 'getContextId')) {
            $val = $issue->getContextId();
            if (!empty($val)) return (int)$val;
        }
        if (method_exists($issue, 'getJournalId')) {
            $val = $issue->getJournalId();
            if (!empty($val)) return (int)$val;
        }

        return null;
    }

    /**
     * Display the plugin interface - OJS 3.x uses this method
     */
    public function display($args, $request)
    {
        parent::display($args, $request);
        $context = $request->getContext();

        // Check for both POST and GET parameters
        $op = $request->getUserVar('op') ?: (isset($args[0]) ? $args[0] : null);

        // Debug logging
        error_log("Copernicus Plugin: display() called with op='$op'");
        error_log("Copernicus Plugin: args = " . print_r($args, true));

        if ($op === 'exportIssue') {
            // Handle export - download XML file
            $issueId = (int)$request->getUserVar('issueId');

            error_log("Copernicus Plugin: Export requested for issue ID: $issueId");

            if (empty($issueId)) {
                error_log('Copernicus Plugin: No issue ID provided for export');
                $this->showError($request, 'No issue ID provided');
                return;
            }

            $issue = Repo::issue()->get($issueId);
            if (!$issue) {
                error_log("Copernicus Plugin: Issue not found with ID: $issueId");
                $this->showError($request, 'Issue not found');
                return;
            }

            $issueContextId = $this->getIssueContextId($issue);
            if ($issueContextId === null || (int)$issueContextId !== (int)$context->getId()) {
                error_log("Copernicus Plugin: Issue $issueId doesn't belong to context " . $context->getId() . ", issue contextId=" . var_export($issueContextId, true));
                $this->showError($request, 'Issue does not belong to this journal');
                return;
            }

            $this->exportIssue($context, $issue);
        } elseif ($op === 'validateIssue') {
            // Handle validation - show validation results page
            $issueId = (int)$request->getUserVar('issueId');

            error_log("Copernicus Plugin: Validation requested for issue ID: $issueId");

            if (empty($issueId)) {
                error_log('Copernicus Plugin: No issue ID provided for validation');
                $this->showError($request, 'No issue ID provided');
                return;
            }

            $issue = Repo::issue()->get($issueId);
            if (!$issue) {
                error_log("Copernicus Plugin: Issue not found with ID: $issueId");
                $this->showError($request, 'Issue not found');
                return;
            }

            $issueContextId = $this->getIssueContextId($issue);
            if ($issueContextId === null || (int)$issueContextId !== (int)$context->getId()) {
                error_log("Copernicus Plugin: Issue $issueId doesn't belong to context " . $context->getId() . ", issue contextId=" . var_export($issueContextId, true));
                $this->showError($request, 'Issue does not belong to this journal');
                return;
            }

            $xmlContent = $this->generateIssueXml($context, $issue);

            if ($xmlContent === false) {
                $this->showError($request, 'Failed to generate XML');
                return;
            }

            // Validate XML and prepare results
            $xml_errors = $this->validateXml($xmlContent);
            $xml_lines = explode("\n", htmlentities($xmlContent));

            // Display validation template
            $templateMgr = TemplateManager::getManager($request);
            $templateMgr->assign([
                'xml_errors' => $xml_errors,
                'xml_lines' => $xml_lines
            ]);
            $templateMgr->display($this->getTemplateResource('validate.tpl'));
        } else {
            // Display list of issues for export
            error_log("Copernicus Plugin: Displaying issues list");
            $this->showIssuesList($request, $context);
        }
    }

    /**
     * Show the list of issues
     */
    private function showIssuesList($request, $context)
    {
        $issuesCollection = Repo::issue()
            ->getCollector()
            ->filterByContextIds([$context->getId()])
            ->filterByPublished(true)
            ->getMany();

        // Convert LazyCollection to array for template
        $issues = [];
        foreach ($issuesCollection as $issue) {
            $issues[] = $issue;
        }

        error_log("Copernicus Plugin: Found " . count($issues) . " issues");

        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign('issues', $issues);
        $templateMgr->display($this->getTemplateResource('issues.tpl'));
    }

    /**
     * Show error message
     */
    private function showError($request, $message)
    {
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign('errorMessage', $message);
        $templateMgr->display($this->getTemplateResource('error.tpl'));
    }

    /**
     * Generate XML for an issue
     */
    private function generateIssueXml(&$context, &$issue)
    {
        try {
            // Use DOMDocument instead of XMLCustomWriter
            $doc = new \DOMDocument('1.0', 'UTF-8');
            $doc->formatOutput = true;

            $issueNode = $this->generateIssueDom($doc, $context, $issue);
            $doc->appendChild($issueNode);

            return $doc->saveXML();
        } catch (\Exception $e) {
            error_log("Copernicus Plugin: XML generation error: " . $e->getMessage());
            return false;
        }
    }

    public function formatDate($date)
    {
        if ($date == '') return null;
        return date('Y-m-d', strtotime($date));
    }

    public function formatXml($xmlContent)
    {
        if (is_string($xmlContent)) {
            $dom = new \DOMDocument('1.0');
            $dom->preserveWhiteSpace = false;
            $dom->formatOutput = true;
            if ($dom->loadXML($xmlContent)) {
                return $dom->saveXML();
            }
        }
        return $xmlContent;
    }
    public function &generateIssueDom(&$doc, &$context, &$issue)
    {
        $issn = $context->getData('printIssn') ?: $context->getData('onlineIssn');

        $root = $doc->createElement('ici-import');
        $root->setAttribute("xmlns:xsi", "http://www.w3.org/2001/XMLSchema-instance");
        $root->setAttribute("xsi:noNamespaceSchemaLocation", "https://journals.indexcopernicus.com/ic-import.xsd");

        $journal_elem = $this->createChildWithText($doc, $root, 'journal', '', true);
        $journal_elem->setAttribute('issn', $issn);

        $issue_elem = $this->createChildWithText($doc, $root, 'issue', '', true);

        $pub_issue_date = $issue->getDatePublished() ? date('Y-m-d\TH:i:s\Z', strtotime($issue->getDatePublished())) : '';

        $issue_elem->setAttribute('number', $issue->getNumber());
        $issue_elem->setAttribute('volume', $issue->getVolume());
        $issue_elem->setAttribute('year', $issue->getYear());
        $issue_elem->setAttribute('publicationDate', $pub_issue_date);

        $num_articles = 0;

        // Get submissions for this issue using Repo pattern - handle LazyCollection
        $submissionsCollection = Repo::submission()
            ->getCollector()
            ->filterByContextIds([$context->getId()])
            ->filterByIssueIds([$issue->getId()])
            ->getMany();

        // Convert LazyCollection to array for processing
        $submissions = [];
        foreach ($submissionsCollection as $submission) {
            $submissions[] = $submission;
        }

        foreach ($submissions as $submission) {
            $publication = $submission->getCurrentPublication();
            if (!$publication) continue;

            $locales = $publication->getData('languages') ?: [$context->getPrimaryLocale()];
            $article_elem = $this->createChildWithText($doc, $issue_elem, 'article', '', true);
            $this->createChildWithText($doc, $article_elem, 'type', 'ORIGINAL_ARTICLE');

            foreach ($locales as $loc) {
                $lc = explode('_', $loc);
                $lang_version = $this->createChildWithText($doc, $article_elem, 'languageVersion', '', true);
                $lang_version->setAttribute('language', $lc[0]);
                $this->createChildWithText($doc, $lang_version, 'title', $publication->getLocalizedTitle($loc), true);
                $this->createChildWithText($doc, $lang_version, 'abstract', strip_tags($publication->getLocalizedData('abstract', $loc)), true);

                // PDF URL - handle LazyCollection for galleys
                $galleysCollection = $publication->getData('galleys');
                if ($galleysCollection && $galleysCollection->count() > 0) {
                    $galley = $galleysCollection->first();
                    $request = Application::get()->getRequest();
                    $url = $request->url(null, 'article', 'view', [
                        $submission->getBestId(),
                        $galley->getBestGalleyId()
                    ]);
                    $this->createChildWithText($doc, $lang_version, 'pdfFileUrl', $url, true);
                }

                $publicationDate = $publication->getData('datePublished') ?
                    date('Y-m-d\TH:i:s\Z', strtotime($publication->getData('datePublished'))) : '';
                $this->createChildWithText($doc, $lang_version, 'publicationDate', $publicationDate, false);

                // FIXED: Parse page range properly
                $pages = $publication->getData('pages') ?? '';
                $pageFrom = '';
                $pageTo = '';

                if (!empty($pages)) {
                    if (strpos($pages, '-') !== false) {
                        // Handle page ranges like "43-49"
                        $pageParts = explode('-', $pages);
                        $pageFrom = trim($pageParts[0]);
                        $pageTo = trim($pageParts[1]) ?? '';
                    } else {
                        // Handle single page numbers
                        $pageFrom = trim($pages);
                        $pageTo = trim($pages);
                    }
                }

                // Only add pageFrom if we have a valid integer
                if (!empty($pageFrom) && is_numeric($pageFrom)) {
                    $this->createChildWithText($doc, $lang_version, 'pageFrom', (int)$pageFrom, true);
                }

                // Only add pageTo if we have a valid integer  
                if (!empty($pageTo) && is_numeric($pageTo)) {
                    $this->createChildWithText($doc, $lang_version, 'pageTo', (int)$pageTo, true);
                }

                $this->createChildWithText($doc, $lang_version, 'doi', $publication->getDoi(), true);

                $keywords = $this->createChildWithText($doc, $lang_version, 'keywords', '', true);
                $keywordArray = $publication->getLocalizedData('keywords', $loc);

                if ($keywordArray && is_array($keywordArray)) {
                    foreach ($keywordArray as $keyword) {
                        $this->createChildWithText($doc, $keywords, 'keyword', $keyword, true);
                    }
                } else {
                    $this->createChildWithText($doc, $keywords, 'keyword', " ", true);
                }
            }

            // Authors - handle LazyCollection
            $authors_elem = $this->createChildWithText($doc, $article_elem, 'authors', '', true);
            $index = 1;

            $authorsCollection = $publication->getData('authors');
            if ($authorsCollection) {
                // Convert LazyCollection to array
                $authors = [];
                foreach ($authorsCollection as $author) {
                    $authors[] = $author;
                }

                foreach ($authors as $author) {
                    $author_elem = $this->createChildWithText($doc, $authors_elem, 'author', '', true);

                    $givenName = $author->getLocalizedGivenName() ?: $author->getGivenName();
                    $familyName = $author->getLocalizedFamilyName() ?: $author->getFamilyName();
                    $middleName = $author->getData('middleName') ?: '';

                    $this->createChildWithText($doc, $author_elem, 'name', $givenName, true);
                    $this->createChildWithText($doc, $author_elem, 'name2', $middleName, false);
                    $this->createChildWithText($doc, $author_elem, 'surname', $familyName, true);
                    $this->createChildWithText($doc, $author_elem, 'email', $author->getEmail(), false);
                    $this->createChildWithText($doc, $author_elem, 'order', $index, true);

                    // FIXED: Use getLocalizedData('affiliation') instead of getLocalizedAffiliation()
                    $affiliation = $author->getLocalizedData('affiliation') ?: '';
                    $this->createChildWithText(
                        $doc,
                        $author_elem,
                        'instituteAffiliation',
                        substr($affiliation, 0, 250),
                        false
                    );

                    $this->createChildWithText($doc, $author_elem, 'role', 'AUTHOR', true);

                    // FIXED: Only add ORCID if it's not empty and matches the expected format
                    $orcid = $author->getOrcid();
                    if (!empty($orcid) && preg_match('/https?:\/\/orcid\.org\/[0-9]{4}-[0-9]{4}-[0-9]{4}-[0-9]{3}[X0-9]{1}/', $orcid)) {
                        $this->createChildWithText($doc, $author_elem, 'ORCID', $orcid, false);
                    }

                    $index++;
                }
            }

            // References
            $citation_text = $publication->getData('citationsRaw');
            if ($citation_text) {
                $citation_arr = explode("\n", $citation_text);
                $references_elem = $this->createChildWithText($doc, $article_elem, 'references', '', true);
                $index = 1;
                foreach ($citation_arr as $citation) {
                    if (trim($citation) != "") {
                        $reference_elem = $this->createChildWithText($doc, $references_elem, 'reference', '', true);
                        $this->createChildWithText($doc, $reference_elem, 'unparsedContent', $citation, true);
                        $this->createChildWithText($doc, $reference_elem, 'order', $index, true);
                        $this->createChildWithText($doc, $reference_elem, 'doi', '', true);
                        $index++;
                    }
                }
            }
            $num_articles++;
        }

        $issue_elem->setAttribute('numberOfArticles', $num_articles);
        return $root;
    }

    /**
     * Helper method to create child element with text using DOMDocument
     */
    private function createChildWithText(&$doc, &$parent, $elementName, $text, $required = false)
    {
        if ($text === '' && !$required) {
            return null;
        }

        $element = $doc->createElement($elementName);
        if ($text !== '') {
            $element->appendChild($doc->createTextNode($text));
        }
        $parent->appendChild($element);
        return $element;
    }

    public function exportIssue(&$context, &$issue, $outputFile = null)
    {
        $xmlContent = $this->generateIssueXml($context, $issue);

        if ($xmlContent === false) {
            return false;
        }

        if (!empty($outputFile)) {
            if (($h = fopen($outputFile, 'wb')) === false) return false;
            fwrite($h, $xmlContent);
            fclose($h);
        } else {
            // Ensure no output has been sent before headers
            if (!headers_sent()) {
                header("Content-Type: application/xml; charset=UTF-8");
                header("Cache-Control: private");
                header("Content-Disposition: attachment; filename=\"copernicus-issue-" . $context->getLocalizedAcronym() . '-' . $issue->getYear() . '-' . $issue->getNumber() . ".xml\"");
                header("Content-Length: " . strlen($xmlContent));
            }
            echo $xmlContent;
            exit;
        }
        return true;
    }

    /**
     * Validate XML against Copernicus schema using local XSD file
     */
    public function validateXml($xmlContent)
    {
        libxml_use_internal_errors(true);

        $doc = new \DOMDocument();

        // Load XML with proper error handling
        if (!$doc->loadXML($xmlContent)) {
            return libxml_get_errors();
        }

        // Validate against local Copernicus schema
        $schemaPath = dirname(__FILE__) . '/ic-import.xsd';
        if (file_exists($schemaPath)) {
            try {
                if (!$doc->schemaValidate($schemaPath)) {
                    error_log("Copernicus Plugin: Schema validation failed");
                }
            } catch (\Exception $e) {
                // Schema validation failed, return basic XML errors
                error_log("Copernicus Plugin: Schema validation error: " . $e->getMessage());
            }
        } else {
            error_log("Copernicus Plugin: Schema file not found at: " . $schemaPath);
        }

        return libxml_get_errors();
    }

    /**
     * Execute import/export tasks using the command-line interface
     */
    public function executeCLI($scriptName, &$args)
    {
        $this->usage($scriptName);
    }

    /**
     * Display the command-line usage information
     */
    public function usage($scriptName)
    {
        echo "USAGE NOT AVAILABLE.\n"
            . "This plugin exports issues to Copernicus Citation Index XML format.\n";
    }
}
