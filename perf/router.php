<?php
// Routeur minimal pour `php -S` : sert les fichiers statiques de public/,
// délègue tout le reste à public/index.php (Laravel). Robuste sur Windows.
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');
$pub = __DIR__ . '/../public';
if ($uri !== '/' && $uri !== '' && file_exists($pub . $uri) && !is_dir($pub . $uri)) {
    return false; // laisse le serveur intégré servir l'asset
}
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $pub . '/index.php';
require $pub . '/index.php';
