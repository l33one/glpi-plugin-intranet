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

class PluginIntranetPortal extends CommonGLPI {

   public static $rightname = 'plugin_intranet';

   public static function getTypeName($nb = 0) {
      return __('Intranet', 'intranet');
   }

   public static function getIcon() {
      return 'ti ti-home';
   }

   /**
    * Whether the current profile may manage the intranet content
    * (announcements and quick links).
    */
   public static function canManage(): bool {
      return Session::haveRight(self::$rightname, UPDATE);
   }

   public static function esc($value): string {
      return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
   }

   /**
    * URL of the plugin settings page (Setup > Plugins > Intranet).
    */
   public static function configUrl(array $params = []): string {
      /** @var array $CFG_GLPI */
      global $CFG_GLPI;
      $url = $CFG_GLPI['root_doc'] . '/plugins/intranet/front/config.php';
      if (count($params) > 0) {
         $url .= '?' . http_build_query($params);
      }
      return $url;
   }

   /**
    * URL of the full portal page.
    */
   public static function portalUrl(array $params = []): string {
      /** @var array $CFG_GLPI */
      global $CFG_GLPI;
      $url = $CFG_GLPI['root_doc'] . '/plugins/intranet/front/intranet.php';
      if (count($params) > 0) {
         $url .= '?' . http_build_query($params);
      }
      return $url;
   }

    /**
     * Legacy dashboard injection point (<= 1.0.0).
     *
     * Since 1.1.0 the intranet lives in its own tab on the main screen
     * (PluginIntranetCentral) instead of inside the dashboard tab, so this is
     * intentionally a no-op kept only for backward compatibility.
     *
     * @param mixed $data Hook payload (unused)
     * @return void
     */
    public static function showCentralPanel($data = null) {
       return;
    }

    /**
     * Intranet panel for the main screen "Intranet" tab (Central) and for the
     * full portal page.
     */
    public static function showPanel(int $max_announcements = 3): void {
      /** @var array $CFG_GLPI */
      global $CFG_GLPI;

       echo '<div class="intranet-panel card mb-3" data-intranet-ajax="'
          . self::esc($CFG_GLPI['root_doc'] . '/plugins/intranet/ajax/announcement.php') . '">';
       self::showCustomCss();
       echo '<div class="card-body">';

       if (PluginIntranetConfig::showGreeting()) {
          self::showGreeting(true);
       }
       self::showBanners('panel');
       if (PluginIntranetConfig::showMetrics()) {
          self::showMetrics();
       }
       // Side by side on wide screens so everything fits with less scrolling.
       echo '<div class="row g-2 mb-2">';
       echo '<div class="col-12 col-xl-5">';
       if (PluginIntranetConfig::showLinks()) {
          self::showLinks(true);
       }
       echo '</div>';
       echo '<div class="col-12 col-xl-7">';
       self::showAnnouncements($max_announcements, true);
       echo '</div>';
       echo '</div>';

       echo '<div class="mt-2 d-flex flex-wrap gap-2">';
      echo '<a class="btn btn-sm btn-outline-primary" href="' . self::esc(self::portalUrl()) . '">'
         . '<i class="ti ti-home me-1"></i>' . __('Open the intranet portal', 'intranet') . '</a>';
      if (self::canManage()) {
         echo '<a class="btn btn-sm btn-outline-secondary" href="' . self::esc(self::configUrl()) . '">'
            . '<i class="ti ti-settings me-1"></i>' . __('Manage content', 'intranet') . '</a>';
      }
      echo '</div>';

      echo '</div>';
      echo '</div>';
      self::showReaderModal();
   }

