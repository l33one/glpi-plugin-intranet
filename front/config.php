<?php

/**
 * -------------------------------------------------------------------------
 * GLPI Intranet Plugin
 * -------------------------------------------------------------------------
 *
 * @package   Plugin Intranet
 * @author    Leewan Meneses
 * @license   GPL-2.0+
 * @since     2026
 *
 * -------------------------------------------------------------------------
 */

include('../../../inc/includes.php');

Session::checkLoginUser();

// The settings page mixes two audiences: the "General" tab needs the GLPI
// configuration right (super-admin), while the content tabs need the plugin
// UPDATE right, so editors can publish without being super-admins.
$can_config = Session::haveRight('config', UPDATE);
$can_manage = PluginIntranetPortal::canManage();
if (!$can_config && !$can_manage) {
   Html::displayRightError();
}

Html::header(
   __('Intranet Configuration', 'intranet'),
   $_SERVER['PHP_SELF']
);

$config = new PluginIntranetConfig();
$config->display(['can_config' => $can_config, 'can_manage' => $can_manage]);

Html::footer();
