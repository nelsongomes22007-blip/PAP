<?php
session_start();
require __DIR__ . '/api/ligacao.php';

$erro = null;
$sucesso = null;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';
    $senha2 = $_POST['senha2'] ?? '';

    // Validações básicas
    if ($nome === '' || $email === '' || $senha === '' || $senha2 === '') {
        $erro = 'Todos os campos são obrigatórios.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = 'Email inválido.';
    } elseif ($senha !== $senha2) {
        $erro = 'As passwords não coincidem.';
    } elseif (strlen($senha) < 6) {
        $erro = 'A password deve ter pelo menos 6 caracteres.';
    } else {
        // determine which table to use
        $userTable = 'utilizadores';
        try {
            $pdo->query("SELECT 1 FROM utilizadores LIMIT 1");
        } catch (Exception $e) {
            $userTable = 'users';
        }

        // Verificar se já existe email
        $check = $pdo->prepare("SELECT id FROM $userTable WHERE email = ?");
        $check->execute([$email]);
        if ($check->rowCount() > 0) {
            $erro = 'Já existe uma conta com esse email.';
        } else {
            // Tratar upload de foto (opcional)
            $fotoFinal = null; // guardar só o nome no DB
            if (!empty($_FILES['foto']['name'])) {
                // check for upload errors first
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
                    $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
                    $permitidas = ['jpg','jpeg','png','webp'];
                    if (!in_array($ext, $permitidas)) {
                        $erro = 'Formato de imagem inválido.';
                    } else {
                        $uploadsDir = __DIR__ . '/uploads';
                        if (!is_dir($uploadsDir)) mkdir($uploadsDir, 0777, true);
                        $novoNome = 'user_' . time() . '.' . $ext;
                        $destino = $uploadsDir . '/' . $novoNome;
                        if (move_uploaded_file($_FILES['foto']['tmp_name'], $destino)) {
                            $fotoFinal = $novoNome; // armazenar só o nome
                        } else {
                            $erro = 'Erro ao enviar a foto.';
                        }
                    }
                }
            }

            if (!$erro) {
                // Inserir utilizador
                $hash = password_hash($senha, PASSWORD_DEFAULT);
                // decidir papel – por omissão o registo é cliente, mesmo que o admin esteja a criar
                // garantir que a comparação de roles não é sensível a maiúsculas
                $isAdmin = isset($_SESSION['role']) && strtolower($_SESSION['role']) === 'admin';
                $role = 'cliente'; // fallback garantido
                if ($isAdmin && isset($_POST['role']) && strtolower($_POST['role']) === 'admin') {
                    $role = 'admin';
                }

                $stmt = $pdo->prepare("INSERT INTO $userTable (nome, email, password, role, foto) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$nome, $email, $hash, $role, $fotoFinal]);

                $newId = $pdo->lastInsertId();

                // se o registo foi feito a partir do painel de admin, não troca de sessão – o administrador continua ligado
                if (!$isAdmin) {
                    // Iniciar sessão do utilizador recém-criado
                    $_SESSION['user_id'] = $newId;
                    $_SESSION['nome'] = $nome;
                    $_SESSION['role'] = $role;
                    if ($fotoFinal) {
                        $_SESSION['foto'] = 'uploads/' . $fotoFinal;
                    } else {
                        $_SESSION['foto'] = 'uploads/default.png';
                    }

                    $sucesso = 'Conta criada com sucesso. A redirecionar...';
                    // Redirecionar após curto atraso para ver a mensagem
                    header('Refresh:1; url=index.php');
                } else {
                    // administrador criou outro utilizador/administrador
                    $sucesso = 'Conta criada com sucesso. A voltar ao painel...';
                    header('Refresh:1; url=Admin/index.php');
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-PT">
<head>
<meta charset="UTF-8">
<title>Registar | Sarytha Nails</title>
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
    background: linear-gradient(135deg, #ffe4f1 0%, #fff0f6 100%);
    display: flex;
    justify-content: center;
    align-items: center;
    font-family: 'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    padding: 20px;
}

.register-container {
    width: 100%;
    max-width: 500px;
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

.register-box {
    background: white;
    padding: 50px;
    border-radius: 25px;
    box-shadow: var(--shadow-lg);
    position: relative;
}

.back-button {
    position: absolute;
    top: 20px;
    left: 20px;
    background: var(--primary-light);
    color: var(--primary);
    border: none;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    cursor: pointer;
    font-size: 18px;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
}

.back-button:hover {
    background: var(--primary);
    color: white;
    transform: translateX(-5px);
}

.register-header {
    text-align: center;
    margin-bottom: 40px;
}

.register-header h1 {
    color: var(--primary);
    font-size: 28px;
    margin-bottom: 8px;
    font-weight: 900;
}

.register-header p {
    color: #888;
    font-size: 14px;
}

.form-group {
    margin-bottom: 18px;
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

.form-group input[type="file"] {
    padding: 8px;
}

.form-group input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(176, 53, 124, 0.1);
}

.form-group input::placeholder {
    color: #bbb;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
}

.form-row .form-group {
    margin-bottom: 0;
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

.form-links {
    margin-top: 30px;
    padding-top: 30px;
    border-top: 1px solid #e0e0e0;
    text-align: center;
}

.form-links p {
    color: #666;
    font-size: 14px;
}

.form-links a {
    color: var(--primary);
    font-weight: 700;
    text-decoration: none;
    transition: 0.3s;
}

.form-links a:hover {
    color: var(--secondary);
    text-decoration: underline;
}

.login-link {
    background: var(--primary-light);
    color: var(--primary);
    padding: 12px;
    border-radius: 12px;
    display: block;
    font-weight: 700;
    transition: all 0.3s;
    margin-top: 15px;
}

.login-link:hover {
    background: var(--primary);
    color: white;
}

.register-footer {
    margin-top: 25px;
    text-align: center;
    font-size: 12px;
    color: #aaa;
}

/* RESPONSIVE */
@media (max-width: 600px) {
    .register-box {
        padding: 35px 25px;
    }

    .register-header h1 {
        font-size: 24px;
    }

    .back-button {
        width: 36px;
        height: 36px;
        font-size: 16px;
    }

    .form-row {
        grid-template-columns: 1fr;
        gap: 0;
    }

    .form-row .form-group {
        margin-bottom: 18px;
    }
}
</style>
</head>

<body>

<div class="register-container">
    <div class="register-box">
        <a class="back-button" href="index.php" title="Voltar">
            <i class="fas fa-arrow-left"></i>
        </a>

        <div class="register-header">
            <h1><i class="fas fa-user-plus"></i> Registar</h1>
            <p>Cria a tua conta na Sarytha Nails</p>
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

        <form method="post" enctype="multipart/form-data">
            <div class="form-group">
                <label for="nome"><i class="fas fa-user"></i> Nome Completo</label>
                <input type="text" id="nome" name="nome" required placeholder="João Silva" value="<?= htmlspecialchars($_POST['nome'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="email"><i class="fas fa-envelope"></i> Email</label>
                <input type="email" id="email" name="email" required placeholder="seu@email.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="senha"><i class="fas fa-lock"></i> Password</label>
                    <input type="password" id="senha" name="senha" required placeholder="Mínimo 6 caracteres">
                </div>

                <div class="form-group">
                    <label for="senha2"><i class="fas fa-lock"></i> Confirmar</label>
                    <input type="password" id="senha2" name="senha2" required placeholder="Confirma a password">
                </div>
            </div>

            <div class="form-group">
                <label for="foto"><i class="fas fa-image"></i> Foto de perfil (opcional)</label>
                <input type="file" id="foto" name="foto" accept="image/*" capture="environment">
            </div>

            <?php if (strtolower($_SESSION['role'] ?? '') === 'admin'): ?>
                <div class="form-group">
                    <label for="role"><i class="fas fa-user-shield"></i> Papel</label>
                    <select id="role" name="role">
                        <option value="cliente" <?= (isset($_POST['role']) && $_POST['role'] === 'cliente') ? 'selected' : '' ?>>Cliente</option>
                        <option value="admin" <?= (isset($_POST['role']) && $_POST['role'] === 'admin') ? 'selected' : '' ?>>Administrador</option>
                    </select>
                </div>
            <?php endif; ?>

            <button type="submit" class="submit-btn">
                <i class="fas fa-check"></i> Criar Conta
            </button>
        </form>

        <div class="form-links">
            <p>Já tens conta?</p>
            <a href="login.php" class="login-link">
                <i class="fas fa-sign-in-alt"></i> Entrar Agora
            </a>
        </div>

        <div class="register-footer">
            © <?= date("Y") ?> Sarytha Nails. Todos os direitos reservados.
        </div>
    </div>
</div>


<script>
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/service-worker.js');
}
</script>
</body>
</html>
