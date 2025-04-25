<?php
$request = $_SERVER['REQUEST_URI'];
$path = __DIR__ . '../pages' . $request;

// Serve o arquivo diretamente, se existir
if (file_exists($path) && !is_dir($path)) {
    return false;
}

// Se for "/", manda para pages/home/index.php
if ($request === '/' || $request === '') {
    require __DIR__ . '/pages/home/index.php';
    exit;
}

// Caso contrário, página não encontrada
http_response_code(404);
echo "Página não encontrada.";