   /**
    * Full portal page (Plugins > Intranet).
    */
   public static function showPortal(): void {
      /** @var array $CFG_GLPI */
      global $CFG_GLPI;

      $category = $_GET['cat'] ?? '';
      if (!is_string($category) || !array_key_exists($category, PluginIntranetAnnouncement::getCategories())) {
         $category = '';
      }
      $search = $_GET['q'] ?? '';
      if (!is_string($search)) {
         $search = '';
      }
      $search = trim(mb_substr($search, 0, 100));

      echo '<div class="intranet-portal container-fluid py-4" data-intranet-ajax="'
         . self::esc($CFG_GLPI['root_doc'] . '/plugins/intranet/ajax/announcement.php') . '">';

       echo '<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">';
      echo '<h1 class="h3 mb-0"><i class="ti ti-home me-1"></i>' . __('Intranet Portal', 'intranet') . '</h1>';
      if (self::canManage()) {
         echo '<a class="btn btn-outline-secondary" href="' . self::esc(self::configUrl()) . '">'
            . '<i class="ti ti-settings me-1"></i>' . __('Manage content', 'intranet') . '</a>';
      }
      echo '</div>';

       self::showCustomCss();
       if (PluginIntranetConfig::showGreeting()) {
          self::showGreeting(false);
       }
       self::showBanners('portal');
       if (PluginIntranetConfig::showMetrics()) {
          self::showMetrics();
       }

       // Quick links first, then the notice board with search + category filter.
       // Side by side on wide screens so everything fits with less scrolling.
       echo '<div class="row g-2">';
       echo '<div class="col-12 col-xl-4">';
       if (PluginIntranetConfig::showLinks()) {
          echo '<div class="card mb-2"><div class="card-body">';
          echo '<h2 class="h5 mb-3"><i class="ti ti-link me-1"></i>'
             . PluginIntranetLink::getTypeName(2) . '</h2>';
          self::showLinks(false);
          echo '</div></div>';
       }
       echo '</div>';
       echo '<div class="col-12 col-xl-8">';
       $categories = PluginIntranetAnnouncement::getCategories();
       echo '<div class="card mb-2"><div class="card-body">';
       echo '<form method="get" action="' . self::esc(self::portalUrl()) . '" class="row g-2 align-items-end mb-3">';
       echo '<div class="col-12 col-md-6"><label class="form-label fw-semibold" for="intranet-q">'
          . __('Search announcements', 'intranet') . '</label>'
          . '<input type="search" class="form-control" id="intranet-q" name="q" value="' . self::esc($search) . '"'
          . ' placeholder="' . self::esc(__('Search by title or content...', 'intranet')) . '"></div>';
       echo '<div class="col-12 col-md-4"><label class="form-label fw-semibold" for="intranet-cat">'
          . __('Category', 'intranet') . '</label>'
          . '<select class="form-select" id="intranet-cat" name="cat">'
          . '<option value="">' . __('All categories', 'intranet') . '</option>';
       foreach ($categories as $key => $label) {
          echo '<option value="' . self::esc($key) . '"' . ($category === $key ? ' selected' : '') . '>'
             . self::esc($label) . '</option>';
       }
       echo '</select></div>';
       echo '<div class="col-12 col-md-2 d-flex gap-2">'
          . '<button type="submit" class="btn btn-primary flex-fill"><i class="ti ti-search me-1"></i>'
          . __('Search', 'intranet') . '</button>';
       if ($search !== '' || $category !== '') {
          echo '<a class="btn btn-outline-secondary" href="' . self::esc(self::portalUrl()) . '"'
             . ' title="' . self::esc(__('Clear', 'intranet')) . '"><i class="ti ti-x"></i></a>';
       }
       echo '</div></form>';

       self::showAnnouncements(0, false, $category, $search);
       echo '</div></div>';
       echo '</div>';
       echo '</div>';

       echo '</div>';
       self::showReaderModal();
   }

