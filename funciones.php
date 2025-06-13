<?php
include_once("conexion.php");

// Agrega un movimiento (ingreso o gasto) para un usuario
function agregarMovimiento($usuario_id, $tipo, $descripcion, $monto, $nombre_familia) {
    global $conexion;

    if ($tipo === 'gasto') {
        $saldo_actual_familia = obtenerSaldoFamilia($nombre_familia);
        if ($saldo_actual_familia < $monto) {
            return "saldo_insuficiente";
        }
    }

    $sql = "INSERT INTO movimientos (usuario, tipo_movimiento, descripcion, monto) VALUES (?, ?, ?, ?)";
    $stmt = $conexion->prepare($sql);
    if ($stmt === false) {
        error_log("Error preparando agregarMovimiento: " . $conexion->error);
        return false;
    }
    $stmt->bind_param("issd", $usuario_id, $tipo, $descripcion, $monto);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

// Devuelve el total de ingresos y gastos de un usuario
function obtenerResumen($usuario_id) {
    global $conexion;
    $sql = "SELECT
                SUM(CASE WHEN tipo_movimiento = 'ingreso' THEN monto ELSE 0 END) AS total_ingresos,
                SUM(CASE WHEN tipo_movimiento = 'gasto' THEN monto ELSE 0 END) AS total_gastos
            FROM movimientos WHERE usuario = ?";
    $stmt = $conexion->prepare($sql);
    if ($stmt === false) {
        error_log("Error preparando obtenerResumen: " . $conexion->error);
        return ['total_ingresos' => 0, 'total_gastos' => 0];
    }
    $stmt->bind_param("i", $usuario_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $res;
}

// Lista los últimos 20 movimientos de un usuario
function listarMovimientos($usuario_id) {
    global $conexion;
    $sql = "SELECT tipo_movimiento, descripcion, monto, fecha FROM movimientos WHERE usuario = ? ORDER BY fecha DESC LIMIT 20";
    $stmt = $conexion->prepare($sql);
    if ($stmt === false) {
        error_log("Error preparando listarMovimientos: " . $conexion->error);
        return false;
    }
    $stmt->bind_param("i", $usuario_id);
    $stmt->execute();
    return $stmt->get_result();
}

// Obtiene las metas de ahorro activas de una familia
function obtenerMetas_ahorro($nombre_familia) {
    global $conexion;
    $sql = "SELECT id, nombre, monto_actual, monto_objetivo FROM metas_ahorro WHERE estado = 'activa' AND nombre_familia = ?";
    $stmt = $conexion->prepare($sql);
    if ($stmt === false) {
        error_log("Error preparando obtenerMetas_ahorro: " . $conexion->error);
        return false;
    }
    $stmt->bind_param("s", $nombre_familia);
    $stmt->execute();
    return $stmt->get_result();
}

// Registra un aporte a una meta de ahorro y actualiza su estado
function registrarAporte($id_usuario, $id_meta, $monto) {
    global $conexion;
    $conexion->begin_transaction();

    try {
        $stmt = $conexion->prepare("INSERT INTO aportes (id_usuario, id_meta, monto, fecha) VALUES (?, ?, ?, NOW())");
        if ($stmt === false) throw new Exception("Error preparando el aporte: " . $conexion->error);
        $stmt->bind_param("iid", $id_usuario, $id_meta, $monto);
        $stmt->execute();
        $stmt->close();

        $stmt2 = $conexion->prepare("UPDATE metas_ahorro SET monto_actual = monto_actual + ? WHERE id = ?");
        if ($stmt2 === false) throw new Exception("Error preparando la actualización de meta: " . $conexion->error);
        $stmt2->bind_param("di", $monto, $id_meta);
        $stmt2->execute();
        $stmt2->close();

        $stmt3 = $conexion->prepare("SELECT monto_actual, monto_objetivo FROM metas_ahorro WHERE id = ?");
        if ($stmt3 === false) throw new Exception("Error preparando la verificación de meta: " . $conexion->error);
        $stmt3->bind_param("i", $id_meta);
        $stmt3->execute();
        $resultado = $stmt3->get_result()->fetch_assoc();
        $stmt3->close();

        if ($resultado['monto_actual'] >= $resultado['monto_objetivo']) {
            $stmt4 = $conexion->prepare("UPDATE metas_ahorro SET estado = 'completada' WHERE id = ?");
            if ($stmt4 === false) throw new Exception("Error preparando la finalización de meta: " . $conexion->error);
            $stmt4->bind_param("i", $id_meta);
            $stmt4->execute();
            $stmt4->close();
        }

        $conexion->commit();
        return true;

    } catch (Exception $e) {
        $conexion->rollback();
        error_log("Error en registrarAporte: " . $e->getMessage());
        return false;
    }
}

// Calcula el saldo total (ingresos - gastos) de una familia
function obtenerSaldoFamilia($nombre_familia) {
    global $conexion;
    $sql_saldo = "SELECT
        SUM(CASE WHEN m.tipo_movimiento = 'ingreso' THEN m.monto ELSE 0 END) AS total_ingresos,
        SUM(CASE WHEN m.tipo_movimiento = 'gasto' THEN m.monto ELSE 0 END) AS total_gastos
        FROM movimientos m
        JOIN usuarios_familia uf ON m.usuario = uf.id_usuario_familia
        WHERE uf.nombre_familia = ?";
    $stmt_saldo = $conexion->prepare($sql_saldo);
    if ($stmt_saldo === false) {
        error_log("Error preparando la consulta de saldo familiar: " . $conexion->error);
        return 0;
    }
    $stmt_saldo->bind_param("s", $nombre_familia);
    $stmt_saldo->execute();
    $data = $stmt_saldo->get_result()->fetch_assoc();
    $stmt_saldo->close();

    $total_ingresos = $data['total_ingresos'] ?? 0;
    $total_gastos = $data['total_gastos'] ?? 0;
    return $total_ingresos - $total_gastos;
}

// Aportes con nombres de usuario y meta
function obtenerAportesConDetalles() {
    global $conexion;
    $sql = "SELECT a.monto, a.fecha, uf.nombre_usuario AS usuario_nombre, ma.nombre AS meta_nombre
            FROM aportes a
            INNER JOIN usuarios_familia uf ON a.id_usuario = uf.id_usuario_familia
            INNER JOIN metas_ahorro ma ON a.id_meta = ma.id
            ORDER BY a.fecha DESC";
    $result = $conexion->query($sql);
    if (!$result) {
        error_log("Error en obtenerAportesConDetalles: " . $conexion->error);
        return false;
    }
    return $result;
}

// Lista todos los movimientos con nombre del usuario
function listarMovimientosGlobales() {
    global $conexion;
    $sql = "SELECT m.tipo_movimiento, m.descripcion, m.monto, m.fecha, uf.nombre_usuario AS username
            FROM movimientos m
            JOIN usuarios_familia uf ON m.usuario = uf.id_usuario_familia
            ORDER BY m.fecha DESC";
    $result = $conexion->query($sql);
    if (!$result) {
        error_log("Error en listarMovimientosGlobales: " . $conexion->error);
        return false;
    }
    return $result;
}
?>
