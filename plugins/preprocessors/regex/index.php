<?php

/**
 * @file plugins/preprocessors/languagemap/index.php
 *
 * Copyright (c) 2005-2006 Alec Smecher and John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Wrapper for ToAlpha3 preprocessor plugin.
 *
 * @package plugins.preprocessors.languagemap
 *
 * $Id$
 */

require('RegexPreprocessorPlugin.inc.php');

return new RegexPreprocessorPlugin();

?>