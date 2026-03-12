<?php
session_start();
require __DIR__ . '/api/ligacao.php';

$mensagem = null;
$tipo_mensagem = null;
$link_html = null;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"] ?? '');

    if (empty($email)) {
        $mensagem = "Por favor, insira um email.";
        $tipo_mensagem = "danger";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $mensagem = "Email inválido.";
        $tipo_mensagem = "danger";
    } else {
        // determine which table holds users
        $userTable = 'utilizadores';
        try { $pdo->query("SELECT 1 FROM utilizadores LIMIT 1"); } catch (Exception $e) { $userTable = 'users'; }
        $idCol = $userTable === 'utilizadores' ? 'id' : 'user_id';

        // Verificar se o email existe
        $stmt = $pdo->prepare("SELECT $idCol AS id, nome FROM $userTable WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            $mensagem = "Este email não está registado no sistema.";
            $tipo_mensagem = "danger";
        } else {
            // Gerar token único
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

            // Guardar token na base de dados
            $stmt = $pdo->prepare("
                UPDATE $userTable 
                SET reset_token = ?, reset_token_expires = ?
                WHERE $idCol = ?
            ");
            $stmt->execute([$token, $expires, $user['id']]);

            // Preparar link de recuperação (funciona em qualquer domínio/pasta)
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $basePath = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
            if ($basePath === '.' || $basePath === '') {
                $basePath = '';
            }
            $reset_link = $protocol . '://' . $host . $basePath . '/redefinir_senha.php?token=' . $token;

            $assunto = "Recuperação de Password - Sarytha Nails";
            $mensagem_email = "
                <html>
                <body style='font-family: Poppins, Arial, sans-serif; background: #f7f7f7; padding: 20px;'>
                    <div style='max-width: 500px; margin: 0 auto; background: white; border-radius: 15px; padding: 30px; box-shadow: 0 10px 30px rgba(0,0,0,0.1);'>
                        <h2 style='color: #b0357c; text-align: center; margin-bottom: 20px;'>✨ Recuperação de Password</h2>
                        
                        <p style='color: #333; font-size: 15px; line-height: 1.6;'>
                            Olá <strong>" . htmlspecialchars($user['nome']) . "</strong>,
                        </p>
                        
                        <p style='color: #555; font-size: 14px; line-height: 1.8;'>
                            Recebemos um pedido para redefinir a sua password. Se não fez este pedido, pode ignorar este email.
                        </p>
                        
                        <div style='text-align: center; margin: 30px 0;'>
                            <a href='" . $reset_link . "' style='
                                background: linear-gradient(90deg, #b0357c, #c13584);
                                color: white;
                                padding: 14px 40px;
                                text-decoration: none;
                                border-radius: 20px;
                                font-weight: 700;
                                display: inline-block;
                            '>Redefinir Password</a>
                        </div>
                        
                        <p style='color: #888; font-size: 13px; text-align: center;'>
                            Este link é válido por <strong>1 hora</strong>.
                        </p>
                        
                        <p style='color: #888; font-size: 13px; text-align: center;'>
                            Se o botão não funcionar, copie este link:<br>
                            <code style='background: #f5f5f5; padding: 8px; border-radius: 5px;'>" . htmlspecialchars($reset_link) . "</code>
                        </p>
                        
                        <hr style='border: none; border-top: 1px solid #ddd; margin: 20px 0;'>
                        
                        <p style='color: #999; font-size: 12px; text-align: center;'>
                            © " . date('Y') . " Sarytha Nails. Todos os direitos reservados.
                        </p>
                    </div>
                </body>
                </html>
            ";

            // Headers para HTML email
            $headers = "MIME-Version: 1.0\r\n";
            $headers .= "Content-type: text/html; charset=UTF-8\r\n";
            $headers .= "From: info@sarytahnails.com\r\n";

            // Enviar email
            $mailSent = mail($email, $assunto, $mensagem_email, $headers);
            $isLocalhost = in_array($host, ['localhost', '127.0.0.1']);
            $showLink = !$mailSent || $isLocalhost;

            if ($mailSent) {
                $mensagem = "Email de recuperação enviado com sucesso! Verifique o seu email.";
                $tipo_mensagem = "success";
                // Limpar o formulário
                $_POST = [];
            } else {
                $mensagem = "Não foi possível enviar o email. Use o link abaixo para redefinir a password.";
                $tipo_mensagem = "danger";
            }

            if ($showLink) {
                $link_html = "<div class='info-box' style='margin-top: 15px;'><strong>Link de recuperação: </strong> <a href='" . htmlspecialchars($reset_link) . "'>" . htmlspecialchars($reset_link) . "</a></div>";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-PT">
<head>
<meta charset="UTF-8">
<title>Recuperar Password | Sarytha Nails</title>
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

.forgot-container {
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

.forgot-box {
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

.forgot-header {
    text-align: center;
    margin-bottom: 40px;
}

.forgot-header h1 {
    color: var(--primary);
    font-size: 28px;
    margin-bottom: 8px;
    font-weight: 900;
}

.forgot-header p {
    color: #888;
    font-size: 14px;
    line-height: 1.6;
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
    background: #e7f3ff;
    color: #004085;
    padding: 15px;
    border-radius: 12px;
    border-left: 5px solid #0056b3;
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

.login-link {
    background: var(--primary-light);
    color: var(--primary);
    padding: 12px;
    border-radius: 12px;
    display: block;
    font-weight: 700;
    transition: all 0.3s;
}

.login-link:hover {
    background: var(--primary);
    color: white;
}

/* RESPONSIVE */
@media (max-width: 500px) {
    .forgot-box {
        padding: 35px 25px;
    }

    .forgot-header h1 {
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

<div class="forgot-container">
    <div class="forgot-box">
        <a class="back-button" href="login.php" title="Voltar">
            <i class="fas fa-arrow-left"></i>
        </a>

        <div class="forgot-header">
            <h1><i class="fas fa-key"></i> Recuperar Password</h1>
            <p>Insira o seu email para receber um link de recuperação</p>
        </div>

        <?php if ($mensagem): ?>
            <div class="alert alert-<?php echo $tipo_mensagem; ?>">
                <i class="fas fa-<?php echo $tipo_mensagem === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <?php echo htmlspecialchars($mensagem); ?>
            </div>
        <?php endif; ?>

        <?php if ($link_html): ?>
            <?php echo $link_html; ?>
        <?php endif; ?>

        <div class="info-box">
            <i class="fas fa-info-circle"></i>
            Enviaremos um email com um link especial para redefinir a sua password. O link é válido por 1 hora.
        </div>

        <form method="post">
            <div class="form-group">
                <label for="email"><i class="fas fa-envelope"></i> Email</label>
                <input 
                    type="email" 
                    id="email" 
                    name="email" 
                    required 
                    placeholder="seu@email.com"
                    value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                >
            </div>

            <button type="submit" class="submit-btn">
                <i class="fas fa-paper-plane"></i> Enviar Email de Recuperação
            </button>
        </form>

        <div class="form-links">
            <p>Lembrou-se da password?</p>
            <a href="login.php" class="login-link">
                <i class="fas fa-sign-in-alt"></i> Voltar ao Login
            </a>

            <p style="margin-top: 15px; margin-bottom: 5px;">
                Ainda não tens conta?
            </p>
            <a href="registar.php" style="color: var(--primary); font-weight: 700; padding: 10px; display: block;">
                <i class="fas fa-user-plus"></i> Registar-se
            </a>
        </div>
    </div>
</div>

</body>
</html>