    /**
     * Welcome message with the user name, the contact info from the user
     * profile (phone, e-mail, manager and groups) and the assets assigned to
     * the current user.
     */
    private static function showGreeting(bool $compact): void {
       /** @var array $CFG_GLPI */
       global $CFG_GLPI;

       $me = (int)Session::getLoginUserID();

       $hour = (int)date('G');
       if ($hour < 12) {
          $greeting = __('Good morning', 'intranet');
       } elseif ($hour < 18) {
          $greeting = __('Good afternoon', 'intranet');
       } else {
          $greeting = __('Good evening', 'intranet');
       }

       $first = '';
       $user_fields = [];
       $user = new User();
       if ($me > 0 && $user->getFromDB($me)) {
          $user_fields = $user->fields;
          $first = trim((string)($user_fields['firstname'] ?? ''));
          if ($first === '') {
             $first = trim((string)($user_fields['name'] ?? ''));
          }
       }

       $title = $compact ? 'h5' : 'h2';
       echo '<div class="intranet-greeting mb-3">';
       echo "<$title class=\"mb-1\">" . $greeting
          . ($first !== '' ? ', ' . self::esc($first) : '') . '!</' . $title . '>';
       echo '<div class="text-muted">' . self::esc(Html::convDate(date('Y-m-d'))) . '</div>';
       echo '</div>';

       if ($me <= 0 || empty($user_fields)) {
          return;
       }

       $contact = self::getUserContact($me, $user_fields);

       echo '<div class="card mb-3 intranet-profile"><div class="card-body">';
       echo '<div class="row g-3">';

       // Contact info from the user profile.
       echo '<div class="col-12 col-lg-6">';
       echo '<h3 class="h6 mb-2"><i class="ti ti-id me-1"></i>'
          . self::esc(__('My contact info', 'intranet')) . '</h3>';
       echo '<ul class="list-unstyled mb-0 intranet-contact">';
       self::contactLine('ti-mail', __('Email', 'intranet'), $contact['emails']);
       self::contactLine('ti-phone', __('Phone', 'intranet'), $contact['phones']);
       self::contactLine('ti-user-check', __('Manager', 'intranet'), $contact['manager']);
       self::contactLine('ti-users', __('My groups', 'intranet'), $contact['groups']);
       echo '</ul>';
       $pref_url = ($CFG_GLPI['root_doc'] ?? '') . '/front/preference.php';
       echo '<div class="mt-2"><a class="btn btn-sm btn-outline-primary" href="' . self::esc($pref_url) . '">'
          . '<i class="ti ti-pencil me-1"></i>' . self::esc(__('Edit my data', 'intranet')) . '</a></div>';
       echo '</div>';

       // Assets assigned to the current user.
       $assets = self::getUserAssets($me);
       echo '<div class="col-12 col-lg-6">';
       echo '<h3 class="h6 mb-2"><i class="ti ti-device-desktop me-1"></i>'
          . self::esc(__('My assets', 'intranet')) . '</h3>';
       if (empty($assets)) {
          echo '<p class="text-muted mb-0">' . self::esc(__('No assets assigned to you.', 'intranet')) . '</p>';
       } else {
          echo '<ul class="list-group intranet-assets">';
          foreach (array_slice($assets, 0, 8) as $asset) {
             echo '<li class="list-group-item d-flex justify-content-between align-items-center gap-2 py-1 px-2">';
             echo '<span class="text-truncate"><i class="' . self::esc($asset['icon']) . ' me-1 text-muted"></i>';
             if (!empty($asset['url'])) {
                echo '<a href="' . self::esc($asset['url']) . '">' . self::esc($asset['label']) . '</a>';
             } else {
                echo self::esc($asset['label']);
             }
             echo '</span>';
             echo '<span class="badge bg-secondary">' . self::esc($asset['type']) . '</span>';
             echo '</li>';
          }
          echo '</ul>';
          if (count($assets) > 8) {
             echo '<small class="text-muted">'
                . sprintf(self::esc(__('Showing %1$d of %2$d assets.', 'intranet')), 8, count($assets))
                . '</small>';
          }
       }
       echo '</div>';

       echo '</div>';
       echo '</div></div>';
    }

    /**
     * Render one contact line (label + single value, list or fallback).
     *
     * @param string       $icon
     * @param string       $label
     * @param array|string $value
     */
    private static function contactLine(string $icon, string $label, $value): void {
       if (is_array($value)) {
          $value = array_values(array_filter(array_map('trim', array_map('strval', $value))));
          if (empty($value)) {
             $value = __('Not informed', 'intranet');
          } else {
             $value = implode(' · ', array_map([self::class, 'esc'], $value));
             echo '<li class="mb-1"><i class="ti ' . self::esc($icon) . ' me-1 text-muted"></i>'
                . '<span class="text-muted">' . self::esc($label) . ':</span> ' . $value . '</li>';
             return;
          }
       } elseif (trim((string)$value) === '') {
          $value = __('Not informed', 'intranet');
       }
       echo '<li class="mb-1"><i class="ti ' . self::esc($icon) . ' me-1 text-muted"></i>'
          . '<span class="text-muted">' . self::esc($label) . ':</span> '
          . self::esc((string)$value) . '</li>';
    }

