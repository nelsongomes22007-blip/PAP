<?php
session_start();
require __DIR__ . "/../api/ligacao.php";

// ==========================
// BUSCAR GALERIA
// ==========================
$trabalhos = [];
try {
    $stmt = $pdo->query("SELECT * FROM trabalhos ORDER BY id_trabalho DESC");
    $trabalhos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $trabalhos = [];
}

// ==========================
// DADOS DO UTILIZADOR LOGADO
// ==========================
$logado = isset($_SESSION["user_id"]);
$nomeUser = $_SESSION["nome"] ?? "";
$roleUser = $_SESSION["role"] ?? "";
$fotoUser = $_SESSION["foto"] ?? "";

// Por estar dentro da pasta uploads/, usar caminhos relativos à mesma pasta
$fotoFinal = "default.png";
if (!empty($fotoUser)) {
    // Se estiver armazenado como 'uploads/xxx', remover prefixo
    $f = $fotoUser;
    if (strpos($f, 'uploads/') === 0) {
        $f = substr($f, strlen('uploads/'));
    }
    if (file_exists(__DIR__ . '/' . $f)) {
        $fotoFinal = $f;
    }
}

// ==========================
// LOGO (CORRETO)
// ==========================
$logo = "logo.png"; 

// Se não existir o logo nesta pasta, usar o default local
if (!file_exists(__DIR__ . "/logo.png")) {
    $logo = "default.png";
}
?>

<!DOCTYPE html>
<html lang="pt-PT">
<head>
<meta charset="UTF-8">
<title>Sarytha Nails</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<style>
body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: linear-gradient(120deg, #fff0f6, #ffe4f1);
    color: #333;
}

/* ─ TOPBAR ────────────────────────────────── */
.topbar {
    background: #b0357c;
    padding: 15px 25px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.topbar .logo {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 20px;
    font-weight: 900;
    color: white;
    text-decoration: none;
}

.topbar .logo img {
    width: 55px;
    height: 55px;
    border-radius: 14px;
    object-fit: cover;
    border: 3px solid rgba(255,255,255,0.9);
    box-shadow: 0 10px 25px rgba(0,0,0,0.25);
}

.topbar .right {
    display: flex;
    align-items: center;
    gap: 12px;
}

.btn-top {
    background: rgba(255,255,255,0.2);
    color: white;
    padding: 10px 14px;
    border-radius: 14px;
    text-decoration: none;
    font-weight: 900;
    transition: 0.2s;
}

.btn-top:hover {
    background: rgba(255,255,255,0.35);
    transform: translateY(-2px);
}

.user-box {
    display: flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
    color: white;
    font-weight: 900;
}

.user-box img {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    border: 3px solid white;
    object-fit: cover;
}

/* ─ CONTAINER ─────────────────────────────── */
.container {
    max-width: 1100px;
    margin: 40px auto;
    padding: 0 20px;
}

/* ─ CARD ─────────────────────────────────── */
.card {
    background: white;
    border-radius: 22px;
    padding: 35px;
    box-shadow: 0 18px 45px rgba(176, 53, 124, 0.25);
    margin-bottom: 35px;
}

.card h2 {
    margin-top: 0;
    text-align: center;
    font-size: 32px;
    color: #b0357c;
}

/* ─ MARCAÇÃO ─────────────────────────────── */
label {
    font-weight: 900;
    margin-top: 15px;
    display: block;
}

input {
    width: 100%;
    padding: 12px;
    margin-top: 6px;
    border-radius: 12px;
    border: 1px solid #ccc;
    font-size: 15px;
}

input:focus {
    outline: none;
    border-color: #b0357c;
}

button {
    width: 100%;
    margin-top: 22px;
    padding: 14px;
    background: #b0357c;
    border: none;
    color: white;
    font-weight: 900;
    border-radius: 14px;
    font-size: 16px;
    cursor: pointer;
    transition: 0.2s;
}

button:hover {
    background: #962e67;
    transform: translateY(-2px);
}

/* ─ GALERIA ─────────────────────────────── */
.galeria {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 25px;
    margin-top: 25px;
}

.foto-card {
    background: #fff7fb;
    border-radius: 22px;
    overflow: hidden;
    box-shadow: 0 15px 35px rgba(176, 53, 124, 0.20);
    transition: 0.2s;
}

.foto-card:hover {
    transform: translateY(-5px);
}

.foto-card img {
    width: 100%;
    height: 240px;
    object-fit: cover;
    display: block;
}

.foto-card .titulo {
    padding: 14px;
    text-align: center;
    font-weight: 900;
    color: #b0357c;
    font-size: 18px;
}

/* ─ FOOTER ───────────────────────────────── */
.footer {
    text-align: center;
    padding: 20px;
    color: #777;
    font-weight: 700;
}
</style>

</head>
<body>

<!-- TOPO -->
<div class="topbar">

    <a class="logo" href="index.php">
        <img src="<?= htmlspecialchars($logo) ?>" alt="Logo Sarytha Nails">
        <span>Sarytha Nails</span>
    </a>

    <div class="right">

        <?php if (!$logado): ?>
            <a class="btn-top" href="login.php">🔑 Login</a>
            <a class="btn-top" href="registar.php">📝 Registar</a>

        <?php else: ?>
            <a class="user-box" href="editar_perfil.php">
                <img src="<?= htmlspecialchars($fotoFinal) ?>" alt="Foto Perfil">
                <span><?= htmlspecialchars($nomeUser) ?></span>
            </a>

            <?php if (strtolower($roleUser) === "admin"): ?>
                <a class="btn-top" href="Admin/index.php">👑 Painel Admin</a>
            <?php endif; ?>

            <a class="btn-top" href="logout.php">🚪 Sair</a>

        <?php endif; ?>

    </div>
</div>

<!-- CONTEÚDO -->
<div class="container">

    <!-- MARCAÇÃO -->
    <div class="card">
        <h2>Marcação Online</h2>

        <form action="marcacoes.php" method="POST">
            <label>Data</label>
            <input type="date" name="data" required>

            <label>Hora</label>
            <input type="time" name="hora" required>

            <button type="submit">Confirmar Marcação</button>
        </form>
    </div>

    <!-- GALERIA -->
    <div class="card">
        <h2>Trabalhos Realizados</h2>

        <div class="galeria">

            <?php if (empty($trabalhos)): ?>
                <p style="grid-column:1/-1; text-align:center; font-weight:900; color:#b0357c;">
                    Ainda não existem trabalhos adicionados.
                </p>
            <?php endif; ?>

            <?php foreach($trabalhos as $t): ?>
                <?php
                    $titulo = htmlspecialchars($t["titulo"]);
                    $imagem = htmlspecialchars($t["imagem"]);
                    $caminho = "trabalhos/" . $imagem;
                ?>
                <div class="foto-card">
                    <img src="<?= htmlspecialchars($caminho) ?>" alt="<?= $titulo ?>">
                    <div class="titulo"><?= $titulo ?></div>
                </div>
            <?php endforeach; ?>

        </div>
    </div>

</div>

<div class="footer">
    © <?= date("Y") ?> Sarytha Nails
</div>

</body>
</html>

