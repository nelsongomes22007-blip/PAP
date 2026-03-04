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

$mensagem = "";

// ==========================
// APAGAR FOTO
// ==========================
if (isset($_GET["apagar"])) {
    $id = (int) $_GET["apagar"];

    $stmt = $pdo->prepare("SELECT imagem FROM trabalhos WHERE id_trabalho = ?");
    $stmt->execute([$id]);
    $img = $stmt->fetchColumn();

    if ($img) {
        $caminho = __DIR__ . "/../uploads/trabalhos/" . $img;

        if (file_exists($caminho)) {
            unlink($caminho);
        }

        $stmt = $pdo->prepare("DELETE FROM trabalhos WHERE id_trabalho = ?");
        $stmt->execute([$id]);

        header("Location: trabalhos.php?sucesso=apagado");
        exit;
    }
}

// ==========================
// ADICIONAR FOTO
// ==========================
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["adicionar"])) {

    $titulo = trim($_POST["titulo"] ?? "");

    if (!$titulo) {
        $mensagem = "❌ O título é obrigatório.";
    } else {

        if (!isset($_FILES["imagem"]) || $_FILES["imagem"]["error"] !== 0) {
            $mensagem = "❌ Erro ao enviar imagem.";
        } else {

            $ext = strtolower(pathinfo($_FILES["imagem"]["name"], PATHINFO_EXTENSION));
            $permitidas = ["jpg", "jpeg", "png", "webp"];

            if (!in_array($ext, $permitidas)) {
                $mensagem = "❌ Formato inválido. Usa JPG, PNG ou WEBP.";
            } else {

                $nomeFicheiro = "trabalho_" . time() . "_" . rand(1000, 9999) . "." . $ext;
                $destino = __DIR__ . "/../uploads/trabalhos/" . $nomeFicheiro;

                if (!is_dir(__DIR__ . "/../uploads/trabalhos")) {
                    mkdir(__DIR__ . "/../uploads/trabalhos", 0777, true);
                }

                if (move_uploaded_file($_FILES["imagem"]["tmp_name"], $destino)) {

                    $stmt = $pdo->prepare("INSERT INTO trabalhos (titulo, imagem) VALUES (?, ?)");
                    $stmt->execute([$titulo, $nomeFicheiro]);

                    header("Location: trabalhos.php?sucesso=1");
                    exit;

                } else {
                    $mensagem = "❌ Não foi possível guardar a imagem.";
                }
            }
        }
    }
}

// ==========================
// EDITAR FOTO
// ==========================
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["editar"])) {

    $id = (int) $_POST["id_trabalho"];
    $titulo = trim($_POST["titulo"] ?? "");

    if (!$titulo) {
        $mensagem = "❌ O título não pode estar vazio.";
    } else {
        $stmt = $pdo->prepare("UPDATE trabalhos SET titulo = ? WHERE id_trabalho = ?");
        $stmt->execute([$titulo, $id]);

        header("Location: trabalhos.php?sucesso=editado");
        exit;
    }
}

// ==========================
// LISTAR TRABALHOS
// ==========================
$stmt = $pdo->query("SELECT * FROM trabalhos ORDER BY id_trabalho DESC");
$trabalhos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-PT">
<head>
<meta charset="UTF-8">
<title>Admin - Trabalhos</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<style>
body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: linear-gradient(120deg, #fff0f6, #ffe4f1);
}

.container {
    max-width: 1200px;
    margin: 40px auto;
    padding: 0 20px;
}

.card {
    background: white;
    border-radius: 22px;
    padding: 35px;
    box-shadow: 0 18px 45px rgba(176, 53, 124, 0.25);
}

h2 {
    text-align: center;
    color: #b0357c;
    margin-top: 0;
    font-size: 32px;
}

.alerta {
    padding: 12px;
    border-radius: 14px;
    font-weight: 900;
    text-align: center;
    margin-bottom: 20px;
}

.alerta.sucesso {
    background: #e7ffe9;
    color: #1f7a35;
}

.alerta.erro {
    background: #ffe1e6;
    color: #a8002d;
}

.form-box {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
    justify-content: center;
    margin-bottom: 30px;
}

.form-box input[type="text"] {
    padding: 12px;
    border-radius: 12px;
    border: 1px solid #ccc;
    width: 250px;
}

.form-box input[type="file"] {
    padding: 10px;
}

.form-box button {
    background: #b0357c;
    border: none;
    color: white;
    padding: 12px 18px;
    border-radius: 14px;
    font-weight: 900;
    cursor: pointer;
    transition: 0.2s;
}

.form-box button:hover {
    background: #962e67;
    transform: translateY(-2px);
}

/* GRID IGUAL AO TEU PRINT */
.galeria {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 25px;
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
    height: 260px;
    object-fit: cover;
    display: block;
}