    /**
     * Contact info of a user taken from the profile.
     *
     * @param integer $users_id
     * @param array   $fields   glpi_users row (avoids a second query)
     * @return array{phones: array, emails: array, manager: string, groups: array}
     */
    public static function getUserContact(int $users_id, array $fields = []): array {
       /** @var DBmysql $DB */
       global $DB;

       if (empty($fields) && $users_id > 0) {
          $user = new User();
          if ($user->getFromDB($users_id)) {
             $fields = $user->fields;
          }
       }

       $phones = [];
       foreach (['phone', 'phone2', 'mobile'] as $field) {
          $phone = trim((string)($fields[$field] ?? ''));
          if ($phone !== '' && !in_array($phone, $phones, true)) {
             $phones[] = $phone;
          }
       }

       $emails = [];
       if ($DB->tableExists('glpi_useremails')) {
          $it = $DB->request([
             'SELECT' => ['email'],
             'FROM'   => 'glpi_useremails',
             'WHERE'  => ['users_id' => $users_id],
             'ORDER'  => ['is_default DESC', 'email'],
          ]);
          foreach ($it as $row) {
             $email = trim((string)($row['email'] ?? ''));
             if ($email !== '' && !in_array($email, $emails, true)) {
                $emails[] = $email;
             }
          }
       }
       // Fallback for very old schemas carrying a denormalized column.
       if (empty($emails) && $DB->fieldExists('glpi_users', 'email')) {
          $email = trim((string)($fields['email'] ?? ''));
          if ($email !== '') {
             $emails[] = $email;
          }
       }

       $manager = '';
       $supervisor_id = (int)($fields['users_id_supervisor'] ?? 0);
       if ($supervisor_id > 0 && $supervisor_id !== $users_id) {
          $name = getUserName($supervisor_id);
          if (is_string($name) && trim($name) !== '') {
             $manager = $name;
          }
       }

       $groups = [];
       if ($DB->tableExists('glpi_groups_users') && $DB->tableExists('glpi_groups')) {
          $it = $DB->request([
             'SELECT' => ['glpi_groups.name', 'glpi_groups.completename'],
             'FROM'   => 'glpi_groups_users',
             'INNER JOIN' => [
                'glpi_groups' => [
                   'ON' => [
                      'glpi_groups_users' => 'groups_id',
                      'glpi_groups'       => 'id',
                   ],
                ],
             ],
             'WHERE' => ['glpi_groups_users.users_id' => $users_id],
             'ORDER' => ['glpi_groups.completename'],
          ]);
          foreach ($it as $row) {
             $gname = trim((string)($row['completename'] ?? $row['name'] ?? ''));
             if ($gname !== '' && !in_array($gname, $groups, true)) {
                $groups[] = $gname;
             }
          }
       }

       return [
          'phones'  => $phones,
          'emails'  => $emails,
          'manager' => $manager,
          'groups'  => $groups,
       ];
    }

    /**
     * Assets assigned to a user (each asset table carries its own users_id).
     *
     * @param integer $users_id
     * @return array List of {type, label, icon, url}
     */
    public static function getUserAssets(int $users_id): array {
       /** @var DBmysql $DB */
       global $DB, $CFG_GLPI;

       $assets = [];
       if ($users_id <= 0) {
          return $assets;
       }

       $types = [
          'Computer'          => ['icon' => 'ti ti-device-desktop'],
          'Monitor'           => ['icon' => 'ti ti-device-tv'],
          'Phone'             => ['icon' => 'ti ti-device-mobile'],
          'Printer'           => ['icon' => 'ti ti-printer'],
          'Peripheral'        => ['icon' => 'ti ti-keyboard'],
          'NetworkEquipment'  => ['icon' => 'ti ti-server'],
       ];

       foreach ($types as $itemtype => $meta) {
          if (!class_exists($itemtype)) {
             continue;
          }
          $table = getTableForItemType($itemtype);
          if (!$DB->tableExists($table) || !$DB->fieldExists($table, 'users_id')) {
             continue;
          }
          $where = [
             $table . '.users_id'    => $users_id,
             $table . '.is_deleted' => 0,
          ];
          if ($DB->fieldExists($table, 'is_template')) {
             $where[$table . '.is_template'] = 0;
          }
          $crit = [
             'SELECT' => [$table . '.id', $table . '.name'],
             'FROM'   => $table,
             'WHERE'  => $where,
             'ORDER'  => [$table . '.name'],
             'LIMIT'  => 50,
          ];
          if ($DB->fieldExists($table, 'serial')) {
             $crit['SELECT'][] = $table . '.serial';
          }
          try {
             $it = $DB->request($crit);
          } catch (\Throwable $e) {
             continue;
          }
          foreach ($it as $row) {
             $name = trim((string)($row['name'] ?? ''));
             if ($name === '') {
                $name = '#' . (int)$row['id'];
             }
             $serial = trim((string)($row['serial'] ?? ''));
             // Core front paths follow /front/<lowercase-itemtype>.form.php
             // on both GLPI 10 and 11 (computer, monitor, phone, ...).
             $url = ($CFG_GLPI['root_doc'] ?? '') . '/front/' . strtolower($itemtype)
                . '.form.php?id=' . (int)$row['id'];
             $assets[] = [
                'type'  => $itemtype::getTypeName(1),
                'label' => $serial !== '' ? ($name . ' (' . $serial . ')') : $name,
                'icon'  => $meta['icon'],
                'url'   => $url,
             ];
          }
       }

       return $assets;
    }

