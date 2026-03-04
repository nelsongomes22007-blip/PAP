<?php
session_start();
require __DIR__ . '/../api/ligacao.php';

if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role'] ?? '') !== 'admin') {
    die('Acesso negado');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: marcacoes.php');
    exit;
}

$id_utilizador = $_POST['id_utilizador'] ?? null;
$data = trim($_POST['data'] ?? '');
$hora = trim($_POST['hora'] ?? '');
$servico = trim($_POST['servico'] ?? '');

// lista de serviços e durações
$servicos = [
    'Manicure' => 60,
    'Pedicure' => 60,
    'Gel & Acrílico' => 90,
    'Design Artístico' => 120,
    'Spa de Mãos' => 45,
    'Pacotes Premium' => 120,
];
// carregar do banco se existir
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'servicos'");
    if ($stmt->rowCount() > 0) {
        $sstmt = $pdo->query("SELECT nome, duracao_min FROM servicos WHERE ativo = 1");
        $rows = $sstmt->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($rows)) {
            $servicos = [];
            foreach ($rows as $r) {
                $servicos[$r['nome']] = (int)$r['duracao_min'];
            }
        }
    }
} catch (Exception $e) { }


// validar
if (empty($data) || empty($hora) || empty($servico)) {
    header('Location: marcacoes.php?erro=empty');
    exit;
}
if (!array_key_exists($servico, $servicos)) {
    header('Location: marcacoes.php?erro=empty');
    exit;
}

// data não pode ser no passado
$dObj = DateTime::createFromFormat('Y-m-d', $data);
$tObj = DateTime::createFromFormat('H:i', $hora);
if (!$dObj || !$tObj) {
    header('Location: marcacoes.php?erro=format');
    exit;
}
$today = new DateTime();
$bookingDT = DateTime::createFromFormat('Y-m-d H:i', "$data $hora");
if ($bookingDT < $today) {
    header('Location: marcacoes.php?erro=past');
    exit;
}
// horário de funcionamento
$weekday = (int)$bookingDT->format('N');
$hour = (int)$tObj->format('H');
$minute = (int)$tObj->format('i');
$inSlot = false;
if ($weekday >= 1 && $weekday <= 5) {
    if ($hour >= 18 && ($hour < 21 || ($hour == 21 && $minute == 0))) {
        $inSlot = true;
    }
} elseif ($weekday == 6) {
    if (($hour >= 9 && $hour < 12) || ($hour >= 13 && $hour < 16) || ($hour == 12 && $minute == 0) || ($hour == 16 && $minute == 0)) {
        $inSlot = true;
    }
}
if (!$inSlot) {
    header('Location: marcacoes.php?erro=horario');
    exit;
}

// verificar disponibilidade (duração baseada no serviço)
$dur = $servicos[$servico];
$stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM bookings \
     WHERE data = ? AND status != 'rejeitada' \
       AND hora < ADDTIME(?, SEC_TO_TIME(? * 60)) \
       AND ADDTIME(hora, SEC_TO_TIME(? * 60)) > ?"
);
$stmt->execute([$data, $hora, $dur, $dur, $hora]);
$cnt = $stmt->fetchColumn();
if ($cnt > 0) {
    header('Location: marcacoes.php?erro=ocupada');
    exit;
}

// inserir
try {
    // build dynamic insert similar to public flow
    $bColsStmt = $pdo->query("DESCRIBE bookings");
    $bookingCols = array_column($bColsStmt->fetchAll(PDO::FETCH_ASSOC), 'Field');

    $cols = ['id_utilizador', 'data', 'hora', 'status'];
    $vals = [ $id_utilizador ?: null, $data, $hora, 'confirmada' ];
    $ph = ['?', '?', '?', '?'];

    if (in_array('servico', $bookingCols)) {
        $cols[] = 'servico';
        $vals[] = $servico;
        $ph[] = '?';
    }

    $query = 'INSERT INTO bookings (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $ph) . ')';
    $stmt = $pdo->prepare($query);
    $stmt->execute($vals);
    header('Location: marcacoes.php?sucesso=criacao');
    exit;
} catch (Exception $e) {
    header('Location: marcacoes.php?erro=server');
    exit;
}
