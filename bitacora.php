<?php
include("db.php");


// Consulta para obtener los datos de la bitácora
$sql = "SELECT id, usuario, fecha, estado FROM bitacora ORDER BY fecha DESC";
$resultado = $conn->query($sql);

// Verificar si hubo un error en la consulta
if (!$resultado) {
    die("Error al consultar la bitácora: " . $conn->error);
}
?>





<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Bitácora de Accesos</title>
    <title>Usuarios</title>
  
</head>



 
<body>
    <div class="container">
        <h1>Bitácora </h1>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Usuario</th>
                    <th>Fecha</th>
                    <th>Estado</th>
                  
                </tr>
            </thead>
            <tbody>
                <?php while ($fila = $resultado->fetch_assoc()): ?>
                    <tr>
                        <td><?= $fila['id'] ?></td>
                        <td><?= htmlspecialchars($fila['usuario']) ?></td>
                        <td><?= $fila['fecha'] ?></td>
                        <td><?= $fila['estado'] ?></td>
                   
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        <a href="index.php" class="btn-volver">🔙 Volver</a>
    </div>
</body>
</html>
