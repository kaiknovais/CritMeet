<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Edit</title>
</head>
<body>
    <div>
        <h1>CritMeet</h1><br>

        <!-- Exibir a imagem se existir -->
        <?php if (!empty($user['image'])): ?>
            <h2>Imagem de Perfil</h2>
            <img src="data:image/jpeg;base64,<?php echo $user['image']; ?>" alt="Imagem de Perfil" style="max-width: 200px; max-height: 200px;"/><br>
        <?php endif; ?>

        <form method="POST" action="" enctype="multipart/form-data">
            <input type="file" name="image" accept="image/*" required /><br>
            <input type="text" name="name" placeholder="Nome" value="<?php echo htmlspecialchars($user['name']); ?>" required /><br>
            <input type="text" name="gender" placeholder="Gênero" value="<?php echo htmlspecialchars($user['gender']); ?>" required /><br>
            <input type="text" name="pronouns" placeholder="Pronomes" value="<?php echo htmlspecialchars($user['pronouns']); ?>" required /><br>
            <input type="text" name="preferences" placeholder="Preferências de Jogo" value="<?php echo htmlspecialchars($user['preferences']); ?>" required /><br>
            <button type="submit">Confirmar Alterações</button><br>
            <button onclick="window.location.href='../home/index.php'">Voltar</button><br>
        </form>
    </div>
</body>
</html>