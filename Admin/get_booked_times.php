<?php
require __DIR__ . '/../api/ligacao.php';

$date = $_GET['date'] ?? null;
header('Content-Type: application/json');

if (!$date) {
    echo json_encode(['error' => 'missing_date']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT hora, servico FROM bookings WHERE data = ? AND status != 'rejeitada'");
    $stmt->execute([$date]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // duration map should mirror the one used elsewhere
    $servicos = [
        'Manicure' => 60,
        'Pedicure' => 60,
        'Gel & Acrílico' => 90,
        'Design Artístico' => 120,
        'Spa de Mãos' => 45,
        'Pacotes Premium' => 120,
    ];

    $blocked = [];
    foreach ($rows as $r) {
        $start = DateTime::createFromFormat('Y-m-d H:i', "$date {$r['hora']}");
        if (!$start) continue;
        $dur = $servicos[$r['servico']] ?? 60;
        $end = (clone $start)->add(new DateInterval('PT' . $dur . 'M'));
        $interval = new DateInterval('PT30M');
        for ($t = clone $start; $t < $end; $t->add($interval)) {
            $blocked[] = $t->format('H:i');
        }
    }
    $times = array_values(array_unique($blocked));
    echo json_encode(['times' => $times]);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
