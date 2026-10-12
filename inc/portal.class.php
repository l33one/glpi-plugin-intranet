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
     * Intranet panel for the main screen "Intranet" tab (Central).
     * Uses the new layout with fixed navbar, sidebar and structured content.
     */
    public static function showPanel(int $max_announcements = 3): void {
       /** @var array $CFG_GLPI */
       global $CFG_GLPI;

       echo '<div class="intranet-app" data-intranet-ajax="'
          . self::esc($CFG_GLPI['root_doc'] . '/plugins/intranet/ajax/announcement.php') . '">';
       self::showCustomCss();
       self::showNavbar();
       echo '<div class="intranet-app-body">';
       self::showSidebar();
       echo '<main class="intranet-main">';
       self::showBanners('panel');
       self::showBoardCompact(false, $max_announcements);
       echo '</main>';
       echo '</div>';
       echo '</div>';
       self::showReaderModal();
    }

    /**
     * Full portal page (Plugins > Intranet) with the same app layout.
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

       echo '<div class="intranet-app" data-intranet-ajax="'
          . self::esc($CFG_GLPI['root_doc'] . '/plugins/intranet/ajax/announcement.php') . '">';
       self::showCustomCss();
       self::showNavbar(true);
       echo '<div class="intranet-app-body">';
       self::showSidebar();
       echo '<main class="intranet-main">';
       self::showBanners('portal');
       self::showBoardCompact(true, 0, $category, $search);
       echo '</main>';
       echo '</div>';
       echo '</div>';
       self::showReaderModal();
    }

    /**
     * Fixed top navbar: hamburger, home/title, search, avatar + actions.
     *
     * @param boolean $is_portal True when rendering the full portal page
     */
    private static function showNavbar(bool $is_portal = false): void {
       /** @var array $CFG_GLPI */
       global $CFG_GLPI;

       $me = (int)Session::getLoginUserID();
       $user = new User();
       $user->getFromDB($me);
       $first = trim((string)($user->fields['firstname'] ?? ''));
       if ($first === '') {
          $first = trim((string)($user->fields['name'] ?? ''));
       }
       $avatar = ($me > 0 && !empty($user->fields['picture']))
          ? self::getUserAvatar($user->fields)
          : '';

       $portal_url = self::portalUrl();
       $pref_url = ($CFG_GLPI['root_doc'] ?? '') . '/front/preference.php';
       $config_url = self::configUrl();

       echo '<nav class="intranet-navbar navbar navbar-expand-lg navbar-light bg-light border-bottom">';
       echo '<div class="container-fluid">';

       // Left: hamburger + home + title
       echo '<button class="btn btn-link text-dark p-2 intranet-sidebar-toggle" type="button" aria-label="'
          . self::esc(__('Toggle sidebar', 'intranet')) . '"><i class="ti ti-menu-2"></i></button>';
       echo '<a class="navbar-brand d-flex align-items-center gap-2 px-0" href="' . self::esc($portal_url) . '">';
       echo '<i class="ti ti-home text-primary fs-4"></i>';
       echo '<span class="fw-semibold d-none d-md-inline">' . self::esc(__('Intranet', 'intranet')) . '</span>';
       echo '</a>';

       // Center: search (desktop only, hidden on mobile)
       echo '<div class="intranet-nav-search d-none d-lg-flex flex-grow-1 mx-4">';
       echo '<form class="w-100" action="' . self::esc($portal_url) . '" method="get">';
       echo '<div class="input-group">';
       echo '<input type="search" class="form-control rounded-start" name="q" placeholder="'
          . self::esc(__('Search announcements...', 'intranet')) . '" value="">';
       echo '<button class="btn btn-outline-secondary rounded-end" type="submit"><i class="ti ti-search"></i></button>';
       echo '</div>';
       echo '</form>';
       echo '</div>';

       // Right: avatar + name + icons + manage
       echo '<div class="d-flex align-items-center gap-2 ms-auto">';
       if ($is_portal && self::canManage()) {
          echo '<a class="btn btn-sm btn-outline-primary d-none d-lg-inline-flex" href="' . self::esc($config_url) . '">'
             . '<i class="ti ti-settings me-1"></i>' . self::esc(__('Manage content', 'intranet')) . '</a>';
       }
       echo '<div class="btn-group">';
       echo '<a class="btn btn-link text-dark p-2" href="#" data-bs-toggle="dropdown" aria-expanded="false">';
       if ($avatar !== '') {
          echo '<img src="' . self::esc($avatar) . '" alt="" class="intranet-nav-avatar">';
       } else {
          echo '<i class="ti ti-user-circle fs-4 text-muted"></i>';
       }
       if ($first !== '') {
          echo '<span class="ms-1 d-none d-md-inline fw-medium">' . self::esc($first) . '</span>';
       }
       echo '</a>';
       echo '<ul class="dropdown-menu dropdown-menu-end">';
       echo '<li><a class="dropdown-item" href="' . self::esc($pref_url) . '"><i class="ti ti-user-cog me-2"></i>'
          . self::esc(__('My settings', 'intranet')) . '</a></li>';
       echo '<li><a class="dropdown-item" href="' . self::esc($portal_url) . '"><i class="ti ti-home me-2"></i>'
          . self::esc(__('Open the intranet portal', 'intranet')) . '</a></li>';
       echo '<li><hr class="dropdown-divider"></li>';
       echo '<li><a class="dropdown-item" href="' . ($CFG_GLPI['root_doc'] ?? '') . '/front/logout.php"><i class="ti ti-logout me-2"></i>'
          . self::esc(__('Logout', 'intranet')) . '</a></li>';
       echo '</ul>';
       echo '</div>';
       echo '</div>';

       echo '</div>';
       echo '</nav>';
    }

    /**
     * Greeting bar: user photo, welcome message with the current date on one
     * side and the profile contact info (phone, e-mail, manager and groups)
     * on the other side, with a shortcut to the user preferences.
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

       $avatar = self::getUserAvatar($user_fields);
       $contact = ($me > 0 && !empty($user_fields)) ? self::getUserContact($me, $user_fields) : null;

       echo '<div class="card mb-2 intranet-hello"><div class="card-body">';
       echo '<div class="d-flex align-items-center gap-3 flex-wrap">';
       echo '<span class="intranet-avatar">';
       if ($avatar !== '') {
          echo '<img src="' . self::esc($avatar) . '" alt="">';
       } else {
          echo '<i class="ti ti-user"></i>';
       }
       echo '</span>';
       echo '<div class="intranet-hello-main">';
       echo '<div class="intranet-hello-title">' . $greeting
          . ($first !== '' ? ', ' . self::esc($first) : '') . '!</div>';
       echo '<div class="text-muted small">' . self::esc(Html::convDate(date('Y-m-d'))) . '</div>';
       echo '</div>';
       if ($contact !== null) {
          echo '<div class="ms-lg-auto intranet-hello-contact small">';
          self::contactInline('ti-mail', $contact['emails']);
          self::contactInline('ti-phone', $contact['phones']);
          self::contactInline('ti-user-check', $contact['manager']);
          self::contactInline('ti-users', $contact['groups']);
          $pref_url = ($CFG_GLPI['root_doc'] ?? '') . '/front/preference.php';
          echo '<a class="btn btn-sm btn-outline-primary mt-1" href="' . self::esc($pref_url) . '">'
             . '<i class="ti ti-pencil me-1"></i>' . self::esc(__('Edit my data', 'intranet')) . '</a>';
          echo '</div>';
       }
       echo '</div>';
       echo '</div></div>';
    }

/**
      * One inline contact value (icon + text) for the greeting bar.
      *
      * @param string       $icon
      * @param array|string $value
      */
     private static function contactInline(string $icon, $value): void {
        if (is_array($value)) {
           $value = array_values(array_filter(array_map('trim', array_map('strval', $value))));
           if (empty($value)) {
              return;
           }
           $value = implode(' · ', $value);
        }
        $value = trim((string)$value);
        if ($value === '') {
           return;
        }
        echo '<div class="intranet-hello-line"><i class="ti ' . self::esc($icon) . ' me-1 text-muted"></i>'
           . self::esc($value) . '</div>';
     }

    /**
     * Compact board for the new layout: main column only (sidebar is separate).
     * Contains: greeting bar (if enabled), metrics row (if enabled), forms + saved searches row,
     * then announcements or notice board.
     */
    private static function showBoardCompact(bool $portal, int $max_announcements = 3, string $category = '', string $search = ''): void {
       echo '<div class="intranet-board-compact">';
       if (PluginIntranetConfig::showGreeting()) {
          self::showGreeting(true);
       }
       if (PluginIntranetConfig::showMetrics()) {
          self::showMetrics();
       }
       echo '<div class="row g-2 mb-2">';
       echo '<div class="col-12 col-lg-6">';
       self::showForms();
       echo '</div>';
       echo '<div class="col-12 col-lg-6">';
       self::showSavedSearches();
       echo '</div>';
       echo '</div>';
       if ($portal) {
          self::showNoticeBoard($category, $search);
       } else {
          self::showAnnouncements($max_announcements, true);
       }
       echo '</div>';
    }

    /**
     * Sidebar with two areas:
     * 1. Thin left strip with stacked navigation icons (always visible)
     * 2. Main panel with GLPI logo + quick links (2 per row) + compact assets
     */
    private static function showSidebar(): void {
       $me = (int)Session::getLoginUserID();
       $portal_url = self::portalUrl();

       echo '<aside class="intranet-sidebar">';
       // Thin navigation strip (always visible)
       echo '<nav class="intranet-nav-strip" aria-label="Navegação principal">';
       $nav_items = [
          ['ti ti-chart-bar', 'Dashboard', $portal_url],
          ['ti ti-file-text', 'Formulários', $portal_url . '#forms'],
          ['ti ti-device-desktop', 'Ativos', $portal_url . '#assets'],
          ['ti ti-users', 'Usuários', $portal_url . '#users'],
          ['ti ti-chart-pie', 'Relatórios', $portal_url . '#reports'],
          ['ti ti-settings', 'Configurações', self::configUrl()],
       ];
       foreach ($nav_items as $item) {
          echo '<a class="intranet-nav-item" href="' . self::esc($item[2]) . '" title="' . self::esc($item[1]) . '">'
             . '<i class="' . self::esc($item[0]) . '"></i></a>';
       }
       // Quick access colored dots at bottom
       echo '<div class="intranet-nav-quick">';
       echo '<span class="intranet-quick-dot" style="background:#206bc4" title="Dashboard"></span>';
       echo '<span class="intranet-quick-dot" style="background:#37c97a" title="Novo chamado"></span>';
       echo '<span class="intranet-quick-dot" style="background:#f5a623" title="Base de conhecimento"></span>';
       echo '<span class="intranet-quick-dot" style="background:#e74c3c" title="Alertas"></span>';
       echo '</div>';
       echo '</nav>';

       // Main sidebar panel (collapsible content)
       echo '<div class="intranet-sidebar-panel">';
       $logo = self::getGlpiLogo();
       if ($logo !== '') {
          echo '<div class="intranet-sidebar-logo"><img src="' . self::esc($logo) . '" alt="GLPI"></div>';
       }
       if (PluginIntranetConfig::showLinks()) {
          self::showLinks(true, 'intranet-sidebar-links', false);
       }

       $assets = ($me > 0) ? self::getUserAssets($me) : [];
       echo '<h2 class="h6 mt-3 mb-2 px-3"><i class="ti ti-device-desktop me-1"></i>'
          . self::esc(__('My assets', 'intranet')) . '</h2>';
       if (empty($assets)) {
          echo '<p class="text-muted small mb-3 px-3">' . self::esc(__('No assets assigned to you.', 'intranet')) . '</p>';
       } else {
          echo '<ul class="list-unstyled mb-3 px-3 intranet-side-assets">';
          foreach (array_slice($assets, 0, 6) as $asset) {
             echo '<li class="text-truncate small"><i class="' . self::esc($asset['icon']) . ' me-1 text-muted"></i>';
             if (!empty($asset['url'])) {
                echo '<a href="' . self::esc($asset['url']) . '">' . self::esc($asset['label']) . '</a>';
             } else {
                echo self::esc($asset['label']);
             }
             echo '</li>';
          }
          echo '</ul>';
       }
       echo '<div class="intranet-sidebar-footer">';
       echo '<small class="text-muted d-block text-center">' . self::esc(__('Intranet', 'intranet')) . '</small>';
       echo '</div>';
       echo '</div>';
       echo '</aside>';
    }

    /**
     * Notice board card with search + category filter (portal page).
     */
    private static function showNoticeBoard(string $category = '', string $search = ''): void {
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
    }

    /**
     * URL of the GLPI logo for the sidebar header: prefers the custom login
     * logo when present, then the stock GLPI 11 / GLPI 10 artwork.
     *
     * @return string Empty when no logo file is found
     */
    public static function getGlpiLogo(): string {
       /** @var array $CFG_GLPI */
       global $CFG_GLPI;

       if (!defined('GLPI_ROOT')) {
          return '';
       }
       $root = $CFG_GLPI['root_doc'] ?? '';
       $candidates = [
          'public/pics/login_logo_glpi.png'         => 'pics/login_logo_glpi.png',
          'public/pics/logos/logo-GLPI-100-black.png' => 'pics/logos/logo-GLPI-100-black.png',
          'public/pics/logos/logo-GLPI-100-grey.png'  => 'pics/logos/logo-GLPI-100-grey.png',
          'pics/logos/logo-GLPI-100-black.png'        => 'pics/logos/logo-GLPI-100-black.png',
       ];
       foreach ($candidates as $disk => $url) {
          if (is_file(GLPI_ROOT . '/' . $disk)) {
             return $root . '/' . $url;
          }
       }
       return '';
    }

    /**
     * Thumbnail URL of the user photo, or empty when the user has none.
     */
    public static function getUserAvatar(array $user_fields): string {
       $picture = trim((string)($user_fields['picture'] ?? ''));
       if ($picture === '' || !method_exists('User', 'getThumbnailURLForPicture')) {
          return '';
       }
       try {
          return (string)User::getThumbnailURLForPicture($picture);
       } catch (\Throwable $e) {
          return '';
       }
    }

    /**
     * Standard forms highlighted in GLPI: pinned forms plus forms published
     * as tiles on the assistance home, limited to active entity-visible ones.
     *
     * @return array List of {name, url}
     */
    public static function getPinnedForms(): array {
       /** @var DBmysql $DB */
       global $DB, $CFG_GLPI;

       $forms = [];
       if (!$DB->tableExists('glpi_forms_forms')) {
          return $forms;
       }
       try {
          $ids = [];
          if ($DB->fieldExists('glpi_forms_forms', 'is_pinned')) {
             $it = $DB->request([
                'SELECT' => ['id'],
                'FROM'   => 'glpi_forms_forms',
                'WHERE'  => [
                   'is_active'  => 1,
                   'is_deleted' => 0,
                   'is_pinned'  => 1,
                ] + getEntitiesRestrictCriteria('glpi_forms_forms'),
             ]);
             foreach ($it as $row) {
                $ids[(int)$row['id']] = true;
             }
          }
          if ($DB->tableExists('glpi_helpdesks_tiles_formtiles')) {
             $it = $DB->request([
                'SELECT' => ['forms_forms_id'],
                'FROM'   => 'glpi_helpdesks_tiles_formtiles',
             ]);
             foreach ($it as $row) {
                $ids[(int)$row['forms_forms_id']] = true;
             }
          }
          if (empty($ids)) {
             return $forms;
          }
          $where = [
             'glpi_forms_forms.id'         => array_keys($ids),
             'glpi_forms_forms.is_active'  => 1,
             'glpi_forms_forms.is_deleted' => 0,
          ];
          if ($DB->fieldExists('glpi_forms_forms', 'is_draft')) {
             $where['glpi_forms_forms.is_draft'] = 0;
          }
          $it = $DB->request([
             'SELECT' => ['glpi_forms_forms.id', 'glpi_forms_forms.name'],
             'FROM'   => 'glpi_forms_forms',
             'WHERE'  => $where + getEntitiesRestrictCriteria('glpi_forms_forms'),
             'ORDER'  => ['glpi_forms_forms.name'],
          ]);
          foreach ($it as $row) {
             $name = trim((string)($row['name'] ?? ''));
             if ($name === '') {
                continue;
             }
             $forms[] = [
                'name' => $name,
                'url'  => ($CFG_GLPI['root_doc'] ?? '') . '/Form/Render/' . (int)$row['id'],
             ];
          }
       } catch (\Throwable $e) {
          return [];
       }
       return $forms;
    }

    /**
     * List of standard GLPI forms.
     */
    private static function showForms(): void {
       $forms = self::getPinnedForms();
       echo '<div class="card mb-2 intranet-forms"><div class="card-body">';
       echo '<h2 class="h6 mb-2"><i class="ti ti-forms me-1"></i>'
          . self::esc(__('Forms', 'intranet')) . '</h2>';
       if (empty($forms)) {
          echo '<p class="text-muted small mb-0">' . self::esc(__('No forms to show.', 'intranet')) . '</p>';
       } else {
          echo '<ul class="list-group intranet-forms-list">';
          foreach ($forms as $form) {
             echo '<li class="list-group-item py-1 px-2">';
             echo '<a href="' . self::esc($form['url']) . '" class="d-flex align-items-center gap-2 text-truncate">';
             echo '<i class="ti ti-chevron-right text-muted"></i>';
             echo '<span class="text-truncate">' . self::esc($form['name']) . '</span>';
             echo '</a></li>';
          }
          echo '</ul>';
       }
       echo '</div></div>';
    }

    /**
     * Saved searches of the current user (personal order kept).
     *
     * @return array List of {name, itemtype, url, external}
     */
    public static function getMySavedSearches(): array {
       /** @var array $CFG_GLPI */
       global $CFG_GLPI;

       $searches = [];
       if (!class_exists('SavedSearch')) {
          return $searches;
       }
       try {
          $rows = (new SavedSearch())->getMine();
       } catch (\Throwable $e) {
          return $searches;
       }
       $uri_type = defined('SavedSearch::URI') ? (int)constant('SavedSearch::URI') : 2;
       foreach ($rows as $row) {
          if (!is_array($row)) {
             continue;
          }
          $name = trim((string)($row['name'] ?? ''));
          if ($name === '') {
             continue;
          }
          $item = [
             'name'     => $name,
             'itemtype' => trim((string)($row['itemtype'] ?? '')),
             'url'      => '',
             'external' => false,
          ];
          if ((int)($row['type'] ?? 0) === $uri_type) {
             $url = trim((string)($row['query'] ?? ''));
             if (!PluginIntranetLink::isExternalUrl($url)) {
                continue;
             }
             $item['url'] = $url;
             $item['external'] = true;
          } else {
             $itemtype = $item['itemtype'];
             if (
                $itemtype === '' || !class_exists($itemtype)
                || !is_subclass_of($itemtype, 'CommonDBTM')
                || !method_exists($itemtype, 'getSearchURL')
             ) {
                continue;
             }
             try {
                $item['url'] = $itemtype::getSearchURL() . '?savedsearches_id=' . (int)($row['id'] ?? 0);
             } catch (\Throwable $e) {
                continue;
             }
          }
          $searches[] = $item;
       }
       return $searches;
    }

    /**
     * List of the current user saved searches.
     */
    private static function showSavedSearches(): void {
       $searches = self::getMySavedSearches();
       echo '<div class="card mb-2 intranet-searches"><div class="card-body">';
       echo '<h2 class="h6 mb-2"><i class="ti ti-bookmark me-1"></i>'
          . self::esc(__('My saved searches', 'intranet')) . '</h2>';
       if (empty($searches)) {
          echo '<p class="text-muted small mb-0">' . self::esc(__('No saved searches.', 'intranet')) . '</p>';
       } else {
          echo '<ul class="list-group intranet-searches-list">';
          foreach ($searches as $search) {
             echo '<li class="list-group-item py-1 px-2">';
             echo '<a href="' . self::esc($search['url']) . '"'
                . ($search['external'] ? ' target="_blank" rel="noopener"' : '')
                . ' class="d-flex align-items-center gap-2">';
             echo '<i class="ti ti-search text-muted"></i>';
             echo '<span class="text-truncate">' . self::esc($search['name']) . '</span>';
             if ($search['itemtype'] !== '') {
                echo '<span class="badge bg-secondary ms-auto">' . self::esc($search['itemtype']) . '</span>';
             }
             echo '</a></li>';
          }
          echo '</ul>';
       }
       echo '</div></div>';
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
          // All cards on a single row on wide screens.
          echo '<div class="col-6 col-md-4 col-xl-2">';
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
     *
     * @param boolean $compact     Compact heading style
     * @param string  $extra_class Extra class for the grid (e.g. 2-per-row sidebar)
     * @param boolean $heading     Whether to print the section heading
     */
    private static function showLinks(bool $compact, string $extra_class = '', bool $heading = true): void {
       $links = PluginIntranetLink::getVisible();
       if (empty($links)) {
          return;
       }
       if ($heading && $compact) {
          echo '<h2 class="h6 mt-2 mb-2"><i class="ti ti-link me-1"></i>'
             . PluginIntranetLink::getTypeName(2) . '</h2>';
       }
       echo '<div class="intranet-links-grid'
          . ($extra_class !== '' ? ' ' . self::esc($extra_class) : '') . '">';
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
