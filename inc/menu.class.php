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

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access this file directly");
}

class PluginIntranetMenu extends CommonGLPI {

   /**
    * Get menu icon
    *
    * @return string
    */
   public static function getIcon() {
      return 'ti ti-home';
   }

   /**
    * Define the menu details for the GLPI menu entry.
    * This registers the plugin under the "Plugins" section of the GLPI menu.
    *
    * @return array
    */
   public static function getMenuContent() {
      $menu = [];

      if (PluginIntranetPortal::canView()) {
         $menu['title'] = __('Intranet', 'intranet');
         $menu['page']  = '/plugins/intranet/front/intranet.php';
         $menu['icon']  = 'ti ti-home';

         $menu['options'] = [
            'portal' => [
               'title' => __('Portal', 'intranet'),
               'page'  => '/plugins/intranet/front/intranet.php',
               'icon'  => 'ti ti-home',
            ],
         ];

         if (PluginIntranetPortal::canManage()) {
            $menu['options']['manage'] = [
               'title' => __('Manage content', 'intranet'),
               'page'  => '/plugins/intranet/front/config.php',
               'icon'  => 'ti ti-settings',
            ];
         }
      }

      return $menu;
   }
}
