<?php
$userAgent = $_SERVER['HTTP_USER_AGENT'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>リンゴジュース</title>
    <?php
    echo (strpos($userAgent, 'Mobile') !== false) ? 
    '<link rel="stylesheet" href="../../../assets/mobile.css">' : 
    '<link rel="stylesheet" href="../../../assets/desktop.css">';
    ?>
</head>
<body>