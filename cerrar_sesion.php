<?php
session_start();
session_unset();      
session_destroy();  

header("Location: gerente_hogar.html");

exit();
?>

