<?php
$host = "localhost";
$db   = "sara_gomes_nails";
$user = "sarytah_user"; // Altere para o seu utilizador MySQL
$pass = "senha_segura123"; // Altere para a sua senha MySQL

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$db;charset=utf8mb4",
        $user,
        $pass,
            [ 
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, 
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC 
            ] 
        ); 
} catch (PDOException $e) {
        die("Erro BD: " . $e->getMessage() . "<br>Verifique o utilizador e senha em api/ligacao.php e se o utilizador tem permissões na base de dados.");
}
