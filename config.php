<?php
function loadEnv($filePath) {
    if (!file_exists($filePath)) {
        throw new Exception("Arquivo de configuração não encontrado: $filePath");
    }

    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $config = [];

    foreach ($lines as $line) {
        // Ignora comentários e linhas inválidas
        if (strpos(trim($line), '#') === 0 || !str_contains($line, '=')) {
            continue;
        }

        list($key, $value) = explode('=', $line, 2);
        $config[trim($key)] = trim($value, " \t\n\r\0\x0B\"'");
    }

    return $config;
}

$config = loadEnv(__DIR__ . '/config.env');
$mysqli = new mysqli(
    $config['host'],
    $config['usuario'],
    $config['senha'],
    $config['banco']
);

if ($mysqli->connect_error) {
    die("Erro ao conectar: " . $mysqli->connect_error);
}

