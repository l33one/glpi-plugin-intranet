<?php

define('PLUGIN_INTRANET_VERSION', '1.4.0');

function plugin_init_intranet() {
   global $PLUGIN_HOOKS;

   $PLUGIN_HOOKS['csrf_compliant']['intranet'] = true;

   $PLUGIN_HOOKS['add_css']['intranet'] = 'css/intranet.css';
   $PLUGIN_HOOKS['add_javascript']['intranet'] = 'js/intranet.js';

   // The intranet lives in its own tab on the main screen (Central) plus the
   // full portal page under Plugins > Intranet - it is intentionally NOT
   // injected into the dashboard anymore.
   $PLUGIN_HOOKS['menu_toadd']['intranet'] = ['plugins' => 'PluginIntranetMenu'];

   // Wrench ("Configure") button on the Setup > Plugins list.
   $PLUGIN_HOOKS['config_page']['intranet'] = 'front/config.php';

   Plugin::registerClass('PluginIntranetConfig');
   Plugin::registerClass('PluginIntranetCentral', ['addtabon' => ['Central']]);
   // "Intranet" tab on the Profile form to grant view/manage rights.
   Plugin::registerClass('PluginIntranetProfile', ['addtabon' => ['Profile']]);
}

function plugin_version_intranet() {
   return [
      'name'           => 'Intranet',
      'version'        => PLUGIN_INTRANET_VERSION,
      'author'         => 'Auto Generated',
      'license'        => 'GPLv2+',
      'homepage'       => '',
      'minGlpiVersion' => '10.0.0',
      'requirements'   => [
         'glpi' => [
            'min' => '10.0.0',
            'dev' => true
         ]
      ]
   ];
}

function plugin_intranet_check_prerequisites() {
   if (version_compare(GLPI_VERSION, '10.0.0', 'lt')) {
      echo __('GLPI version not compatible with plugin intranet', 'intranet');
      return false;
   }
   return true;
}

function plugin_intranet_check_config($verbose = false) {
   return true;
}
