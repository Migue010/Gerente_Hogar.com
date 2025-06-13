<?php
session_start();
include_once("conexion.php");

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'administrador' || !isset($_SESSION['nombre_familia'])) {
    header("Location: inicio_sesion.php");
    exit();
}


$familia_actual = $_SESSION['nombre_familia'];

// --- Agregar meta de ahorro ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nombre_meta'], $_POST['monto_objetivo'])) {
    $nombre = $_POST['nombre_meta'];
    $monto = floatval($_POST['monto_objetivo']);

    if (!empty($nombre) && $monto > 0) {
        $sql = "INSERT INTO metas_ahorro (nombre, monto_objetivo, monto_actual, estado, nombre_familia) VALUES (?, ?, 0, 'activa', ?)";
        $stmt = $conexion->prepare($sql);
        if ($stmt === false) {
            die("Error preparando la consulta de meta: " . $conexion->error);
        }
        $stmt->bind_param("sds", $nombre, $monto, $familia_actual);
        if ($stmt->execute()) {
            echo "<script>alert('Meta de ahorro agregada con éxito para " . htmlspecialchars($familia_actual) . ".');</script>";
        } else {
            echo "<script>alert('Error al agregar la meta de ahorro: " . $stmt->error . "');</script>";
        }
        $stmt->close();
    } else {
        echo "<script>alert('Por favor, ingrese un nombre y un monto válido para la meta.');</script>";
    }
}

// --- Saldo total familiar ---
$sql_saldo = "SELECT
    SUM(CASE WHEN m.tipo_movimiento = 'ingreso' THEN m.monto ELSE 0 END) AS total_ingresos,
    SUM(CASE WHEN m.tipo_movimiento = 'gasto' THEN m.monto ELSE 0 END) AS total_gastos
    FROM movimientos m
    JOIN usuarios_familia uf ON m.usuario = uf.id_usuario_familia
    WHERE uf.nombre_familia = ?"; 

$stmt_saldo = $conexion->prepare($sql_saldo);
if ($stmt_saldo === false) {
    die("Error en la consulta de saldo SQL: " . $conexion->error);
}
$stmt_saldo->bind_param("s", $familia_actual);
$stmt_saldo->execute();
$resultado_saldo = $stmt_saldo->get_result();
$data = $resultado_saldo->fetch_assoc();
$stmt_saldo->close(); 

$total_ingresos = $data['total_ingresos'] ?? 0;
$total_gastos = $data['total_gastos'] ?? 0;
$saldo_total = $total_ingresos - $total_gastos;

// --- Ingresos vs gastos últimos 6 meses ---
$sql_grafica = "SELECT
    DATE_FORMAT(m.fecha, '%Y-%m') AS mes,
    SUM(CASE WHEN m.tipo_movimiento = 'ingreso' THEN m.monto ELSE 0 END) AS ingresos,
    SUM(CASE WHEN m.tipo_movimiento = 'gasto' THEN m.monto ELSE 0 END) AS gastos
    FROM movimientos m
    JOIN usuarios_familia uf ON m.usuario = uf.id_usuario_familia
    WHERE m.fecha >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) AND uf.nombre_familia = ?
    GROUP BY mes
    ORDER BY mes DESC";

$stmt_grafica = $conexion->prepare($sql_grafica);
if ($stmt_grafica === false) {
    die("Error en la consulta gráfica: " . $conexion->error);
}
$stmt_grafica->bind_param("s", $familia_actual);
$stmt_grafica->execute();
$res_grafica = $stmt_grafica->get_result();


// --- Historial de movimientos  ---

$sql_movimientos = "SELECT m.tipo_movimiento, m.descripcion, m.monto, m.fecha, uf.nombre_usuario AS username
                    FROM movimientos m
                    JOIN usuarios_familia uf ON m.usuario = uf.id_usuario_familia
                    WHERE uf.nombre_familia = ?
                    ORDER BY m.fecha DESC
                    LIMIT 30";

$stmt_movimientos = $conexion->prepare($sql_movimientos);
if ($stmt_movimientos === false) {
    die("Error en la consulta de movimientos: " . $conexion->error);
}
$stmt_movimientos->bind_param("s", $familia_actual);
$stmt_movimientos->execute();
$res_movimientos = $stmt_movimientos->get_result();

// --- Metas de ahorro ---

