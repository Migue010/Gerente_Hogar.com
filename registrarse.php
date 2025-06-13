<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <title>Registrarse - GerenteHogar.com</title>
  <link rel="stylesheet" href="Archivos_CSS/login.css" />
</head>
<body>

<header>
  <div class="logo">GERENTEHOGAR.COM</div>
  <nav>
    <a href="gerente_hogar.html">Inicio</a>
    <a href="inicio_sesion.php">Iniciar sesión</a>
    <a href="registrarse.php">Registrarse</a>
  </nav>
</header>

<section class="hero" style="padding: 2rem; text-align: center;">
  <h1>Crear una cuenta nueva para tu familia</h1>
    <p>Regístrate para gestionar las finanzas de tu hogar en familia.</p>
</section>

<section class="form-container">
  <form action="" method="POST" autocomplete="off">
    <label for="family_name">Nombre de la Familia/Casa</label>
    <input type="text" id="family_name" name="family_name" required />

    <label for="username">Tu Nombre de Usuario</label>
    <input type="text" id="username" name="username" required />

    <label for="email">Correo electrónico</label>
    <input type="email" id="email" name="email" required />

    <label for="password">Contraseña</label>
    <input type="password" id="password" name="password" required />

    <label for="role">Rol en la Familia</label>
      <select id="role" name="role" required>
        <option value="">Selecciona tu rol</option>
        <option value="administrador">Administrador</option>
        <option value="integrante">Integrante</option>
      </select>

         <button name="button" type="submit">Registrarse</button>
  </form>
</section>

  <footer>
    <p>&copy; 2025 GERENTEHOGAR.COM - Todos los derechos reservados.</p>
      <a href="inicio_sesion.php">Ingresar</a>
   
  </footer>
</body>
</html>

<?php
include_once("conexion.php");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nombre_familia = trim($_POST["family_name"]); 
    $nombre_usuario = trim($_POST["username"]);
    $correo = trim($_POST["email"]);
    $contrasena_plana = $_POST["password"]; 
    $rol = $_POST["role"];

    if (empty($nombre_familia) || empty($nombre_usuario) || empty($correo) || empty($contrasena_plana) || empty($rol)) {
        echo "<script>alert('Por favor, complete todos los campos.');</script>";
        exit();
    }

    $contrasena = password_hash($contrasena_plana, PASSWORD_DEFAULT); 

    $check_user_sql = "SELECT id_usuario_familia FROM usuarios_familia WHERE nombre_usuario = ? OR correo = ?";
    $stmt_check_user = $conexion->prepare($check_user_sql);
    if ($stmt_check_user === false) {
        die("Error preparando la consulta de verificación de usuario/correo: " . $conexion->error);
    }
    $stmt_check_user->bind_param("ss", $nombre_usuario, $correo);
    $stmt_check_user->execute();
    $stmt_check_user->store_result();

    if ($stmt_check_user->num_rows > 0) {
        echo "<script>alert('El nombre de usuario o correo electrónico ya está registrado. Por favor, elija otro.');</script>";
        $stmt_check_user->close();
        exit(); 
    }
    $stmt_check_user->close();

    // --- Lógica de validación de familia según el rol ---
    if ($rol === 'administrador') {
        // Si es administrador, verificar si ya existe un administrador para esta familia
        $check_admin_family_sql = "SELECT id_usuario_familia FROM usuarios_familia WHERE nombre_familia = ? AND rol = 'administrador'";
        $stmt_check_admin_family = $conexion->prepare($check_admin_family_sql);
        if ($stmt_check_admin_family === false) {
            die("Error preparando la consulta de verificación de administrador de familia: " . $conexion->error);
        }
        $stmt_check_admin_family->bind_param("s", $nombre_familia);
        $stmt_check_admin_family->execute();
        $stmt_check_admin_family->store_result();

        if ($stmt_check_admin_family->num_rows > 0) {
            echo "<script>alert('Ya existe un administrador para la familia \\'" . htmlspecialchars($nombre_familia) . "\\'. Si desea unirse a esta familia, regístrese como \\'integrante\\'.');</script>";
            $stmt_check_admin_family->close();
            exit();
        }
        $stmt_check_admin_family->close();

    } elseif ($rol === 'integrante') {
        // Si es integrante, verificar si existe AL MENOS UN administrador para la familia
        $check_family_exists_sql = "SELECT id_usuario_familia FROM usuarios_familia WHERE nombre_familia = ? AND rol = 'administrador'";
        $stmt_check_family_exists = $conexion->prepare($check_family_exists_sql);
        if ($stmt_check_family_exists === false) {
            die("Error preparando la consulta de verificación de existencia de familia para integrante: " . $conexion->error);
        }
        $stmt_check_family_exists->bind_param("s", $nombre_familia);
        $stmt_check_family_exists->execute();
        $stmt_check_family_exists->store_result();

        if ($stmt_check_family_exists->num_rows === 0) {
            echo "<script>alert('La familia \\'" . htmlspecialchars($nombre_familia) . "\\' aún no ha sido creada o no tiene un administrador. Pida a un miembro de su hogar que se registre primero como \\'Administrador\\' de la familia.');</script>";
            $stmt_check_family_exists->close();
            exit();
        }
        $stmt_check_family_exists->close();
    }
    // --- Fin de la lógica de validación de familia ---

     // Insertar nuevo usuario en la tabla usuarios_familia

    $sql = "INSERT INTO usuarios_familia (nombre_familia, nombre_usuario, correo, contrasena, rol) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conexion->prepare($sql);

    if ($stmt === false) {
        die("Error preparando la consulta de inserción: " . $conexion->error);
    }

    $stmt->bind_param("sssss", $nombre_familia, $nombre_usuario, $correo, $contrasena, $rol);

    if ($stmt->execute()) {
        header("Location: inicio_sesion.php?registration=success"); 
        exit(); 
    } else {
        echo "<script>alert('Error al registrar el usuario: " . $stmt->error . "');</script>";
    }
    $stmt->close(); 
}

?>