/* Overlay actions */
.foto-card .overlay {
    position: absolute;
    top: 12px;
    right: 12px;
    display: flex;
    gap: 8px;
    opacity: 0;
    transition: opacity 0.2s;
}
.foto-card:hover .overlay {
    opacity: 1;
}
.foto-card .overlay .icon {
    background: rgba(255,255,255,0.9);
    padding: 8px;
    border-radius: 8px;
    box-shadow: 0 6px 18px rgba(0,0,0,0.12);
    cursor: pointer;
    color: var(--primary, #b0357c);
}

/* Modal preview */
.img-modal {position:fixed;inset:0;display:none;align-items:center;justify-content:center;background:rgba(0,0,0,0.6);z-index:9999}
.img-modal .inner{max-width:90%;max-height:90%;background:white;padding:12px;border-radius:12px;box-shadow:0 20px 50px rgba(0,0,0,0.5)}
.img-modal img{max-width:100%;max-height:80vh;display:block;border-radius:8px}

.foto-card .info {
    padding: 14px;
    text-align: center;
}

.foto-card .info h3 {
    margin: 0;
    font-size: 18px;
    color: #b0357c;
    font-weight: 900;
}

.btns {
    margin-top: 12px;
    display: flex;
    justify-content: center;
    gap: 10px;
    flex-wrap: wrap;
}

.btn {
    padding: 10px 14px;
    border-radius: 14px;
    font-weight: 900;
    text-decoration: none;
    font-size: 14px;
    cursor: pointer;
    border: none;
}

.btn.apagar {
    background: #dc3545;
    color: white;
}

.btn.apagar:hover {
    background: #a71d2a;
}

.btn.editar {
    background: #17a2b8;
    color: white;
}

.btn.editar:hover {
    background: #117a8b;
}

.btn.voltar {
    display: inline-block;
    margin-top: 25px;
    background: #b0357c;
    color: white;
    padding: 12px 18px;
    border-radius: 14px;
    text-decoration: none;
    font-weight: 900;
    transition: 0.2s;
}

.btn.voltar:hover {
    background: #962e67;
}

.edit-form input {
    width: 90%;
    padding: 10px;
    border-radius: 12px;
    border: 1px solid #ccc;
    margin-top: 10px;
}
</style>
</head>

<body>
<div class="img-modal" id="imgModal" onclick="document.getElementById('imgModal').style.display='none'">
function openPreview(src){
    document.getElementById('modalImg').src = src;
    document.getElementById('imgModal').style.display = 'flex';
}
</script>
    <div class="inner" onclick="event.stopPropagation()">
        <img id="modalImg" src="" alt="preview">
    </div>
</div>

<div class="container">
    <div class="card">
        <h2>🖼 Trabalhos Realizados</h2>

        <?php if ($mensagem): ?>
            <div class="alerta erro"><?= htmlspecialchars($mensagem) ?></div>
        <?php endif; ?>

        <?php if (isset($_GET["sucesso"])): ?>
            <div class="alerta sucesso">✅ Operação realizada com sucesso!</div>
        <?php endif; ?>

        <!-- FORM ADICIONAR -->
        <form class="form-box" method="POST" enctype="multipart/form-data">
            <input type="text" name="titulo" placeholder="Título da foto" required>
            <input type="file" name="imagem" required>
            <button type="submit" name="adicionar">➕ Adicionar</button>
        </form>

        <!-- GALERIA -->
        <div class="galeria">

            <?php if (count($trabalhos) === 0): ?>
                <p style="grid-column:1/-1; text-align:center; font-weight:900; color:#b0357c;">
                    Nenhuma foto adicionada ainda.
                </p>
            <?php endif; ?>

            <?php foreach ($trabalhos as $t): ?>
                <div class="foto-card" style="position:relative;">
                    <img src="../uploads/trabalhos/<?= htmlspecialchars($t["imagem"]) ?>" alt="<?= htmlspecialchars($t["titulo"]) ?>">
                    <div class="overlay">
                        <div class="icon" title="Ver" onclick="openPreview('../uploads/trabalhos/<?= htmlspecialchars($t["imagem"]) ?>')"><i class="fas fa-search"></i></div>
                        <div class="icon" title="Editar" onclick="document.getElementById('edit-<?= $t['id_trabalho'] ?>').scrollIntoView({behavior:'smooth'})"><i class="fas fa-edit"></i></div>
                    </div>

                    <div class="info">
                        <h3><?= htmlspecialchars($t["titulo"]) ?></h3>

                        <!-- FORM EDITAR TITULO -->
                        <form id="edit-<?= $t['id_trabalho'] ?>" class="edit-form" method="POST">
                            <input type="hidden" name="id_trabalho" value="<?= $t["id_trabalho"] ?>">
                            <input type="text" name="titulo" value="<?= htmlspecialchars($t["titulo"]) ?>" required>
                            <button class="btn editar" type="submit" name="editar">✏ Guardar</button>
                        </form>

                        <div class="btns">
                            <a class="btn apagar" href="trabalhos.php?apagar=<?= $t["id_trabalho"] ?>" onclick="return confirm('Tens a certeza que queres apagar esta foto?')">
                                🗑 Apagar
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

        </div>

        <a class="btn voltar" href="index.php">⬅ Voltar ao Painel</a>

    </div>
</div>

</body>
</html>
