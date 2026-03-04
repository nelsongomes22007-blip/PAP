<?php
session_start();
require __DIR__ . '/../api/ligacao.php';

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit;
}

if (!isset($_SESSION["role"]) || strtolower($_SESSION["role"]) !== "admin") {
    die("Acesso restrito a administradores.");
}

$id = isset($_GET["id"]) ? (int) $_GET["id"] : 0;
$status = $_GET["status"] ?? "";

$permitidos = ["pendente", "confirmada", "rejeitada", "concluida"];

if ($id > 0 && in_array($status, $permitidos)) {

    // Só permite concluir se já estiver confirmada
    if ($status === "concluida") {
        $check = $pdo->prepare("SELECT status FROM bookings WHERE id = ?");
        $check->execute([$id]);
        $row = $check->fetch();

        if (!$row || $row["status"] !== "confirmada") {
            header("Location: marcacoes.php");
            exit;
        }
    }

    $stmt = $pdo->prepare("UPDATE bookings SET status = ? WHERE id = ?");
    $stmt->execute([$status, $id]);
}

header("Location: marcacoes.php");
exit;
