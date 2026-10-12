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

class PluginIntranetConfig extends CommonGLPI {

   public static $rightname = 'config';

   /** Configuration context used in the glpi_configs table */
   const CONFIG_CONTEXT = 'plugin:intranet';

   /**
    * Load config stored through GLPI's configuration API.
    * Returns defaults when nothing is stored yet.
    */
   public static function load(): array {
      $defaults = [
         'show_panel'        => 1,
         'show_greeting'     => 1,
         'show_metrics'      => 1,
         'show_links'        => 1,
         'max_announcements' => 3,
      ];
      $stored = \Config::getConfigurationValues(self::CONFIG_CONTEXT);
      $cfg = [];
      foreach (array_keys($defaults) as $key) {
         if (isset($stored[$key])) {
            $cfg[$key] = $stored[$key];
         }
      }
      return array_merge($defaults, $cfg);
   }

   /**
    * Persist config through GLPI's configuration API.
    */
   public static function save(array $data): bool {
      $allowed = ['show_panel', 'show_greeting', 'show_metrics', 'show_links', 'max_announcements'];
      $to_store = [];
      foreach ($allowed as $key) {
         if (array_key_exists($key, $data)) {
            $to_store[$key] = $data[$key];
         }
      }
      \Config::setConfigurationValues(self::CONFIG_CONTEXT, $to_store);
      return true;
   }

   public static function isPanelEnabled(): bool {
      $cfg = self::load();
      return !empty($cfg['show_panel']);
   }

   public static function showGreeting(): bool {
      $cfg = self::load();
      return !empty($cfg['show_greeting']);
   }

   public static function showMetrics(): bool {
      $cfg = self::load();
      return !empty($cfg['show_metrics']);
   }

   public static function showLinks(): bool {
      $cfg = self::load();
      return !empty($cfg['show_links']);
   }

   public static function getMaxAnnouncements(): int {
      $cfg = self::load();
      $value = (int)($cfg['max_announcements'] ?? 3);
      if ($value < 1) {
         return 1;
      }
      return min(10, $value);
   }

   public static function getTypeName($nb = 0) {
      return __('Intranet Configuration', 'intranet');
   }

   public static function getIcon() {
      return 'ti ti-settings';
   }

   /**
    * Read a boolean switch coming from the configuration form.
    */
   private static function readSwitch(string $key): bool {
      return isset($_POST[$key]) && in_array($_POST[$key], ['1', 'on', 'true'], true);
   }

   private static function esc($value): string {
      return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
   }

