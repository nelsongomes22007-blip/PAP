<?php
session_start();
require __DIR__ . '/../api/ligacao.php';

if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role'] ?? '') !== 'admin') {
    die('Acesso restrito');
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
action:
$action = $_GET['action'] ?? '';

if ($id && $action === 'aprovar') {
    try {
        // figure out primary key column name
        $colRes = $pdo->query("DESCRIBE avaliacoes");
        $cols = array_column($colRes->fetchAll(PDO::FETCH_ASSOC), 'Field');
        $pk = in_array('id_avaliacao', $cols) ? 'id_avaliacao' : 'id';
        
        $pdo->exec("UPDATE avaliacoes SET aprovado = 1 WHERE $pk = " . (int)$id);
    } catch (Exception $e) {
        // ignore
    }
}

header('Location: index.php');
exit;
