<?php 
session_start();

session_destroy();

echo"<script language='javascript'>
        window.location.href='../../front_end/home/home.html'
        </script>";
?>