    /**
     * Print the administrator-defined extra styles (sanitized on save).
     */
    private static function showCustomCss(): void {
       $css = PluginIntranetConfig::getCustomCss();
       if ($css === '') {
          return;
       }
       echo '<style class="intranet-custom-css">' . $css . '</style>';
    }

    /**
     * Carousel with the active banners (Bootstrap 5). Rendered only when at
     * least one banner with a readable image exists.
     *
     * @param string $suffix Unique suffix for the carousel id
     */
    private static function showBanners(string $suffix): void {
       $banners = PluginIntranetBanner::getVisible();
       if (empty($banners)) {
          return;
       }
       $id = 'intranet-carousel-' . preg_replace('/[^a-z0-9_-]/i', '', $suffix);
       $multiple = count($banners) > 1;

       echo '<div id="' . self::esc($id) . '" class="carousel slide intranet-carousel mb-2" data-bs-ride="carousel">';
       if ($multiple) {
          echo '<div class="carousel-indicators">';
          foreach (array_keys($banners) as $i) {
             echo '<button type="button" data-bs-target="#' . self::esc($id) . '" data-bs-slide-to="' . $i . '"'
                . ($i === 0 ? ' class="active" aria-current="true"' : '')
                . ' aria-label="Slide ' . ($i + 1) . '"></button>';
          }
          echo '</div>';
       }
       echo '<div class="carousel-inner">';
       foreach ($banners as $i => $banner) {
          $title = trim((string)($banner['title'] ?? ''));
          $url = trim((string)($banner['url'] ?? ''));
          echo '<div class="carousel-item' . ($i === 0 ? ' active' : '') . '">';
          $img = '<img src="' . self::esc(PluginIntranetBanner::imageUrl($banner)) . '"'
             . ' class="d-block w-100 intranet-carousel-img" alt="' . self::esc($title) . '">';
          if ($url !== '') {
             $external = PluginIntranetLink::isExternalUrl($url);
             echo '<a href="' . self::esc($url) . '"'
                . ($external ? ' target="_blank" rel="noopener"' : '') . '>' . $img . '</a>';
          } else {
             echo $img;
          }
          if ($title !== '') {
             echo '<div class="carousel-caption d-none d-md-block intranet-carousel-caption">'
                . '<span>' . self::esc($title) . '</span></div>';
          }
          echo '</div>';
       }
       echo '</div>';
       if ($multiple) {
          echo '<button class="carousel-control-prev" type="button" data-bs-target="#' . self::esc($id) . '" data-bs-slide="prev">'
             . '<span class="carousel-control-prev-icon" aria-hidden="true"></span>'
             . '<span class="visually-hidden">' . self::esc(__('Previous', 'intranet')) . '</span></button>';
          echo '<button class="carousel-control-next" type="button" data-bs-target="#' . self::esc($id) . '" data-bs-slide="next">'
             . '<span class="carousel-control-next-icon" aria-hidden="true"></span>'
             . '<span class="visually-hidden">' . self::esc(__('Next', 'intranet')) . '</span></button>';
       }
       echo '</div>';
    }

    /**
     * Metric tiles. Ticket numbers only appear for profiles that can view
     * tickets; the asset tile only for profiles that can view computers.
     */
    private static function showMetrics(): void {
      $metrics = self::getMetrics();
      if (empty($metrics)) {
         return;
      }
       echo '<div class="row g-2 mb-2 intranet-metrics">';
       foreach ($metrics as $metric) {
          // 2 rows of 3 cards on desktop, 3 rows of 2 on small screens.
          echo '<div class="col-6 col-lg-4">';
         echo '<div class="intranet-metric card h-100"><div class="card-body d-flex align-items-center gap-3">';
         echo '<div class="intranet-metric-icon text-' . self::esc($metric['color']) . '">'
            . '<i class="' . self::esc($metric['icon']) . '"></i></div>';
         echo '<div><div class="intranet-metric-value">' . (int)$metric['value'] . '</div>'
            . '<div class="intranet-metric-label">' . self::esc($metric['label']) . '</div></div>';
         echo '</div>';
         if (!empty($metric['url'])) {
            echo '<a class="stretched-link" href="' . self::esc($metric['url']) . '"'
               . ' aria-label="' . self::esc($metric['label']) . '"></a>';
         }
         echo '</div></div>';
      }
      echo '</div>';
   }

