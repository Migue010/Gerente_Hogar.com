<?php 
session_start();
include_once("conexion.php");
include_once("funciones.php");

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'integrante' || !isset($_SESSION['nombre_familia'])) {
    header("Location: inicio_sesion.php");
    exit();
}

$mensaje = "";
$mensajeAporte = "";
$familia_actual = $_SESSION['nombre_familia'];

// Registro de ingresos o gastos personales
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['tipo'], $_POST['descripcion'], $_POST['monto'])) {
    $tipo = $_POST['tipo'];
    $descripcion = trim($_POST['descripcion']);
    $monto = floatval($_POST['monto']);

    if ($monto > 0 && ($tipo === 'ingreso' || $tipo === 'gasto')) {
        $resultado_movimiento = agregarMovimiento($_SESSION['usuario_id'], $tipo, $descripcion, $monto, $familia_actual);

        if ($resultado_movimiento === "saldo_insuficiente") {
            $mensaje = "¡Alerta! No se pudo registrar el gasto de $" . number_format($monto, 2) . ". El saldo familiar es insuficiente.";
            $mensaje_clase = "error-message";
        } elseif ($resultado_movimiento) {
            $mensaje = "Movimiento registrado correctamente.";
            $mensaje_clase = "success-message";
        } else {
            $mensaje = "Error al registrar el movimiento.";
            $mensaje_clase = "error-message";
        }
    } else {
        $mensaje = "Datos inválidos. Asegúrese de que el monto sea positivo.";
        $mensaje_clase = "error-message";
    }
}

// Registro de aportes a metas familiares
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['id_meta'], $_POST['monto_aporte'])) {
    $id_meta = intval($_POST['id_meta']);
    $monto_aporte = floatval($_POST['monto_aporte']);

    if ($monto_aporte > 0) {
        if (registrarAporte($_SESSION['usuario_id'], $id_meta, $monto_aporte)) {
            $mensajeAporte = "Aporte registrado correctamente.";
            $mensajeAporte_clase = "success-message";
        } else {
            $mensajeAporte = "Error al registrar el aporte.";
            $mensajeAporte_clase = "error-message";
        }
    } else {
        $mensajeAporte = "Monto inválido. Debe ser positivo.";
        $mensajeAporte_clase = "error-message";
    }
}

// Datos para mostrar en el panel
$resumen = obtenerResumen($_SESSION['usuario_id']);
$movimientos = listarMovimientos($_SESSION['usuario_id']);
$metas = obtenerMetas_ahorro($familia_actual);
?>


<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Panel Integrante - GerenteHogar</title>
  <link rel="stylesheet" href="Archivos_CSS/gerente_hogar.css">
  <link rel="stylesheet" href="Archivos_CSS/panel_integrante.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>

<body>
  <header>
    <div class="logo">GERENTEHOGAR.COM</div>
    <nav>
      <a href="integrante.php">Inicio</a>
      <a href="cerrar_sesion.php">Cerrar sesión</a>
    </nav>
  </header>

  <section class="hero">
    <h1>Bienvenido, <?php echo htmlspecialchars($_SESSION['usuario']); ?> de la Familia: <?php echo htmlspecialchars($familia_actual); ?>!</h1>
    <p>Gestiona tus ingresos y gastos aquí.</p>

    <?php if ($mensaje): ?>
      <div class="message <?php echo htmlspecialchars($mensaje_clase ?? ''); ?>">
        <p><?php echo htmlspecialchars($mensaje); ?></p>
      </div>
    <?php endif; ?>

    <h2><i class="fas fa-hand-holding-usd icon"></i> Registrar Movimiento</h2>
    <form method="POST" class="movement-form">
      <div class="form-group">
        <label for="tipo_movimiento">Tipo de movimiento:</label>
        <select name="tipo" id="tipo_movimiento" required>
          <option value="ingreso">Ingreso</option>
          <option value="gasto">Gasto</option>
        </select>
      </div>

      <div class="form-group">
        <label for="descripcion_movimiento">Descripción:</label>
        <input type="text" name="descripcion" id="descripcion_movimiento" required>
      </div>

      <div class="form-group">
        <label for="monto_movimiento">Monto:</label>
        <input type="number" step="0.01" name="monto" id="monto_movimiento" required>
      </div>

      <button type="submit"><i class="fas fa-plus-circle"></i> Agregar Movimiento</button>
    </form>

    <h2><i class="fas fa-chart-line icon"></i> Resumen Mensual (Personal)</h2>
    <div class="resumen">
      <p>Total ingresos: <span class="income-value">$<?php echo number_format($resumen['total_ingresos'] ?? 0, 2); ?></span></p>
      <p>Total gastos: <span class="expense-value">$<?php echo number_format($resumen['total_gastos'] ?? 0, 2); ?></span></p>
      <p class="balance-value"><strong>Saldo personal: $<?php echo number_format(($resumen['total_ingresos'] ?? 0) - ($resumen['total_gastos'] ?? 0), 2); ?></strong></p>
    </div>

    <h2><i class="fas fa-piggy-bank icon"></i> Aportar a Metas Familiares</h2>
    <?php if ($mensajeAporte): ?>
      <div class="message <?php echo htmlspecialchars($mensajeAporte_clase ?? ''); ?>">
        <p><?php echo htmlspecialchars($mensajeAporte); ?></p>
      </div>
    <?php endif; ?>

    <form method="POST" class="contribution-form">
      <div class="form-group">
        <label for="id_meta">Selecciona una meta:</label>
        <select name="id_meta" id="id_meta" required>
          <?php
          if ($metas && $metas->num_rows > 0) {
              while ($meta = $metas->fetch_assoc()): ?>
                  <option value="<?php echo htmlspecialchars($meta['id']); ?>"><?php echo htmlspecialchars($meta['nombre']); ?></option>
              <?php endwhile;
          } else {
              echo '<option value="">No hay metas de ahorro activas para tu familia</option>';
          }
          ?>
        </select>
      </div>

      <div class="form-group">
        <label for="monto_aporte">Monto del aporte:</label>
        <input type="number" name="monto_aporte" id="monto_aporte" step="0.01" required>
      </div>

      <button type="submit"><i class="fas fa-donate"></i> Aportar</button>
    </form>

    <h2><i class="fas fa-exchange-alt icon"></i> Movimientos Recientes (Personales)</h2>
    <table class="movimientos-table">
      <thead>
        <tr>
          <th>Tipo</th>
          <th>Descripción</th>
          <th>Monto</th>
          <th>Fecha</th>
        </tr>
      </thead>
      <tbody>
        <?php
        if ($movimientos->num_rows > 0) {
            while ($mov = $movimientos->fetch_assoc()): ?>
                <tr class="<?php echo ($mov['tipo_movimiento'] === 'ingreso' ? 'row-ingreso' : 'row-gasto'); ?>">
                    <td><?php echo htmlspecialchars(ucfirst($mov['tipo_movimiento'])); ?></td>
                    <td><?php echo htmlspecialchars($mov['descripcion']); ?></td>
                    <td>$<?php echo number_format($mov['monto'], 2); ?></td>
                    <td><?php echo htmlspecialchars($mov['fecha']); ?></td>
                </tr>
            <?php endwhile;
        } else {
            echo "<tr><td colspan='4'>No hay movimientos registrados para este usuario.</td></tr>";
        }
        ?>
      </tbody>
    </table>
  </section>
</body>
</html>