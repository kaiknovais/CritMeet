<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home</title>
    <link rel="stylesheet" href="../../../assets/mobile.css" media="screen and (max-width: 600px)">
    <link rel="stylesheet" href="../../../assets/desktop.css" media="screen and (min-width: 601px)">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'header.php'; 
    require_once __DIR__ . '/../../config.php';
    ?>
    
    <div class="container">
        <h1>CritMeet</h1>
        <h2>Saudações!</h2>
        <h2>Vamos começar a sua jornada!</h2>
        <a href="../../login/">
            <button type="button">INICIAR</button>
        </a>
    </div>

    <?php include 'footer.php'; ?>

</body>
</html>