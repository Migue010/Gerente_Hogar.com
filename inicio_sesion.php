<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <title>Iniciar Sesión - GerenteHogar.com</title>
  <link rel="stylesheet" href="Archivos_CSS/login.css" />
</head>
<body>

  <header>
    <div class="logo">GERENTEHOGAR.COM</div>
    <nav>
      <a href="gerente_hogar.html">Inicio</a>
      <a href="registrarse.php">Registrarse</a>
    </nav>
  </header>

  <section class="hero" style="padding: 2rem; text-align: center;">
    <h1>Inicia Sesión para gestionar las finanzas de tu hogar.</h1>
  </section>

  <section class="form-container">
    <form action="" method="POST" autocomplete="off">

      <label for="email">Correo electrónico</label>
      <input type="email" id="email" name="correo" required />

      <label for="password">Contraseña</label>
      <input type="password" id="password" name="password" required />

      <button type="submit">Iniciar Sesión</button>
    </form>
  </section>

  <footer>
    <p>&copy; 2025 GERENTEHOGAR.COM - Todos los derechos reservados.</p>
    <div>
      <a href="inicio_sesion.php">Ingresar</a>
    </div>
  </footer>

</body>
</html>

<?php
include_once("conexion.php");
session_start();

// Procesar formulario solo si se envió por POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $correo = $_POST["correo"];
    $password = $_POST["password"];

    // Consulta para obtener usuario por correo
    $sql = "SELECT id_usuario_familia, nombre_usuario, contrasena, rol, nombre_familia FROM usuarios_familia WHERE correo = ?";
    $stmt = $conexion->prepare($sql);
    if ($stmt === false) die("Error en consulta: " . $conexion->error);

    $stmt->bind_param("s", $correo);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows == 1) {
        $fila = $resultado->fetch_assoc();

        // Verificar contraseña con hash
        if (password_verify($password, $fila['contrasena'])) {
            session_regenerate_id(true); 

            // Guardar datos del usuario en sesión
            $_SESSION["usuario_id"] = $fila["id_usuario_familia"];
            $_SESSION["usuario"] = $fila["nombre_usuario"];
            $_SESSION["rol"] = $fila["rol"];
            $_SESSION["nombre_familia"] = $fila["nombre_familia"];

            // Redirigir según rol
            if ($fila["rol"] == "administrador") {
                header("Location: administrador.php");
            } elseif ($fila["rol"] == "integrante") {
                header("Location: integrante.php");
            } else {
                echo "<script>alert('Rol no reconocido');</script>";
            }
            exit;
        } else {
            echo "<script>alert('Contraseña incorrecta');</script>";
        }
    } else {
        echo "<script>alert('Correo no encontrado');</script>";
    }
    $stmt->close();
}
?>