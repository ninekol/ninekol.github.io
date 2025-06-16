<?php
include("db.php");

$fecha_ini = isset($_GET['fecha_ini']) ? mysqli_real_escape_string($conn, $_GET['fecha_ini']) : '';
$fecha_fin = isset($_GET['fecha_fin']) ? mysqli_real_escape_string($conn, $_GET['fecha_fin']) : '';

$datos = [];
$error = '';

if ($fecha_ini && $fecha_fin) {
    $date_ini_obj = DateTime::createFromFormat('Y-m-d', $fecha_ini);
    $date_fin_obj = DateTime::createFromFormat('Y-m-d', $fecha_fin);

    if (!$date_ini_obj || !$date_fin_obj || $date_ini_obj->format('Y-m-d') !== $fecha_ini || $date_fin_obj->format('Y-m-d') !== $fecha_fin) {
        $error = "Por favor, introduce fechas válidas en formato YYYY-MM-DD.";
    } elseif ($date_ini_obj > $date_fin_obj) {
        $error = "La fecha inicial no puede ser mayor que la fecha final";
    } else {
        $query = "SELECT p.nombre, SUM(v.cantidad) AS total_vendidos, p.stock
                  FROM ventas v
                  INNER JOIN productos p ON v.id_producto = p.id
                  WHERE v.fecha BETWEEN ? AND ?
                  GROUP BY p.id
                  ORDER BY total_vendidos DESC";
        
        $stmt = mysqli_prepare($conn, $query);
        
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "ss", $fecha_ini, $fecha_fin);
            mysqli_stmt_execute($stmt);
            $resultado = mysqli_stmt_get_result($stmt);

            while ($row = mysqli_fetch_assoc($resultado)) {
                $datos[] = $row;
            }
            mysqli_stmt_close($stmt);
        } else {
            $error = "Error al preparar la consulta: " . mysqli_error($conn);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gráfica de Ventas</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
        }
        canvas {
            max-width: 100%;
            height: 400px;
        }
        .error {
            color: red;
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <h2>Gráfica de Productos Vendidos</h2>

    <form method="GET" action="graficas.php">
        <label>Fecha Inicial: <input type="date" name="fecha_ini" required value="<?php echo htmlspecialchars($fecha_ini); ?>"></label>
        <label>Fecha Final: <input type="date" name="fecha_fin" required value="<?php echo htmlspecialchars($fecha_fin); ?>"></label>
        <button type="submit">Generar Gráfica</button>
    </form>

    <?php if ($error): ?>
        <div class="error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if (!empty($datos)): ?>
        <div class="chart-container">
            <canvas id="graficaVentas"></canvas>
        </div>
        <button onclick="generarPDF()">Descargar Gráfica en PDF</button>
    <?php elseif ($fecha_ini && $fecha_fin && !$error): ?>
        <div class="error">No se encontraron datos para el rango de fechas seleccionado.</div>
    <?php endif; ?>

    <!-- Incluir jsPDF y html2canvas -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

    <script>
        const datos = <?php echo json_encode($datos); ?>;
        const ctx = document.getElementById('graficaVentas')?.getContext('2d');

        if (ctx && datos.length > 0) {
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: datos.map(d => d.nombre),
                    datasets: [
                        {
                            label: 'Unidades Vendidas',
                            data: datos.map(d => d.total_vendidos),
                            backgroundColor: 'rgba(91, 14, 14, 0.7)',
                            borderColor: 'rgba(91, 14, 14, 1)',
                            borderWidth: 1
                        },
                        {
                            label: 'Stock Disponible',
                            data: datos.map(d => d.stock),
                            backgroundColor: 'rgba(226, 194, 144, 0.7)',
                            borderColor: 'rgba(226, 194, 144, 1)',
                            borderWidth: 1
                        }
                    ]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Cantidad'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Productos'
                            }
                        }
                    }
                }
            });
        }

        async function generarPDF() {
            const canvas = document.getElementById('graficaVentas');
            if (!canvas) return;

            const { jsPDF } = window.jspdf;
            const pdf = new jsPDF();

            const imgData = canvas.toDataURL("image/png", 1.0);
            const pdfWidth = 210;
            const imgProps = pdf.getImageProperties(imgData);
            const pdfHeight = (imgProps.height * pdfWidth) / imgProps.width;

            // Título
            const fechaIni = "<?php echo $fecha_ini; ?>";
            const fechaFin = "<?php echo $fecha_fin; ?>";
            pdf.setFontSize(16);
            pdf.text("Gráfica de Ventas", pdfWidth / 2, 15, { align: "center" });
            pdf.setFontSize(12);
            pdf.text(`Periodo: ${fechaIni} a ${fechaFin}`, pdfWidth / 2, 22, { align: "center" });

            // Imagen de la gráfica
            pdf.addImage(imgData, 'PNG', 10, 30, pdfWidth - 20, pdfHeight);

            // Guardar archivo
            pdf.save(`grafica_ventas_${fechaIni}_a_${fechaFin}.pdf`);
        }
    </script>
</body>
</html>
