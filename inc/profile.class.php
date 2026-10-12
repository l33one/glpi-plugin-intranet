<?php

/**
 * -------------------------------------------------------------------------
 * GLPI Intranet Plugin
 * -------------------------------------------------------------------------
 *
 * "Intranet" tab on the Profile form (Administration > Profiles): grants the
 * plugin rights per profile without touching the database by hand.
 *
 * - No access (0): the profile does not see the intranet at all.
 * - View (READ): sees the Intranet tab, the portal, announcements and links.
 * - Manage (READ + UPDATE): additionally publishes announcements, links and
 *   banners (no need to be super-admin).
 *
 * @package   Plugin Intranet
 * @author    Leewan Meneses
 * @license   GPL-2.0+
 * @since     1.2.0
 *
 * -------------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access this file directly");
}

class PluginIntranetProfile extends CommonDBTM {

   public static function getTypeName($nb = 0) {
      return __('Intranet', 'intranet');
   }

   public static function getIcon() {
      return 'ti ti-home';
   }

   /**
    * Current intranet right value stored for a profile.
    *
    * @param integer $profiles_id
    * @return integer 0, READ or (READ | UPDATE)
    */
   public static function getProfileRight(int $profiles_id): int {
      /** @var DBmysql $DB */
      global $DB;

      if ($profiles_id <= 0) {
         return 0;
      }
      $it = $DB->request([
         'SELECT' => ['rights'],
         'FROM'   => 'glpi_profilerights',
         'WHERE'  => [
            'profiles_id' => $profiles_id,
            'name'        => PluginIntranetPortal::$rightname,
         ],
      ]);
      $row = $it->current();
      return (int)($row['rights'] ?? 0);
   }

   /**
    * Persist the intranet right for a profile (create / update / delete row).
    *
    * @param integer $profiles_id
    * @param integer $right 0, READ or (READ | UPDATE)
    * @return boolean
    */
   public static function setProfileRight(int $profiles_id, int $right): bool {
      $allowed = [0, READ, (READ | UPDATE)];
      if ($profiles_id <= 0 || !in_array($right, $allowed, true)) {
         return false;
      }
      $pr = new ProfileRight();
      $exists = $pr->getFromDBByCrit([
         'profiles_id' => $profiles_id,
         'name'        => PluginIntranetPortal::$rightname,
      ]);
      if ($right === 0) {
         return !$exists || $pr->delete(['id' => $pr->getID()]);
      }
      if ($exists) {
         return $pr->update(['id' => $pr->getID(), 'rights' => $right]);
      }
      return (bool)$pr->add([
         'profiles_id' => $profiles_id,
         'name'        => PluginIntranetPortal::$rightname,
         'rights'      => $right,
      ]);
   }

   /**
    * Tab label on the Profile form.
    */
   public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {
      if (!($item instanceof Profile)) {
         return '';
      }
      if (!Session::haveRight('profile', READ)) {
         return '';
      }
      return self::createTabEntry(__('Intranet', 'intranet'), 0, null, self::getIcon());
   }

   /**
    * Tab content on the Profile form: current right + editor form.
    */
   public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {
      if (!($item instanceof Profile)) {
         return false;
      }
      if (!Session::haveRight('profile', READ)) {
         return false;
      }
      $profiles_id = (int)$item->getID();
      if ($profiles_id <= 0) {
         echo '<p class="text-muted">' . __('Save the profile first to manage its intranet rights.', 'intranet') . '</p>';
         return true;
      }

      /** @var array $CFG_GLPI */
      global $CFG_GLPI;

      $current = self::getProfileRight($profiles_id);
      $can_edit = Session::haveRight('profile', UPDATE);
      $action = ($CFG_GLPI['root_doc'] ?? '') . '/plugins/intranet/front/profile.form.php';

      $options = [
         0              => __('No access', 'intranet'),
         READ           => __('View', 'intranet'),
         (READ | UPDATE) => __('Manage content', 'intranet'),
      ];
      $help = [
         0              => __('The profile does not see the intranet.', 'intranet'),
         READ           => __('Sees the Intranet tab, the portal, announcements and quick links.', 'intranet'),
         (READ | UPDATE) => __('Additionally publishes announcements, links and banners.', 'intranet'),
      ];

      echo '<div class="intranet-profile-rights">';
      echo '<form method="post" action="' . htmlspecialchars($action, ENT_QUOTES, 'UTF-8') . '">';
      echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
      echo Html::hidden('profiles_id', ['value' => $profiles_id]);
      foreach ($options as $value => $label) {
         $checked = ($current === (int)$value) ? ' checked' : '';
         $disabled = $can_edit ? '' : ' disabled';
         echo '<div class="form-check mb-2">';
         echo '<input class="form-check-input" type="radio" name="intranet_right"'
            . ' id="intranet-right-' . (int)$value . '" value="' . (int)$value . '"'
            . $checked . $disabled . '>';
         echo '<label class="form-check-label fw-semibold" for="intranet-right-' . (int)$value . '">'
            . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</label>';
         echo '<div class="form-text">' . htmlspecialchars($help[$value], ENT_QUOTES, 'UTF-8') . '</div>';
         echo '</div>';
      }
      if ($can_edit) {
         echo '<button type="submit" name="update" value="1" class="btn btn-primary mt-2">'
            . '<i class="ti ti-device-floppy me-1"></i>' . __('Save', 'intranet') . '</button>';
      }
      echo '</form>';
      echo '</div>';
      return true;
   }
}
