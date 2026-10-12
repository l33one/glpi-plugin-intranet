<?php

/**
 * -------------------------------------------------------------------------
 * GLPI Intranet Plugin
 * -------------------------------------------------------------------------
 *
 * Central tab ("Intranet") on the main screen. Replaces the former dashboard
 * injection: the intranet now lives in its own tab instead of inside the
 * dashboard tab.
 *
 * @package   Plugin Intranet
 * @author    Leewan Meneses
 * @license   GPL-2.0+
 * @since     1.1.0
 *
 * -------------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access this file directly");
}

class PluginIntranetCentral extends CommonGLPI {

   public static function getTypeName($nb = 0) {
      return __('Intranet', 'intranet');
   }

   public static function getIcon() {
      return 'ti ti-home';
   }

   public static function canView(): bool {
      return PluginIntranetPortal::canView();
   }

   /**
    * Tab label on Central. Returning an empty string hides the tab (no right
    * or panel disabled in the settings).
    *
    * @param CommonGLPI $item
    * @param integer    $withtemplate
    * @return string
    */
   public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {
      if (!($item instanceof Central)) {
         return '';
      }
      if (!PluginIntranetPortal::canView()) {
         return '';
      }
      if (!PluginIntranetConfig::isPanelEnabled()) {
         return '';
      }
      return self::createTabEntry(__('Intranet', 'intranet'), 0, null, self::getIcon());
   }

   /**
    * Tab content on Central: the intranet panel (greeting + profile, ticket
    * cards, notice board and quick links).
    *
    * @param CommonGLPI $item
    * @param integer    $tabnum
    * @param integer    $withtemplate
    * @return boolean
    */
   public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {
      if (!($item instanceof Central)) {
         return false;
      }
      if (!PluginIntranetPortal::canView()) {
         return false;
      }
      PluginIntranetPortal::showPanel(PluginIntranetConfig::getMaxAnnouncements());
      return true;
   }
}
