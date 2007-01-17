<?php

/**
 * @file PluginSettingsDAO.inc.php
 *
 * Copyright (c) 2005-2007 Alec Smecher and John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @package plugins
 * @class PluginSettingsDAO
 *
 * Class for Plugin Settings DAO.
 * Operations for retrieving and modifying plugin settings.
 *
 * $Id$
 */

class PluginSettingsDAO extends DAO {

	/**
	 * Constructor.
	 */
	function PluginSettingsDAO() {
		parent::DAO();
	}

	function &_getCache($pluginName) {
		static $settingCache;
		if (!isset($settingCache)) {
			$settingCache = array();
		}
		if (!isset($settingCache[$pluginName])) {
			import('cache.CacheManager');
			$cacheManager =& CacheManager::getManager();
			$settingCache[$pluginName] =& $cacheManager->getCache(
				'pluginSettings', $pluginName,
				array($this, '_cacheMiss')
			);
		}
		return $settingCache[$pluginName];
	}

	/**
	 * Retrieve a plugin setting value.
	 * @param $pluginName string
	 * @param $name
	 * @return mixed
	 */
	function getSetting($pluginName, $name) {
		$cache =& $this->_getCache($pluginName);
		return $cache->get($name);
	}

	function _cacheMiss(&$cache, $id) {
		$settings =& $this->getPluginSettings($cache->getCacheId());
		if (!isset($settings[$id])) {
			// Make sure that even null values are cached
			$cache->setCache($id, null);
			return null;
		}
		return $settings[$id];
	}

	/**
	 * Retrieve and cache all settings for a plugin.
	 * @param $pluginName string
	 * @return array
	 */
	function &getPluginSettings($pluginName) {
		$pluginSettings[$pluginName] = array();
		
		$result = &$this->retrieve(
			'SELECT setting_name, setting_value, setting_type FROM plugin_settings WHERE plugin_name = ?', $pluginName
		);
		
		if ($result->RecordCount() == 0) {
			$returner = null;
			return $returner;
			
		} else {
			while (!$result->EOF) {
				$row = &$result->getRowAssoc(false);
				switch ($row['setting_type']) {
					case 'bool':
						$value = (bool) $row['setting_value'];
						break;
					case 'int':
						$value = (int) $row['setting_value'];
						break;
					case 'float':
						$value = (float) $row['setting_value'];
						break;
					case 'object':
						$value = unserialize($row['setting_value']);
						break;
					case 'string':
					default:
						$value = $row['setting_value'];
						break;
				}
				$pluginSettings[$pluginName][$row['setting_name']] = $value;
				$result->MoveNext();
			}
			$result->close();
			unset($result);

			$cache =& $this->_getCache($pluginName);
			$cache->setEntireCache($pluginSettings[$pluginName]);

			return $pluginSettings[$pluginName];
		}
	}
	
	/**
	 * Add/update a plugin setting.
	 * @param $pluginName string
	 * @param $name string
	 * @param $value mixed
	 * @param $type string data type of the setting. If omitted, type will be guessed
	 */
	function updateSetting($pluginName, $name, $value, $type = null) {
		$cache =& $this->_getCache($pluginName);
		$cache->setCache($name, $value);
		
		if ($type == null) {
			switch (gettype($value)) {
				case 'boolean':
				case 'bool':
					$type = 'bool';
					break;
				case 'integer':
				case 'int':
					$type = 'int';
					break;
				case 'double':
				case 'float':
					$type = 'float';
					break;
				case 'array':
				case 'object':
					$type = 'object';
					break;
				case 'string':
				default:
					$type = 'string';
					break;
			}
		}
		
		if ($type == 'object') {
			$value = serialize($value);
			
		} else if ($type == 'bool') {
			$value = isset($value) && $value ? 1 : 0;
		}
		
		$result = $this->retrieve(
			'SELECT COUNT(*) FROM plugin_settings WHERE plugin_name = ? AND setting_name = ?',
			array($pluginName, $name)
		);
		
		if ($result->fields[0] == 0) {
			$returner = $this->update(
				'INSERT INTO plugin_settings
					(plugin_name, setting_name, setting_value, setting_type)
					VALUES
					(?, ?, ?, ?)',
				array($pluginName, $name, $value, $type)
			);
		} else {
			$returner = $this->update(
				'UPDATE plugin_settings SET
					setting_value = ?,
					setting_type = ?
					WHERE plugin_name = ? AND setting_name = ?',
				array($value, $type, $pluginName, $name)
			);
		}

		$result->Close();
		unset($result);

		return $returner;
	}
	