    /**
     * Ticket status constants with fallbacks for GLPI 10/11.
     */
    private static function ticketStatus(string $name, int $fallback): int {
       $const = 'Ticket::' . $name;
       return defined($const) ? (int)constant($const) : $fallback;
    }

    /**
     * Count DISTINCT tickets where the current user is requester/observer.
     */
    private static function countMyTickets(int $users_id, int $actor, array $statuses): int {
       /** @var DBmysql $DB */
       global $DB;

       if ($users_id <= 0 || empty($statuses)) {
          return 0;
       }
       try {
          $it = $DB->request([
             'SELECT'     => ['glpi_tickets.id'],
             'DISTINCT'   => true,
             'FROM'       => 'glpi_tickets',
             'INNER JOIN' => [
                'glpi_tickets_users' => [
                   'ON' => [
                      'glpi_tickets_users' => 'tickets_id',
                      'glpi_tickets'       => 'id',
                   ],
                ],
             ],
             'WHERE' => [
                'glpi_tickets.status'         => array_values($statuses),
                'glpi_tickets.is_deleted'     => 0,
                'glpi_tickets_users.users_id' => $users_id,
                'glpi_tickets_users.type'     => $actor,
             ] + getEntitiesRestrictCriteria('glpi_tickets'),
          ]);
       } catch (\Throwable $e) {
          return 0;
       }
       // DISTINCT ids counted in PHP: works identically on GLPI 10 and 11
       // (the builders disagree on the COUNT DISTINCT spelling).
       $count = 0;
       foreach ($it as $row) {
          $count++;
       }
       return $count;
    }

    /**
     * Tickets waiting for the current user approval (ticket validations).
     */
    private static function countMyValidations(int $users_id): int {
       /** @var DBmysql $DB */
       global $DB;

       if ($users_id <= 0 || !$DB->tableExists('glpi_ticketvalidations')) {
          return 0;
       }
       $waiting = class_exists('TicketValidation') && defined('TicketValidation::WAITING')
          ? (int)constant('TicketValidation::WAITING')
          : 2;
       $solved = self::ticketStatus('SOLVED', 5);
       $closed = self::ticketStatus('CLOSED', 6);
       try {
          $it = $DB->request([
             'SELECT'     => ['glpi_ticketvalidations.id'],
             'DISTINCT'   => true,
             'FROM'       => 'glpi_ticketvalidations',
             'INNER JOIN' => [
                'glpi_tickets' => [
                   'ON' => [
                      'glpi_ticketvalidations' => 'tickets_id',
                      'glpi_tickets'           => 'id',
                   ],
                ],
             ],
             'WHERE' => [
                'glpi_ticketvalidations.users_id_validate' => $users_id,
                'glpi_ticketvalidations.status'             => $waiting,
                'glpi_tickets.is_deleted'                   => 0,
                'NOT' => ['glpi_tickets.status' => [$solved, $closed]],
             ] + getEntitiesRestrictCriteria('glpi_tickets'),
          ]);
       } catch (\Throwable $e) {
          return 0;
       }
       $count = 0;
       foreach ($it as $row) {
          $count++;
       }
       return $count;
    }

    /**
     * Satisfaction surveys of the user tickets answered in the last 7 days
     * window and still pending (no answer yet).
     */
    private static function countPendingSurveys(int $users_id): int {
       /** @var DBmysql $DB */
       global $DB;

       if ($users_id <= 0 || !$DB->tableExists('glpi_ticketsatisfactions')) {
          return 0;
       }
       $since = date('Y-m-d H:i:s', strtotime('-7 days'));
       try {
          $it = $DB->request([
             'SELECT'     => ['glpi_tickets.id'],
             'DISTINCT'   => true,
             'FROM'       => 'glpi_tickets',
             'INNER JOIN' => [
                'glpi_tickets_users' => [
                   'ON' => [
                      'glpi_tickets_users' => 'tickets_id',
                      'glpi_tickets'       => 'id',
                   ],
                ],
                'glpi_ticketsatisfactions' => [
                   'ON' => [
                      'glpi_ticketsatisfactions' => 'tickets_id',
                      'glpi_tickets'             => 'id',
                   ],
                ],
             ],
             'WHERE' => [
                'glpi_tickets.is_deleted'                => 0,
                'glpi_tickets_users.users_id'            => $users_id,
                'glpi_tickets_users.type'                => CommonITILActor::REQUESTER,
                'glpi_ticketsatisfactions.date_answered' => null,
                'glpi_ticketsatisfactions.date_begin'    => ['>=', $since],
             ] + getEntitiesRestrictCriteria('glpi_tickets'),
          ]);
       } catch (\Throwable $e) {
          return 0;
       }
       $count = 0;
       foreach ($it as $row) {
          $count++;
       }
       return $count;
    }

