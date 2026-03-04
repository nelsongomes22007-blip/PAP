<?php
session_start();
require __DIR__ . '/api/ligacao.php';

$erro = null;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"] ?? '');
    $senha = trim($_POST["senha"] ?? '');

    // determine which table holds users
    $userTable = 'utilizadores';
    try {
        $pdo->query("SELECT 1 FROM utilizadores LIMIT 1");
    } catch (Exception $e) {
        $userTable = 'users';
    }

    $stmt = $pdo->prepare("SELECT * FROM $userTable WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($senha, $user["password"])) {
        $_SESSION["user_id"] = $user["id"];
        $_SESSION["nome"] = $user["nome"];
        // Normalizar valores antigos ('user') para 'cliente' para manter consistência
        $roleVal = $user["role"];
        if ($roleVal === 'user') {
            $roleVal = 'cliente';
        }
        // for consistency always store lowercase roles
        $roleVal = strtolower($roleVal);
        $_SESSION["role"] = $roleVal;

        // Normalizar e guardar o caminho da foto na sessão.
        $fotoPath = $user['foto'] ?? '';
        if ($fotoPath) {
            if (strpos($fotoPath, 'uploads/') !== 0 && file_exists(__DIR__ . '/uploads/' . $fotoPath)) {
                $fotoPath = 'uploads/' . $fotoPath;
            } elseif (strpos($fotoPath, 'uploads/') === 0 && !file_exists(__DIR__ . '/' . $fotoPath)) {
                // Fallback se o ficheiro não existir
                $fotoPath = 'uploads/default.png';
            }
        } else {
            $fotoPath = 'uploads/default.png';
        }
        $_SESSION['foto'] = $fotoPath;

        if (strtolower($user["role"]) === "admin") {
            header("Location: Admin/index.php");
        } else {
            header("Location: index.php");
        }
        exit;
    } else {
        $erro = "Email ou password inválidos.";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-PT">
<head>
<meta charset="UTF-8">
<title>Login | Sarytha Nails</title>
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

.login-container {
    width: 100%;
    max-width: 450px;
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

.login-box {
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

.login-header {
    text-align: center;
    margin-bottom: 40px;
}

.login-header h1 {
    color: var(--primary);
    font-size: 28px;
    margin-bottom: 8px;
    font-weight: 900;
}

.login-header p {
    color: #888;
    font-size: 14px;
}

.form-group {
    margin-bottom: 22px;
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

.form-group input::placeholder {
    color: #bbb;
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
    margin-top: 10px;
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
    text-align: center;
    margin-bottom: 20px;
    font-weight: 600;
    animation: shake 0.5s ease;
}

@keyframes shake {
    0%, 100% { transform: translateX(0); }
    25% { transform: translateX(-10px); }
    75% { transform: translateX(10px); }
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
    margin-bottom: 15px;
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

.signup-link {
    background: var(--primary-light);
    color: var(--primary);
    padding: 12px;
    border-radius: 12px;
    display: block;
    font-weight: 700;
    transition: all 0.3s;
}

.signup-link:hover {
    background: var(--primary);
    color: white;
}

.login-footer {
    margin-top: 25px;
    text-align: center;
    font-size: 12px;
    color: #aaa;
}

/* RESPONSIVE */
@media (max-width: 500px) {
    .login-box {
        padding: 35px 25px;
    }

    .login-header h1 {
        font-size: 24px;
    }

    .back-button {
        width: 36px;
        height: 36px;
        font-size: 16px;
    }
}
</style>
</head>

<body>

<div class="login-container">
    <div class="login-box">
        <a class="back-button" href="index.php" title="Voltar">
            <i class="fas fa-arrow-left"></i>
        </a>

        <div class="login-header">
            <h1><i class="fas fa-sparkles"></i> Login</h1>
            <p>Bem-vindo à Sarytha Nails</p>
        </div>

        <?php if ($erro): ?>
            <div class="erro">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($erro) ?>
            </div>
        <?php endif; ?>

        <form method="post">
            <div class="form-group">
                <label for="email"><i class="fas fa-envelope"></i> Email</label>
                <input type="email" id="email" name="email" required placeholder="o.seu@email.com">
            </div>

            <div class="form-group">
                <label for="senha"><i class="fas fa-lock"></i> Password</label>
                <input type="password" id="senha" name="senha" required placeholder="A sua password">
            </div>

            <button type="submit" class="submit-btn">
                <i class="fas fa-sign-in-alt"></i> Entrar
            </button>
        </form>

        <div class="form-links">
            <p>Ainda não tens conta?</p>
            <a href="registar.php" class="signup-link">
                <i class="fas fa-user-plus"></i> Criar Conta Agora
            </a>

            <p style="margin-top: 15px; margin-bottom: 5px;">
                <a href="esqueci_senha.php" style="font-size:13px; color: var(--primary); font-weight: 700;">
                    <i class="fas fa-key"></i> Esqueceste a password?
                </a>
            </p>
        </div>

        <div class="login-footer">
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

