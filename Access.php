<?php

/**
 * Access
 *
 * Класс Access отвечает за базовый доступ к контроллерам, действиям
 *
 * @author Репеха Михаил <mikerepeha@gmail.com>
 * @version 1.0
 */
class Access {

	// Значения по умолчанию для поля is_numeric
	const isNumeric = false;
	// Значение по умолчанию для поля allow
	const allow = true;
	// Значения по умолчанию для поля roles
	private static $_arrDefaultRoles = array('all');

	// Символы обозначения роли(role), где "?" - неавторизованный пользователь, "@"-авторизованный, "all" - любой пользователь.
	// Установка callback-ов в методе static::setRoleCallbacks
	private static $_arrRoles = array('?', '@', 'all', 'role>5', 'role9', 'admin', 'strongAdmin', 'buhg', 'managerOrAdmin');

	// Переменная хранит callback-и для ролей
	private static $_arrRoleCallbacks;

	/**
	 * Метод устанавливает callback-и для ролей в переменную $_arrRoleCallbacks
	 */
	private static function _setRoleCallbacks() {
		$objSession = User_Session::instance();

		static::$_arrRoleCallbacks = array(
			'?' => function() use ($objSession) {
				return !$objSession->isLoggedIn();
			},
			'@' => function() use ($objSession) {
				return $objSession->isLoggedIn();
			},
			'all' => function() {
				return true;
			},
			'role>5' => function() {
				return User_User::getUserRole() > 5;
			},
			'role9' => function() {
				return User_User::getUserRole() === 9;
			},
			'admin' => function() {
				return SimpleAdministration::isAdmin();
			},
			'strongAdmin' => function() {
				return SimpleAdministration::isStrongAdmin();
			},
			'buhg' => function() {
				return SimpleAdministration::isBuhg();
			},
			'managerOrAdmin' => function() {
				return CompanyAdministration::isManagerOrAdmin();
			}
		);
	}

	/**
	 * Метод проверяет доступ к действиям(actions, экшенам) контроллера
	 *
	 * @param array $arrRequest - массив, содержащий информацию о запросе к странице (элементы массива  - то что между слешами в URL)
	 * @param array $arrRules - массив правил для экшенов контроллера
	 *
	 * @return bool - флаг доступа
	 */
	public static function controllerRequestAccess(array $arrRequest, array $arrRules) {
		if (!Service_Function::isNotEmptyArr($arrRequest) || !Service_Function::isNotEmptyArr($arrRules)) return true;

		list($controller, $action, $actionIfNumeric) = $arrRequest;

		// Учитываем AJAX- запросы, "двигая" параметры вправо
		if ($controller == 'ajax') list(, $controller, $action, $actionIfNumeric) = $arrRequest;

		self::_setRoleCallbacks();

		foreach ($arrRules as $arrRule) {
			$isNumericActions = Service_Function::isSetValue($arrRule['is_numeric'], static::isNumeric);
			$varAction = $isNumericActions ? $actionIfNumeric : $action;

			$arrActions = Service_Function::isSetValue($arrRule['actions']);

			if (!Service_Function::isNotEmptyArr($arrActions) || !in_array($varAction, $arrActions)) continue;

			$allow = Service_Function::isSetValue($arrRule['allow'], static::allow);
			$arrRoles = isset($arrRule['roles']) && Service_Function::isNotEmptyArr($arrRule['roles']) && self::_isCorrectRoles($arrRule['roles'])
						? $arrRule['roles']
						: self::$_arrDefaultRoles;
			$matchCallback = Service_Function::isSetValue($arrRule['matchCallback']);

			if (self::_accessRole($arrRoles) && (!isset($matchCallback) || call_user_func($matchCallback, $varAction))) return $allow;
			else return !$allow;
		}
		return true;
	}

