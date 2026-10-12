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

include ('../../../inc/includes.php');

// Ensure user is logged in
Session::checkLoginUser();

header('Content-Type: application/json; charset=UTF-8');

// Read-only endpoint (GET): CSRF tokens only apply to POSTs in GLPI, and this
// endpoint only ever reads data the caller is already allowed to see. The
// authorization layer is the plugin READ right plus the announcement
// visibility window below.
if (!PluginIntranetPortal::canView()) {
   http_response_code(403);
   echo json_encode(['success' => false, 'message' => __('Access denied.', 'intranet')]);
   exit;
}

$id = (int)($_GET['id'] ?? 0);
$ann = new PluginIntranetAnnouncement();
if ($id <= 0 || !$ann->getFromDB($id)) {
   http_response_code(404);
   echo json_encode(['success' => false, 'message' => __('Announcement not found.', 'intranet')]);
   exit;
}

$fields = $ann->fields;
if (!PluginIntranetAnnouncement::isCurrentlyVisible($fields)) {
   http_response_code(404);
   echo json_encode(['success' => false, 'message' => __('Announcement not found.', 'intranet')]);
   exit;
}

$categories = PluginIntranetAnnouncement::getCategories();
$users_id = (int)($fields['users_id'] ?? 0);
$author = ($users_id > 0) ? getUserName($users_id) : __('Intranet', 'intranet');

// JSON_HEX_* keep `<`, `>`, `&`, quotes and apostrophes out of the JSON so
// DB-sourced strings can never break out of the JS context. The content is
// plain text (stored without HTML) and is inserted with textContent on the
// client side.
$flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
echo json_encode([
   'success' => true,
   'announcement' => [
      'id'           => (int)$fields['id'],
      'title'        => (string)($fields['title'] ?? ''),
      'category'     => (string)($categories[$fields['category']] ?? $fields['category']),
      'category_key' => (string)($fields['category'] ?? ''),
      'content'      => (string)($fields['content'] ?? ''),
      'date'     => Html::convDateTime($fields['date_creation'] ?? ''),
      'author'   => is_string($author) ? $author : '',
      'sticky'   => !empty($fields['is_sticky']),
   ],
], $flags);
