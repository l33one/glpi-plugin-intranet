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

class PluginIntranetLink extends CommonDBTM {

   public static $rightname = 'plugin_intranet';

   public static function getTypeName($nb = 0) {
      return __('Quick links', 'intranet');
   }

   public static function getIcon() {
      return 'ti ti-link';
   }

   // Same policy as announcements: managing links is the UPDATE bit.
   // The ": bool" return type matches GLPI 11 (also valid on GLPI 10).
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
    * Curated list of icons (Tabler) offered for quick links.
    *
    * @return array icon class => label
    */
   public static function getIcons(): array {
      return [
         'ti ti-link'           => __('Link', 'intranet'),
         'ti ti-world'          => __('Website', 'intranet'),
         'ti ti-book'           => __('Documentation', 'intranet'),
         'ti ti-ticket'         => __('Ticket', 'intranet'),
         'ti ti-plus'           => __('New', 'intranet'),
         'ti ti-calendar-event' => __('Calendar', 'intranet'),
         'ti ti-users'          => __('People', 'intranet'),
         'ti ti-file-text'      => __('Document', 'intranet'),
         'ti ti-mail'           => __('E-mail', 'intranet'),
         'ti ti-phone'          => __('Phone', 'intranet'),
         'ti ti-video'          => __('Video', 'intranet'),
         'ti ti-chart-bar'      => __('Reports', 'intranet'),
         'ti ti-bell'           => __('Alerts', 'intranet'),
         'ti ti-folder'         => __('Files', 'intranet'),
         'ti ti-clock'          => __('Time', 'intranet'),
         'ti ti-map-pin'        => __('Location', 'intranet'),
         'ti ti-headset'        => __('Support', 'intranet'),
         'ti ti-star'           => __('Favorite', 'intranet'),
      ];
   }

    /** Max uploaded icon image size (2 MiB). */
    const IMAGE_MAX_SIZE = 2097152;