	/**
	 * Delete a plugin setting.
	 * @param $pluginName int
	 * @param $name string
	 */
	function deleteSetting($pluginName, $name) {
		$cache =& $this->_getCache($pluginName);
		$cache->setCache($name, null);
		
		return $this->update(
			'DELETE FROM plugin_settings WHERE plugin_name = ? AND setting_name = ?',
			array($pluginName, $name)
		);
	}
	
	/**
	 * Delete all settings for a plugin.
	 * @param $pluginName string
	 */
	function deleteSettingsByPlugin($pluginName) {
		$cache =& $this->_getCache($pluginName);
		$cache->flush();
		
		return $this->update(
				'DELETE FROM plugin_settings WHERE plugin_name = ?', $pluginName
		);
	}

	/**
	 * Used internally by installSettings to perform variable and translation replacements.
	 * @param $rawInput string contains text including variable and/or translate replacements.
	 * @param $paramArray array contains variables for replacement
	 * @returns string
	 */
	function _performReplacement($rawInput, $paramArray = array()) {
		$value = preg_replace_callback('{{translate key="([^"]+)"}}', '_installer_plugin_regexp_callback', $rawInput);
		foreach ($paramArray as $pKey => $pValue) {
			$value = str_replace('{$' . $pKey . '}', $pValue, $value);
		}
		return $value;
	}

	/**
	 * Used internally by installSettings to recursively build nested arrays.
	 * Deals with translation and variable replacement calls.
	 * @param $node object XMLNode <array> tag
	 * @param $paramArray array Parameters to be replaced in key/value contents
	 */
	function &_buildObject (&$node, $paramArray = array()) {
		$value = array();
		foreach ($node->getChildren() as $element) {
			$key = $element->getAttribute('key');
			$childArray = &$element->getChildByName('array');
			if (isset($childArray)) {
				$content = $this->_buildObject($childArray, $paramArray);
			} else {
				$content = $this->_performReplacement($element->getValue(), $paramArray);
			}
			if (!empty($key)) {
				$key = $this->_performReplacement($key, $paramArray);
				$value[$key] = $content;
			} else $value[] = $content;
		}
		return $value;
	}

	/**
	 * Install plugin settings from an XML file.
	 * @param $pluginName name of plugin for settings to apply to
	 * @param $filename string Name of XML file to parse and install
	 * @param $paramArray array Optional parameters for variable replacement in settings
	 */
	function installSettings($pluginName, $filename, $paramArray = array()) {
		$xmlParser = &new XMLParser();
		$tree = $xmlParser->parse($filename);

		if (!$tree) {
			$xmlParser->destroy();
			return false;
		}

		foreach ($tree->getChildren() as $setting) {
			$nameNode = &$setting->getChildByName('name');
			$valueNode = &$setting->getChildByName('value');

			if (isset($nameNode) && isset($valueNode)) {
				$type = $setting->getAttribute('type');
				$name = &$nameNode->getValue();

				if ($type == 'object') {
					$arrayNode = &$valueNode->getChildByName('array');
					$value = $this->_buildObject($arrayNode, $paramArray);
				} else {
					$value = $this->_performReplacement($valueNode->getValue(), $paramArray);
				}

				// Replace translate calls with translated content
				$this->updateSetting($pluginName, $name, $value, $type);
			}
		}

		$xmlParser->destroy();

	}
}

/**
 * Used internally by plugin setting installation code to perform translation function.
 */
function _installer_plugin_regexp_callback($matches) {
	return Locale::translate($matches[1]);
}

?>
