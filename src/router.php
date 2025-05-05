<?php
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$requestPath = trim(str_replace($basePath, '', $requestUri), '/');

// Se for raiz, redireciona para src/pages/home/index.php
if ($requestPath === '' || $requestPath === 'index.php') {
    require __DIR__ . '/../pages/home/index.php';
    exit;
}

// Caminho dentro de src/pages
$pagesPath = __DIR__ . "/../pages/$requestPath/index.php";

// Caminho direto dentro de src/
$localPath = __DIR__ . "/../$requestPath/index.php";

// Verifica se existe em src/pages
if (file_exists($pagesPath)) {
    require $pagesPath;
    exit;
}

// Verifica se existe direto em src/
if (file_exists($localPath)) {
    require $localPath;
    exit;
}

// Se não encontrar, mostra erro
http_response_code(404);
echo "Página '$requestPath' não encontrada.";