    /**
     * Compute the metric tiles for the current user: only personal ticket
     * cards plus pending satisfaction surveys from the last 7 days.
     *
     * @return array
     */
    public static function getMetrics(): array {
       /** @var array $CFG_GLPI */
       global $CFG_GLPI;

       $metrics = [];
       if (!class_exists('Ticket') || !Ticket::canView()) {
          return $metrics;
       }

       $root = $CFG_GLPI['root_doc'] ?? '';
       $me = (int)Session::getLoginUserID();

       $incoming = self::ticketStatus('INCOMING', 1);
       $assigned = self::ticketStatus('ASSIGNED', 2);
       $planned  = self::ticketStatus('PLANNED', 3);
       $waiting  = self::ticketStatus('WAITING', 4);
       $solved   = self::ticketStatus('SOLVED', 5);

       $ticket_url = method_exists('Ticket', 'getSearchURL')
          ? Ticket::getSearchURL()
          : ($root . '/front/ticket.php');

       $metrics[] = [
          'icon'  => 'ti ti-ticket',
          'color' => 'primary',
          'value' => self::countMyTickets($me, CommonITILActor::REQUESTER, [$incoming, $assigned, $planned]),
          'label' => __('My open tickets', 'intranet'),
          'url'   => $ticket_url,
       ];
       $metrics[] = [
          'icon'  => 'ti ti-stamp',
          'color' => 'warning',
          'value' => self::countMyValidations($me),
          'label' => __('Tickets awaiting my approval', 'intranet'),
          'url'   => $ticket_url,
       ];
       $metrics[] = [
          'icon'  => 'ti ti-eye',
          'color' => 'info',
          'value' => self::countMyTickets($me, CommonITILActor::OBSERVER, [$incoming, $assigned, $planned, $waiting]),
          'label' => __('Tickets I watch', 'intranet'),
          'url'   => $ticket_url,
       ];
       $metrics[] = [
          'icon'  => 'ti ti-clock-pause',
          'color' => 'secondary',
          'value' => self::countMyTickets($me, CommonITILActor::REQUESTER, [$waiting]),
          'label' => __('My pending tickets', 'intranet'),
          'url'   => $ticket_url,
       ];
       $metrics[] = [
          'icon'  => 'ti ti-check',
          'color' => 'success',
          'value' => self::countMyTickets($me, CommonITILActor::REQUESTER, [$solved]),
          'label' => __('My solved tickets', 'intranet'),
          'url'   => $ticket_url,
       ];
       $metrics[] = [
          'icon'  => 'ti ti-star',
          'color' => 'orange',
          'value' => self::countPendingSurveys($me),
          'label' => __('Pending satisfaction surveys (7 days)', 'intranet'),
          'url'   => $ticket_url,
       ];

       return $metrics;
    }

   /**
    * Notice board section.
    */
   private static function showAnnouncements(
      int $limit = 0,
      bool $compact = true,
      string $category = '',
      string $search = ''
   ): void {
      $announcements = PluginIntranetAnnouncement::getVisible($limit, $category, $search);
      $categories = PluginIntranetAnnouncement::getCategories();

      if (!$compact) {
         echo '<h2 class="h5 mb-3"><i class="ti ti-speakerphone me-1"></i>'
            . PluginIntranetAnnouncement::getTypeName(2) . '</h2>';
      } else {
         echo '<h2 class="h6 mb-2"><i class="ti ti-speakerphone me-1"></i>'
            . PluginIntranetAnnouncement::getTypeName(2) . '</h2>';
      }

      if (empty($announcements)) {
         echo '<p class="text-muted mb-0">' . __('No announcements at the moment.', 'intranet') . '</p>';
         return;
      }

      echo '<div class="list-group intranet-announcements">';
      foreach ($announcements as $row) {
         $excerpt = trim((string)($row['content'] ?? ''));
         if (mb_strlen($excerpt) > 160) {
            $excerpt = mb_substr($excerpt, 0, 160) . '…';
         }
         $author = self::authorName((int)($row['users_id'] ?? 0));
         echo '<button type="button" class="list-group-item list-group-item-action intranet-announcement"'
            . ' data-intranet-announcement="' . (int)$row['id'] . '">';
         echo '<div class="d-flex w-100 justify-content-between align-items-center gap-2">';
         echo '<span class="fw-semibold text-truncate">';
         if (!empty($row['is_sticky'])) {
            echo '<i class="ti ti-pin text-warning me-1"></i>';
         }
         echo self::esc($row['title']) . '</span>';
         echo '<span class="badge bg-' . self::esc(self::categoryBadgeClass((string)$row['category'])) . '">'
            . self::esc($categories[$row['category']] ?? $row['category']) . '</span>';
         echo '</div>';
         echo '<div><small class="text-muted">';
         $created = $row['date_creation'] ?? null;
         echo self::esc(!empty($created) ? Html::convDateTime($created) : '');
         if ($author !== '') {
            echo ' · ' . self::esc($author);
         }
         echo '</small></div>';
         if ($excerpt !== '') {
            echo '<p class="mb-0 small text-muted intranet-announcement-excerpt">'
               . nl2br(self::esc($excerpt)) . '</p>';
         }
         echo '</button>';
      }
      echo '</div>';
   }

