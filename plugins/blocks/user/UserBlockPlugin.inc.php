<?php

/**
 * @file plugins/blocks/user/UserBlockPlugin.inc.php
 *
 * Copyright (c) 2005-2012 Alec Smecher and John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class UserBlockPlugin
 * @ingroup plugins_blocks_user
 *
 * @brief Class for user block plugin
 */



import('lib.pkp.classes.plugins.BlockPlugin');

class UserBlockPlugin extends BlockPlugin {
	function register($category, $path) {
		$success = parent::register($category, $path);
		if ($success) {
			AppLocale::requireComponents(array(LOCALE_COMPONENT_PKP_USER));
		}
		return $success;
	}

	/**
	 * Install default settings on system install.
	 * @return string
	 */
	function getInstallSitePluginSettingsFile() {
		return $this->getPluginPath() . '/settings.xml';
	}

	/**
	 * Get the display name of this plugin.
	 * @return String
	 */
	function getDisplayName() {
		return __('plugins.block.user.displayName');
	}

	/**
	 * Get a description of the plugin.
	 */
	function getDescription() {
		return __('plugins.block.user.description');
	}

	/**
	 * Get the HTML contents for this block.
	 * @param $templateMgr PKPTemplateManager
	 * @param $request PKPRequest
	 * @return String
	 */
	function getContents(&$templateMgr, $request = null) {
		if (!defined('SESSION_DISABLE_INIT')) {
			$session =& $request->getSession();
			$templateMgr->assign_by_ref('userSession', $session);
			$templateMgr->assign('loggedInUsername', $session->getSessionVar('username'));
		}
		return parent::getContents($templateMgr, $request);
	}
}

?>
