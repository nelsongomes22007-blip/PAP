<?php
session_start();
require __DIR__ . '/api/ligacao.php';

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION["user_id"];
$nome = $_SESSION["nome"] ?? "Utilizador";
$fotoUser = $_SESSION["foto"] ?? "uploads/default.png";

// Detectar logo da empresa
$logo = "uploads/default.png";
$candidates = glob(__DIR__ . '/uploads/logo.*');
if (!empty($candidates)) {
    $logo = 'uploads/' . basename($candidates[0]);
}

// Determine which user column to filter on
$colStmt = $pdo->query("DESCRIBE bookings");
$bookingCols = array_column($colStmt->fetchAll(PDO::FETCH_ASSOC), 'Field');
$userCol = in_array('id_utilizador', $bookingCols) ? 'id_utilizador' : (in_array('user_id', $bookingCols) ? 'user_id' : 'id_utilizador');

// Carregar as marcações do utilizador
$stmt = $pdo->prepare("SELECT id, data, hora, status FROM bookings WHERE $userCol = ? ORDER BY data DESC");
$stmt->execute([$user_id]);
$marcacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Função para traduzir dia e mês para português
function traduzirData($date) {
    $dias = ['Monday' => 'segunda-feira', 'Tuesday' => 'terça-feira', 'Wednesday' => 'quarta-feira', 
             'Thursday' => 'quinta-feira', 'Friday' => 'sexta-feira', 'Saturday' => 'sábado', 'Sunday' => 'domingo'];
    $meses = ['January' => 'janeiro', 'February' => 'fevereiro', 'March' => 'março', 'April' => 'abril',
              'May' => 'maio', 'June' => 'junho', 'July' => 'julho', 'August' => 'agosto',
              'September' => 'setembro', 'October' => 'outubro', 'November' => 'novembro', 'December' => 'dezembro'];
    
    $dia_semana = $dias[$date->format('l')] ?? $date->format('l');
    $mes = $meses[$date->format('F')] ?? $date->format('F');
    
    return ucfirst($dia_semana) . ', ' . $date->format('d') . ' de ' . $mes . ' de ' . $date->format('Y');
}

function traduzirMes($date) {
    $meses = ['JAN' => 'JAN', 'FEB' => 'FEV', 'MAR' => 'MAR', 'APR' => 'ABR',
              'MAY' => 'MAI', 'JUN' => 'JUN', 'JUL' => 'JUL', 'AUG' => 'AGO',
              'SEP' => 'SET', 'OCT' => 'OUT', 'NOV' => 'NOV', 'DEC' => 'DEZ'];
    return $meses[strtoupper($date->format('M'))] ?? strtoupper($date->format('M'));
}

// Normalizar caminho da foto
$fotoFinal = "uploads/default.png";
if (!empty($fotoUser)) {
    if (strpos($fotoUser, 'uploads/') === 0 && file_exists(__DIR__ . '/' . $fotoUser)) {
        $fotoFinal = $fotoUser;
    } elseif (file_exists(__DIR__ . '/uploads/' . $fotoUser)) {
        $fotoFinal = 'uploads/' . $fotoUser;
    } elseif (file_exists(__DIR__ . '/' . $fotoUser)) {
        $fotoFinal = $fotoUser;
    }
}
?>

<!DOCTYPE html>
<html lang="pt-PT">
<head>
<meta charset="UTF-8">
<title>Minhas Marcações | Sarytha Nails</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="manifest" href="/manifest.json">
<meta name="theme-color" content="#b0357c">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link rel="stylesheet" href="styles.css">

<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

:root {
    --primary: #b0357c;
    --primary-dark: #8b1e5f;
    --primary-light: #f8c8dc;
    --secondary: #c13584;
    --shadow: 0 10px 30px rgba(176, 53, 124, 0.15);
    --shadow-lg: 0 20px 50px rgba(176, 53, 124, 0.25);
}

body {
    font-family: 'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    background: linear-gradient(135deg, #f7d7e6, #fceef5);
    color: #333;
    padding: 20px;
}

