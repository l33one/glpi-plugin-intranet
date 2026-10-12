<?php

/**
 * -------------------------------------------------------------------------
 * GLPI Intranet Plugin
 * -------------------------------------------------------------------------
 *
 * Carousel banners shown at the top of the intranet panel/portal. Each banner
 * carries an uploaded image (PNG/JPG/GIF/WEBP/ICO, max 2 MB), an optional
 * title and an optional click-through URL.
 *
 * Image storage and validation are shared with quick-link icons
 * (PluginIntranetLink::handleUploadedImage() with the 'banner_' prefix).
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

class PluginIntranetBanner extends CommonDBTM {

   public static $rightname = 'plugin_intranet';

   public static function getTypeName($nb = 0) {
      return __('Banners', 'intranet');
   }

   public static function getIcon() {
      return 'ti ti-carousel-horizontal';
   }

   // Same policy as announcements and links: managing banners is UPDATE.
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
    * URL that serves the banner image.
    */
   public static function imageUrl(array $row): string {
      /** @var array $CFG_GLPI */
      global $CFG_GLPI;
      return ($CFG_GLPI['root_doc'] ?? '')
         . '/plugins/intranet/front/image.php?banner=' . (int)($row['id'] ?? 0);
   }

   /**
    * Whether a banner row has a readable uploaded image.
    */
   public static function hasImage(array $row): bool {
      $file = trim((string)($row['image'] ?? ''));
      if ($file === '' || preg_match('#[\\\\/]#', $file)) {
         return false;
      }
      return is_file(PluginIntranetLink::getImagePath($file));
   }

   /**
    * Active banners with a readable image, ordered for display.
    *
    * @return array
    */
   public static function getVisible(): array {
      /** @var DBmysql $DB */
      global $DB;

      $rows = [];
      $it = $DB->request([
         'FROM'  => self::getTable(),
         'WHERE' => ['is_active' => 1],
         'ORDER' => ['sort_order', 'id'],
      ]);
      foreach ($it as $row) {
         if (self::hasImage($row)) {
            $rows[] = $row;
         }
      }
      return $rows;
   }

   /**
    * Every banner for the management table.
    *
    * @return array
    */
   public static function getAllForManagement(): array {
      /** @var DBmysql $DB */
      global $DB;

      $rows = [];
      $it = $DB->request([
         'FROM'  => self::getTable(),
         'ORDER' => ['sort_order', 'id'],
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
      if (empty($input['image'])) {
         Session::addMessageAfterRedirect(__('An image is required for the banner.', 'intranet'), false, ERROR);
         return false;
      }
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
    * Validate and normalize banner input.
    *
    * @param array $input
    * @return array|false
    */
   private function sanitizeInput($input) {
      if (isset($input['title'])) {
         $input['title'] = mb_substr(trim((string)$input['title']), 0, 255);
      }

      if (isset($input['url'])) {
         $url = trim((string)$input['url']);
         if ($url === '') {
            $input['url'] = null;
         } else {
            $url = PluginIntranetLink::sanitizeUrl($url);
            if ($url === false) {
               Session::addMessageAfterRedirect(
                  __('Invalid URL. Use a full http(s) address or an internal path starting with "/".', 'intranet'),
                  false,
                  ERROR
               );
               return false;
            }
            $input['url'] = $url;
         }
      }

      // Uploaded image filename (set by the settings form after moving the
      // upload). Same basename policy as quick-link icons.
      if (array_key_exists('image', $input)) {
         $image = $input['image'];
         if ($image === null || $image === '') {
            $input['image'] = null;
         } else {
            $image = (string)$image;
            $ext = strtolower(pathinfo($image, PATHINFO_EXTENSION));
            if (
               preg_match('#[\\\\/]#', $image)
               || !in_array($ext, PluginIntranetLink::IMAGE_ALLOWED_EXT, true)
               || mb_strlen($image) > 255
            ) {
               unset($input['image']);
            } else {
               $input['image'] = basename($image);
            }
         }
      }

      if (isset($input['sort_order'])) {
         $order = (int)$input['sort_order'];
         $input['sort_order'] = min(9999, max(0, $order));
      }

      $input['is_active'] = !empty($input['is_active']) ? 1 : 0;

      return $input;
   }

   public function pre_deleteItem() {
      $image = trim((string)($this->fields['image'] ?? ''));
      if ($image !== '') {
         PluginIntranetLink::deleteImageFile($image);
      }
      return parent::pre_deleteItem();
   }
}
