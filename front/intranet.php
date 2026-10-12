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

include ('../../../inc/includes.php');

// Ensure user is logged in
Session::checkLoginUser();

// Check rights to view the intranet portal
if (!PluginIntranetPortal::canView()) {
   Html::displayRightError();
}

// Render Page Header (Plugins > Intranet menu entry)
Html::header(
   __('Intranet Portal', 'intranet'),
   $_SERVER['PHP_SELF'],
   'plugins',
   'plugintranetmenu'
);

// This page only renders the portal. The announcement reader endpoint lives
// in ajax/announcement.php, so the markup and the data contract are separate.
PluginIntranetPortal::showPortal();

Html::footer();