.topbar {
    background: linear-gradient(90deg, var(--primary), var(--secondary));
    height: 75px;
    padding: 0 40px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    box-shadow: var(--shadow-lg);
    position: sticky;
    top: 0;
    z-index: 1000;
    margin: -20px -20px 40px -20px;
    border-radius: 0 0 15px 15px;
}

.topbar .logo {
    display: flex;
    align-items: center;
    gap: 12px;
    color: white;
    font-size: 24px;
    font-weight: 900;
    text-decoration: none;
    transition: 0.3s;
}

.topbar .logo:hover {
    transform: scale(1.05);
}

.topbar .logo img {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    object-fit: cover;
    border: 3px solid white;
}

.topbar .right {
    display: flex;
    gap: 12px;
    align-items: center;
}

.btn-top {
    background: rgba(255, 255, 255, 0.2);
    color: white;
    padding: 10px 20px;
    border-radius: 25px;
    font-weight: 700;
    text-decoration: none;
    font-size: 13px;
    border: 2px solid white;
    transition: all 0.3s;
    cursor: pointer;
}

.btn-top:hover {
    background: white;
    color: var(--primary);
    transform: translateY(-2px);
}

.perfil-box {
    display: flex;
    align-items: center;
    gap: 10px;
    text-decoration: none;
    color: white;
    font-weight: 700;
    transition: 0.3s;
    padding: 5px 10px;
    border-radius: 20px;
}

.perfil-box:hover {
    background: rgba(255, 255, 255, 0.15);
}

.perfil-box img {
    width: 42px;
    height: 42px;
    border-radius: 50%;
    border: 3px solid white;
    object-fit: cover;
    transition: 0.3s;
}

.perfil-box:hover img {
    transform: scale(1.12);
    box-shadow: 0 0 15px rgba(255, 255, 255, 0.5);
}

.container {
    max-width: 1000px;
    margin: 0 auto;
}

.page-header {
    text-align: center;
    margin-bottom: 40px;
    animation: slideUp 0.5s ease;
}

