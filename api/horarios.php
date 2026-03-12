<?php
require __DIR__ . "/ligacao.php";
header('Content-Type: application/json');

date_default_timezone_set('Europe/Lisbon');

$date = $_GET['date'] ?? '';
if (!$date) {
    echo json_encode(['error' => 'missing_date']);
    exit;
}
// validate format
$dt = DateTime::createFromFormat('Y-m-d', $date);
if (!$dt || $dt->format('Y-m-d') !== $date) {
    echo json_encode(['error' => 'invalid_date']);
    exit;
}

$weekday = (int)$dt->format('N'); // 1=Mon ... 7=Sun
$periods = [];
if ($weekday >= 1 && $weekday <= 5) {
    $periods[] = ['start' => '18:00', 'end' => '21:00'];
} elseif ($weekday == 6) {
    $periods[] = ['start' => '09:00', 'end' => '12:00'];
    $periods[] = ['start' => '13:00', 'end' => '16:00'];
}

// fetch existing bookings for the day with duration
try {
    $stmt = $pdo->prepare(
        "SELECT hora, servico, COALESCE(s.duracao_min, 0) AS dur
         FROM bookings b
         LEFT JOIN servicos s ON s.nome = b.servico
         WHERE data = ? AND status != 'rejeitada'"
    );
    $stmt->execute([$date]);
    $bookings = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $bookings[] = [
            'hora' => $r['hora'],
            'dur'  => (int)$r['dur'],
        ];
    }
} catch (Exception $e) {
    $bookings = [];
}

echo json_encode([
    'periods' => $periods,
    'bookings' => $bookings,
]);
