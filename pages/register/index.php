<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar</title>
    <link rel="stylesheet" type="text/css" href="../../assets/mobile.css" media="screen and (max-width: 600px)">
    <link rel="stylesheet" type="text/css" href="../../assets/desktop.css" media="screen and (min-width: 601px)">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Agdasima:wght@400;700&display=swap" rel="stylesheet">
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="container">
        <h1>CritMeet</h1><br>
        <input type="text" placeholder="Nome" /><br>
        <input type="text" placeholder="Gênero" /><br>
        <input type="text" placeholder="Pronomes" /><br>
        <input type="text" placeholder="Email" /><br>
        <input type="password" placeholder="Senha" /><br>
        <input type="password" placeholder="Confirmar Senha" /><br>
        <button type="button">Registrar</button><br>
        
        <?php
        include '../../components/NavBack/index.php';
        ?>
    </div>
    <?php include 'footer.php'; ?>
</body>
</html>