<?php
// Captura o caminho da URL (sem query strings)
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Remove barras extras do começo/fim
$request = trim($requestUri, '/');

// Se for vazio, redireciona para a página 'home'
if ($request === '') {
    $request = 'home';
}

// Constrói o caminho: pages/[rota]/index.php
$targetFile = __DIR__ . "/../$request/index.php";

// Verifica se o arquivo existe
if (file_exists($targetFile)) {
    require $targetFile;
    exit;
}

// Se não existir, mostra erro
http_response_code(404);
echo "Página '$request' não encontrada.";
