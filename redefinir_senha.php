<?php
session_start();
require __DIR__ . '/api/ligacao.php';

$mensagem = null;
$tipo_mensagem = null;
$token_valido = false;
$user = null;

// Verificar se o token foi fornecido
$token = $_GET['token'] ?? '';

if (!empty($token)) {
    // Validar o token
    // determine user table
    $userTable = 'utilizadores';
    try { $pdo->query("SELECT 1 FROM utilizadores LIMIT 1"); } catch (Exception $e) { $userTable = 'users'; }
    $idCol = $userTable === 'utilizadores' ? 'id' : 'user_id';

    $stmt = $pdo->prepare("
        SELECT $idCol AS id, nome, email FROM $userTable 
        WHERE reset_token = ? 
        AND reset_token_expires > NOW()
    ");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if ($user) {
        $token_valido = true;
    } else {
        $mensagem = "Link de recuperação inválido ou expirado. Por favor, solicite um novo link.";
        $tipo_mensagem = "danger";
    }
} else {
    $mensagem = "Token não fornecido.";
    $tipo_mensagem = "danger";
}

// Processar formulário de redefinição de password
if ($_SERVER["REQUEST_METHOD"] === "POST" && $token_valido) {
    $nova_senha = $_POST['senha'] ?? '';
    $confirmar_senha = $_POST['senha2'] ?? '';

    if (empty($nova_senha) || empty($confirmar_senha)) {
        $mensagem = "Por favor, preencha todos os campos.";
        $tipo_mensagem = "danger";
    } elseif ($nova_senha !== $confirmar_senha) {
        $mensagem = "As passwords não coincidem.";
        $tipo_mensagem = "danger";
    } elseif (strlen($nova_senha) < 6) {
        $mensagem = "A password deve ter pelo menos 6 caracteres.";
        $tipo_mensagem = "danger";
    } else {
        try {
            // Hash da nova password
            $hash = password_hash($nova_senha, PASSWORD_DEFAULT);

            // Atualizar password e limpar token
            $stmt = $pdo->prepare("
                UPDATE $userTable 
                SET password = ?, reset_token = NULL, reset_token_expires = NULL
                WHERE $idCol = ?
            ");
            $stmt->execute([$hash, $user['id']]);

            $mensagem = "Password redefinida com sucesso! A redirecionar para login...";
            $tipo_mensagem = "success";
            $token_valido = false; // Mostrar apenas a mensagem de sucesso

            // Redirecionar após 2 segundos
            header("Refresh: 2; url=login.php");

        } catch (Exception $e) {
            $mensagem = "Erro ao redefinir password. Por favor, tente novamente.";
            $tipo_mensagem = "danger";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-PT">
<head>
<meta charset="UTF-8">
<title>Redefinir Password | Sarytha Nails</title>
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

.reset-container {
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

.reset-box {
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

.reset-header {
    text-align: center;
    margin-bottom: 40px;
}

.reset-header h1 {
    color: var(--primary);
    font-size: 28px;
    margin-bottom: 8px;
    font-weight: 900;
}

.reset-header p {
    color: #888;
    font-size: 14px;
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

.form-group input::placeholder {
    color: #bbb;
}

.password-strength {
    margin-top: 8px;
    font-size: 12px;
    color: #666;
}

.password-strength-bar {
    height: 4px;
    background: #e0e0e0;
    border-radius: 2px;
    margin-top: 4px;
    overflow: hidden;
}

.password-strength-bar span {
    height: 100%;
    display: block;
    width: 0%;
    transition: width 0.3s;
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

.submit-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.alert {
    padding: 15px 20px;
    border-radius: 12px;
    border-left: 5px solid;
    margin-bottom: 20px;
    animation: slideInDown 0.4s ease;
    display: flex;
    align-items: center;
    gap: 10px;
}

.alert-success {
    background: #d4edda;
    color: #155724;
    border-color: #28a745;
}

.alert-danger {
    background: #f8d7da;
    color: #721c24;
    border-color: #dc3545;
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

.info-box {
    background: #e3f2fd;
    color: #1565c0;
    padding: 15px;
    border-radius: 12px;
    border-left: 5px solid #1976d2;
    margin-bottom: 20px;
    font-size: 14px;
    line-height: 1.6;
}

.form-links {
    margin-top: 30px;
    padding-top: 30px;
    border-top: 1px solid #e0e0e0;
    text-align: center;
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

/* RESPONSIVE */
@media (max-width: 500px) {
    .reset-box {
        padding: 35px 25px;
    }

    .reset-header h1 {
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

<div class="reset-container">
    <div class="reset-box">
        <a class="back-button" href="login.php" title="Voltar">
            <i class="fas fa-arrow-left"></i>
        </a>

        <div class="reset-header">
            <h1><i class="fas fa-lock"></i> Redefinir Password</h1>
            <p>Cria uma nova password segura</p>
        </div>

        <?php if ($mensagem): ?>
            <div class="alert alert-<?php echo $tipo_mensagem; ?>">
                <i class="fas fa-<?php echo $tipo_mensagem === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <?php echo htmlspecialchars($mensagem); ?>
            </div>
        <?php endif; ?>

        <?php if ($token_valido && $user): ?>
            <div class="info-box">
                <i class="fas fa-shield-alt"></i>
                <strong>Dica:</strong> Cria uma password com pelo menos 6 caracteres, incluindo letras e números.
            </div>

            <form method="post">
                <div class="form-group">
                    <label for="senha"><i class="fas fa-lock"></i> Nova Password</label>
                    <input 
                        type="password" 
                        id="senha" 
                        name="senha" 
                        required 
                        placeholder="Mínimo 6 caracteres"
                        onkeyup="checkPasswordStrength()"
                    >
                    <div class="password-strength">
                        <span id="strength-text">Força da password: Fraca</span>
                        <div class="password-strength-bar">
                            <span id="strength-bar" style="background: #dc3545;"></span>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="senha2"><i class="fas fa-lock"></i> Confirmar Password</label>
                    <input 
                        type="password" 
                        id="senha2" 
                        name="senha2" 
                        required 
                        placeholder="Confirma a password"
                    >
                </div>

                <button type="submit" class="submit-btn">
                    <i class="fas fa-check"></i> Redefinir Password
                </button>
            </form>

        <?php endif; ?>

        <?php if (!$token_valido): ?>
            <div class="form-links">
                <a href="esqueci_senha.php" class="login-link">
                    <i class="fas fa-arrow-left"></i> Voltar à Recuperação
                </a>

                <p style="margin-top: 20px;">
                    <a href="login.php">Voltar ao Login</a>
                </p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function checkPasswordStrength() {
    const password = document.getElementById('senha').value;
    const strengthText = document.getElementById('strength-text');
    const strengthBar = document.getElementById('strength-bar');
    
    let strength = 0;
    let text = 'Fraca';
    let color = '#dc3545';
    
    if (password.length >= 6) strength += 25;
    if (password.length >= 12) strength += 25;
    if (/[0-9]/.test(password)) strength += 25;
    if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength += 25;
    
    if (strength <= 25) {
        text = 'Muito Fraca';
        color = '#dc3545';
    } else if (strength <= 50) {
        text = 'Fraca';
        color = '#fd7e14';
    } else if (strength <= 75) {
        text = 'Média';
        color = '#ffc107';
    } else {
        text = 'Forte';
        color = '#28a745';
    }
    
    strengthText.textContent = 'Força da password: ' + text;
    strengthBar.style.width = strength + '%';
    strengthBar.style.background = color;
}
</script>

</body>
</html>
