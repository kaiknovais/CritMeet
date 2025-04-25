<?php
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$requestPath = trim($requestUri, '/');

// Se for vazio (raiz), redireciona para pages/home/index.php
if ($requestPath === '') {
    require __DIR__ . '/pages/home/index.php';
    exit;
}

// Tenta buscar dentro de pages/
$pagesPath = __DIR__ . "/pages/$requestPath/index.php";

// Tenta buscar na raiz do projeto
$rootPath = __DIR__ . "/$requestPath/index.php";

// Verifica se existe em pages/
if (file_exists($pagesPath)) {
    require $pagesPath;
    exit;
}

// Verifica se existe na raiz
if (file_exists($rootPath)) {
    require $rootPath;
    exit;
}

// Se não existir em nenhum lugar
http_response_code(404);
echo "Página '$requestPath' não encontrada.";
