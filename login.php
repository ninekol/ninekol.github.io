<?php
session_start();
include("db.php");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = $_POST['usuario'] ?? '';
    $clave = $_POST['contrasena'] ?? '';

    if (empty($usuario) || empty($clave)) {
        header("Location: index.php?error=empty");
        exit();
    }

    $sql = "SELECT id, contrasena, rol FROM usuarios WHERE usuario = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $usuario);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows === 1) {
        $fila = $resultado->fetch_assoc();

        if (password_verify($clave, $fila['contrasena'])) {
            // ✅ Login exitoso
            $_SESSION['usuario'] = $usuario;
            $_SESSION['rol'] = $fila['rol'];
            
            // Registro en bitácora
            $conn->query("INSERT INTO bitacora (usuario, estado) VALUES ('$usuario', 'Exitoso')");

            // Redirección por rol
            if ($fila['rol'] === 'admin') {
                header("Location: admin.php");
            } else {
                header("Location: consultor.php");
            }
            exit();
        } else {
            // ❌ Contraseña incorrecta
            $conn->query("INSERT INTO bitacora (usuario, estado) VALUES ('$usuario', 'Fallido - Contraseña incorrecta')");
            header("Location: index.php?error=credentials");
            exit();
        }
    } else {
        // ❌ Usuario no existe
        $conn->query("INSERT INTO bitacora (usuario, estado) VALUES ('$usuario', 'Fallido - Usuario no encontrado')");
        header("Location: index.php?error=credentials");
        exit();
    }

    $stmt->close();
    $conn->close();
}
?>