    // NOTE: SVG is intentionally NOT allowed: an inline SVG served from the
    // GLPI origin could execute embedded scripts (stored XSS). Raster images
    // and ICO cannot carry executable content.
    /** Allowed uploaded icon image extensions (lowercase, no dot). */
    const IMAGE_ALLOWED_EXT = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'ico'];

    /**
     * Directory where quick-link icon images are stored.
     */
    public static function getImageDir(): string {
       $base = defined('GLPI_PLUGIN_DOC_DIR')
          ? (string)GLPI_PLUGIN_DOC_DIR
          : ((defined('GLPI_DOC_DIR') ? (string)GLPI_DOC_DIR : sys_get_temp_dir()) . '/_plugins');
       return rtrim($base, '/\\') . '/intranet';
    }

    /**
     * Absolute path of a stored icon image file (basename only, no traversal).
     */
    public static function getImagePath(string $file): string {
       return self::getImageDir() . '/' . basename($file);
    }

    /**
     * URL that serves the stored icon image for a link row.
     */
    public static function imageUrl(array $row): string {
       /** @var array $CFG_GLPI */
       global $CFG_GLPI;
       return ($CFG_GLPI['root_doc'] ?? '')
          . '/plugins/intranet/front/image.php?link=' . (int)($row['id'] ?? 0);
    }

    /**
     * Whether a link row has a readable uploaded icon image.
     */
    public static function hasImage(array $row): bool {
       $file = trim((string)($row['image'] ?? ''));
       if ($file === '' || preg_match('#[\\\\/]#', $file)) {
          return false;
       }
       return is_file(self::getImagePath($file));
    }

    /**
     * Delete a stored icon image file (best effort).
     */
    public static function deleteImageFile(string $file): void {
       $file = trim($file);
       if ($file === '' || preg_match('#[\\\\/]#', $file)) {
          return;
       }
       $path = self::getImagePath($file);
       if (is_file($path)) {
          @unlink($path);
       }
    }

    /**
     * Validate and move an uploaded image (link icons and carousel banners
     * share this routine; only the filename prefix differs).
     *
     * @param array       $upload   Single $_FILES entry (or empty array)
     * @param string|null $current  Currently stored filename (kept when no new file)
     * @param boolean     $remove   True when the "remove image" checkbox was ticked
     * @param string      $prefix   Stored filename prefix ('link_' or 'banner_')
     * @return string|null|false New filename, null (keep removed), or false on error
     */
    public static function handleUploadedImage($upload, ?string $current, bool $remove = false, string $prefix = 'link_') {
       $current = trim((string)$current);
       if ($current !== '' && preg_match('#[\\\\/]#', $current)) {
          $current = '';
       }

       $has_new = is_array($upload)
          && ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE
          && isset($upload['tmp_name']);

       if (!$has_new) {
          if ($remove && $current !== '') {
             self::deleteImageFile($current);
             return null;
          }
          // Keep the current value (null when empty so the DB stays NULL).
          return $current !== '' ? $current : null;
       }

       if (($upload['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
          Session::addMessageAfterRedirect(__('Could not upload the image.', 'intranet'), false, ERROR);
          return false;
       }

       if (!is_uploaded_file($upload['tmp_name'])) {
          Session::addMessageAfterRedirect(__('Could not upload the image.', 'intranet'), false, ERROR);
          return false;
       }

       if ((int)($upload['size'] ?? 0) > self::IMAGE_MAX_SIZE) {
          Session::addMessageAfterRedirect(
             __('The image is too large (max 2 MB).', 'intranet'),
             false,
             ERROR
          );
          return false;
       }

       $orig = (string)($upload['name'] ?? 'image');
       $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
       if (!in_array($ext, self::IMAGE_ALLOWED_EXT, true)) {
          Session::addMessageAfterRedirect(
             __('Invalid image type. Use PNG, JPG, GIF, WEBP or ICO.', 'intranet'),
             false,
             ERROR
          );
          return false;
       }

       // MIME check: the file must really be an image (ICO has no getimagesize
       // signature, so it is accepted on extension alone after the checks above).
       if ($ext !== 'ico') {
          $info = @getimagesize($upload['tmp_name']);
          if ($info === false || strpos((string)($info['mime'] ?? ''), 'image/') !== 0) {
             Session::addMessageAfterRedirect(
                __('Invalid image type. Use PNG, JPG, GIF, WEBP or ICO.', 'intranet'),
                false,
                ERROR
             );
             return false;
          }
       }

       $dir = self::getImageDir();
       if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
          Session::addMessageAfterRedirect(__('Could not upload the image.', 'intranet'), false, ERROR);
          return false;
       }

       $prefix = preg_match('/^[a-z]+_$/', $prefix) ? $prefix : 'link_';
       try {
          $new = $prefix . bin2hex(random_bytes(8)) . '.' . $ext;
       } catch (\Throwable $e) {
          $new = $prefix . uniqid('', true) . '.' . $ext;
          $new = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $new);
       }

       if (!@move_uploaded_file($upload['tmp_name'], $dir . '/' . $new)) {
          Session::addMessageAfterRedirect(__('Could not upload the image.', 'intranet'), false, ERROR);
          return false;
       }

       if ($current !== '') {
          self::deleteImageFile($current);
       }
       return $new;
    }

    /**
     * Whether the URL points outside GLPI (opens in a new tab).
     *
     * @param string $url
     * @return boolean
     */
    public static function isExternalUrl(string $url): bool {
       return (bool)preg_match('#^https?://#i', $url);
    }

   /**
    * Get the active links, ordered for display.
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
         'ORDER' => ['sort_order', 'name', 'id'],
      ]);
      foreach ($it as $row) {
         $rows[] = $row;
      }
      return $rows;
   }

   /**
    * Get every link for the management table.
    *
    * @return array
    */
   public static function getAllForManagement(): array {
      /** @var DBmysql $DB */
      global $DB;

      $rows = [];
      $it = $DB->request([
         'FROM'  => self::getTable(),
         'ORDER' => ['sort_order', 'name', 'id'],
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
    * Validate and normalize link input.
    *
    * @param array $input
    * @return array|false
    */
   private function sanitizeInput($input) {
      if (isset($input['name'])) {
         $name = trim((string)$input['name']);
         if ($name === '') {
            Session::addMessageAfterRedirect(__('The name is required.', 'intranet'), false, ERROR);
            return false;
         }
         $input['name'] = mb_substr($name, 0, 255);
      }

      if (isset($input['url'])) {
         $url = self::sanitizeUrl((string)$input['url']);
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

      if (isset($input['icon'])) {
         $icons = self::getIcons();
         if (!array_key_exists($input['icon'], $icons)) {
            $input['icon'] = 'ti ti-link';
         }
      }

       if (isset($input['sort_order'])) {
          $order = (int)$input['sort_order'];
          $input['sort_order'] = min(9999, max(0, $order));
       }

       // Uploaded icon image filename (set by the settings form after moving
       // the upload). Accept only a plain basename with an allowed extension;
       // anything else is dropped so no path can ever be stored.
       if (array_key_exists('image', $input)) {
          $image = $input['image'];
          if ($image === null || $image === '') {
             $input['image'] = null;
          } else {
             $image = (string)$image;
             $ext = strtolower(pathinfo($image, PATHINFO_EXTENSION));
             if (
                preg_match('#[\\\\/]#', $image)
                || !in_array($ext, self::IMAGE_ALLOWED_EXT, true)
                || mb_strlen($image) > 255
             ) {
                unset($input['image']);
             } else {
                $input['image'] = basename($image);
             }
          }
       }

       $input['is_active'] = !empty($input['is_active']) ? 1 : 0;

       return $input;
    }

    public function pre_deleteItem() {
       $image = trim((string)($this->fields['image'] ?? ''));
       if ($image !== '') {
          self::deleteImageFile($image);
       }
       return parent::pre_deleteItem();
    }

   /**
    * Accept only absolute http(s) URLs or root-relative internal paths.
    * Anything else (javascript:, data:, protocol-relative, ...) is rejected
    * so a stored link can never become an XSS vector.
    *
    * @param string $url
    * @return string|false Normalized URL or false when invalid
    */
   public static function sanitizeUrl(string $url) {
      $url = trim($url);
      if ($url === '' || mb_strlen($url) > 1024) {
         return false;
      }

      // Internal GLPI path, e.g. /front/ticket.form.php
      if (str_starts_with($url, '/')) {
         if (str_starts_with($url, '//') || preg_match('/[\s\\\\<>"\']/', $url)) {
            return false;
         }
         return $url;
      }

      // Absolute URL: http(s) only, with a host.
      if (!preg_match('#^https?://#i', $url)) {
         return false;
      }
      try {
         $parts = parse_url($url);
      } catch (\Throwable $e) {
         return false;
      }
      if ($parts === false || empty($parts['host'])) {
         return false;
      }
      return $url;
   }
}
