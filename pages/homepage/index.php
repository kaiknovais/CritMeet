<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Página Inicial</title>
    <link rel="stylesheet" type="text/css" href="../../assets/mobile.css" media="screen and (max-width: 600px)">
    <link rel="stylesheet" type="text/css" href="../../assets/desktop.css" media="screen and (min-width: 601px)">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Agdasima:wght@400;700&display=swap" rel="stylesheet">
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="container">
        <h1>CritMeet</h1>
        <a href="../profile/index.php">
            <button type="button">Configurações</button>
        </a><br>
        <button type="button">Notificação de Acontecimentos</button><br>
        <button type="button">Notificação de Mensagens</button><br>
        <button type="button">Sessões Agendadas</button>
    </div>

    <?php include 'footer.php'; ?>
</body>
</html>