<?php

/**
 * @file ArchiveForm.inc.php
 *
 * Copyright (c) 2005-2007 Alec Smecher and John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @package admin.form
 * @class ArchiveForm
 *
 * Form for site administrator to edit archive settings.
 *
 * $Id$
 */

import('db.DBDataXMLParser');
import('form.Form');

class ArchiveForm extends Form {

	/** The ID of the archive being edited */
	var $archiveId;

	/** Whether or not Captcha tests are enabled */
	var $captchaEnabled;

	/** The archive object */
	var $archive;

	/** The name of the harvester being used for this archive. */
	var $harvesterPluginName;

	/**
	 * Constructor.
	 * @param $archiveId omit for a new archive
	 */
	function ArchiveForm($archiveId = null) {
		parent::Form('admin/archiveForm.tpl');

		$this->archiveId = isset($archiveId) ? (int) $archiveId : null;

		// Validation checks for this form
		$this->addCheck(new FormValidator($this, 'title', 'required', 'admin.archives.form.titleRequired'));
		$this->addCheck(new FormValidator($this, 'url', 'required', 'admin.archives.form.urlRequired'));
		$this->addCheck(new FormValidatorPost($this));

		import('captcha.CaptchaManager');
		$captchaManager =& new CaptchaManager();
		$this->captchaEnabled = $captchaManager->isEnabled();
		if ($this->captchaEnabled && !Validation::isLoggedIn()) {
			$this->addCheck(new FormValidatorCaptcha($this, 'captcha', 'captchaId', 'common.captchaField.badCaptcha'));
		}

		$this->harvesterPluginName = Request::getUserVar('harvesterPluginName');

		if ($archiveId) {
			$archiveDao =& DAORegistry::getDAO('ArchiveDAO');
			$this->archive =& $archiveDao->getArchive($this->archiveId, false);
			if (empty($this->harvesterPluginName) && $this->archive) $this->harvesterPluginName = $this->archive->getHarvesterPluginName();
		}

		if (empty($this->harvesterPluginName)) {
			$site =& Request::getSite();
			$this->harvesterPluginName = $site->getSetting('defaultHarvesterPlugin');
		}

		$harvesters =& PluginRegistry::loadCategory('harvesters');

		HookRegistry::call('ArchiveForm::ArchiveForm', array(&$this, $this->harvesterPluginName));
	}

	/**
	 * Display the form.
	 */
	function display() {
		$templateMgr = &TemplateManager::getManager();
		$templateMgr->assign('archiveId', $this->archiveId);
		if ($this->captchaEnabled && !Validation::isLoggedIn()) {
			import('captcha.CaptchaManager');
			$captchaManager =& new CaptchaManager();
			$captcha =& $captchaManager->createCaptcha();
			if ($captcha) {
				$templateMgr->assign('captchaEnabled', $this->captchaEnabled);
				$this->setData('captchaId', $captcha->getCaptchaId());
			}
		}
		$templateMgr->assign_by_ref('harvesters', PluginRegistry::getPlugins('harvesters'));
		HookRegistry::call('ArchiveForm::display', array(&$this, &$templateMgr, $this->harvesterPluginName));
		parent::display();
	}

	/**
	 * Initialize form data from current settings.
	 */
	function initData() {
		if (isset($this->archive)) {
			$this->_data = array(
				'title' => $this->archive->getTitle(),
				'publicArchiveId' => $this->archive->getPublicArchiveId(),
				'description' => $this->archive->getDescription(),
				'url' => $this->archive->getUrl(),
				'harvesterPluginName' => $this->harvesterPluginName,
				'archive' => $this->archive,
				'enabled' => $this->archive->getEnabled()
			);
		} else {
			$this->archiveId = null;
			$this->_data = array(
				'harvesterPluginName' => $this->harvesterPluginName,
				'enabled' => true
			);
		}

		HookRegistry::call('ArchiveForm::initData', array(&$this, &$this->archive, $this->harvesterPluginName));

		// Allow user-submitted parameters to override the 
		// usual form values. This is useful for when users
		// change the harvester plugin so that they don't have
		// to re-key changes to form elements.
		if (!empty($this->harvesterPluginName)) {
			$parameterNames = $this->getParameterNames();
			foreach ($parameterNames as $name) {
				$value = Request::getUserVar($name);
				if (!empty($value)) {
					$this->setData($name, $value);
				}
			}
		}
	}

	function getParameterNames() {
		$parameterNames = array('title', 'description', 'url', 'enabled');

		if ($this->captchaEnabled && !Validation::isLoggedIn()) {
			$parameterNames[] = 'captchaId';
			$parameterNames[] = 'captcha';
		}

		if (Validation::isLoggedIn()) $parameterNames[] = 'publicArchiveId';
		HookRegistry::call('ArchiveForm::getParameterNames', array(&$this, &$parameterNames, $this->harvesterPluginName));
		return $parameterNames;
	}

	/**
	 * Assign form data to user-submitted data.
	 */
	function readInputData() {
		$this->readUserVars($this->getParameterNames());
	}

	function validate() {
		if (Validation::isLoggedIn()) {
			// Check to ensure that the public ID, if specified, is unique
			$publicArchiveId = $this->getData('publicArchiveId');
			$archiveDao =& DAORegistry::getDAO('ArchiveDAO');
			if ($publicArchiveId != '' && $archiveDao->archiveExistsByPublicArchiveId($publicArchiveId, $this->archiveId)) {
				$this->addError('publicArchiveId', 'admin.archives.form.publicArchiveIdExists');
				$this->addErrorField('publicArchiveId');
			}
		}
		return parent::validate();
	}

	/**
	 * Save archive settings.
	 */
	function execute() {
		$archiveDao = &DAORegistry::getDAO('ArchiveDAO');

		if (!isset($this->archive)) {
			$this->archive = &new Archive();
		}

		$this->harvesterPluginName = Request::getUserVar('harvesterPluginName');
		$this->archive->setHarvesterPluginName($this->harvesterPluginName);
		$this->archive->setDescription($this->getData('description'));
		$this->archive->setUrl($this->getData('url'));
		$this->archive->setTitle($this->getData('title'));
		if (Validation::isLoggedIn()) {
			$this->archive->setPublicArchiveId($this->getData('publicArchiveId'));
			$this->archive->setEnabled($this->getData('enabled'));
		} else {
			$this->archive->setEnabled(true);
		}

		if ($this->archive->getArchiveId() != null) {
			$archiveDao->updateArchive($this->archive);
		} else {
			$archiveId = $archiveDao->insertArchive($this->archive);

			// Include the current default set of reading tools.
			import('rt.harvester2.HarvesterRTAdmin');
			$rtAdmin =& new HarvesterRTADmin($archiveId);
			$rtAdmin->restoreVersions(false);
		}

		HookRegistry::call('ArchiveForm::execute', array(&$this, &$this->archive, $this->harvesterPluginName));

		if (!Validation::isLoggedIn()) {
			// Send an email notifying the administrator of the new archive.
			import('mail.MailTemplate');
			$email =& new MailTemplate('NEW_ARCHIVE_NOTIFY');
			if ($email->isEnabled()) {
				$site =& Request::getSite();
				$email->assignParams(array(
					'archiveTitle' => $this->getData('title'),
					'siteTitle' => $site->getTitle(),
					'loginUrl' => Request::url('admin', 'manage', $this->archive->getArchiveId())
				));
				$email->addRecipient($site->getContactEmail(), $site->getContactName());
				$email->send();
			}
		}
	}

}

?>
