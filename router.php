<?php
// Caminho base dinâmico do script
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');

// URL solicitada
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Remove o caminho base
$requestPath = trim(str_replace($basePath, '', $requestUri), '/');

// Caminho absoluto até o arquivo solicitado
$fullStaticPath = __DIR__ . '/' . $requestPath;

// Se o arquivo solicitado existe (CSS, JS, imagem etc.), serve diretamente
if (file_exists($fullStaticPath) && is_file($fullStaticPath)) {
    // Detecta tipo de conteúdo
    $mimeType = mime_content_type($fullStaticPath);
    header("Content-Type: $mimeType");
    readfile($fullStaticPath);
    exit;
}

// Se a URL for raiz, redireciona para pages/home/index.php
if ($requestPath === '') {
    require __DIR__ . '/pages/home/index.php';
    exit;
}

// Caminho para a página dentro de /pages/
$pagePath = __DIR__ . "/pages/$requestPath/index.php";

// Se existir a página, carrega
if (file_exists($pagePath)) {
    require $pagePath;
    exit;
}

// Página não encontrada
http_response_code(404);
echo "Página '$requestPath' não encontrada.";
