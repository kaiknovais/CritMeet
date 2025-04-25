<?php
$request = $_SERVER['REQUEST_URI'];

// Serve arquivos existentes normalmente
$file = __DIR__ . '/pages' . $request;
if (file_exists($file)) {
    return false;
}

// Se for a raiz "/", redireciona para home/index.php
if ($request === '/' || $request === '') {
    require __DIR__ . '/pages/home/index.php';
    exit;
}

// Se não existir, mostra 404
http_response_code(404);
echo "Página não encontrada.";
