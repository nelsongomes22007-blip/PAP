<?php
session_start();
require __DIR__ . "/../api/ligacao.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit;
}

if (!isset($_SESSION["role"]) || strtolower($_SESSION["role"]) !== "admin") {
    die("Acesso restrito.");
}

$currentYear = date('Y');
$stmt = $pdo->prepare("SELECT MONTH(data) as mes, COUNT(*) as total FROM bookings WHERE YEAR(data) = ? GROUP BY MONTH(data) ORDER BY mes");
$stmt->execute([$currentYear]);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Prepare data for chart
$months = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
$data = array_fill(0, 12, 0);
foreach ($bookings as $b) {
    $data[$b['mes'] - 1] = (int)$b['total'];
}

// Dashboard Statistics
$totalBookings = 0;
$totalClientes = 0;
$bookingHoje = 0;
$proximasMarcacoes = [];
$ultimasAvaliacoes = [];

try {
    // Total de marcações
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM bookings");
    $res = $stmt->fetch();
    $totalBookings = $res['total'] ?? 0;
    
    // Marcações de hoje
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM bookings WHERE DATE(data) = CURDATE()");
    $stmt->execute();
    $res = $stmt->fetch();
    $bookingHoje = $res['total'] ?? 0;
    
    // Próximas 5 marcações
    // determine user table and id column dynamically
    $userTable = 'users';
    $userIdCol = 'user_id';
    try {
        $pdo->query("SELECT 1 FROM utilizadores LIMIT 1");
        $userTable = 'utilizadores';
        $userIdCol = 'id';
    } catch (Exception $e) {
        // keep defaults
    }

    // Buscar próximas marcações (sem imagens) — usar coluna correta para o JOIN (id_utilizador ou user_id)
    $bColsStmt = $pdo->query("DESCRIBE bookings");
    $bookingCols = array_column($bColsStmt->fetchAll(PDO::FETCH_ASSOC), 'Field');
    $joinCol = in_array('id_utilizador', $bookingCols) ? 'id_utilizador' : (in_array('user_id', $bookingCols) ? 'user_id' : 'id_utilizador');

    $sql = "SELECT b.*, u.nome as nome_cliente FROM bookings b LEFT JOIN $userTable u ON b." . $joinCol . " = u." . $userIdCol . " WHERE b.data >= CURDATE() ORDER BY b.data, b.hora LIMIT 5";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $proximasMarcacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Últimas 3 avaliações (adaptar esquema caso colunas sejam diferentes)
    try {
        $colRes = $pdo->query("DESCRIBE avaliacoes");
        $cols = array_column($colRes->fetchAll(PDO::FETCH_ASSOC), 'Field');
        // always include primary key column so we can refer to it later
        $idField = in_array('id_avaliacao', $cols) ? 'id_avaliacao' : 'id';
        $select = ["a.$idField AS id"];
        if (in_array('classificacao', $cols)) {
            $select[] = 'a.classificacao AS rating';
        } elseif (in_array('rating', $cols)) {
            $select[] = 'a.rating';
        }
        if (in_array('comentario', $cols)) {
            $select[] = 'a.comentario';
        }
        $hasName = in_array('nome', $cols);
        $hasUserId = in_array('id_utilizador', $cols) || in_array('user_id', $cols);
        if ($hasName) {
            $select[] = 'a.nome';
        } elseif ($hasUserId) {
            $select[] = 'u.nome AS nome';
        }
        if (in_array('criado_em', $cols)) {
            $select[] = 'a.criado_em AS created_at';
        } elseif (in_array('created_at', $cols)) {
            $select[] = 'a.created_at';
        }

        // first get pending reviews if approval column exists
        if (in_array('aprovado', $cols)) {
            $pendingQuery = "SELECT a.*";
            // rebuild same select fields for pending display
            $pendingQuery = "SELECT " . implode(', ', $select) . "";
            $pendingQuery .= " FROM avaliacoes a";
            if ($hasUserId && !$hasName) {
                $joinTable = $userTable;
                $joinId   = $userTable === 'utilizadores' ? 'id' : 'user_id';
                $pendingQuery .= " LEFT JOIN $joinTable u ON a." . (in_array('id_utilizador', $cols) ? 'id_utilizador' : 'user_id') . " = u." . $joinId;
            }
            $pendingQuery .= " WHERE a.aprovado = 0";
            $pendingQuery .= " ORDER BY " . (in_array('criado_em', $cols) ? 'a.criado_em' : 'a.created_at') . " DESC";
            $stmt = $pdo->query($pendingQuery);
            $pendentesAvaliacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $query = "SELECT " . implode(', ', $select) . " FROM avaliacoes a";
        if ($hasUserId && !$hasName) {
            // make join use whichever user table exists
            $joinTable = $userTable; // determined earlier above
            $joinId   = $userTable === 'utilizadores' ? 'id' : 'user_id';
            $query .= " LEFT JOIN $joinTable u ON a." . (in_array('id_utilizador', $cols) ? 'id_utilizador' : 'user_id') . " = u." . $joinId;
        }
        if (in_array('aprovado', $cols)) {
            $query .= " WHERE a.aprovado = 1";
        }
        $query .= " ORDER BY " . (in_array('criado_em', $cols) ? 'a.criado_em' : 'a.created_at') . " DESC LIMIT 3";
        $stmt = $pdo->query($query);
        $ultimasAvaliacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $ultimasAvaliacoes = [];
        $pendentesAvaliacoes = [];
    }
} catch (Exception $e) {
    // Silenciosamente falhar se as tabelas não existirem
}

$nome = $_SESSION["nome"] ?? "Admin";
$fotoSessao = $_SESSION["foto"] ?? '';

// Resolver caminho da foto de sessão de forma robusta.
$foto = "../uploads/default.png";
if (!empty($fotoSessao)) {
    if (strpos($fotoSessao, 'uploads/') === 0) {
        if (file_exists(__DIR__ . '/../' . $fotoSessao)) {
            $foto = '../' . $fotoSessao;
        }
    } else {
        if (file_exists(__DIR__ . '/../uploads/' . $fotoSessao)) {
            $foto = '../uploads/' . $fotoSessao;
        } elseif (file_exists(__DIR__ . '/../' . $fotoSessao)) {
            $foto = '../' . $fotoSessao;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-PT">
<head>
<meta charset="UTF-8">
<title>Painel Admin</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link rel="stylesheet" href="../styles.css">

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
}

/* TOPBAR */
.topbar {
    background: linear-gradient(90deg, var(--primary), var(--secondary));
    padding: 20px 40px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    color: white;
    box-shadow: var(--shadow-lg);
    position: sticky;
    top: 0;
    z-index: 100;
}

.topbar .left {
    display: flex;
    align-items: center;
    gap: 15px;
    font-weight: 700;
}

.topbar .left a {
    display: flex;
    align-items: center;
    gap: 12px;
    color: white;
    text-decoration: none;
    transition: all 0.3s;
}

.topbar .left a:hover {
    opacity: 0.9;
}

.topbar .left a img {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    border: 3px solid white;
    object-fit: cover;
    transition: all 0.3s;
}

.topbar .left a:hover img {
    transform: scale(1.1);
    box-shadow: 0 0 15px rgba(255, 255, 255, 0.5);
}

.topbar .left span {
    font-size: 16px;
}

/* TOP RIGHT BUTTONS */
.topbar .right {
    display: flex;
    gap: 12px;
}

.home-btn, .logout-btn {
    padding: 12px 24px;
    border-radius: 12px;
    text-decoration: none;
    font-weight: 700;
    transition: all 0.3s;
    border: none;
    cursor: pointer;
    font-size: 14px;
    font-family: 'Poppins', sans-serif;
}

.home-btn {
    background: rgba(255, 255, 255, 0.2);
    color: white;
    border: 2px solid white;
}

.home-btn:hover {
    background: white;
    color: var(--primary);
    transform: translateY(-2px);
}

.logout-btn {
    background: rgba(255, 0, 0, 0.7);
    color: white;
}

.logout-btn:hover {
    background: rgba(255, 0, 0, 0.9);
    transform: translateY(-2px);
}

/* CONTAINER */
.container {
    max-width: 1200px;
    margin: 50px auto;
    padding: 0 20px;
}

/* CARDS */
.card {
    background: white;
    border-radius: 25px;
    padding: 45px;
    box-shadow: var(--shadow-lg);
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

.card h2 {
    text-align: center;
    color: var(--primary);
    font-size: 28px;
    margin-bottom: 35px;
    font-weight: 900;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}

/* MENU GRID */
.menu {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 25px;
    margin-bottom: 40px;
}

.menu a {
    background: linear-gradient(135deg, #fff0f6, #ffe4f1);
    padding: 30px;
    border-radius: 18px;
    text-align: center;
    font-weight: 700;
    text-decoration: none;
    color: var(--primary);
    font-size: 15px;
    transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
    box-shadow: var(--shadow);
    border: 2px solid transparent;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 12px;
}

.menu a:hover {
    transform: translateY(-10px);
    background: var(--primary-light);
    box-shadow: var(--shadow-lg);
    border-color: var(--primary);
}

.menu a i {
    font-size: 32px;
    transition: 0.3s;
}

.menu a:hover i {
    transform: scale(1.2) rotate(5deg);
}

/* CHART SECTION */
#bookingsChart {
    max-width: 100%;
}

.chart-container {
    position: relative;
    height: 300px;
    margin-top: 25px;
}

/* FOOTER */
.footer {
    text-align: center;
    margin-top: 40px;
    padding-top: 20px;
    border-top: 1px solid #e0e0e0;
    color: #888;
    font-weight: 600;
}

/* STATS CARDS */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 40px;
}

.stat-card {
    background: linear-gradient(135deg, #fff0f6, #ffe4f1);
    padding: 25px;
    border-radius: 18px;
    text-align: center;
    box-shadow: var(--shadow);
    border-left: 5px solid var(--primary);
    transition: all 0.3s;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-lg);
}

.stat-card .number {
    font-size: 32px;
    font-weight: 900;
    color: var(--primary);
    margin: 10px 0;
}

.stat-card .label {
    font-size: 14px;
    color: #666;
    font-weight: 700;
}

.stat-card i {
    font-size: 28px;
    color: var(--secondary);
}

/* UPCOMING BOOKINGS */
.bookings-list {
    margin-top: 20px;
}

.booking-item {
    background: #fafafa;
    padding: 15px;
    border-radius: 12px;
    margin-bottom: 12px;
    border-left: 4px solid var(--primary);
    display: flex;
    justify-content: space-between;
    align-items: center;
    transition: all 0.3s;
}

.booking-item:hover {
    background: #f0f0f0;
    padding-left: 20px;
}

.booking-info {
    flex: 1;
}

.booking-info .cliente {
    font-weight: 700;
    color: var(--primary);
}

.booking-info .data-hora {
.booking-info .cliente {
    font-weight: 700;
    color: var(--primary);
}

.booking-info .data-hora {
    font-size: 13px;
    color: #666;
    margin-top: 5px;

.booking-status {
    padding: 6px 12px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 700;
    background: rgba(176, 53, 124, 0.1);
    color: var(--primary);
}

/* REVIEWS LIST */
.reviews-list {
    margin-top: 20px;
}

.review-item {
    background: #fafafa;
    padding: 15px;
    border-radius: 12px;
    margin-bottom: 12px;
    border-left: 4px solid #ffc107;
}

.review-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}

.review-autor {
    font-weight: 700;
    color: var(--primary);
}

.review-stars {
    color: #ffc107;
    font-size: 14px;
}

.review-text {
    font-size: 13px;
    color: #555;
    font-style: italic;
    line-height: 1.6;
}

.review-date {
    font-size: 12px;
    color: #999;
    margin-top: 8px;
}

/* RESPONSIVE */
@media (max-width: 768px) {
    .topbar {
        padding: 15px 20px;
        flex-direction: column;
        gap: 15px;
    }

    .topbar .left {
        width: 100%;
    }

    .topbar .right {
        width: 100%;
        justify-content: center;
    }

    .container {
        margin: 30px auto;
    }

    .card {
        padding: 30px;
    }

    .menu {
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 15px;
    }

    .menu a {
        padding: 20px;
        font-size: 13px;
    }

    .menu a i {
        font-size: 24px;
    }
}
</style>
</head>

<body>

<div class="topbar">
    <div class="left">
        <a href="../editar_perfil.php">
            <img src="<?php echo htmlspecialchars($foto); ?>" alt="Foto Admin" onerror="this.src='../uploads/default.png'">
            <span><i class="fas fa-crown"></i> <?php echo htmlspecialchars($nome); ?></span>
        </a>
    </div>

    <div class="right">
        <a class="home-btn" href="../index.php"><i class="fas fa-home"></i> Voltar ao Site</a>
        <a class="logout-btn" href="../logout.php"><i class="fas fa-sign-out-alt"></i> Sair</a>
    </div>
</div>

<div class="container">
    <div class="card">
        <h2><i class="fas fa-crown"></i> Painel de Administração</h2>

        <!-- STATS -->
        <div class="stats-grid">
            <div class="stat-card">
                <i class="fas fa-calendar-check"></i>
                <div class="number"><?php echo $totalBookings; ?></div>
                <div class="label">Total de Marcações</div>
            </div>
            <div class="stat-card">
                <i class="fas fa-calendar-day"></i>
                <div class="number"><?php echo $bookingHoje; ?></div>
                <div class="label">Marcações de Hoje</div>
            </div>
        </div>

        <div class="menu">
            <a href="add_marcacao.php">
                <i class="fas fa-plus-circle"></i>
                Adicionar Marcação
            </a>
            <a href="marcacoes.php">
                <i class="fas fa-calendar-alt"></i>
                Gerir Marcações
            </a>
            <a href="trabalhos.php">
                <i class="fas fa-images"></i>
                Trabalhos Realizados
            </a>
            <a href="../registar.php">
                <i class="fas fa-user-plus"></i>
                Criar Utilizador
            </a>
            <a href="../index.php">
                <i class="fas fa-home"></i>
                Voltar ao Site
            </a>
        </div>
    </div>

    <!-- PRÓXIMAS MARCAÇÕES -->
    <div class="card">
        <h2><i class="fas fa-clock"></i> Próximas Marcações</h2>
        <?php if (!empty($proximasMarcacoes)): ?>
            <div class="bookings-list">
                <?php foreach ($proximasMarcacoes as $m): ?>
                    <div class="booking-item">
                        <div class="booking-info">
                            <div class="cliente">
                                <?php echo htmlspecialchars($m['nome_cliente'] ?? $m['nome'] ?? 'Cliente'); ?>
                            </div>
                        </div>
                        <div class="booking-status">
                            <i class="fas fa-check-circle"></i> Agendado
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p style="text-align: center; color: #999; padding: 20px;">Sem marcações próximas</p>
        <?php endif; ?>
    </div>

    <!-- PENDENTES DE APROVAÇÃO -->
    <?php if (!empty($pendentesAvaliacoes)): ?>
    <div class="card">
        <h2><i class="fas fa-clock"></i> Avaliações por Aprovar</h2>
        <div class="reviews-list">
            <?php foreach ($pendentesAvaliacoes as $a): ?>
                <div class="review-item">
                    <div class="review-header" style="flex-direction:column; align-items:flex-start;">
                        <div class="review-autor" style="font-size:1.1em; font-weight:700; margin-bottom:5px;">
                            <?php echo htmlspecialchars($a['nome'] ?? ($a['user_id'] ?? 'Anon')); ?>
                        </div>
                        <div style="display:flex; align-items:center; width:100%; justify-content:space-between;">
                            <div class="review-stars"><?php echo str_repeat('★', max(0, min(5, (int)$a['rating']))); ?></div>
                            <div class="review-action">
                                <a href="atualizar_avaliacao.php?id=<?php echo urlencode($a['id']); ?>&action=aprovar" class="btn btn-small btn-success" style="font-weight:600;">
                                    <i class="fas fa-check"></i> Aprovar
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="review-text">"<?php echo htmlspecialchars($a['comentario']); ?>"</div>
                    <div class="review-date"><?php echo date('d/m/Y H:i', strtotime($a['created_at'] ?? $a['criado_em'])); ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ÚLTIMAS AVALIAÇÕES -->
    <?php if (!empty($ultimasAvaliacoes)): ?>
    <div class="card">
        <h2><i class="fas fa-star"></i> Últimas Avaliações de Clientes</h2>
        <div class="reviews-list">
            <?php foreach ($ultimasAvaliacoes as $a): ?>
                <div class="review-item">
                    <div class="review-header">
                        <div class="review-autor"><?php echo htmlspecialchars($a['nome']); ?></div>
                        <div class="review-stars"><?php echo str_repeat('★', max(0, min(5, (int)$a['rating']))); ?></div>
                    </div>
                    <div class="review-text">"<?php echo htmlspecialchars($a['comentario']); ?>"</div>
                    <div class="review-date"><?php echo date('d/m/Y H:i', strtotime($a['created_at'])); ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="card">
        <h2><i class="fas fa-chart-bar"></i> Marcações por Mês (<?php echo $currentYear; ?>)</h2>
        <div class="chart-container">
            <canvas id="bookingsChart"></canvas>
        </div>
    </div>

    <div class="footer">
        © <?php echo date("Y"); ?> Sarytha Nails. Painel de Administração
    </div>
</div>

<script>
const ctx = document.getElementById('bookingsChart').getContext('2d');
const bookingsChart = new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($months); ?>,
        datasets: [{
            label: 'Marcações',
            data: <?php echo json_encode($data); ?>,
            backgroundColor: [
                'rgba(176, 53, 124, 0.7)',
                'rgba(193, 53, 132, 0.7)',
                'rgba(210, 53, 140, 0.7)',
                'rgba(227, 53, 148, 0.7)',
                'rgba(176, 80, 130, 0.7)',
                'rgba(176, 107, 136, 0.7)',
                'rgba(176, 134, 142, 0.7)',
                'rgba(176, 161, 148, 0.7)',
                'rgba(176, 188, 154, 0.7)',
                'rgba(176, 215, 160, 0.7)',
                'rgba(176, 242, 166, 0.7)',
                'rgba(176, 53, 124, 0.7)'
            ],
            borderColor: 'rgba(176, 53, 124, 1)',
            borderWidth: 2,
            borderRadius: 8,
            borderSkipped: false
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    stepSize: 1
                },
                grid: {
                    color: 'rgba(0, 0, 0, 0.05)'
                }
            },
            x: {
                grid: {
                    display: false
                }
            }
        },
        plugins: {
            legend: {
                display: true,
                labels: {
                    font: {
                        family: "'Poppins', sans-serif",
                        size: 14,
                        weight: 'bold'
                    },
                    color: '#333'
                }
            }
        }
    }
});
</script>

</body>
</html>
