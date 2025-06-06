<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

define('ROOT_PATH', __DIR__);
require_once ROOT_PATH . '/config.php';

$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Remove o caminho base e normaliza
$requestPath = trim(str_replace($basePath, '', $requestUri), '/');

// Evita diretórios acima do root
if (strpos($requestPath, '..') !== false) {
    http_response_code(403);
    exit('Acesso proibido.');
}

$fullStaticPath = __DIR__ . '/' . $requestPath;

if (file_exists($fullStaticPath) && is_file($fullStaticPath)) {
    $mimeType = mime_content_type($fullStaticPath) ?: 'application/octet-stream';
    header("Content-Type: $mimeType");

    // Cache control
    header("Cache-Control: public, max-age=31536000");
    readfile($fullStaticPath);
    exit;
}

// Página inicial
if ($requestPath === '') {
    require __DIR__ . '/pages/home/index.php';
    exit;
}

// Página em /pages/{caminho}/index.php
$pagePath = __DIR__ . "/pages/$requestPath/index.php";
if (file_exists($pagePath)) {
    require $pagePath;
    exit;
}

// Página 404
http_response_code(404);
echo "Página '$requestPath' não encontrada.";
