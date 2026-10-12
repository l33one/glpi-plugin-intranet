<?php

/**
 * -------------------------------------------------------------------------
 * GLPI Intranet Plugin
 * -------------------------------------------------------------------------
 *
 * Saves the intranet right of a profile (posted from the "Intranet" tab of
 * the Profile form).
 *
 * @package   Plugin Intranet
 * @author    Leewan Meneses
 * @license   GPL-2.0+
 * @since     1.2.0
 *
 * -------------------------------------------------------------------------
 */

include ('../../../inc/includes.php');

Session::checkLoginUser();

if (!Session::haveRight('profile', UPDATE)) {
   Html::displayRightError();
}

$profiles_id = (int)($_POST['profiles_id'] ?? 0);
$right = (int)($_POST['intranet_right'] ?? -1);

$profile = new Profile();
if ($profiles_id <= 0 || !$profile->getFromDB($profiles_id)) {
   Session::addMessageAfterRedirect(__('Profile not found.', 'intranet'), false, ERROR);
   Html::back();
}

if (!PluginIntranetProfile::setProfileRight($profiles_id, $right)) {
   Session::addMessageAfterRedirect(__('Could not save the intranet rights.', 'intranet'), false, ERROR);
} else {
   Session::addMessageAfterRedirect(__('Intranet rights saved successfully.', 'intranet'), false, INFO);
}

Html::back();
