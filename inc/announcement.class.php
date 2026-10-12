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

class PluginIntranetAnnouncement extends CommonDBTM {

   public static $rightname = 'plugin_intranet';

   public static function getTypeName($nb = 0) {
      return __('Announcements', 'intranet');
   }

   public static function getIcon() {
      return 'ti ti-speakerphone';
   }

   // Managing the notice board (create / edit / delete) is mapped to the
   // UPDATE bit of the plugin right: whoever can "manage" the intranet
   // content can do all three. Reading is gated by canView() (READ bit).
   // The ": bool" return type matches GLPI 11 (adding it is also valid on
   // GLPI 10, whose parent methods declare no return type).
   public static function canCreate(): bool {
      return Session::haveRight(self::$rightname, UPDATE);
   }

   public static function canUpdate(): bool {
      return Session::haveRight(self::$rightname, UPDATE);
   }

   public static function canDelete(): bool {
      return Session::haveRight(self::$rightname, UPDATE);
   }

   public static function canPurge(): bool {
      return Session::haveRight(self::$rightname, UPDATE);
   }

   /**
    * Announcement categories (key => label).
    *
    * @return array
    */
   public static function getCategories(): array {
      return [
         'notice'      => __('Notice', 'intranet'),
         'news'        => __('News', 'intranet'),
         'maintenance' => __('Maintenance', 'intranet'),
         'event'       => __('Event', 'intranet'),
      ];
   }

   /**
    * Whether a DB row is currently visible on the notice board: active, not
    * deleted and inside its validity window.
    *
    * @param array       $fields DB row
    * @param string|null $now    Reference time (Y-m-d H:i:s), defaults to now
    * @return boolean
    */
   public static function isCurrentlyVisible(array $fields, ?string $now = null): bool {
      if ((int)($fields['is_active'] ?? 0) !== 1) {
         return false;
      }
      if ((int)($fields['is_deleted'] ?? 0) !== 0) {
         return false;
      }
      $now   = $now ?? date('Y-m-d H:i:s');
      $begin = $fields['date_begin'] ?? null;
      $end   = $fields['date_end'] ?? null;
      if (!empty($begin) && $begin > $now) {
         return false;
      }
      if (!empty($end) && $end < $now) {
         return false;
      }
      return true;
   }

   /**
    * Get the announcements visible right now, pinned ones first.
    *
    * @param integer $limit    Max rows (0 = no limit)
    * @param string  $category Optional category filter (whitelisted)
    * @param string  $search   Optional free-text filter on title/content
    * @return array
    */
   public static function getVisible(int $limit = 0, string $category = '', string $search = ''): array {
      /** @var DBmysql $DB */
      global $DB;

      $now   = date('Y-m-d H:i:s');
      $where = [
         'is_active'  => 1,
         'is_deleted' => 0,
      ];
      // Validity window: null means "no bound".
      $where[] = ['OR' => [['date_begin' => null], ['date_begin' => ['<=', $now]]]];
      $where[] = ['OR' => [['date_end' => null], ['date_end' => ['>=', $now]]]];

      if ($category !== '' && array_key_exists($category, self::getCategories())) {
         $where['category'] = $category;
      }

      $search = trim($search);
      if ($search !== '') {
         // Escape LIKE wildcards so the search is literal; the query builder
         // quotes the value itself.
         $like    = '%' . addcslashes(mb_substr($search, 0, 100), '\\%_') . '%';
         $where[] = ['OR' => [['title' => ['LIKE', $like]], ['content' => ['LIKE', $like]]]];
      }

      $crit = [
         'FROM'  => self::getTable(),
         'WHERE' => $where,
         'ORDER' => ['is_sticky DESC', 'date_creation DESC', 'id DESC'],
      ];
      if ($limit > 0) {
         $crit['LIMIT'] = $limit;
      }

      $rows = [];
      foreach ($DB->request($crit) as $row) {
         $rows[] = $row;
      }
      return $rows;
   }

   /**
    * Get every announcement for the management table (including inactive and
    * out-of-window ones, newest first).
    *
    * @return array
    */
   public static function getAllForManagement(): array {
      /** @var DBmysql $DB */
      global $DB;

      $rows = [];
      $it = $DB->request([
         'FROM'  => self::getTable(),
         'WHERE' => ['is_deleted' => 0],
         'ORDER' => ['date_creation DESC', 'id DESC'],
      ]);
      foreach ($it as $row) {
         $rows[] = $row;
      }
      return $rows;
   }

   public function prepareInputForAdd($input) {
      $input = $this->sanitizeInput($input);
      if ($input === false) {
         return false;
      }
      $input['users_id']      = Session::getLoginUserID();
      $input['date_creation'] = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');
      $input['date_mod']      = $input['date_creation'];
      return parent::prepareInputForAdd($input);
   }

   public function prepareInputForUpdate($input) {
      $input = $this->sanitizeInput($input);
      if ($input === false) {
         return false;
      }
      $input['date_mod'] = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');
      return parent::prepareInputForUpdate($input);
   }

   /**
    * Validate and normalize announcement input. Returns false (and queues an
    * error message) when a hand-built POST carries an invalid value, so an
    * out-of-range value can never be stored.
    *
    * @param array $input
    * @return array|false
    */
   private function sanitizeInput($input) {
      if (isset($input['title'])) {
         $title = trim((string)$input['title']);
         if ($title === '') {
            Session::addMessageAfterRedirect(__('The title is required.', 'intranet'), false, ERROR);
            return false;
         }
         $input['title'] = mb_substr($title, 0, 255);
      }

      if (isset($input['category'])) {
         if (!array_key_exists($input['category'], self::getCategories())) {
            $input['category'] = 'notice';
         }
      }

      if (isset($input['content'])) {
         // Plain text only: it is rendered escaped (nl2br + htmlspecialchars),
         // so no HTML is ever stored as markup.
         $input['content'] = mb_substr(trim((string)$input['content']), 0, 65535);
      }

      $input['is_sticky'] = !empty($input['is_sticky']) ? 1 : 0;
      $input['is_active'] = !empty($input['is_active']) ? 1 : 0;

      // Validity window comes from <input type="date"> (Y-m-d); empty means
      // "no bound". date_begin starts at 00:00:00, date_end ends at 23:59:59.
      $date_fields = ['date_begin' => '00:00:00', 'date_end' => '23:59:59'];
      foreach ($date_fields as $field => $time) {
         if (!array_key_exists($field, $input)) {
            continue;
         }
         $raw = trim((string)$input[$field]);
         if ($raw === '') {
            $input[$field] = null;
            continue;
         }
         if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $raw, $m)
            || !checkdate((int)$m[2], (int)$m[3], (int)$m[1])
         ) {
            Session::addMessageAfterRedirect(__('Invalid date.', 'intranet'), false, ERROR);
            return false;
         }
         $input[$field] = $raw . ' ' . $time;
      }

      if (
         !empty($input['date_begin']) && !empty($input['date_end'])
         && $input['date_begin'] > $input['date_end']
      ) {
         Session::addMessageAfterRedirect(
            __('The start date must be before the end date.', 'intranet'),
            false,
            ERROR
         );
         return false;
      }

      return $input;
   }
}
