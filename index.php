<?php

/**
 * @defgroup plugins_importexport_copernicus
 */
 
/**
 * @file plugins/importexport/copernicus/index.php
 *
 * Copyright (c) 2013-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @ingroup plugins_importexport_copernicus
 * @brief Wrapper for Copernicus import/export plugin.
 *
 */

require_once('CopernicusExportPlugin.php');

return new APP\plugins\importexport\copernicus\CopernicusExportPlugin();
