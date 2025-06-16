<?php
// Iniciar buffer de salida inmediatamente
ob_start();
session_start();

// Configuración de errores (solo para desarrollo)
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

include("db.php");

// Incluir PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

// Inicializar carrito
if (!isset($_SESSION['carrito'])) {
    $_SESSION['carrito'] = [];
}

// Función para limpiar buffers de salida
function cleanOutputBuffers() {
    while (ob_get_level()) {
        ob_end_clean();
    }
}

// Agregar producto al carrito
if (isset($_POST['agregar'])) {
    $id = intval($_POST['agregar']);
    $stmt = $conn->prepare("SELECT id, nombre, precio, imagen, stock FROM productos WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $producto = $stmt->get_result()->fetch_assoc();

    if ($producto && $producto['stock'] > 0) {
        $existe = false;
        foreach ($_SESSION['carrito'] as &$item) {
            if ($item['id'] == $id) {
                $item['cantidad'] += 1;
                $existe = true;
                break;
            }
        }
        if (!$existe) {
            $_SESSION['carrito'][] = [
                'id' => $producto['id'],
                'nombre' => $producto['nombre'],
                'precio' => $producto['precio'],
                'imagen' => $producto['imagen'],
                'cantidad' => 1
            ];
        }
    } else {
        $_SESSION['mensaje'] = 'El producto no está disponible en stock';
    }
    header("Location: ".$_SERVER['PHP_SELF']);
    exit;
}

// Eliminar producto del carrito
if (isset($_POST['eliminar'])) {
    $id = intval($_POST['eliminar']);
    foreach ($_SESSION['carrito'] as $key => &$item) {
        if ($item['id'] == $id) {
            if ($item['cantidad'] > 1) {
                $item['cantidad'] -= 1;
            } else {
                unset($_SESSION['carrito'][$key]);
            }
            break;
        }
    }
    $_SESSION['carrito'] = array_values($_SESSION['carrito']);
    header("Location: ".$_SERVER['PHP_SELF']);
    exit;
}

// Generar ticket y actualizar stock
if (isset($_POST['generar_ticket'])) {
    // Limpiar cualquier salida potencial
    cleanOutputBuffers();
    
    $fecha = date("Y-m-d");
    
    // Iniciar transacción
    $conn->begin_transaction();
    
    try {
        foreach ($_SESSION['carrito'] as $item) {
            $id_producto = $item['id'];
            $cantidad = $item['cantidad'];
            
            // Verificar stock disponible
            $stmt = $conn->prepare("SELECT stock FROM productos WHERE id = ? FOR UPDATE");
            $stmt->bind_param("i", $id_producto);
            $stmt->execute();
            $stock = $stmt->get_result()->fetch_assoc()['stock'];
            
            if ($stock < $cantidad) {
                throw new Exception("No hay suficiente stock para {$item['nombre']}");
            }
            
            // Registrar venta
            $stmt = $conn->prepare("INSERT INTO ventas (id_producto, cantidad, fecha) VALUES (?, ?, ?)");
            $stmt->bind_param("iis", $id_producto, $cantidad, $fecha);
            $stmt->execute();
            
            // Actualizar stock
            $conn->query("UPDATE productos SET stock = stock - $cantidad WHERE id = $id_producto");
        }
        
        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
        cleanOutputBuffers();
        die("<script>alert('Error al procesar la compra: ".addslashes($e->getMessage())."'); window.location.href='index.php';</script>");
    }

    // Generar PDF
    require('fpdf/fpdf.php');
    $pdf = new FPDF();
    $pdf->AddPage();

    // Configuración del PDF
    $pdf->SetTextColor(9, 14, 14);
    $pdf->SetFont('Times', 'B', 20);
    $pdf->Cell(0, 10, utf8_decode("Las Perras al moverse riko se termina en menos de 1 minuto 🥵"), 0, 1, 'C');
    $pdf->SetDrawColor(91, 14, 14);
    $pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
    $pdf->Ln(4);
    $pdf->SetFont('Times', '', 12);
    $pdf->SetTextColor(5, 25, 15);
    $pdf->Cell(0, 10, 'Fecha: ' . date('d/m/Y H:i:s'), 0, 1, 'C');
    $pdf->Ln(5);

    // Tabla de productos
    $pdf->SetFont('Times', 'B', 12);
    $pdf->SetFillColor(24, 235, 220);
    $pdf->Cell(35, 10, 'Imagen', 1, 0, 'C', true);
    $pdf->Cell(65, 10, 'Producto', 1, 0, 'C', true);
    $pdf->Cell(25, 10, 'Precio', 1, 0, 'C', true);
    $pdf->Cell(25, 10, 'Cantidad', 1, 0, 'C', true);
    $pdf->Cell(30, 10, 'Total', 1, 1, 'C', true);

    $total = 0;
    $pdf->SetFont('Times', '', 11);

    foreach ($_SESSION['carrito'] as $productoInfo) {
        $rutaImagen = file_exists($productoInfo['imagen']) ? $productoInfo['imagen'] : 'no_imagen.png';
        $y = $pdf->GetY();

        $pdf->Cell(35, 40, $pdf->Image($rutaImagen, $pdf->GetX() + 4, $y + 3, 28), 1);
        $pdf->MultiCell(65, 10, utf8_decode($productoInfo['nombre']), 1);
        $pdf->SetXY(100, $y);
        $pdf->Cell(25, 40, "$" . number_format($productoInfo['precio'], 2), 1, 0, 'C');
        $pdf->Cell(25, 40, $productoInfo['cantidad'], 1, 0, 'C');
        $pdf->Cell(30, 40, "$" . number_format($productoInfo['precio'] * $productoInfo['cantidad'], 2), 1, 1, 'C');

        $total += $productoInfo['precio'] * $productoInfo['cantidad'];
    }

    // Total final
    $pdf->SetFont('Times', 'B', 12);
    $pdf->Ln(5);
    $pdf->Cell(150, 10, 'Total a Pagar:', 1, 0, 'R');
    $pdf->Cell(30, 10, "$" . number_format($total, 2), 1, 1, 'C');

    // Mensaje final
    $pdf->SetFont('Times', 'I', 12);
    $pdf->SetTextColor(101, 67, 33);
    $pdf->Ln(10);
    $pdf->MultiCell(0, 10, utf8_decode("“Las Perras siempre se mueven riko🥵.”\nGracias por comprar su salchicha negra! 🥵"), 0, 'C');

    // Guardar PDF en servidor temporalmente
    if (!file_exists('tmp')) {
        mkdir('tmp', 0777, true);
    }
    $filename = 'ticket_' . date('Ymd_His') . '.pdf';
    $pdf_path = __DIR__ . '/tmp/' . $filename;
    $pdf->Output($pdf_path, 'F');

    // Enviar por correo si se solicitó
    if (isset($_POST['enviar_correo']) && !empty($_POST['correo_cliente'])) {
        $correo_cliente = filter_var($_POST['correo_cliente'], FILTER_VALIDATE_EMAIL);
        
        if ($correo_cliente) {
            $mail = new PHPMailer(true);
            
          
try {
    // Configuración del servidor SMTP
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';  // Reemplaza con tu servidor SMTP
    $mail->SMTPAuth = true;
    $mail->Username = 'kerbeross89@gmail.com';
    $mail->Password = 'xxiq ovdr gnwv oird';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // O ENCRYPTION_SMTPS para SSL
    $mail->Port = 587; // 465 para SSL, 587 para TLS
    
  
          
                
                // Remitente y destinatario
                $mail->setFrom('salchicasnegras@calientes.com', 'PERRAS🥵');
                $mail->addAddress($correo_cliente);
                
                // Contenido del correo
                $mail->isHTML(true);
                $mail->Subject = 'Tu ticket de compra';
                $mail->Body    = '
                    <h1 style="color:rgb(14, 77, 91);">¡Gracias por comprar tu Perra Caliente🥵</h1>
                    <p>Adjuntamos tu ticket de compra.</p>
                    <p><em>"Las Perras siempre te calientan."</em></p>
                    <p>🥵</p>
                ';
                $mail->AltBody = 'Gracias por comprar a tu Perra Caliente🥵. Adjunto encontrarás tu ticket de compra.';
                
                // Adjuntar PDF
                $mail->addAttachment($pdf_path, $filename);
                
                $mail->send();
                $_SESSION['mensaje'] = 'Ticket enviado al correo electrónico proporcionado';
            } catch (Exception $e) {
                $_SESSION['mensaje'] = 'Error al enviar el correo: '.$e->getMessage();
            }
        } else {
            $_SESSION['mensaje'] = 'Por favor ingresa un correo electrónico válido';
        }
        
        // Si solo queríamos enviar por correo, redirigir
        if (isset($_POST['solo_correo'])) {
            unlink($pdf_path);
            header("Location: ".$_SERVER['PHP_SELF']);
            exit;
        }
    }

    // Limpiar carrito
    $_SESSION['carrito'] = [];

    // Descargar PDF
    $pdf->Output('D', $filename);
    
    // Eliminar archivo temporal
    unlink($pdf_path);
    exit;
}

// Limpiar buffer antes de enviar HTML
ob_end_flush();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Perras Calientes 🥵</title>
  <link href="https://fonts.googleapis.com/css2?family=Cardo:ital,wght@0,400;0,700;1,400&family=IM+Fell+English+SC&display=swap" rel="stylesheet">
  <style>
    /* [Todo tu CSS anterior permanece igual] */
  </style>
</head>
<body>
  <div class="container">
    <?php if (isset($_SESSION['mensaje'])): ?>
      <div style="background:rgb(44, 14, 91); color: white; padding: 10px; margin-bottom: 20px; border-radius: 5px;">
        <?php echo $_SESSION['mensaje']; unset($_SESSION['mensaje']); ?>
      </div>
    <?php endif; ?>
    
    <h2>Catalogo de Perras Calientes 🥵</h2>
    <form method="POST">
      <table>
        <tr><th>Imagen</th><th>Nombre</th><th>Precio</th><th>Stock</th><th>Agregar</th></tr>
        <?php
        $resultado = $conn->query("SELECT * FROM productos");
        while ($fila = $resultado->fetch_assoc()) {
            $imagen = (!empty($fila['imagen']) && file_exists($fila['imagen'])) ? $fila['imagen'] : 'no_imagen.png';
            echo "<tr>
                    <td><img src='$imagen' width='60' height='60'></td>
                    <td>{$fila['nombre']}</td>
                    <td>\${$fila['precio']}</td>
                    <td>{$fila['stock']}</td>
                    <td><button type='submit' name='agregar' value='{$fila['id']}'>Agregar</button></td>
                  </tr>";
        }
        ?>
      </table>
    </form>

    <?php if (!empty($_SESSION['carrito'])): ?>
      <h3>Carrito de Compras</h3>
      <ul>
        <?php
        $total = 0;
        foreach ($_SESSION['carrito'] as $productoInfo) {
            echo "<li>
                    <img src='{$productoInfo['imagen']}' width='40' height='40'>
                    <span>{$productoInfo['nombre']} - \$" . number_format($productoInfo['precio'], 2) . " x {$productoInfo['cantidad']}</span>
                    <form method='POST' style='display:inline'>
                        <button type='submit' name='eliminar' value='{$productoInfo['id']}'>-</button>
                    </form>
                    <form method='POST' style='display:inline'>
                        <button type='submit' name='agregar' value='{$productoInfo['id']}'>+</button>
                    </form>
                  </li>";
            $total += $productoInfo['precio'] * $productoInfo['cantidad'];
        }
        ?>
      </ul>
      <p style="font-size: 20px; text-align: right; margin-right: 20px;">
        Total: <strong>$<?php echo number_format($total, 2); ?></strong>
      </p>
      
      <form method="POST">
        <div style="margin: 20px 0;">
          <label for="correo_cliente" style="display: block; margin-bottom: 8px; color: #e2c290; font-size: 18px;">
            ¿Deseas recibir el ticket en tu correo electrónico?
          </label>
          <input type="email" id="correo_cliente" name="correo_cliente" 
                 placeholder="tucorreo@ejemplo.com" required>
        </div>
        
        <div style="display: flex; gap: 10px;">
          <button type="submit" name="generar_ticket" style="flex: 1; background-color:rgb(85, 74, 235);">
            Descargar Ticket
          </button>
          
          <button type="submit" name="generar_ticket" style="flex: 1; background-color:rgb(65, 183, 215);">
            <input type="hidden" name="enviar_correo" value="1">
            Enviar Ticket por Correo
          </button>
        </div>
      </form>
    <?php endif; ?>
  </div>
</body>
</html>