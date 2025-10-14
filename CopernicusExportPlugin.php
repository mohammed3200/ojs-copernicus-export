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
     */
    public function getName()
    {
        return 'CopernicusExportPlugin';
    }

    public function getDisplayName()
    {
        return __('plugins.importexport.copernicus.displayName');
    }

    public function getDescription()
    {
        return __('plugins.importexport.copernicus.description');
    }

    public function formatDate($date)
    {
        if ($date == '') return null;
        return date('Y-m-d', strtotime($date));
    }

    public function formatXml($xmlContent)
    {
        if (is_string($xmlContent)) {
            // If it's already a string, just format it
            $dom = new \DOMDocument('1.0');
            $dom->preserveWhiteSpace = false;
            $dom->formatOutput = true;
            $dom->loadXML($xmlContent);
            return $dom->saveXML();
        } else {
            // Handle DOMDocument objects properly
            $xmlContent->preserveWhiteSpace = false;
            $xmlContent->formatOutput = true;
            return $xmlContent->saveXML();
        }
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
        $doc = XMLCustomWriter::createDocument();
        $issueNode = $this->generateIssueDom($doc, $context, $issue);
        XMLCustomWriter::appendChild($doc, $issueNode);

        $xmlContent = XMLCustomWriter::getXML($doc);
        $formattedXml = $this->formatXml($xmlContent);

        if (!empty($outputFile)) {
            if (($h = fopen($outputFile, 'wb')) === false) return false;
            fwrite($h, $formattedXml);
            fclose($h);
        } else {
            // Ensure no output has been sent before headers
            if (!headers_sent()) {
                header("Content-Type: application/xml; charset=UTF-8");
                header("Cache-Control: private");
                header("Content-Disposition: attachment; filename=\"copernicus-issue-" . $context->getLocalizedAcronym() . '-' . $issue->getYear() . '-' . $issue->getNumber() . ".xml\"");
                header("Content-Length: " . strlen($formattedXml));
            }
            echo $formattedXml;
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
                $isValid = $doc->schemaValidate($schemaPath);
            } catch (Exception $e) {
                // Schema validation failed, return basic XML errors
                return libxml_get_errors();
            }
        } else {
            // Fallback: basic XML validation
            $isValid = $doc->validate();
        }

        return libxml_get_errors();
    }

    /**
     * Handle plugin requests via callback
     * This is called by manage() when handling import/export plugin requests
     */
    public function display($args, $request)
    {
        parent::display($args, $request);
        $context = $request->getContext();

        $op = array_shift($args);

        switch ($op) {
            case 'exportIssue':
                // Handle export - download XML file
                $issueId = (int)($request->getUserVar('issueId')
                    ?: $request->getUserVar('id')
                    ?: array_shift($args));

                if (empty($issueId)) {
                    throw new \Exception('No issue ID provided');
                }

                $issue = Repo::issue()->get($issueId);
                if (!$issue) {
                    throw new \Exception('Issue not found');
                }

                if ($issue->getData('contextId') != $context->getId()) {
                    throw new \Exception('Issue does not belong to this journal');
                }

                $this->exportIssue($context, $issue);
                exit;
                break;

            case 'validateIssue':
                // Handle validation - show validation results page
                $issueId = (int)($request->getUserVar('issueId')
                    ?: $request->getUserVar('id')
                    ?: array_shift($args));

                if (empty($issueId)) {
                    throw new \Exception('No issue ID provided');
                }

                $issue = Repo::issue()->get($issueId);
                if (!$issue) {
                    throw new \Exception('Issue not found');
                }

                if ($issue->getData('contextId') != $context->getId()) {
                    throw new \Exception('Issue does not belong to this journal');
                }

                // Generate XML for validation - FIXED APPROACH
                $doc = XMLCustomWriter::createDocument();
                $issueNode = $this->generateIssueDom($doc, $context, $issue);
                XMLCustomWriter::appendChild($doc, $issueNode);

                // Get XML as string and format it properly
                $xmlContent = XMLCustomWriter::getXML($doc);
                $formattedXml = $this->formatXml($xmlContent);

                // Validate XML and prepare results
                $xml_errors = $this->validateXml($formattedXml);
                $xml_lines = explode("\n", $formattedXml);

                // Display validation template
                $templateMgr = TemplateManager::getManager($request);
                $templateMgr->assign('xml_errors', $xml_errors);
                $templateMgr->assign('xml_lines', $xml_lines);
                $templateMgr->display($this->getTemplateResource('validate.tpl'));
                break;
            default:
                // Display list of issues for export
                $issues = Repo::issue()
                    ->getCollector()
                    ->filterByContextIds([$context->getId()])
                    ->filterByPublished(true)
                    ->getMany();

                $templateMgr = TemplateManager::getManager($request);
                $templateMgr->assign('issues', $issues);
                $templateMgr->display($this->getTemplateResource('issues.tpl'));
        }
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
