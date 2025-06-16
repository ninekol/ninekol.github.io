<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gráfica de Ventas</title>
  
</head>
<body>
    <div class="container">
        <h2>Selecciona un Rango de Fechas</h2>


<!-- Formulario -->
<form method="GET" action="graficas.php">
    <label>Fecha ini. <input type="date" name="fecha_ini" required value="<?php echo htmlspecialchars($fecha_ini); ?>"></label>
    <label>Fecha Final <input type="date" name="fecha_fin" required value="<?php echo htmlspecialchars($fecha_fin); ?>"></label>
    <button type="submit">Enviar</button>
</form>

<!-- Caja de la gráfica -->
<div class="grafica-box">
    <canvas id="graficaVentas" width="800" height="400"></canvas>
</div>


    <!-- Botón para imprimir -->
    <button onclick="imprimirGrafica()">Imprimir Gráfica</button>

    <!-- Librería Chart.js (si estás usando esta) -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script>
        // Aquí iría el código para generar la gráfica
        const ctx = document.getElementById('miGrafica').getContext('2d');
        const miGrafico = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Enero', 'Febrero', 'Marzo'],
                datasets: [{
                    label: 'Ventas',
                    data: [12, 19, 3],
                    backgroundColor: 'rgba(75, 192, 192, 0.5)'
                }]
            }
        });

        function imprimirGrafica() {
            const contenido = document.getElementById('grafica').innerHTML;
            const ventana = window.open('', '', 'height=600,width=800');
            ventana.document.write('<html><head><title>Imprimir Gráfica</title></head><body>');
            ventana.document.write(contenido);
            ventana.document.write('</body></html>');
            ventana.document.close();
            ventana.focus();
            ventana.print();
            ventana.close();
        }
    </script>