   /**
    * Quick links section.
    */
   private static function showLinks(bool $compact): void {
      $links = PluginIntranetLink::getVisible();
      if (empty($links)) {
         return;
      }
      if ($compact) {
          echo '<h2 class="h6 mt-2 mb-2"><i class="ti ti-link me-1"></i>'
            . PluginIntranetLink::getTypeName(2) . '</h2>';
      }
       echo '<div class="intranet-links-grid">';
       foreach ($links as $link) {
          $external = PluginIntranetLink::isExternalUrl((string)$link['url']);
          echo '<a class="intranet-tile" href="' . self::esc($link['url']) . '"'
             . ' title="' . self::esc($link['name']) . '"'
             . ($external ? ' target="_blank" rel="noopener"' : '') . '>';
          if (PluginIntranetLink::hasImage($link)) {
             echo '<span class="intranet-tile-icon"><img src="'
                . self::esc(PluginIntranetLink::imageUrl($link)) . '" alt=""></span>';
          } else {
             echo '<span class="intranet-tile-icon"><i class="' . self::esc($link['icon']) . '"></i></span>';
          }
          echo '<span class="intranet-tile-label">' . self::esc($link['name']) . '</span>';
          if ($external) {
             echo '<span class="intranet-tile-external"><i class="ti ti-external-link"></i></span>';
          }
          echo '</a>';
       }
       echo '</div>';
   }

   /**
    * Bootstrap modal used to read an announcement (filled by intranet.js).
    */
   private static function showReaderModal(): void {
      ?>
      <div class="modal fade" id="intranet-announcement-modal" tabindex="-1" aria-hidden="true">
         <div class="modal-dialog modal-lg">
            <div class="modal-content">
               <div class="modal-header">
                  <h5 class="modal-title" id="intranet-announcement-title"></h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal"
                          aria-label="<?php echo self::esc(__('Close', 'intranet')); ?>"></button>
               </div>
               <div class="modal-body">
                  <p class="mb-2"><span class="badge" id="intranet-announcement-category"></span>
                     <small class="text-muted ms-2" id="intranet-announcement-meta"></small></p>
                  <div id="intranet-announcement-body"></div>
               </div>
               <div class="modal-footer">
                  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                     <?php echo __('Close', 'intranet'); ?>
                  </button>
               </div>
            </div>
         </div>
      </div>
      <?php
   }

   private static function authorName(int $users_id): string {
      if ($users_id <= 0) {
         return __('Intranet', 'intranet');
      }
      $name = getUserName($users_id);
      return is_string($name) ? $name : '';
   }

   private static function categoryBadgeClass(string $category): string {
      $map = [
         'notice'      => 'info',
         'news'        => 'primary',
         'maintenance' => 'warning',
         'event'       => 'success',
      ];
      return $map[$category] ?? 'secondary';
   }

   /**
    * Status badge for the management table.
    */
   public static function announcementStatusBadge(array $row): string {
      if (empty($row['is_active'])) {
         return '<span class="badge bg-secondary">' . __('Inactive', 'intranet') . '</span>';
      }
      if (PluginIntranetAnnouncement::isCurrentlyVisible($row)) {
         return '<span class="badge bg-success">' . __('Visible', 'intranet') . '</span>';
      }
      $now = date('Y-m-d H:i:s');
      if (!empty($row['date_begin']) && $row['date_begin'] > $now) {
         return '<span class="badge bg-info">' . __('Scheduled', 'intranet') . '</span>';
      }
      return '<span class="badge bg-dark">' . __('Expired', 'intranet') . '</span>';
   }
}
