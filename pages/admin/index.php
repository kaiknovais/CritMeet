<?php
include('../../config.php');
session_start();

// Consulta para buscar todos os usuários
$sql = "SELECT * FROM users";
$result = $mysqli->query($sql);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista de Usuários</title>
    <style>
        table {
            width: 100%;
            border-collapse: collapse;
        }
        table, th, td {
            border: 1px solid #ddd;
        }
        th, td {
            padding: 10px;
            text-align: left;
        }
        th {
            background-color: #f4f4f4;
        }
        button {
            padding: 5px 10px;
            cursor: pointer;
        }
        button.view {
            background-color: #007bff;
            color: white;
            border: none;
        }
        button.edit {
            background-color: #ffc107;
            color: black;
            border: none;
        }
        button.delete {
            background-color: #dc3545;
            color: white;
            border: none;
        }
    </style>
</head>
<body>
    <h1>Lista de Usuários</h1>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Nome</th>
                <th>Email</th>
                <th>Admin</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($result && $result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= $row['id'] ?></td>
                        <td><?= htmlspecialchars($row['name']) ?></td>
                        <td><?= htmlspecialchars($row['email']) ?></td>
                        <td><?= $row['admin'] ? 'Sim' : 'Não' ?></td>
                        <td class="actions">
                            <a href="../../components/ProfileAdmin/index.php?id=<?= $row['id'] ?>"><button class="view">Ver Perfil</button></a>
                            <!-- Atualize o link do botão "Editar" para o componente EditAdmin -->
                            <a href="../../components/EditAdmin/index.php?id=<?= $row['id'] ?>"><button class="edit">Editar</button></a>
                            <a href="../../components/DeleteAdmin/index.php?id=<?= $row['id'] ?>" onclick="return confirm('Tem certeza que deseja excluir este usuário?')">
                                <button class="delete">Excluir</button>
                            </a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5">Nenhum usuário encontrado.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</body>
</html>