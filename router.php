<?php
$request = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');

// Se estiver em branco, redireciona para 'home'
if ($request === '') {
    $request = 'home';
}

// Caminho completo até o index.php da página
$target = __DIR__ . "/pages/$request/index.php";

if (file_exists($target)) {
    require $target;
} else {
    http_response_code(404);
    echo "Página '$request' não encontrada.";
}
