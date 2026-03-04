<?php
session_start();
require __DIR__ . '/api/ligacao.php';

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION["user_id"];
$erro = null;
$sucesso = null;

// determine table name for users (utilizadores or users)
$userTable = 'utilizadores';
try {
    $pdo->query("SELECT 1 FROM utilizadores LIMIT 1");
} catch (Exception $e) {
    $userTable = 'users';
}

// buscar dados atuais
$stmt = $pdo->prepare("SELECT nome, email, foto FROM $userTable WHERE " . ($userTable === 'utilizadores' ? 'id' : 'user_id') . " = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Normalizar caminho da foto para exibição (aceita 'uploads/xxx' ou só 'xxx')
$fotoUserDb = $user['foto'] ?? '';
$avatarPath = 'uploads/default.png';
if (!empty($fotoUserDb)) {
    if (strpos($fotoUserDb, 'uploads/') === 0 && file_exists(__DIR__ . '/' . $fotoUserDb)) {
        $avatarPath = $fotoUserDb;
    } elseif (file_exists(__DIR__ . '/uploads/' . $fotoUserDb)) {
        $avatarPath = 'uploads/' . $fotoUserDb;
    } elseif (file_exists(__DIR__ . '/' . $fotoUserDb)) {
        $avatarPath = $fotoUserDb;
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nome = trim($_POST["nome"] ?? '');
    $email = trim($_POST["email"] ?? '');

    if ($nome === '' || $email === '') {
        $erro = "Nome e email são obrigatórios.";
    } else {

        // verificar email duplicado
        $check = $pdo->prepare("
            SELECT " . ($userTable === 'utilizadores' ? 'id' : 'user_id') . " FROM $userTable 
            WHERE email = ? AND " . ($userTable === 'utilizadores' ? 'id' : 'user_id') . " != ?
        ");
        $check->execute([$email, $user_id]);

        if ($check->rowCount() > 0) {
            $erro = "Este email já está a ser utilizado.";
        } else {

            // tratar foto
            $fotoFinal = $user["foto"];

            if (!empty($_FILES["foto"]["name"])) {
                switch ($_FILES['foto']['error']) {
                    case UPLOAD_ERR_OK:
                        break;
                    case UPLOAD_ERR_INI_SIZE:
                    case UPLOAD_ERR_FORM_SIZE:
                        $erro = 'A imagem é demasiado grande. Verifique o tamanho máximo de upload no servidor.';
                        break;
                    case UPLOAD_ERR_PARTIAL:
                        $erro = 'O upload da imagem foi interrompido.';
                        break;
                    default:
                        $erro = 'Erro ao enviar a foto (código ' . $_FILES['foto']['error'] . ').';
                        break;
                }

                if (!$erro) {
                    $ext = strtolower(pathinfo($_FILES["foto"]["name"], PATHINFO_EXTENSION));
                    $permitidas = ['jpg', 'jpeg', 'png', 'webp'];

                    if (!in_array($ext, $permitidas)) {
                        $erro = "Formato de imagem inválido.";
                    } else {
                        $novoNome = "user_{$user_id}_" . time() . "." . $ext;
                        // Assegurar que a pasta uploads existe
                        $uploadsDir = __DIR__ . '/uploads';
                        if (!is_dir($uploadsDir)) {
                            mkdir($uploadsDir, 0777, true);
                        }

                        $destinoFisico = $uploadsDir . '/' . $novoNome;

                        if (move_uploaded_file($_FILES["foto"]["tmp_name"], $destinoFisico)) {
                            $fotoFinal = $novoNome; // armazenar só o nome no DB
                            $avatarPath = 'uploads/' . $novoNome; // atualizar avatar imediatamente
                        } else {
                            $erro = "Erro ao enviar a imagem.";
                        }
                    }
                }
            }

            if (!$erro) {
                $stmt = $pdo->prepare("
                    UPDATE $userTable 
                    SET nome = ?, email = ?, foto = ?
                    WHERE " . ($userTable === 'utilizadores' ? 'id' : 'user_id') . " = ?
                ");
                $stmt->execute([$nome, $email, $fotoFinal, $user_id]);

                $_SESSION["nome"] = $nome;
                $_SESSION["foto"] = $fotoFinal ? 'uploads/' . $fotoFinal : 'uploads/default.png';

                $sucesso = "Perfil atualizado com sucesso!";
            }
        }
    }
}

// Detectar logo da empresa
$logo = '/uploads/default.png';
$candidates = glob(__DIR__ . '/uploads/logo.*');
if (!empty($candidates)) {
    $logo = '/uploads/' . basename($candidates[0]);
}
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
<meta charset="UTF-8">
<title>Editar Perfil | Sarytha Nails</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
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
    min-height: 100vh;
    background: linear-gradient(135deg, #fff0f6 0%, #ffe4f1 100%);
    font-family: 'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    padding: 0;
    display: flex;
    flex-direction: column;
}

/* TOPBAR */
.topbar {
    background: linear-gradient(90deg, var(--primary), var(--secondary));
    height: 75px;
    padding: 0 40px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    box-shadow: var(--shadow-lg);
    margin-bottom: 40px;
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

.profile-container {
    width: 100%;
    max-width: 520px;
    margin: 0 auto;
    padding: 0 20px 40px 20px;
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

.profile-box {
    background: white;
    padding: 50px;
    border-radius: 25px;
    box-shadow: var(--shadow-lg);
    position: relative;
}

.profile-header {
    text-align: center;
    margin-bottom: 40px;
}

.profile-header h1 {
    color: var(--primary);
    font-size: 28px;
    margin-bottom: 8px;
    font-weight: 900;
}

.profile-header p {
    color: #888;
    font-size: 14px;
}

.avatar-section {
    text-align: center;
    margin-bottom: 40px;
}

.avatar-container {
    position: relative;
    display: inline-block;
    margin-bottom: 15px;
}

.avatar-image {
    width: 140px;
    height: 140px;
    border-radius: 50%;
    object-fit: cover;
    border: 5px solid var(--primary);
    cursor: pointer;
    transition: all 0.3s;
}

.avatar-image:hover {
    transform: scale(1.05);
    box-shadow: 0 10px 30px rgba(176, 53, 124, 0.3);
}

.avatar-overlay {
    position: absolute;
    bottom: 0;
    right: 0;
    background: var(--primary);
    color: white;
    width: 45px;
    height: 45px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s;
    border: 4px solid white;
}

.avatar-overlay:hover {
    background: var(--secondary);
    transform: scale(1.1);
}

#foto-input {
    display: none;
}

.avatar-label {
    display: block;
    color: #666;
    font-size: 13px;
    margin-top: 10px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    font-weight: 700;
    display: block;
    margin-bottom: 8px;
    font-size: 14px;
    color: var(--primary);
}

.form-group input {
    width: 100%;
    padding: 14px 16px;
    border-radius: 12px;
    border: 2px solid #e0e0e0;
    font-size: 14px;
    background: white;
    font-family: 'Poppins', sans-serif;
    transition: all 0.3s;
}

.form-group input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(176, 53, 124, 0.1);
}

.submit-btn {
    width: 100%;
    padding: 15px;
    background: linear-gradient(90deg, var(--primary), var(--secondary));
    border: none;
    color: white;
    border-radius: 14px;
    font-size: 16px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s;
    font-family: 'Poppins', sans-serif;
    margin-top: 15px;
}

.submit-btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 25px rgba(176, 53, 124, 0.3);
}

.submit-btn:active {
    transform: translateY(-1px);
}

.erro {
    background: #ffe1e6;
    color: #c41e3a;
    padding: 14px 16px;
    border-radius: 12px;
    border-left: 5px solid #c41e3a;
    margin-bottom: 20px;
    font-weight: 600;
    animation: shake 0.5s ease;
}

.sucesso {
    background: #d4edda;
    color: #155724;
    padding: 14px 16px;
    border-radius: 12px;
    border-left: 5px solid #28a745;
    margin-bottom: 20px;
    font-weight: 600;
    animation: slideInDown 0.4s ease;
}

@keyframes shake {
    0%, 100% { transform: translateX(0); }
    25% { transform: translateX(-10px); }
    75% { transform: translateX(10px); }
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

.profile-footer {
    margin-top: 30px;
    padding-top: 30px;
    border-top: 1px solid #e0e0e0;
    text-align: center;
}

.profile-footer a {
    color: var(--primary);
    font-weight: 700;
    text-decoration: none;
    transition: 0.3s;
    display: inline-block;
    padding: 10px 15px;
    border-radius: 8px;
    transition: all 0.3s;
}

.profile-footer a:hover {
    background: var(--primary-light);
    color: var(--primary-dark);
}

/* RESPONSIVE */
@media (max-width: 600px) {
    .profile-box {
        padding: 35px 25px;
    }

    .profile-header h1 {
        font-size: 24px;
    }

    .avatar-image {
        width: 120px;
        height: 120px;
    }

    .topbar {
        padding: 0 20px;
    }

    .topbar .logo span {
        display: none;
    }
}
</style>
</head>

<body>

<!-- TOPBAR -->
<div class="topbar">
    <a class="logo" href="index.php">
        <img src="<?php echo htmlspecialchars($logo); ?>" alt="Logo" onerror="this.src='uploads/default.png'">
        <span>Sarytha Nails</span>
    </a>

    <div class="right">
        <a class="btn-top" href="minhas_marcacoes.php"><i class="fas fa-calendar-check"></i> Minhas Marcações</a>
        <a class="perfil-box" href="editar_perfil.php">
            <img src="<?php echo htmlspecialchars($avatarPath); ?>" alt="Perfil" onerror="this.src='uploads/default.png'">
            <span><?php echo htmlspecialchars($user["nome"]); ?></span>
        </a>
        <a class="btn-top" href="index.php"><i class="fas fa-home"></i> Início</a>
        <a class="btn-top" href="logout.php"><i class="fas fa-sign-out-alt"></i> Sair</a>
    </div>
</div>

<div class="profile-container">
    <div class="profile-box">
        <div class="profile-header">
            <h1><i class="fas fa-user-circle"></i> O Meu Perfil</h1>
            <p>Edita as tuas informações pessoais</p>
        </div>

        <?php if ($erro): ?>
            <div class="erro">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($erro) ?>
            </div>
        <?php endif; ?>

        <?php if ($sucesso): ?>
            <div class="sucesso">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($sucesso) ?>
            </div>
        <?php endif; ?>

        <div class="avatar-section">
            <div class="avatar-container">
                <img class="avatar-image" src="<?= htmlspecialchars($avatarPath) ?>" alt="Avatar" onerror="this.src='uploads/default.png'">
                <label class="avatar-overlay" for="foto-input" title="Clica para alterar">
                    <i class="fas fa-camera"></i>
                </label>
            </div>
            <label class="avatar-label">Clica na imagem para alterar a tua foto de perfil</label>
        </div>

        <form method="post" enctype="multipart/form-data">
            <div class="form-group">
                <label for="nome"><i class="fas fa-user"></i> Nome Completo</label>
                <input type="text" id="nome" name="nome" value="<?= htmlspecialchars($user["nome"]) ?>" required placeholder="O seu nome">
            </div>

            <div class="form-group">
                <label for="email"><i class="fas fa-envelope"></i> Email</label>
                <input type="email" id="email" name="email" value="<?= htmlspecialchars($user["email"]) ?>" required placeholder="o.seu@email.com" autocapitalize="off" autocorrect="off" spellcheck="false">
            </div>

            <input type="file" id="foto-input" name="foto" accept="image/*" capture="environment">

            <button type="submit" class="submit-btn">
                <i class="fas fa-save"></i> Guardar Alterações
            </button>
        </form>

        <div class="profile-footer">
            <a href="minhas_marcacoes.php">
                <i class="fas fa-calendar-alt"></i> Minhas Marcações
            </a>
            <a href="index.php">
                <i class="fas fa-home"></i> Voltar ao Início
            </a>
            <a href="logout.php" style="color:#dc3545;">
                <i class="fas fa-sign-out-alt"></i> Sair
            </a>
        </div>
    </div>
</div>

<script>
// Clique na imagem para selecionar arquivo
document.querySelector('.avatar-image').addEventListener('click', function() {
    document.getElementById('foto-input').click();
});

// Mostrar pré-visualização da imagem
document.getElementById('foto-input').addEventListener('change', function(e) {
    if (e.target.files && e.target.files[0]) {
        const reader = new FileReader();
        reader.onload = function(event) {
            document.querySelector('.avatar-image').src = event.target.result;
        };
        reader.readAsDataURL(e.target.files[0]);
    }
});
</script>


<script>
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/service-worker.js');
}
</script>
</body>
</html>
