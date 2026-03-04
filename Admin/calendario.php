<?php
session_start();
require __DIR__ . '/../api/ligacao.php';

if (!isset($_SESSION["user_id"]) || strtolower($_SESSION["role"]) !== 'admin') {
    die("Acesso negado");
}

// buscar marcações
$stmt = $pdo->query("
    SELECT id, data, hora, status 
    FROM bookings
");
$eventos = [];

while ($m = $stmt->fetch()) {
    $eventos[] = [
        'title' => ucfirst($m['status']),
        'start' => $m['data'].'T'.$m['hora'],
        'color' => $m['status'] === 'confirmada' ? '#4caf50' :
                   ($m['status'] === 'pendente' ? '#ff9800' : '#f44336')
    ];
}
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
<meta charset="UTF-8">
<title>Calendário</title>

<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>

<style>
body {
    background: linear-gradient(120deg,#fff0f6,#ffe4f1);
    font-family: Poppins;
}
#calendar {
    max-width: 900px;
    margin: 50px auto;
    background: #fff;
    padding: 20px;
    border-radius: 20px;
}
</style>
</head>

<body>

<div id="calendar"></div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    new FullCalendar.Calendar(document.getElementById('calendar'), {
        initialView: 'dayGridMonth',
        locale: 'pt',
        events: <?= json_encode($eventos) ?>
    }).render();
});
</script>

</body>
</html>
