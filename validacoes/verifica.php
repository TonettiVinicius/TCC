<?php
session_start();

if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['professor_id'])) {
    session_destroy();
    header("Location: ../../front_end/usuario/login.html");
    exit;
}
?>