   /**
    * Render the settings page (Setup > Plugins > Intranet).
    *
    * The "General" tab needs the GLPI configuration right; the content tabs
    * need the plugin UPDATE right, so editors can publish without being
    * super-admins.
    *
    * @param array $options ['can_config' => bool, 'can_manage' => bool]
    */
   public function display($options = []) {
      $can_config = !empty($options['can_config']);
      $can_manage = !empty($options['can_manage']);

      $tabs = [];
      if ($can_config) {
         $tabs['general'] = __('General', 'intranet');
      }
      if ($can_manage) {
         $tabs['announcements'] = PluginIntranetAnnouncement::getTypeName(2);
         $tabs['links']         = PluginIntranetLink::getTypeName(2);
      }
      if (empty($tabs)) {
         Html::displayRightError();
         return false;
      }

      $tab = $_GET['tab'] ?? '';
      if (!is_string($tab) || !isset($tabs[$tab])) {
         $tab = array_key_first($tabs);
      }

      // CSRF for POSTs is enforced by GLPI core before this script runs; the
      // per-form token below is rendered with Session::getNewCSRFToken().
      if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['intranet_action'])) {
         $this->handlePost($_POST['intranet_action'], $can_config, $can_manage);
      }

      $self_url = self::esc($_SERVER['REQUEST_URI']);
      ?>
      <div class="container-fluid py-4 intranet-config" style="max-width: 960px;">
         <h3 class="mb-3">
            <i class="ti ti-settings"></i>
            <?php echo __('Intranet Configuration', 'intranet'); ?>
         </h3>

         <?php
         foreach (['intranet_success' => 'success', 'intranet_error' => 'error'] as $key => $kind) {
            $messages = $_SESSION[$key] ?? [];
            unset($_SESSION[$key]);
            foreach ((array)$messages as $message) {
               if ($kind === 'success') {
                  echo '<div class="alert alert-success alert-dismissible fade show" role="alert">'
                     . '<i class="ti ti-check me-1"></i>' . self::esc($message)
                     . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
               } else {
                  echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">'
                     . '<i class="ti ti-alert-triangle me-1"></i>' . self::esc($message)
                     . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
               }
            }
         }
         ?>

         <ul class="nav nav-tabs mb-3">
            <?php foreach ($tabs as $key => $label): ?>
               <li class="nav-item">
                  <a class="nav-link <?php echo $key === $tab ? 'active' : ''; ?>"
                     href="<?php echo PluginIntranetPortal::configUrl(['tab' => $key]); ?>">
                     <?php echo self::esc($label); ?>
                  </a>
               </li>
            <?php endforeach; ?>
         </ul>

         <?php
         if ($tab === 'general' && $can_config) {
            $this->showGeneralTab($self_url);
         } elseif ($tab === 'announcements' && $can_manage) {
            $this->showAnnouncementsTab($self_url);
         } elseif ($tab === 'links' && $can_manage) {
            $this->showLinksTab($self_url);
         } else {
            Html::displayRightError();
            return false;
         }
         ?>
      </div>
      <?php
      return true;
   }

   /**
    * Queue a feedback message for the next render (same request, no redirect).
    */
   private static function flash(string $message, bool $success): void {
      $key = $success ? 'intranet_success' : 'intranet_error';
      if (!isset($_SESSION[$key]) || !is_array($_SESSION[$key])) {
         $_SESSION[$key] = [];
      }
      $_SESSION[$key][] = $message;
   }

   /**
    * Process settings POSTs. Rights are re-checked per action: a hand-built
    * POST can never escalate from content editor to general settings.
    */
   private function handlePost($action, bool $can_config, bool $can_manage): void {
      if (!is_string($action)) {
         return;
      }
      switch ($action) {
         case 'save_general':
            if (!$can_config) {
               Html::displayRightError();
            }
            $max = (int)($_POST['max_announcements'] ?? 3);
            $max = min(10, max(1, $max));
            self::save([
               'show_panel'        => self::readSwitch('show_panel') ? 1 : 0,
               'show_greeting'     => self::readSwitch('show_greeting') ? 1 : 0,
               'show_metrics'      => self::readSwitch('show_metrics') ? 1 : 0,
               'show_links'        => self::readSwitch('show_links') ? 1 : 0,
               'max_announcements' => $max,
            ]);
            self::flash(__('Configuration saved successfully.', 'intranet'), true);
            break;

         case 'save_announcement':
            if (!$can_manage) {
               Html::displayRightError();
            }
            $this->saveAnnouncement();
            break;

         case 'delete_announcement':
            if (!$can_manage) {
               Html::displayRightError();
            }
            $id = (int)($_POST['id'] ?? 0);
            $ann = new PluginIntranetAnnouncement();
            if ($id > 0 && $ann->getFromDB($id) && $ann->delete(['id' => $id])) {
               self::flash(__('Announcement deleted.', 'intranet'), true);
            } else {
               self::flash(__('Could not delete the announcement.', 'intranet'), false);
            }
            break;

         case 'save_link':
            if (!$can_manage) {
               Html::displayRightError();
            }
            $this->saveLink();
            break;

          case 'delete_link':
             if (!$can_manage) {
                Html::displayRightError();
             }
             $id = (int)($_POST['id'] ?? 0);
             $link = new PluginIntranetLink();
             // pre_deleteItem() removes the uploaded icon image file.
             if ($id > 0 && $link->getFromDB($id) && $link->delete(['id' => $id], 1)) {
                self::flash(__('Link deleted.', 'intranet'), true);
             } else {
                self::flash(__('Could not delete the link.', 'intranet'), false);
             }
             break;
      }
   }

   private function saveAnnouncement(): void {
      $id = (int)($_POST['id'] ?? 0);
      $ann = new PluginIntranetAnnouncement();
      if ($id > 0 && !$ann->getFromDB($id)) {
         self::flash(__('Announcement not found.', 'intranet'), false);
         return;
      }
      $input = [
         'title'      => $_POST['title'] ?? '',
         'category'   => $_POST['category'] ?? 'notice',
         'content'    => $_POST['content'] ?? '',
         'is_sticky'  => $_POST['is_sticky'] ?? 0,
         'is_active'  => $_POST['is_active'] ?? 0,
         'date_begin' => $_POST['date_begin'] ?? '',
         'date_end'   => $_POST['date_end'] ?? '',
      ];
      if ($id > 0) {
         $input['id'] = $id;
         if ($ann->update($input)) {
            self::flash(__('Announcement saved successfully.', 'intranet'), true);
         } else {
            self::flash(__('Could not save the announcement.', 'intranet'), false);
         }
      } else {
         if ($ann->add($input)) {
            self::flash(__('Announcement saved successfully.', 'intranet'), true);
         } else {
            self::flash(__('Could not save the announcement.', 'intranet'), false);
         }
      }
   }

    private function saveLink(): void {
       $id = (int)($_POST['id'] ?? 0);
       $link = new PluginIntranetLink();
       if ($id > 0 && !$link->getFromDB($id)) {
          self::flash(__('Link not found.', 'intranet'), false);
          return;
       }
       $current_image = ($id > 0) ? (string)($link->fields['image'] ?? '') : '';
       $remove_image = !empty($_POST['remove_image']);
       $upload = $_FILES['image_file'] ?? [];
       $image = PluginIntranetLink::handleUploadedImage($upload, $current_image, $remove_image);
       if ($image === false) {
          self::flash(__('Could not save the link.', 'intranet'), false);
          return;
       }
       $input = [
          'name'       => $_POST['name'] ?? '',
          'url'        => $_POST['url'] ?? '',
          'icon'       => $_POST['icon'] ?? 'ti ti-link',
          'image'      => $image,
          'sort_order' => $_POST['sort_order'] ?? 0,
          'is_active'  => $_POST['is_active'] ?? 0,
       ];
      if ($id > 0) {
         $input['id'] = $id;
         if ($link->update($input)) {
            self::flash(__('Link saved successfully.', 'intranet'), true);
         } else {
            self::flash(__('Could not save the link.', 'intranet'), false);
         }
      } else {
         if ($link->add($input)) {
            self::flash(__('Link saved successfully.', 'intranet'), true);
         } else {
            self::flash(__('Could not save the link.', 'intranet'), false);
         }
      }
   }

   private function showGeneralTab(string $self_url): void {
      $cfg = self::load();
      ?>
      <form method="post" action="<?php echo $self_url; ?>">
         <?php echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]); ?>
         <input type="hidden" name="intranet_action" value="save_general">

         <div class="card">
             <div class="card-header">
                <i class="ti ti-layout-dashboard me-1"></i>
                <?php echo __('Intranet tab', 'intranet'); ?>
             </div>
            <div class="card-body">
               <div class="form-check form-switch mb-3">
                  <input class="form-check-input" type="checkbox" role="switch"
                         id="show_panel" name="show_panel" value="1"
                         <?php echo !empty($cfg['show_panel']) ? 'checked' : ''; ?>>
                   <label class="form-check-label fw-semibold" for="show_panel">
                      <?php echo __('Show the Intranet tab on the main screen', 'intranet'); ?>
                   </label>
               </div>

               <div class="form-check form-switch mb-3">
                  <input class="form-check-input" type="checkbox" role="switch"
                         id="show_greeting" name="show_greeting" value="1"
                         <?php echo !empty($cfg['show_greeting']) ? 'checked' : ''; ?>>
                  <label class="form-check-label fw-semibold" for="show_greeting">
                     <?php echo __('Show the welcome message with the user name', 'intranet'); ?>
                  </label>
               </div>

               <div class="form-check form-switch mb-3">
                  <input class="form-check-input" type="checkbox" role="switch"
                         id="show_metrics" name="show_metrics" value="1"
                         <?php echo !empty($cfg['show_metrics']) ? 'checked' : ''; ?>>
                   <label class="form-check-label fw-semibold" for="show_metrics">
                      <?php echo __('Show my ticket cards', 'intranet'); ?>
                   </label>
                   <div class="form-text">
                      <?php echo __('Cards respect each user\'s permissions: ticket numbers only appear for profiles that can view tickets.', 'intranet'); ?>
                   </div>
               </div>

               <div class="form-check form-switch mb-3">
                  <input class="form-check-input" type="checkbox" role="switch"
                         id="show_links" name="show_links" value="1"
                         <?php echo !empty($cfg['show_links']) ? 'checked' : ''; ?>>
                  <label class="form-check-label fw-semibold" for="show_links">
                     <?php echo __('Show quick links on the panel', 'intranet'); ?>
                  </label>
               </div>

               <div class="mb-3" style="max-width: 220px;">
                  <label for="max_announcements" class="form-label fw-semibold">
                     <?php echo __('Announcements shown on the panel', 'intranet'); ?>
                  </label>
                  <input type="number" class="form-control" id="max_announcements"
                         name="max_announcements" min="1" max="10"
                         value="<?php echo self::getMaxAnnouncements(); ?>">
               </div>
            </div>
         </div>

         <div class="mt-3">
            <button type="submit" class="btn btn-primary">
               <i class="ti ti-device-floppy me-1"></i>
               <?php echo __('Save', 'intranet'); ?>
            </button>
         </div>
      </form>
      <?php
   }

   private function showAnnouncementsTab(string $self_url): void {
      $edit_id = (int)($_GET['edit'] ?? 0);
      $editing = null;
      if ($edit_id > 0) {
         $candidate = new PluginIntranetAnnouncement();
         if ($candidate->getFromDB($edit_id) && (int)$candidate->fields['is_deleted'] === 0) {
            $editing = $candidate->fields;
         }
      }
      $categories = PluginIntranetAnnouncement::getCategories();
      $rows = PluginIntranetAnnouncement::getAllForManagement();
      ?>
      <div class="card mb-3">
         <div class="card-header">
            <i class="ti ti-speakerphone me-1"></i>
            <?php echo $editing ? __('Edit announcement', 'intranet') : __('New announcement', 'intranet'); ?>
         </div>
         <div class="card-body">
            <form method="post" action="<?php echo $self_url; ?>">
               <?php echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]); ?>
               <input type="hidden" name="intranet_action" value="save_announcement">
               <?php if ($editing): ?>
                  <input type="hidden" name="id" value="<?php echo (int)$editing['id']; ?>">
               <?php endif; ?>

               <div class="row g-3">
                  <div class="col-12 col-lg-8">
                     <label for="ann_title" class="form-label fw-semibold">
                        <?php echo __('Title', 'intranet'); ?>
                     </label>
                     <input type="text" class="form-control" id="ann_title" name="title"
                            maxlength="255" required
                            value="<?php echo self::esc($editing['title'] ?? ''); ?>">
                  </div>
                  <div class="col-12 col-lg-4">
                     <label for="ann_category" class="form-label fw-semibold">
                        <?php echo __('Category', 'intranet'); ?>
                     </label>
                     <select class="form-select" id="ann_category" name="category">
                        <?php foreach ($categories as $key => $label): ?>
                           <option value="<?php echo self::esc($key); ?>"
                              <?php echo ($editing['category'] ?? 'notice') === $key ? 'selected' : ''; ?>>
                              <?php echo self::esc($label); ?>
                           </option>
                        <?php endforeach; ?>
                     </select>
                  </div>
                  <div class="col-12">
                     <label for="ann_content" class="form-label fw-semibold">
                        <?php echo __('Content', 'intranet'); ?>
                     </label>
                     <textarea class="form-control" id="ann_content" name="content" rows="4"
                               maxlength="65535"><?php echo self::esc($editing['content'] ?? ''); ?></textarea>
                     <div class="form-text">
                        <?php echo __('Plain text: line breaks are kept, no HTML is needed.', 'intranet'); ?>
                     </div>
                  </div>
                  <div class="col-12 col-md-6">
                     <label for="ann_begin" class="form-label fw-semibold">
                        <?php echo __('Visible from', 'intranet'); ?>
                     </label>
                     <input type="date" class="form-control" id="ann_begin" name="date_begin"
                            value="<?php echo self::esc(self::datePart($editing['date_begin'] ?? null)); ?>">
                  </div>
                  <div class="col-12 col-md-6">
                     <label for="ann_end" class="form-label fw-semibold">
                        <?php echo __('Visible until', 'intranet'); ?>
                     </label>
                     <input type="date" class="form-control" id="ann_end" name="date_end"
                            value="<?php echo self::esc(self::datePart($editing['date_end'] ?? null)); ?>">
                     <div class="form-text">
                        <?php echo __('Leave empty for no time limit.', 'intranet'); ?>
                     </div>
                  </div>
                  <div class="col-12 d-flex flex-wrap gap-4">
                     <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch"
                               id="ann_sticky" name="is_sticky" value="1"
                               <?php echo !empty($editing['is_sticky']) ? 'checked' : ''; ?>>
                        <label class="form-check-label fw-semibold" for="ann_sticky">
                           <?php echo __('Pin to the top', 'intranet'); ?>
                        </label>
                     </div>
                     <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch"
                               id="ann_active" name="is_active" value="1"
                               <?php echo ($editing === null || !empty($editing['is_active'])) ? 'checked' : ''; ?>>
                        <label class="form-check-label fw-semibold" for="ann_active">
                           <?php echo __('Active', 'intranet'); ?>
                        </label>
                     </div>
                  </div>
               </div>

               <div class="mt-3 d-flex gap-2">
                  <button type="submit" class="btn btn-primary">
                     <i class="ti ti-device-floppy me-1"></i>
                     <?php echo __('Save', 'intranet'); ?>
                  </button>
                  <?php if ($editing): ?>
                     <a class="btn btn-outline-secondary"
                        href="<?php echo PluginIntranetPortal::configUrl(['tab' => 'announcements']); ?>">
                        <?php echo __('Cancel', 'intranet'); ?>
                     </a>
                  <?php endif; ?>
               </div>
            </form>
         </div>
      </div>

      <div class="card">
         <div class="card-header">
            <i class="ti ti-list me-1"></i>
            <?php echo __('Published announcements', 'intranet'); ?>
         </div>
         <div class="card-body p-0">
            <div class="table-responsive">
               <table class="table table-hover table-striped mb-0">
                  <thead>
                     <tr>
                        <th><?php echo __('Title', 'intranet'); ?></th>
                        <th><?php echo __('Category', 'intranet'); ?></th>
                        <th><?php echo __('Status', 'intranet'); ?></th>
                        <th><?php echo __('Actions', 'intranet'); ?></th>
                     </tr>
                  </thead>
                  <tbody>
                     <?php if (empty($rows)): ?>
                        <tr>
                           <td colspan="4" class="text-center text-muted py-3">
                              <?php echo __('No announcements yet.', 'intranet'); ?>
                           </td>
                        </tr>
                     <?php endif; ?>
                     <?php foreach ($rows as $row): ?>
                        <tr>
                           <td>
                              <?php if (!empty($row['is_sticky'])): ?>
                                 <i class="ti ti-pin text-warning me-1" title="<?php echo __('Pinned', 'intranet'); ?>"></i>
                              <?php endif; ?>
                              <?php echo self::esc($row['title']); ?>
                           </td>
                           <td><?php echo self::esc($categories[$row['category']] ?? $row['category']); ?></td>
                           <td>
                              <?php echo PluginIntranetPortal::announcementStatusBadge($row); ?>
                           </td>
                           <td class="text-nowrap">
                              <a class="btn btn-sm btn-outline-primary"
                                 href="<?php echo PluginIntranetPortal::configUrl(['tab' => 'announcements', 'edit' => (int)$row['id']]); ?>">
                                 <i class="ti ti-pencil"></i>
                                 <span class="d-none d-lg-inline"><?php echo __('Edit', 'intranet'); ?></span>
                              </a>
                              <form method="post" action="<?php echo $self_url; ?>" class="d-inline"
                                    data-intranet-confirm="<?php echo self::esc(__('Delete this announcement?', 'intranet')); ?>">
                                 <?php echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]); ?>
                                 <input type="hidden" name="intranet_action" value="delete_announcement">
                                 <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                                 <button type="submit" class="btn btn-sm btn-outline-danger">
                                    <i class="ti ti-trash"></i>
                                    <span class="d-none d-lg-inline"><?php echo __('Delete', 'intranet'); ?></span>
                                 </button>
                              </form>
                           </td>
                        </tr>
                     <?php endforeach; ?>
                  </tbody>
               </table>
            </div>
         </div>
      </div>
      <?php
   }

   private function showLinksTab(string $self_url): void {
      $edit_id = (int)($_GET['edit'] ?? 0);
      $editing = null;
      if ($edit_id > 0) {
         $candidate = new PluginIntranetLink();
         if ($candidate->getFromDB($edit_id)) {
            $editing = $candidate->fields;
         }
      }
      $icons = PluginIntranetLink::getIcons();
      $rows = PluginIntranetLink::getAllForManagement();
      ?>
      <div class="card mb-3">
         <div class="card-header">
            <i class="ti ti-link me-1"></i>
            <?php echo $editing ? __('Edit link', 'intranet') : __('New link', 'intranet'); ?>
         </div>
          <div class="card-body">
             <form method="post" action="<?php echo $self_url; ?>" enctype="multipart/form-data">
                <?php echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]); ?>
                <input type="hidden" name="intranet_action" value="save_link">
               <?php if ($editing): ?>
                  <input type="hidden" name="id" value="<?php echo (int)$editing['id']; ?>">
               <?php endif; ?>

               <div class="row g-3">
                  <div class="col-12 col-lg-6">
                     <label for="link_name" class="form-label fw-semibold">
                        <?php echo __('Name', 'intranet'); ?>
                     </label>
                     <input type="text" class="form-control" id="link_name" name="name"
                            maxlength="255" required
                            value="<?php echo self::esc($editing['name'] ?? ''); ?>">
                  </div>
                  <div class="col-12 col-lg-6">
                     <label for="link_url" class="form-label fw-semibold">
                        <?php echo __('URL', 'intranet'); ?>
                     </label>
                     <input type="text" class="form-control" id="link_url" name="url"
                            maxlength="1024" required dir="ltr"
                            placeholder="https://... /front/..."
                            value="<?php echo self::esc($editing['url'] ?? ''); ?>">
                     <div class="form-text">
                        <?php echo __('Full http(s) address or an internal path starting with "/".', 'intranet'); ?>
                     </div>
                  </div>
                   <div class="col-12 col-md-6">
                      <label for="link_icon" class="form-label fw-semibold">
                         <?php echo __('Icon', 'intranet'); ?>
                      </label>
                      <select class="form-select" id="link_icon" name="icon">
                         <?php foreach ($icons as $class => $label): ?>
                            <option value="<?php echo self::esc($class); ?>"
                               <?php echo ($editing['icon'] ?? 'ti ti-link') === $class ? 'selected' : ''; ?>>
                               <?php echo self::esc($label); ?>
                            </option>
                         <?php endforeach; ?>
                      </select>
                      <div class="form-text">
                         <?php echo __('Used when no image is uploaded.', 'intranet'); ?>
                      </div>
                   </div>
                   <div class="col-12 col-md-6">
                      <label for="link_image" class="form-label fw-semibold">
                         <?php echo __('Icon image (optional)', 'intranet'); ?>
                      </label>
                      <?php if (!empty($editing['image']) && PluginIntranetLink::hasImage($editing)): ?>
                         <div class="mb-2 d-flex align-items-center gap-2">
                            <img src="<?php echo self::esc(PluginIntranetLink::imageUrl($editing)); ?>"
                                 alt="" class="intranet-link-thumb">
                            <small class="text-muted"><?php echo self::esc(basename((string)$editing['image'])); ?></small>
                         </div>
                         <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="link_remove_image"
                                   name="remove_image" value="1">
                            <label class="form-check-label" for="link_remove_image">
                               <?php echo __('Remove the current image', 'intranet'); ?>
                            </label>
                         </div>
                      <?php endif; ?>
                      <input type="file" class="form-control" id="link_image" name="image_file"
                             accept=".png,.jpg,.jpeg,.gif,.webp,.svg,.ico,image/*">
                      <div class="form-text">
                         <?php echo __('PNG, JPG, GIF, WEBP, SVG or ICO, max 2 MB. Replaces the icon above when set.', 'intranet'); ?>
                      </div>
                   </div>
                  <div class="col-12 col-md-3">
                     <label for="link_order" class="form-label fw-semibold">
                        <?php echo __('Order', 'intranet'); ?>
                     </label>
                     <input type="number" class="form-control" id="link_order" name="sort_order"
                            min="0" max="9999" value="<?php echo (int)($editing['sort_order'] ?? 0); ?>">
                  </div>
                  <div class="col-12 col-md-3 d-flex align-items-end">
                     <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" role="switch"
                               id="link_active" name="is_active" value="1"
                               <?php echo ($editing === null || !empty($editing['is_active'])) ? 'checked' : ''; ?>>
                        <label class="form-check-label fw-semibold" for="link_active">
                           <?php echo __('Active', 'intranet'); ?>
                        </label>
                     </div>
                  </div>
               </div>

               <div class="mt-3 d-flex gap-2">
                  <button type="submit" class="btn btn-primary">
                     <i class="ti ti-device-floppy me-1"></i>
                     <?php echo __('Save', 'intranet'); ?>
                  </button>
                  <?php if ($editing): ?>
                     <a class="btn btn-outline-secondary"
                        href="<?php echo PluginIntranetPortal::configUrl(['tab' => 'links']); ?>">
                        <?php echo __('Cancel', 'intranet'); ?>
                     </a>
                  <?php endif; ?>
               </div>
            </form>
         </div>
      </div>

      <div class="card">
         <div class="card-header">
            <i class="ti ti-list me-1"></i>
            <?php echo __('Configured links', 'intranet'); ?>
         </div>
         <div class="card-body p-0">
            <div class="table-responsive">
               <table class="table table-hover table-striped mb-0">
                  <thead>
                     <tr>
                        <th><?php echo __('Name', 'intranet'); ?></th>
                        <th><?php echo __('URL', 'intranet'); ?></th>
                        <th><?php echo __('Status', 'intranet'); ?></th>
                        <th><?php echo __('Actions', 'intranet'); ?></th>
                     </tr>
                  </thead>
                  <tbody>
                     <?php if (empty($rows)): ?>
                        <tr>
                           <td colspan="4" class="text-center text-muted py-3">
                              <?php echo __('No links yet.', 'intranet'); ?>
                           </td>
                        </tr>
                     <?php endif; ?>
                      <?php foreach ($rows as $row): ?>
                         <tr>
                            <td>
                               <?php if (PluginIntranetLink::hasImage($row)): ?>
                                  <img src="<?php echo self::esc(PluginIntranetLink::imageUrl($row)); ?>"
                                       alt="" class="intranet-link-thumb me-1">
                               <?php else: ?>
                                  <i class="<?php echo self::esc($row['icon']); ?> me-1"></i>
                               <?php endif; ?>
                               <?php echo self::esc($row['name']); ?>
                            </td>
                           <td class="text-break" dir="ltr">
                              <small><?php echo self::esc($row['url']); ?></small>
                           </td>
                           <td>
                              <?php if (!empty($row['is_active'])): ?>
                                 <span class="badge bg-success"><?php echo __('Active', 'intranet'); ?></span>
                              <?php else: ?>
                                 <span class="badge bg-secondary"><?php echo __('Inactive', 'intranet'); ?></span>
                              <?php endif; ?>
                           </td>
                           <td class="text-nowrap">
                              <a class="btn btn-sm btn-outline-primary"
                                 href="<?php echo PluginIntranetPortal::configUrl(['tab' => 'links', 'edit' => (int)$row['id']]); ?>">
                                 <i class="ti ti-pencil"></i>
                                 <span class="d-none d-lg-inline"><?php echo __('Edit', 'intranet'); ?></span>
                              </a>
                              <form method="post" action="<?php echo $self_url; ?>" class="d-inline"
                                    data-intranet-confirm="<?php echo self::esc(__('Delete this link?', 'intranet')); ?>">
                                 <?php echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]); ?>
                                 <input type="hidden" name="intranet_action" value="delete_link">
                                 <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                                 <button type="submit" class="btn btn-sm btn-outline-danger">
                                    <i class="ti ti-trash"></i>
                                    <span class="d-none d-lg-inline"><?php echo __('Delete', 'intranet'); ?></span>
                                 </button>
                              </form>
                           </td>
                        </tr>
                     <?php endforeach; ?>
                  </tbody>
               </table>
            </div>
         </div>
      </div>
      <?php
   }

   /**
    * Extract the Y-m-d part of a datetime value for <input type="date">.
    */
   private static function datePart($value): string {
      $value = (string)($value ?? '');
      if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $value, $m)) {
         return $m[1];
      }
      return '';
   }
}