@keyframes slideUp {
    from {
        transform: translateY(30px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

.page-header h1 {
    color: var(--primary);
    font-size: 32px;
    margin-bottom: 10px;
}

.page-header p {
    color: #666;
    font-size: 16px;
}

.bookings-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 25px;
    margin-bottom: 40px;
}

.booking-card {
    background: white;
    border-radius: 20px;
    padding: 30px;
    box-shadow: var(--shadow);
    border-left: 5px solid var(--primary);
    transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.booking-card:hover {
    transform: translateY(-10px);
    box-shadow: var(--shadow-lg);
}

.booking-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
}

.booking-date {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: white;
    padding: 15px 20px;
    border-radius: 12px;
    text-align: center;
    min-width: 90px;
}

.booking-date .day {
    font-size: 24px;
    font-weight: 900;
}

.booking-date .month {
    font-size: 12px;
    text-transform: uppercase;
    opacity: 0.9;
}

.status-badge {
    padding: 8px 16px;
    border-radius: 20px;
    font-weight: 700;
    font-size: 12px;
    text-transform: uppercase;
}

.status-pendente {
    background: #fff3cd;
    color: #856404;
}

.status-confirmada {
    background: #d1ecf1;
    color: #0c5460;
}

.status-concluida {
    background: #d4edda;
    color: #155724;
}

.status-rejeitada {
    background: #f8d7da;
    color: #721c24;
}

.booking-details {
    margin-bottom: 20px;
}

.booking-detail-row {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 12px;
    color: #555;
    font-size: 15px;
}

.booking-detail-row i {
    color: var(--primary);
    width: 20px;
    text-align: center;
    font-size: 16px;
}

.empty-state {
    background: white;
    border-radius: 20px;
    padding: 80px 40px;
    text-align: center;
    box-shadow: var(--shadow);
}

.empty-state i {
    font-size: 64px;
    color: var(--primary-light);
    margin-bottom: 20px;
}

.empty-state h3 {
    color: var(--primary);
    margin-bottom: 10px;
    font-size: 22px;
}

.empty-state p {
    color: #666;
    margin-bottom: 30px;
    font-size: 15px;
}

.empty-state a {
    display: inline-block;
    background: linear-gradient(90deg, var(--primary), var(--secondary));
    color: white;
    padding: 12px 30px;
    border-radius: 20px;
    text-decoration: none;
    font-weight: 700;
    transition: all 0.3s;
}

.empty-state a:hover {
    transform: translateY(-3px);
    box-shadow: var(--shadow-lg);
}

.footer {
    text-align: center;
    color: #888;
    font-size: 14px;
    margin-top: 60px;
    padding-top: 30px;
    border-top: 1px solid #e0e0e0;
}

/* RESPONSIVE */
@media (max-width: 768px) {
    .topbar {
        padding: 0 20px;
        height: 65px;
    }

    .topbar .logo {
        font-size: 18px;
    }

    .page-header h1 {
        font-size: 24px;
    }

    .bookings-grid {
        grid-template-columns: 1fr;
    }
}
</style>
</head>

<body>

<!-- TOPBAR -->
<div class="topbar">
    <a class="logo" href="index.php">
        <img src="<?php echo htmlspecialchars($logo); ?>" alt="Logo" onerror="this.src='/uploads/default.png'">
        <span>Sarytha Nails</span>
    </a>

    <div class="right">
        <a class="perfil-box" href="editar_perfil.php">
            <img src="<?php echo htmlspecialchars($fotoFinal); ?>" alt="Perfil" onerror="this.src='uploads/default.png'">
            <span><?php echo htmlspecialchars($nome); ?></span>
        </a>
        <a class="btn-top" href="index.php"><i class="fas fa-home"></i> Início</a>
        <a class="btn-top" href="logout.php"><i class="fas fa-sign-out-alt"></i> Sair</a>
    </div>
</div>

<!-- MAIN CONTENT -->
<div class="container">
    <div class="page-header">
        <h1><i class="fas fa-calendar-check"></i> Minhas Marcações</h1>
        <p>Acompanha o estado das tuas marcações</p>
    </div>

    <?php if (!empty($marcacoes)): ?>
        <div class="bookings-grid">
            <?php foreach ($marcacoes as $m): 
                $date = new DateTime($m['data']);
                $status_class = 'status-' . $m['status'];
                $status_texto = ucfirst($m['status']);
            ?>
                <div class="booking-card">
                    <div class="booking-header">
                        <div class="booking-date">
                            <div class="day"><?php echo $date->format('d'); ?></div>
                            <div class="month"><?php echo traduzirMes($date); ?></div>
                        </div>
                        <div class="status-badge <?php echo $status_class; ?>">
                            <?php echo $status_texto; ?>
                        </div>
                    </div>

                    <div class="booking-details">
                        <div class="booking-detail-row">
                            <i class="fas fa-calendar-alt"></i>
                            <span><?php echo traduzirData($date); ?></span>
                        </div>

                        <div class="booking-detail-row">
                            <i class="fas fa-clock"></i>
                            <span><?php echo $m['hora']; ?></span>
                        </div>

                        <div class="booking-detail-row">
                            <i class="fas fa-info-circle"></i>
                            <span>
                                <?php
                                    switch($m['status']) {
                                        case 'pendente':
                                            echo 'À espera de confirmação';
                                            break;
                                        case 'confirmada':
                                            echo 'Confirmada';
                                            break;
                                        case 'concluida':
                                            echo 'Já foi realizada';
                                            break;
                                        case 'rejeitada':
                                            echo 'Foi cancelada';
                                            break;
                                    }
                                ?>
                            </span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-calendar-times"></i>
            <h3>Nenhuma marcação</h3>
            <p>Ainda não tens marcações. Agenda uma agora para começar!</p>
            <a href="index.php#booking"><i class="fas fa-plus"></i> Agendar Agora</a>
        </div>
    <?php endif; ?>

    <div class="footer">
        © <?php echo date("Y"); ?> Sarytha Nails. Todos os direitos reservados.
    </div>
</div>


<script>
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/service-worker.js');
}
</script>
</body>
</html>