	/**
	 *  Метод проверяет соответствие ролей существующим(static::$_arrRoles)
	 *
	 * @param array $arrRoles - массив ролей, которые нужно проверить
	 *
	 * @return bool - флаг корректности ролей
	 */
	private static function _isCorrectRoles(array $arrRoles) {
		foreach ($arrRoles as $role) if (!in_array($role, self::$_arrRoles)) return false;
		return true;
	}

	/**
	 * Метод проверяет подходит ли хотя бы одна роль в данной ситуации(чекает callback-и ролей на истину)
	 *
	 * @param array $arrRoles - массив ролей на проверку. Должны быть в массиве static::$_arrRoles и иметь callback в static::$_arrRoleCallbacks
	 *
	 * @return bool - флаг принадлежность роли
	 */
	private static function _accessRole(array $arrRoles) {
		foreach ($arrRoles as $role) if (call_user_func(self::$_arrRoleCallbacks[$role])) return true;
		return false;
	}

	/**
	 * Метод проверяет доступ заказа к отправке
	 *
	 * @param Order $objOrder - заказ
	 * @param array $arrFile - файл заказа
	 * @param array $arrRule - правило
	 *
	 * @return bool - флаг доступа к отправке
	 */
	public static function orderFileSendAccess(Order $objOrder, array $arrFile, array $arrRule) {
		return
			self::_checkFileType(Service_Function::isSetValue($arrFile['file_type']), Service_Function::isSetValue($arrRule['file_type'], array())) &&
			self::_checkCompanyPay(Service_Function::isSetValue($objOrder->tableFields['company_pay']), Service_Function::isSetValue($arrRule['company_pay'], array())) &&
			self::_checkIdUser(Service_Function::isSetValue($objOrder->tableFields['ID_user']), Service_Function::isSetValue($arrRule['ID_user'], array()));
	}

	/**
	 * Метод проверяет подходит ли тип файла под правило
	 *
	 * @param int $fileType - тип файла
	 * @param array $arrRuleFileType - типы нужных файлов
	 *
	 * @return bool - флаг проверки типа файла
	 */
	private static function _checkFileType($fileType, array $arrRuleFileType) {
		return !Service_Function::isNotEmptyArr($arrRuleFileType) || in_array($fileType, $arrRuleFileType);
	}

	/**
	 * Метод проверяет подходит ли company_pay под правило
	 *
	 * @param int $companyPay
	 * @param array $arrRuleCompanyPay
	 *
	 * @return bool
	 */
	private static function _checkCompanyPay($companyPay, array $arrRuleCompanyPay) {
		return !Service_Function::isNotEmptyArr($arrRuleCompanyPay) || in_array($companyPay, $arrRuleCompanyPay);
	}

	/**
	 * Метод проверяет подходит ли ID_user под правило
	 *
	 * @param $idUser
	 * @param array $arrRuleIdUser
	 *
	 * @return bool
	 */
	private static function _checkIdUser($idUser, array $arrRuleIdUser) {
		return !Service_Function::isNotEmptyArr($arrRuleIdUser) || in_array($idUser, $arrRuleIdUser);
	}

	/**
	 * Метод проверяет доступ к отправке оповещений по типу длл конкретного пользователя
	 *
	 * @param $userId - id пользователя
	 * @param $alertType - тип оповещения
	 *
	 * @return bool - флаг доступа
	 */
	public static function alertPrivacyAccess($userId, $alertType) {
		// Настройки приватности пользователя
		$userPrivacy = AlertPrivacy::getDataByUserId($userId);
		// Если настройки не найдены
		if ($userPrivacy === false) return true;
		// Если в настройках нет типов разрешенных оповещений
		if (is_string($userPrivacy) && empty($userPrivacy)) return false;

		$arrUserPrivacy = explode(', ', $userPrivacy);
		// Id настройки приватности
		$privacyTypeId = AlertPrivacy::getPrivacyTypeByAlertType($alertType);
		// Если настройка для типа оповещения найдена
		if (isset($privacyTypeId)) return in_array($privacyTypeId, $arrUserPrivacy);

		return true;
	}
}