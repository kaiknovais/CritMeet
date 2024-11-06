<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configs</title>
    <script>
        function confirmDelete() {
            const confirmation = confirm("Tem certeza que deseja deletar sua conta?");
            if (confirmation) {
                const accountName = prompt("Por favor, insira o nome da sua conta para confirmar a exclusão:");
                if (accountName) {
                    window.location.href = "../../components/Delete/index.php";
                } else {
                    alert("Nome da conta não pode ser vazio.");
                }
            }
        }
    </script>
</head>

<body>
    <div>
        <h1>CritMeet</h1><br>

        <button onclick="window.location.href='../login/index.php'">Voltar</button><br>

        <a href="../editprofile/index.php">
            <button type="button">Editar Perfil</button>
        </a><br>

        <button type="button">Notificações</button><br>
        <button type="button">Conexões</button><br>
        <button type="button">Configurações de Segurança</button><br>

        <a href="../../components/Logout/index.php">
            <button type="button">Logout</button>
        </a><br>

        <button type="button">Suporte e Ajuda</button><br>
        <button type="button" onclick="confirmDelete()">Deletar Conta</button><br>
    </div>
</body>

</html>
