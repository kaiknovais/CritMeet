<?php
// Caminho base dinâmico onde o script está
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');

// URI solicitada
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Remove o caminho base do início da URI e limpa as barras
$requestPath = trim(str_replace($basePath, '', $requestUri), '/');

// Se a URL for raiz, redireciona para pages/home/index.php
if ($requestPath === '') {
    require __DIR__ . '/pages/home/index.php';
    exit;
}

// Caminho dentro de src/pages/
$pagesPath = __DIR__ . "/pages/$requestPath/index.php";

// Caminho direto dentro de src/
$localPath = __DIR__ . "/$requestPath/index.php";

// Tenta carregar da pasta pages/
if (file_exists($pagesPath)) {
    require $pagesPath;
    exit;
}

// Tenta carregar de uma pasta no mesmo nível que router.php (ex: src/admin)
if (file_exists($localPath)) {
    require $localPath;
    exit;
}

// Se nada for encontrado, retorna erro 404
http_response_code(404);
echo "Página '$requestPath' não encontrada.";
