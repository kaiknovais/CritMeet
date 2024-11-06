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
    
    <header class="header">
        <h1>CritMeet</h1>
        <?php include '../../components/Profile/index.php'; ?>
    </header>
    
    <div class="container">
        <nav class="side-menu">
            <h2>Menu</h2>
            <ul>
                <li><a href="#">Home</a></li>
                <li><a href="#">About</a></li>
                <li><a href="../settings/index.php">Configurações</a></li>
                <li><a href="#">Contact</a></li>
            </ul>
        </nav>
        <div class="content">
            <div class="column"><button type="button">Notificação de Acontecimentos</button></div>
            <div class="column"><button type="button">Notificação de Mensagens</button></div>
            <div class="column"><button type="button">Sessões Agendadas</button></div>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    
</body>
</html>