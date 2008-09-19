<?php

/**
 * @file classes/user/UserSettingsDAO.inc.php
 *
 * Copyright (c) 2005-2008 Alec Smecher and John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class UserSettingsDAO
 * @ingroup user
 * @see User
 *
 * @brief Operations for retrieving and modifying user settings.
 */

// $Id$


class UserSettingsDAO extends DAO {
	/**
	 * Retrieve a user setting value.
	 * @param $userId int
	 * @param $name
	 * @return mixed
	 */
	function &getSetting($userId, $name) {
		$result =& $this->retrieve(
			'SELECT setting_value, setting_type FROM user_settings WHERE user_id = ? AND setting_name = ?',
			array((int) $userId, $name)
		);

		if ($result->RecordCount() != 0) {
			$row =& $result->getRowAssoc(false);
			$returner = $this->convertFromDB($row['setting_value'], $row['setting_type']);
		} else {
			$returner = null;
		}

		return $returner;
	}

	/**
	 * Retrieve all users by setting name and value.
	 * @param $name string
	 * @param $value mixed
	 * @param $type string
	 * @return DAOResultFactory matching Users
	 */
	function &getUsersBySetting($name, $value, $type = null) {
		$userDao =& DAORegistry::getDAO('UserDAO');

		$value = $this->convertToDB($value, $type);
		$result =& $this->retrieve(
			'SELECT u.* FROM users u, user_settings s WHERE u.user_id = s.user_id AND s.setting_name = ? AND s.setting_value = ?',
			array($name, $value)
		);

		$returner =& new DAOResultFactory($result, $userDao, '_returnUserFromRow');
		return $returner;
	}

	/**
	 * Add/update a user setting.
	 * @param $userId int
	 * @param $name string
	 * @param $value mixed
	 * @param $type string data type of the setting. If omitted, type will be guessed
	 */
	function updateSetting($userId, $name, $value, $type = null) {
		$result = $this->retrieve(
			'SELECT COUNT(*) FROM user_settings WHERE user_id = ? AND setting_name = ?',
			array($userId, $name)
		);

		$value = $this->convertToDB($value, $type);
		if ($result->fields[0] == 0) {
			$returner = $this->update(
				'INSERT INTO user_settings
					(user_id, setting_name, setting_value, setting_type)
					VALUES
					(?, ?, ?, ?)',
				array((int) $userId, $name, $value, $type)
			);
		} else {
			$returner = $this->update(
				'UPDATE user_settings SET
					setting_value = ?,
					setting_type = ?
					WHERE user_id = ? AND setting_name = ?',
				array($value, $type, (int) $userId, $name)
			);
		}

		$result->Close();
		unset($result);

		return $returner;
	}

	/**
	 * Delete a user setting.
	 * @param $userId int
	 * @param $name string
	 */
	function deleteSetting($userId, $name) {
		return $this->update(
			'DELETE FROM user_settings WHERE user_id = ? AND setting_name = ?',
			array((int) $userId, $name)
		);
	}

	/**
	 * Delete all settings for a user.
	 * @param $userId int
	 */
	function deleteSettings($userId) {
		return $this->update(
			'DELETE FROM user_settings WHERE user_id = ?', $userId
		);
	}
}

?>