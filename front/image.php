<?php

/**
 * -------------------------------------------------------------------------
 * GLPI Intranet Plugin
 * -------------------------------------------------------------------------
 *
 * Serves an uploaded quick-link icon image.
 *
 * @package   Plugin Intranet
 * @author    Leewan Meneses
 * @license   GPL-2.0+
 * @since     1.1.0
 *
 * -------------------------------------------------------------------------
 */

include ('../../../inc/includes.php');

Session::checkLoginUser();

if (!PluginIntranetPortal::canView()) {
   Html::displayRightError();
}

$link_id = (int)($_GET['link'] ?? 0);
if ($link_id <= 0) {
   http_response_code(404);
   exit;
}

$link = new PluginIntranetLink();
if (!$link->getFromDB($link_id)) {
   http_response_code(404);
   exit;
}

if (empty($link->fields['is_active'])) {
   http_response_code(404);
   exit;
}

$file = trim((string)($link->fields['image'] ?? ''));
if ($file === '' || preg_match('#[\\\\/]#', $file)) {
   http_response_code(404);
   exit;
}

$path = PluginIntranetLink::getImagePath($file);
if (!is_file($path)) {
   http_response_code(404);
   exit;
}

$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
$mime = [
   'png'  => 'image/png',
   'jpg'  => 'image/jpeg',
   'jpeg' => 'image/jpeg',
   'gif'  => 'image/gif',
   'webp' => 'image/webp',
   'ico'  => 'image/x-icon',
][$ext] ?? 'application/octet-stream';
if ($mime === 'application/octet-stream') {
   // Extension allow-list changed (e.g. legacy SVG): refuse to serve.
   http_response_code(404);
   exit;
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('Cache-Control: public, max-age=86400');
header('X-Content-Type-Options: nosniff');
readfile($path);
