<?php
session_start();
require __DIR__ . '/../api/ligacao.php';

if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role'] ?? '') !== 'admin') {
    die('Acesso negado');
}

// serviços e durações
$servicos = [
    'Manicure' => 60,
    'Pedicure' => 60,
    'Gel & Acrílico' => 90,
    'Design Artístico' => 120,
    'Spa de Mãos' => 45,
    'Pacotes Premium' => 120,
];
// carregar serviços do BD se disponível
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'servicos'");
    if ($stmt->rowCount() > 0) {
        $sstmt = $pdo->query("SELECT nome, duracao_min FROM servicos WHERE ativo = 1");
        $rows = $sstmt->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($rows)) {
            $servicos = [];
            foreach ($rows as $r) {
                $servicos[$r['nome']] = (int)$r['duracao_min'];
            }
        }
    }
} catch (Exception $e) {}


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

$erro = null;
$sucesso = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_utilizador = $_POST['id_utilizador'] ?? null;
    $nome_guest = trim($_POST['nome_guest'] ?? '');
    $email_guest = trim($_POST['email_guest'] ?? '');
    $data = trim($_POST['data'] ?? '');
    $hora = trim($_POST['hora'] ?? '');

    if (empty($data) || empty($hora)) {
        $erro = "Data e hora são obrigatórias.";
    } elseif (empty($id_utilizador) && empty($nome_guest)) {
        $erro = "Para clientes não registados, o nome é obrigatório.";
    } else {
        $today = (new DateTime())->setTime(0,0,0);
        $dObj = DateTime::createFromFormat('Y-m-d', $data);
        if (!$dObj) {
            $erro = "Formato de data inválido.";
        } elseif ($dObj < $today) {
            $erro = "A data não pode ser no passado.";
        } else {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE data = ? AND hora = ? AND status != 'rejeitada'");
            $stmt->execute([$data, $hora]);
            $cnt = $stmt->fetchColumn();
            if ($cnt > 0) {
                $erro = "Horário já ocupado.";
            } else {
                try {
                    // figure out which column stores user reference
                    $bColsStmt = $pdo->query("DESCRIBE bookings");
                    $bookingCols = array_column($bColsStmt->fetchAll(PDO::FETCH_ASSOC), 'Field');
                    $userCol = in_array('id_utilizador', $bookingCols) ? 'id_utilizador' : (in_array('user_id', $bookingCols) ? 'user_id' : 'id_utilizador');

                    if (!empty($id_utilizador)) {
                        // logged in user, add service if available
                        $cols = [$userCol, 'data', 'hora', 'status'];
                        $vals = [$id_utilizador, $data, $hora, 'confirmada'];
                        $ph = ['?', '?', '?', '?'];
                        if (in_array('servico', $bookingCols)) {
                            $cols[] = 'servico';
                            $vals[] = $servico;
                            $ph[] = '?';
                        }
                        $q = 'INSERT INTO bookings (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $ph) . ')';
                        $stmt = $pdo->prepare($q);
                        $stmt->execute($vals);
                    } else {
                        // guest
                        $cols = [$userCol, 'nome', 'email', 'data', 'hora', 'status'];
                        $vals = [null, $nome_guest, $email_guest, $data, $hora, 'confirmada'];
                        $ph = ['?', '?', '?', '?', '?', '?'];
                        if (in_array('servico', $bookingCols)) {
                            $cols[] = 'servico';
                            $vals[] = $servico;
                            $ph[] = '?';
                        }
                        $q = 'INSERT INTO bookings (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $ph) . ')';
                        $stmt = $pdo->prepare($q);
                        $stmt->execute($vals);
                    }
                    $sucesso = "Marcação criada com sucesso!";
                } catch (Exception $e) {
                    $erro = "Erro ao criar marcação.";
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
<title>Adicionar Marcação | Admin</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<style>
body {
    margin: 0;
    min-height: 100vh;
    display: flex;
    justify-content: center;
    align-items: center;
    background: linear-gradient(135deg, #fff0f6, #ffe4f1);
    font-family: 'Poppins', Arial, sans-serif;
}

.form-box {
    background: #fff;
    width: 100%;
    max-width: 500px;
    padding: 40px;
    border-radius: 22px;
    box-shadow: 0 20px 45px rgba(176, 53, 124, 0.25);
}

.form-box h2 {
    text-align: center;
    color: #b0357c;
    margin-bottom: 10px;
}

.subtitle {
    text-align: center;
    color: #777;
    font-size: 14px;
    margin-bottom: 30px;
}

label {
    display: block;
    margin-top: 15px;
    font-weight: 600;
    color: #444;
}

select, input[type="date"], input[type="time"], input[type="text"], input[type="email"] {
    width: 100%;
    padding: 12px;
    margin-top: 6px;
    border-radius: 12px;
    border: 1px solid #ccc;
    font-size: 15px;
}

select:focus, input:focus {
    outline: none;
    border-color: #b0357c;
}

button {
    width: 100%;
    margin-top: 30px;
    padding: 14px;
    background: #b0357c;
    border: none;
    color: #fff;
    border-radius: 16px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.2s, transform 0.1s;
}

button:hover {
    background: #962e67;
    transform: translateY(-2px);
}

.erro {
    background: #ffe1e6;
    color: #a8002d;
    padding: 12px;
    border-radius: 14px;
    text-align: center;
    margin-bottom: 15px;
    font-weight: 600;
}

.sucesso {
    background: #e7ffe9;
    color: #1f7a35;
    padding: 12px;
    border-radius: 14px;
    text-align: center;
    margin-bottom: 15px;
    font-weight: 600;
}

.links {
    margin-top: 25px;
    text-align: center;
}

.links a {
    color: #b0357c;
    text-decoration: none;
    font-weight: 600;
}

.links a:hover {
    text-decoration: underline;
}
</style>
</head>

<body>

<div class="form-box">
    <h2>➕ Adicionar Marcação</h2>
    <p class="subtitle">Cria uma nova marcação para um cliente</p>

    <?php if ($erro): ?>
        <div class="erro"><?= htmlspecialchars($erro) ?></div>
    <?php endif; ?>

    <?php if ($sucesso): ?>
        <div class="sucesso"><?= htmlspecialchars($sucesso) ?></div>
    <?php endif; ?>

    <form method="post">
        <label>Cliente (opcional)</label>
        <select name="id_utilizador" id="id_utilizador">
            <option value="">-- Cliente não registado --</option>
            <?php
                // determine user table to populate dropdown
                $userTable = 'utilizadores';
                $idCol = 'id';
                try { $pdo->query("SELECT 1 FROM utilizadores LIMIT 1"); } catch (Exception $e) { $userTable = 'users'; $idCol = 'user_id'; }
                $uStmt = $pdo->query("SELECT $idCol AS id, nome, email FROM $userTable ORDER BY nome ASC");
                $users = $uStmt->fetchAll();
                foreach ($users as $u) {
                    echo '<option value="'.htmlspecialchars($u["id"]).'">'.htmlspecialchars($u["nome"]).' ('.htmlspecialchars($u["email"]).')</option>';
                }
            ?>
        </select>

        <div id="guest-fields" style="display: none;">
            <label>Nome</label>
            <input type="text" name="nome_guest" required>

            <label>Email (opcional)</label>
            <input type="email" name="email_guest">
        </div>

        <label>Serviço</label>
        <select name="servico" required>
            <option value="">-- Escolher serviço --</option>
            <?php foreach ($servicos as $s => $d): ?>
                <option value="<?= htmlspecialchars($s) ?>"><?= htmlspecialchars($s) ?></option>
            <?php endforeach; ?>
        </select>

        <label>Data</label>
        <input type="date" name="data" required>

        <label>Hora</label>
        <input type="time" name="hora" required min="09:00" max="18:00" step="1800">

        <button>Criar Marcação</button>
    </form>

    <div class="links">
        <a href="index.php">⬅ Voltar ao Painel</a>
    </div>
</div>

<script>
document.getElementById('id_utilizador').addEventListener('change', function() {
    const guestFields = document.getElementById('guest-fields');
    if (this.value === '') {
        guestFields.style.display = 'block';
    } else {
        guestFields.style.display = 'none';
    }
});
</script>

</body>
</html>