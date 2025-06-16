<?php
include("db.php");

// Verificar conexión
if (!$conn) {
    die("Error de conexión a la base de datos");
}

// Eliminar usuario si se hace clic en "Eliminar"
if (isset($_GET['eliminar'])) {
    $id = $_GET['eliminar'];
    $stmt = $conn->prepare("DELETE FROM usuarios WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header("Location: usuarios.php");
    exit;
}

// Obtener usuarios de la base de datos
$resultado = $conn->query("SELECT id, usuario, rol FROM usuarios");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Usuarios</title>
    <link href="https://fonts.googleapis.com/css2?family=Cardo&family=IM+Fell+English+SC&display=swap" rel="stylesheet">
   
</head>
<body>
    <div class="container">
        <h1>👤 Usuarios Registrados</h1>

        <table border="1">
            <tr>
                <th>ID</th>
                <th>Usuario</th>
                <th>Rol</th>
                <th>Acciones</th>
            </tr>
            <?php while ($fila = $resultado->fetch_assoc()): ?>
                <tr>
                    <td><?= $fila['id'] ?></td>
                    <td><?= htmlspecialchars($fila['usuario']) ?></td>
                    <td><?= htmlspecialchars($fila['rol']) ?></td>
                    <td>
                        <a href="editar_usuario.php?id=<?= $fila['id'] ?>" class="btn-editar">✏️ Editar</a>
                        <a href="usuarios.php?eliminar=<?= $fila['id'] ?>" class="btn-eliminar" onclick="return confirm('¿Seguro que quieres eliminar este usuario?');">🗑️ Eliminar</a>
                    </td>
                </tr>
            <?php endwhile; ?>
        </table>

        <a href="admin.php" class="btn-volver">⬅️ Volver al Panel</a>
    </div>
</body>
</html>