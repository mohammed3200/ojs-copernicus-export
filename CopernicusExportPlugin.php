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
use PKP\xml\XMLCustomWriter;
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
        error_log("Copernicus Plugin: context ID = " . $context->getId());
        
        if ($op === 'exportIssue') {
            // Handle export - download XML file
            $issueId = (int)$request->getUserVar('issueId');
            
            error_log("Copernicus Plugin: Export requested for issue ID: $issueId");
            
            if (empty($issueId)) {
                error_log('Copernicus Plugin: No issue ID provided for export');
                $this->showError($request, 'No issue ID provided');
                return;
            }
            
            // Get issue WITH journal context filter
            $issue = Repo::issue()->get($issueId, $context->getId());
            if (!$issue) {
                error_log("Copernicus Plugin: Issue not found with ID: $issueId in journal: " . $context->getId());
                $this->showError($request, 'Issue not found in this journal');
                return;
            }
            
            // Additional safety check
            if ($issue->getJournalId() != $context->getId()) {
                error_log("Copernicus Plugin: Security violation - Issue $issueId journal mismatch. Expected: " . $context->getId() . ", Got: " . $issue->getJournalId());
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
            
            // Get issue WITH journal context filter
            $issue = Repo::issue()->get($issueId, $context->getId());
            if (!$issue) {
                error_log("Copernicus Plugin: Issue not found with ID: $issueId in journal: " . $context->getId());
                $this->showError($request, 'Issue not found in this journal');
                return;
            }
            
            // Additional safety check
            if ($issue->getJournalId() != $context->getId()) {
                error_log("Copernicus Plugin: Security violation - Issue $issueId journal mismatch. Expected: " . $context->getId() . ", Got: " . $issue->getJournalId());
                $this->showError($request, 'Issue does not belong to this journal');
                return;
            }
            
            // Generate XML for validation
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
            error_log("Copernicus Plugin: Displaying issues list for journal: " . $context->getId());
            $this->showIssuesList($request, $context);
        }
    }

    /**
     * Show the list of issues
     */
    private function showIssuesList($request, $context)
    {
        $issues = Repo::issue()
            ->getCollector()
            ->filterByContextIds([$context->getId()])
            ->filterByPublished(true)
            ->getMany();

        error_log("Copernicus Plugin: Found " . count($issues) . " issues for journal: " . $context->getId());

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
            $doc = XMLCustomWriter::createDocument();
            $issueNode = $this->generateIssueDom($doc, $context, $issue);
            XMLCustomWriter::appendChild($doc, $issueNode);
            $xmlContent = XMLCustomWriter::getXML($doc);
            return $this->formatXml($xmlContent);
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

        $root = XMLCustomWriter::createElement($doc, 'ici-import');
        XMLCustomWriter::setAttribute($root, "xmlns:xsi", "http://www.w3.org/2001/XMLSchema-instance");
        XMLCustomWriter::setAttribute($root, "xsi:noNamespaceSchemaLocation", "https://journals.indexcopernicus.com/ic-import.xsd");

        $journal_elem = XMLCustomWriter::createChildWithText($doc, $root, 'journal', '', true);
        XMLCustomWriter::setAttribute($journal_elem, 'issn', $issn);

        $issue_elem = XMLCustomWriter::createChildWithText($doc, $root, 'issue', '', true);

        $pub_issue_date = $issue->getDatePublished() ? str_replace(' ', "T", $issue->getDatePublished()) . 'Z' : '';

        XMLCustomWriter::setAttribute($issue_elem, 'number', $issue->getNumber());
        XMLCustomWriter::setAttribute($issue_elem, 'volume', $issue->getVolume());
        XMLCustomWriter::setAttribute($issue_elem, 'year', $issue->getYear());
        XMLCustomWriter::setAttribute($issue_elem, 'publicationDate', $pub_issue_date, false);

        $num_articles = 0;

        // Get submissions for this issue using Repo pattern
        $submissions = Repo::submission()
            ->getCollector()
            ->filterByContextIds([$context->getId()])
            ->filterByIssueIds([$issue->getId()])
            ->getMany();

        foreach ($submissions as $submission) {
            $publication = $submission->getCurrentPublication();
            if (!$publication) continue;

            $locales = $publication->getData('languages') ?: [$context->getPrimaryLocale()];
            $article_elem = XMLCustomWriter::createChildWithText($doc, $issue_elem, 'article', '', true);
            XMLCustomWriter::createChildWithText($doc, $article_elem, 'type', 'ORIGINAL_ARTICLE');

            foreach ($locales as $loc) {
                $lc = explode('_', $loc);
                $lang_version = XMLCustomWriter::createChildWithText($doc, $article_elem, 'languageVersion', '', true);
                XMLCustomWriter::setAttribute($lang_version, 'language', $lc[0]);
                XMLCustomWriter::createChildWithText($doc, $lang_version, 'title', $publication->getLocalizedTitle($loc), true);
                XMLCustomWriter::createChildWithText($doc, $lang_version, 'abstract', strip_tags($publication->getLocalizedData('abstract', $loc)), true);

                // PDF URL
                $galleys = $publication->getData('galleys');
                if ($galleys && count($galleys) > 0) {
                    $galley = $galleys[0];
                    $request = Application::get()->getRequest();
                    $url = $request->url(null, 'article', 'view', [
                        $submission->getBestId(),
                        $galley->getBestGalleyId()
                    ]);
                    XMLCustomWriter::createChildWithText($doc, $lang_version, 'pdfFileUrl', $url, true);
                }

                $publicationDate = $publication->getData('datePublished') ?
                    str_replace(' ', "T", $publication->getData('datePublished')) . 'Z' : '';
                XMLCustomWriter::createChildWithText($doc, $lang_version, 'publicationDate', $publicationDate, false);

                XMLCustomWriter::createChildWithText($doc, $lang_version, 'pageFrom', $publication->getData('pages'), true);
                XMLCustomWriter::createChildWithText($doc, $lang_version, 'pageTo', '', true);
                XMLCustomWriter::createChildWithText($doc, $lang_version, 'doi', $publication->getDoi(), true);

                $keywords = XMLCustomWriter::createChildWithText($doc, $lang_version, 'keywords', '', true);
                $keywordArray = $publication->getLocalizedData('keywords', $loc);

                if ($keywordArray && is_array($keywordArray)) {
                    foreach ($keywordArray as $keyword) {
                        XMLCustomWriter::createChildWithText($doc, $keywords, 'keyword', $keyword, true);
                    }
                } else {
                    XMLCustomWriter::createChildWithText($doc, $keywords, 'keyword', " ", true);
                }
            }

            // Authors
            $authors_elem = XMLCustomWriter::createChildWithText($doc, $article_elem, 'authors', '', true);
            $index = 1;

            $authors = $publication->getData('authors');
            if ($authors) {
                foreach ($authors as $author) {
                    $author_elem = XMLCustomWriter::createChildWithText($doc, $authors_elem, 'author', '', true);

                    $givenName = $author->getLocalizedGivenName() ?: $author->getGivenName();
                    $familyName = $author->getLocalizedFamilyName() ?: $author->getFamilyName();
                    $middleName = $author->getData('middleName') ?: '';

                    XMLCustomWriter::createChildWithText($doc, $author_elem, 'name', $givenName, true);
                    XMLCustomWriter::createChildWithText($doc, $author_elem, 'name2', $middleName, false);
                    XMLCustomWriter::createChildWithText($doc, $author_elem, 'surname', $familyName, true);
                    XMLCustomWriter::createChildWithText($doc, $author_elem, 'email', $author->getEmail(), false);
                    XMLCustomWriter::createChildWithText($doc, $author_elem, 'order', $index, true);
                    XMLCustomWriter::createChildWithText(
                        $doc,
                        $author_elem,
                        'instituteAffiliation',
                        substr($author->getLocalizedAffiliation() ?: '', 0, 250),
                        false
                    );
                    XMLCustomWriter::createChildWithText($doc, $author_elem, 'role', 'AUTHOR', true);
                    XMLCustomWriter::createChildWithText($doc, $author_elem, 'ORCID', $author->getOrcid(), false);

                    $index++;
                }
            }

            // References
            $citation_text = $publication->getData('citationsRaw');
            if ($citation_text) {
                $citation_arr = explode("\n", $citation_text);
                $references_elem = XMLCustomWriter::createChildWithText($doc, $article_elem, 'references', '', true);
                $index = 1;
                foreach ($citation_arr as $citation) {
                    if (trim($citation) != "") {
                        $reference_elem = XMLCustomWriter::createChildWithText($doc, $references_elem, 'reference', '', true);
                        XMLCustomWriter::createChildWithText($doc, $reference_elem, 'unparsedContent', $citation, true);
                        XMLCustomWriter::createChildWithText($doc, $reference_elem, 'order', $index, true);
                        XMLCustomWriter::createChildWithText($doc, $reference_elem, 'doi', '', true);
                        $index++;
                    }
                }
            }
            $num_articles++;
        }

        XMLCustomWriter::setAttribute($issue_elem, 'numberOfArticles', $num_articles, false);
        return $root;
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
     * Validate XML against Copernicus schema
     */
    public function validateXml($xmlContent)
    {
        libxml_use_internal_errors(true);

        $doc = new \DOMDocument();

        // Load XML with proper error handling
        if (!$doc->loadXML($xmlContent)) {
            return libxml_get_errors();
        }

        // Validate against Copernicus schema
        $schemaPath = dirname(__FILE__) . '/ic-import.xsd';
        if (file_exists($schemaPath)) {
            try {
                $doc->schemaValidate($schemaPath);
            } catch (\Exception $e) {
                // Schema validation failed, return basic XML errors
                error_log("Copernicus Plugin: Schema validation error: " . $e->getMessage());
            }
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