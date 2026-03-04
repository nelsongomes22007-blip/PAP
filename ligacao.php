<?php
session_start();
require __DIR__ . '/api/ligacao.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = $_POST["email"];
    $senha = $_POST["senha"];

    $stmt = $pdo->prepare("SELECT * FROM utilizadores WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($senha, $user["password"])) {
        $_SESSION["user_id"] = $user["id"];
        $_SESSION["nome"] = $user["name"];
        // normalizar role para minúsculas (pode vir com maiúsculas da BD)
        $_SESSION["role"] = strtolower($user["role"]);

        header("Location: Dashboard.php");
        exit;
    } else {
        $erro = "Login inválido";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <title>Login</title>
</head>
<body>

<h2>Login</h2>

<?php if (isset($erro)): ?>
    <p style="color:red"><?= $erro ?></p>
<?php endif; ?>

<form method="post">
    <input type="email" name="email" placeholder="Email" required>
    <input type="password" name="senha" placeholder="Password" required>
    <button type="submit">Entrar</button>
</form>

<a href="registar.php">Criar conta</a>

</body>
</html>