$sql_metas = "SELECT * FROM metas_ahorro WHERE nombre_familia = ? ORDER BY id DESC";
$stmt_metas = $conexion->prepare($sql_metas);
if ($stmt_metas === false) {
    die("Error en la consulta de metas: " . $conexion->error);
}
$stmt_metas->bind_param("s", $familia_actual);
$stmt_metas->execute();
$metas = $stmt_metas->get_result();

// --- Aportes a metas ---
$sql_aportes = "SELECT a.monto, a.fecha, uf.nombre_usuario AS usuario, ma.nombre AS meta
                FROM aportes a
                INNER JOIN usuarios_familia uf ON a.id_usuario = uf.id_usuario_familia
                INNER JOIN metas_ahorro ma ON a.id_meta = ma.id
                WHERE uf.nombre_familia = ?
                ORDER BY a.fecha DESC";

$stmt_aportes = $conexion->prepare($sql_aportes);
if ($stmt_aportes === false) {
    die("Error en la consulta de aportes: " . $conexion->error);
}
$stmt_aportes->bind_param("s", $familia_actual);
$stmt_aportes->execute();
$res_aportes = $stmt_aportes->get_result();

// --- Obtener integrantes de la familia ---
$sql_integrantes = "SELECT nombre_usuario, correo, rol FROM usuarios_familia WHERE nombre_familia = ? ORDER BY rol DESC, nombre_usuario ASC";
$stmt_integrantes = $conexion->prepare($sql_integrantes);
if ($stmt_integrantes === false) {
    die("Error en la consulta de integrantes: " . $conexion->error);
}
$stmt_integrantes->bind_param("s", $familia_actual);
$stmt_integrantes->execute();
$res_integrantes = $stmt_integrantes->get_result();

