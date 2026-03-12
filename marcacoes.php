<?php
session_start();
require __DIR__ . "/api/ligacao.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: index.php");
    exit;
}

$data = trim($_POST["data"] ?? "");
$hora = trim($_POST["hora"] ?? "");
$servico = trim($_POST['servico'] ?? '');

// lista de serviços para cálculo de duração
$servicos = [
    'Manicure' => 60,
    'Pedicure' => 60,
    'Gel & Acrílico' => 90,
    'Design Artístico' => 120,
    'Spa de Mãos' => 45,
    'Pacotes Premium' => 120,
];
// tentar carregar do banco se disponível
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
} catch (Exception $e) {
    // ignore
} 

// Campos opcionais para guest
$guest_nome = trim($_POST['nome'] ?? '');
$guest_email = trim($_POST['email'] ?? '');

// Validar se veio preenchido
if (empty($data) || empty($hora) || empty($servico)) {
    // redirect with error
    header("Location: index.php?marcacao_erro=empty#booking");
    exit;
}

// serviço válido
if (!array_key_exists($servico, $servicos)) {
    header("Location: index.php?marcacao_erro=service#booking");
    exit;
}

// Validar formato de data e hora
$dObj = DateTime::createFromFormat('Y-m-d', $data);
$tObj = DateTime::createFromFormat('H:i', $hora);
if (!$dObj || $dObj->format('Y-m-d') !== $data || !$tObj || $tObj->format('H:i') !== $hora) {
    header("Location: index.php?marcacao_erro=format#booking");
    exit;
}

// não pode marcar no passado (data+hora)
$now = new DateTime();
$bookingDT = DateTime::createFromFormat('Y-m-d H:i', "$data $hora");
if (!$bookingDT || $bookingDT < $now) {
    file_put_contents(__DIR__ . '/scripts/marcacoes_errors.log', "[".date('Y-m-d H:i:s')."] past check failed: now={$now->format('Y-m-d H:i:s')}, booking={$bookingDT?->format('Y-m-d H:i:s')}\n", FILE_APPEND);
    header("Location: index.php?marcacao_erro=past#booking");
    exit;
}

// verificar horário de trabalho
$weekday = (int)$bookingDT->format('N'); // 1=Mon,6=Sat,7=Sun
$hour = (int)$tObj->format('H');
$minute = (int)$tObj->format('i');
$inSlot = false;
if ($weekday >= 1 && $weekday <= 5) {
    // seg-sex 18:00-21:00
    if ($hour >= 18 && ($hour < 21 || ($hour == 21 && $minute == 0))) {
        $inSlot = true;
    }
} elseif ($weekday == 6) {
    // sábado 09-12 e 13-16
    if (($hour >= 9 && $hour < 12) || ($hour >= 13 && $hour < 16) || ($hour == 12 && $minute == 0) || ($hour == 16 && $minute == 0)) {
        $inSlot = true;
    }
}
if (!$inSlot) {
    file_put_contents(__DIR__ . '/scripts/marcacoes_errors.log', "[".date('Y-m-d H:i:s')."] outside business hours: weekday=$weekday, time={$tObj->format('H:i')}\n", FILE_APPEND);
    header("Location: index.php?marcacao_erro=horario#booking");
    exit;
}

// verificar disponibilidade (usa duração do serviço)
$dur = $servicos[$servico];
$stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM bookings WHERE data = ? AND status != 'rejeitada' AND hora < ADDTIME(?, SEC_TO_TIME(? * 60)) AND ADDTIME(hora, SEC_TO_TIME(? * 60)) > ?"
);
$stmt->execute([$data, $hora, $dur, $dur, $hora]);
$cnt = $stmt->fetchColumn();
if ($cnt > 0) {
    file_put_contents(__DIR__ . '/scripts/marcacoes_errors.log', "[".date('Y-m-d H:i:s')."] ocupado check failed: date=$data, hora=$hora, count=$cnt\n", FILE_APPEND);
    header("Location: index.php?marcacao_erro=ocupada#booking");
    exit;
}

$id_utilizador = $_SESSION['user_id'] ?? null;

// Se não estiver logado, obrigar nome + email
if (!$id_utilizador) {
    if (empty($guest_nome) || empty($guest_email)) {
        die("Erro: Nome e email são necessários para marcações sem conta.");
    }
    if (!filter_var($guest_email, FILTER_VALIDATE_EMAIL)) {
        die("Erro: Email inválido.");
    }
}

try {
    // Inserir marcação. adaptar conforme colunas disponíveis
    $cols = [];
    $vals = [];
    $placeholders = [];

    // figure out which column stores user reference
    $bColsStmt = $pdo->query("DESCRIBE bookings");
    $bookingCols = array_column($bColsStmt->fetchAll(PDO::FETCH_ASSOC), 'Field');
    $userCol = in_array('id_utilizador', $bookingCols) ? 'id_utilizador' : (in_array('user_id', $bookingCols) ? 'user_id' : 'id_utilizador');

    // always present fields
    $cols[] = $userCol;
    $vals[] = $id_utilizador;
    $placeholders[] = '?';

    $cols[] = 'nome';
    $vals[] = $guest_nome ?: null;
    $placeholders[] = '?';

    $cols[] = 'email';
    $vals[] = $guest_email ?: null;
    $placeholders[] = '?';

    $cols[] = 'data';
    $vals[] = $data;
    $placeholders[] = '?';

    $cols[] = 'hora';
    $vals[] = $hora;
    $placeholders[] = '?';

    // add servico if column exists
    if (in_array('servico', $bookingCols)) {
        $cols[] = 'servico';
        $vals[] = $servico;
        $placeholders[] = '?';
    }

    $cols[] = 'status';
    $vals[] = 'pendente';
    $placeholders[] = '?';

    $query = "INSERT INTO bookings (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $placeholders) . ")";
    $stmt = $pdo->prepare($query);
    $stmt->execute($vals);

    header("Location: index.php?marcacao_sucesso=1#booking");
    exit;

} catch (Exception $e) {
    // Log detalhado para debugging
    $logDir = __DIR__ . '/scripts';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0777, true);
    }
    $logFile = $logDir . '/marcacoes_errors.log';
    $msg = "[" . date('Y-m-d H:i:s') . "] Erro ao criar marcação: " . $e->getMessage() . "\n";
    $msg .= "POST: " . json_encode($_POST) . "\n\n";
    file_put_contents($logFile, $msg, FILE_APPEND);

    header("Location: index.php?marcacao_erro=server");
    exit;
}
?>
