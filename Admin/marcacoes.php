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

// Atualizar status
if (isset($_GET["acao"]) && isset($_GET["id"])) {
    $id = (int) $_GET["id"];
    $acao = $_GET["acao"];

    $check = $pdo->prepare("SELECT status FROM bookings WHERE id = ?");
    $check->execute([$id]);
    $statusAtual = $check->fetchColumn();

    if ($statusAtual === "concluida") {
        header("Location: marcacoes.php?erro=concluida");
        exit;
    }

    if ($acao === "concluida" && $statusAtual !== "confirmada") {
        header("Location: marcacoes.php?erro=nao_confirmada");
        exit;
    }

    if (in_array($acao, ["confirmada", "rejeitada", "concluida"])) {
        $stmt = $pdo->prepare("UPDATE bookings SET status = ? WHERE id = ?");
        $stmt->execute([$acao, $id]);

        header("Location: marcacoes.php?sucesso=1");
        exit;
    }
}

// determine which user table and id column to join
$userTable = 'utilizadores';
$userIdCol = 'id';
try {
    $pdo->query("SELECT 1 FROM utilizadores LIMIT 1");
} catch (Exception $e) {
    $userTable = 'users';
    $userIdCol = 'user_id';
}

// Buscar marcações + cliente
try {
    // tentamos incluir coluna servico (se existir)
    $stmt = $pdo->query("
        SELECT 
            b.id,
            b.id_utilizador,
            b.data,
            b.hora,
            b.servico,
            b.status,
            COALESCE(u.nome, b.nome) AS cliente_nome,
            COALESCE(u.email, b.email) AS cliente_email
        FROM bookings b
        LEFT JOIN $userTable u ON b.id_utilizador = u.$userIdCol
        WHERE b.status != 'rejeitada'
        ORDER BY b.id_utilizador ASC
    ");
    $marcacoes = $stmt->fetchAll();
} catch (PDOException $e) {
    // coluna servico não existe; refaz consulta sem ela
    $stmt = $pdo->query("
        SELECT 
            b.id,
            b.id_utilizador,
            b.data,
            b.hora,
            b.status,
            COALESCE(u.nome, b.nome) AS cliente_nome,
            COALESCE(u.email, b.email) AS cliente_email
        FROM bookings b
        LEFT JOIN $userTable u ON b.id_utilizador = u.$userIdCol
        WHERE b.status != 'rejeitada'
        ORDER BY b.id_utilizador ASC
    ");
    $marcacoes = $stmt->fetchAll();
}


$nome = $_SESSION["nome"] ?? "Administrador";
$fotoSessao = $_SESSION["foto"] ?? '';

// Resolver caminho da foto de sessão de forma robusta.
$foto = "../uploads/default.png";
if (!empty($fotoSessao)) {
    // Caminho já inclui 'uploads/'
    if (strpos($fotoSessao, 'uploads/') === 0) {
        if (file_exists(__DIR__ . '/../' . $fotoSessao)) {
            $foto = '../' . $fotoSessao;
        }
    } else {
        // Tentar dentro de ../uploads/
        if (file_exists(__DIR__ . '/../uploads/' . $fotoSessao)) {
            $foto = '../uploads/' . $fotoSessao;
        } elseif (file_exists(__DIR__ . '/../' . $fotoSessao)) {
            $foto = '../' . $fotoSessao;
        }
    }
}

$erroMsg = null;

if (isset($_GET["erro"])) {
    if ($_GET["erro"] === "nao_confirmada") {
        $erroMsg = "Só podes marcar como concluída uma marcação que esteja confirmada.";
    }

    if ($_GET["erro"] === "concluida") {
        $erroMsg = "Esta marcação já está concluída e não pode ser alterada.";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-PT">
<head>
<meta charset="UTF-8">
<title>Admin | Marcações</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
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

.topbar {
    background: linear-gradient(90deg, var(--primary), var(--secondary));
    padding: 20px 40px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: var(--shadow-lg);
    position: sticky;
    top: 0;
    z-index: 100;
}

.topbar .left {
    display: flex;
    align-items: center;
    gap: 15px;
    color: white;
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

.topbar .left img {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid white;
    transition: all 0.3s;
}

.logout-btn {
    background: rgba(255, 0, 0, 0.7);
    color: white;
    padding: 12px 24px;
    border-radius: 12px;
    text-decoration: none;
    font-weight: 700;
    transition: all 0.3s;
    border: none;
    cursor: pointer;
}

.logout-btn:hover {
    background: rgba(255, 0, 0, 0.9);
    transform: translateY(-2px);
}

.container {
    max-width: 1200px;
    margin: 50px auto;
    padding: 0 20px;
}

.card {
    background: white;
    border-radius: 25px;
    padding: 45px;
    box-shadow: var(--shadow-lg);
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
    color: var(--primary);
    font-size: 28px;
    text-align: center;
    margin-bottom: 30px;
    font-weight: 900;
}

.alerta {
    padding: 16px 20px;
    border-radius: 14px;
    font-weight: 700;
    text-align: center;
    margin-bottom: 25px;
    border-left: 5px solid;
    animation: slideInDown 0.4s ease;
}

.alerta.erro {
    background: #ffe1e6;
    color: #c41e3a;
    border-color: #c41e3a;
}

.alerta.sucesso {
    background: #d4edda;
    color: #155724;
    border-color: #28a745;
}

@keyframes slideInDown {
    from {
        transform: translateY(-100%);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

/* TABLE STYLING */
.table-container {
    overflow-x: auto;
    border-radius: 15px;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
}

table {
    width: 100%;
    border-collapse: collapse;
    background: white;
}

th {
    background: linear-gradient(90deg, var(--primary), var(--secondary));
    color: white;
    padding: 18px 14px;
    font-weight: 900;
    text-align: center;
    font-size: 14px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

td {
    padding: 16px 14px;
    text-align: center;
    border-bottom: 1px solid #f0f0f0;
    font-size: 14px;
}

tr:nth-child(even) {
    background: #f9f9f9;
}

tr:hover {
    background: #f3f3f3;
    transition: 0.3s;
}

tr:last-child td {
    border-bottom: none;
}

.status {
    font-weight: 900;
    padding: 10px 16px;
    border-radius: 20px;
    display: inline-block;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.status.pendente {
    background: #fff3cd;
    color: #856404;
}

.status.confirmada {
    background: #d1ecf1;
    color: #0c5460;
}

.status.rejeitada {
    background: #f8d7da;
    color: #721c24;
}

.status.concluida {
    background: #d4edda;
    color: #155724;
}

.btns {
    display: flex;
    justify-content: center;
    gap: 10px;
    flex-wrap: wrap;
}

.btn {
    padding: 10px 14px;
    border-radius: 12px;
    text-decoration: none;
    font-weight: 700;
    font-size: 12px;
    transition: all 0.3s;
    border: none;
    cursor: pointer;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.btn.confirmar {
    background: #28a745;
    color: white;
}

.btn.confirmar:hover {
    background: #218838;
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(40, 167, 69, 0.3);
}

.btn.rejeitar {
    background: #dc3545;
    color: white;
}

.btn.rejeitar:hover {
    background: #c82333;
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(220, 53, 69, 0.3);
}

.btn.concluida {
    background: #17a2b8;
    color: white;
}

.btn.concluida:hover {
    background: #138496;
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(23, 162, 184, 0.3);
}

.locked {
    font-weight: 700;
    color: #0c5460;
    background: #d1ecf1;
    padding: 10px 14px;
    border-radius: 12px;
    display: inline-block;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.voltar {
    display: inline-block;
    margin-top: 30px;
    background: linear-gradient(90deg, var(--primary), var(--secondary));
    color: white;
    padding: 14px 28px;
    border-radius: 14px;
    text-decoration: none;
    font-weight: 700;
    transition: all 0.3s;
}

.voltar:hover {
    transform: translateY(-3px);
    box-shadow: var(--shadow-lg);
}

/* RESPONSIVE */
@media (max-width: 768px) {
    .card {
        padding: 20px;
    }

    th, td {
        padding: 12px 8px;
        font-size: 12px;
    }

    .btn {
        padding: 8px 10px;
        font-size: 11px;
    }

    table {
        font-size: 12px;
    }
}
</style>
</head>

<body>

<div class="topbar">
    <div class="left">
        <a href="../editar_perfil.php">
            <img src="<?= htmlspecialchars($foto) ?>" alt="Foto Admin" onerror="this.src='../uploads/default.png'">
            <span><i class="fas fa-crown"></i> <?= htmlspecialchars($nome) ?></span>
        </a>
    </div>

    <a class="logout-btn" href="../logout.php"><i class="fas fa-sign-out-alt"></i> Sair</a>
</div>

<div class="container">
    <div class="card">
        <h2><i class="fas fa-calendar-list"></i> Gestão de Marcações</h2>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;gap:12px;flex-wrap:wrap;">
            <div style="flex:1;min-width:220px;">
                <input id="searchBox" placeholder="Pesquisar por cliente, email ou data..." style="width:100%;padding:12px 14px;border-radius:12px;border:1px solid #e6d5de;">
            </div>
            <div style="min-width:200px;text-align:right;">
                <a class="voltar" href="index.php" style="padding:10px 18px;">⬅ Voltar ao Painel</a>
            </div>
        </div>
        <?php if ($erroMsg): ?>
            <div class="alerta erro">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($erroMsg) ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET["sucesso"])): ?>
            <div class="alerta sucesso">
                <i class="fas fa-check-circle"></i> Status atualizado com sucesso!
            </div>
        <?php endif; ?>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th><i class="fas fa-id-card"></i> ID</th>
                        <th><i class="fas fa-user"></i> Cliente</th>
                        <th><i class="fas fa-envelope"></i> Email</th>
                        <th><i class="fas fa-calendar-alt"></i> Data</th>
                        <th><i class="fas fa-clock"></i> Hora</th>
                        <th><i class="fas fa-concierge-bell"></i> Serviço</th>
                        <th><i class="fas fa-tag"></i> Status</th>
                        <th><i class="fas fa-cogs"></i> Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($marcacoes as $m): ?>
                        <tr>
                            <td><?= $m["id_utilizador"] ? htmlspecialchars($m["id_utilizador"]) : '<em>Visitante</em>' ?></td>
                            <td><?= htmlspecialchars($m["cliente_nome"]) ?></td>
                            <td><?= htmlspecialchars($m["cliente_email"]) ?></td>
                            <td><?= htmlspecialchars($m["data"]) ?></td>
                            <td><?= htmlspecialchars($m["hora"]) ?></td>
                            <td><?= htmlspecialchars($m["servico"] ?? '') ?></td>

                            <td>
                                <span class="status <?= htmlspecialchars($m["status"]) ?>">
                                    <?= htmlspecialchars($m["status"]) ?>
                                </span>
                            </td>

                            <td>
                                <?php if ($m["status"] === "concluida"): ?>
                                    <span class="locked"><i class="fas fa-lock"></i> Finalizada</span>
                                <?php else: ?>
                                    <div class="btns">
                                        <?php if ($m["status"] === "confirmada"): ?>
                                            <a class="btn concluida" href="marcacoes.php?acao=concluida&id=<?= $m["id"] ?>">
                                                <i class="fas fa-check-double"></i> Finalizar
                                            </a>
                                        <?php else: ?>
                                            <a class="btn confirmar" href="marcacoes.php?acao=confirmada&id=<?= $m["id"] ?>">
                                                <i class="fas fa-check"></i> Confirmar
                                            </a>
                                            <a class="btn rejeitar" href="marcacoes.php?acao=rejeitada&id=<?= $m["id"] ?>">
                                                <i class="fas fa-times"></i> Rejeitar
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
    </div>
</div>

<script>
// Pesquisa client-side na tabela
document.getElementById('searchBox')?.addEventListener('input', function(e){
    const q = e.target.value.toLowerCase().trim();
    const rows = document.querySelectorAll('table tbody tr');
    rows.forEach(r=>{
        const txt = r.innerText.toLowerCase();
        r.style.display = txt.indexOf(q) !== -1 ? '' : 'none';
    });
});

// quando o admin escolher data, buscar horários ocupados e desabilitar
document.addEventListener('DOMContentLoaded', function(){
    const date = document.getElementById('admin-date');
    const hora = document.getElementById('admin-hora');
    if (!date) return;

    // set min = hoje
    const today = new Date().toISOString().split('T')[0];
    date.setAttribute('min', today);

    date.addEventListener('change', fetchTimes);

    function fetchTimes(){
        const d = date.value;
        if (!d) return;
        fetch('get_booked_times.php?date=' + encodeURIComponent(d))
            .then(r=>r.json())
            .then(data=>{
                const busy = data.times || [];
                for (let opt of hora.options){
                    opt.disabled = busy.includes(opt.value);
                    if (opt.disabled && opt.selected) opt.selected = false;
                }
            }).catch(()=>{});
    }
});
</script>

</body>
</html>