// --- Alerta de presupuesto negativo ---
$alerta_presupuesto = $total_gastos > $total_ingresos;
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <title>Panel Administrador - GerenteHogar.com</title>
  <link rel="stylesheet" href="Archivos_CSS/panel_administrador.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>

  <div class="sidebar">
    <div class="logo">GERENTE HOGAR</div>
    <nav>
      <ul>
        <li class="active"><a href="#dashboard">
          <i class="fas fa-home"></i>
          Dashboard
        </a></li>
        <li><a href="#metas">
          <i class="fas fa-bullseye"></i>
          Metas de Ahorro
        </a></li>
        <li><a href="#integrantes">
          <i class="fas fa-users"></i>
          Integrantes
        </a></li>
        <li><a href="cerrar_sesion.php">
          <i class="fas fa-sign-out-alt"></i>
          Cerrar sesión
        </a></li>
      </ul>
    </nav>
  </div>

  <div class="main-content">
    <h1>Panel Administrador de la Familia: <?= htmlspecialchars($familia_actual) ?></h1>

    <div id="dashboard">
      <section class="resumen-financiero">
        <div class="card">
          <h2>Saldo total del hogar 💰</h2>
          <p>$<?= number_format($saldo_total, 2) ?></p>
          <span>Ingresos: $<?= number_format($total_ingresos, 2) ?></span><br>
          <span>Gastos: $<?= number_format($total_gastos, 2) ?></span>
        </div>
      </section>

      <?php if($alerta_presupuesto): ?>
        <div class="alerta">
          ⚠️ Atención: Los gastos superan los ingresos familiares.
        </div>
      <?php endif; ?>

      <section class="grafica">
        <h2>Ingresos vs Gastos últimos 6 meses 📈</h2>
        <table>
          <thead>
            <tr><th>Mes</th><th>Ingresos</th><th>Gastos</th></tr>
          </thead>
          <tbody>
            <?php
            if ($res_grafica->num_rows > 0) {
                while($row = $res_grafica->fetch_assoc()): ?>
                  <tr>
                    <td><?= htmlspecialchars($row['mes']) ?></td>
                    <td>$<?= number_format($row['ingresos'], 2) ?></td>
                    <td>$<?= number_format($row['gastos'], 2) ?></td>
                  </tr>
                <?php endwhile;
            } else {
                echo "<tr><td colspan='3'>No hay datos de ingresos/gastos para los últimos 6 meses en esta familia.</td></tr>";
            }
            ?>
          </tbody>
        </table>
      </section>

      <section class="transacciones">
        <h2>Historial de transacciones (últimos 30) 🔄</h2>
        <table>
          <thead>
            <tr>
              <th>Usuario</th>
              <th>Tipo</th>
              <th>Descripción</th>
              <th>Monto</th>
              <th>Fecha</th>
            </tr>
          </thead>
          <tbody>
            <?php
            if ($res_movimientos->num_rows > 0) {
                while ($mov = $res_movimientos->fetch_assoc()): ?>
                  <tr>
                    <td><?= htmlspecialchars($mov['username']) ?></td>
                    <td><?= htmlspecialchars($mov['tipo_movimiento']) ?></td>
                    <td><?= htmlspecialchars($mov['descripcion']) ?></td>
                    <td>$<?= number_format($mov['monto'], 2) ?></td>
                    <td><?= htmlspecialchars($mov['fecha']) ?></td>
                  </tr>
                <?php endwhile;
            } else {
                echo "<tr><td colspan='5'>No hay movimientos registrados para esta familia.</td></tr>";
            }
            ?>
          </tbody>
        </table>
      </section>

      <section class="aportes-metas">
        <h2>Aportes realizados por integrantes a las metas 💸</h2>
        <table>
          <thead>
            <tr>
              <th>Integrante</th>
              <th>Meta</th>
              <th>Monto</th>
              <th>Fecha</th>
            </tr>
          </thead>
          <tbody>
            <?php
            if ($res_aportes->num_rows > 0) {
                while ($aporte = $res_aportes->fetch_assoc()): ?>
                  <tr>
                    <td><?= htmlspecialchars($aporte['usuario']) ?></td>
                    <td><?= htmlspecialchars($aporte['meta']) ?></td>
                    <td>$<?= number_format($aporte['monto'], 2) ?></td>
                    <td>$<?= htmlspecialchars($aporte['fecha']) ?></td>
                  </tr>
                <?php endwhile;
            } else {
                echo "<tr><td colspan='4'>No se han realizado aportes a las metas de esta familia.</td></tr>";
            }
            ?>
          </tbody>
        </table>
      </section>
    </div>

    <section id="metas" class="objetivos">
      <h2>Gestión de Metas de Ahorro Familiares 🎯</h2>

      <form method="POST">
        <input type="text" name="nombre_meta" placeholder="Nombre de la meta" required />
        <input type="number" step="0.01" name="monto_objetivo" placeholder="Monto objetivo" required />
        <button type="submit" class="nuevo-objetivo">Agregar Meta</button>
      </form>

      <table>
        <thead>
          <tr>
            <th>Nombre</th>
            <th>Monto Objetivo</th>
            <th>Monto Actual</th>
            <th>Estado</th>
          </tr>
        </thead>
        <tbody>
          <?php
          if ($metas->num_rows > 0) {
              while ($meta = $metas->fetch_assoc()): ?>
                <tr>
                  <td><?= htmlspecialchars($meta['nombre']) ?></td>
                  <td>$<?= number_format($meta['monto_objetivo'], 2) ?></td>
                  <td>$<?= number_format($meta['monto_actual'], 2) ?></td>
                  <td><?= ucfirst($meta['estado']) ?></td>
                </tr>
              <?php endwhile;
          } else {
              echo "<tr><td colspan='4'>No hay metas de ahorro definidas para esta familia.</td></tr>";
          }
          ?>
        </tbody>
      </table>
    </section>

    <section id="integrantes" class="integrantes">
      <h2>Integrantes de la Familia <?= htmlspecialchars($familia_actual) ?> 👨‍👩‍👧‍👦</h2>
      <table>
        <thead>
          <tr>
            <th>Nombre de Usuario</th>
            <th>Correo Electrónico</th>
            <th>Rol</th>
          </tr>
        </thead>
        <tbody>
          <?php
          if ($res_integrantes->num_rows > 0) {
              while ($integrante = $res_integrantes->fetch_assoc()): ?>
                <tr>
                  <td><?= htmlspecialchars($integrante['nombre_usuario']) ?></td>
                  <td><?= htmlspecialchars($integrante['correo']) ?></td>
                  <td><?= htmlspecialchars(ucfirst($integrante['rol'])) ?></td>
                </tr>
              <?php endwhile;
          } else {
              echo "<tr><td colspan='3'>No hay integrantes registrados para esta familia.</td></tr>";
          }
          ?>
        </tbody>
      </table>
    </section>
  </div>
</body>
</html>