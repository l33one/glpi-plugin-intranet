<?php
// Coverage check: every __('...', 'intranet') string in the plugin code must
// exist as msgid in locales/pt_BR.po (and en_GB.po).
// CLI only.
if (PHP_SAPI !== 'cli') {
    exit(1);
}
$dir = $argv[1] ?? __DIR__ . '/..';

$code = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
foreach ($it as $file) {
    if (!$file->isFile()) {
        continue;
    }
    $path = $file->getPathname();
    if (substr($path, -4) !== '.php') {
        continue;
    }
    if (strpos($path, DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR) !== false) {
        continue;
    }
    $c = file_get_contents($path);
    preg_match_all("/__\(\s*'((?:[^'\\\\]|\\\\.)*)'/", $c, $m);
    foreach ($m[1] as $s) {
        $code[stripslashes($s)] = true;
    }
}
unset($code['']);

foreach (['pt_BR.po', 'en_GB.po'] as $po_file) {
    $po = file_get_contents($dir . '/locales/' . $po_file);
    preg_match_all('/^msgid "(.*)"$/m', $po, $m2);
    $have = [];
    foreach ($m2[1] as $s) {
        $have[stripcslashes($s)] = true;
    }
    $missing = array_diff_key($code, $have);
    echo $po_file . ': code=' . count($code) . ' missing=' . count($missing) . PHP_EOL;
    foreach ($missing as $k => $v) {
        echo '  MISSING: ' . $k . PHP_EOL;
    }
}
