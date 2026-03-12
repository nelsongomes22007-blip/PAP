<?php
require __DIR__ . "/../api/ligacao.php";

echo "<!DOCTYPE html>
<html lang='pt-PT'>
<head>
    <meta charset='UTF-8'>
    <title>Inicializar Base de Dados - Sarytha Nails</title>
    <style>
        body {
            font-family: 'Poppins', Arial, sans-serif;
            background: linear-gradient(135deg, #f7d7e6, #fceef5);
            padding: 30px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 20px 50px rgba(176, 53, 124, 0.25);
        }
        h1 {
            color: #b0357c;
            text-align: center;
        }
        .log {
            background: #f5f5f5;
            padding: 20px;
            border-radius: 8px;
            font-family: monospace;
            font-size: 12px;
            max-height: 500px;
            overflow-y: auto;
            border-left: 4px solid #b0357c;
        }
        .log-item {
            margin: 8px 0;
            line-height: 1.6;
        }
        .success {
            color: #28a745;
        }
        .error {
            color: #dc3545;
        }
        .warning {
            color: #ffc107;
        }
        .info {
            color: #17a2b8;
        }
    </style>
</head>
<body>
<div class='container'>
    <h1>⚙️ Inicializar/Reparar Base de Dados</h1>
    <div class='log'>";

function log_output($message, $type = 'info') {
    $classes = [
        'success' => 'success',
        'error' => 'error',
        'warning' => 'warning',
        'info' => 'info'
    ];
    $class = $classes[$type] ?? 'info';
    $icons = [
        'success' => '✓',
        'error' => '✗',
        'warning' => '⚠',
        'info' => 'ℹ'
    ];
    $icon = $icons[$type] ?? '';
    echo "<div class='log-item $class'>$icon $message</div>";
    flush();
    ob_flush();
}

$operations = [];

// determine which user table is in use (or should be created)
$userTable = null;
$userColumn = null; // primary key column
try {
    $pdo->query("DESCRIBE utilizadores");
    $userTable = 'utilizadores';
    $userColumn = 'id';
    log_output("Tabela 'utilizadores' já existe", 'info');
} catch (PDOException $e) {
    try {
        $pdo->query("DESCRIBE users");
        $userTable = 'users';
        $userColumn = 'user_id';
        log_output("Tabela 'users' já existe", 'info');
    } catch (PDOException $e2) {
        // neither table exists, create utilizadores by default
        $pdo->exec("CREATE TABLE utilizadores (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nome VARCHAR(255) NOT NULL,
            email VARCHAR(255) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            foto VARCHAR(255),
            role ENUM('cliente', 'admin') DEFAULT 'cliente',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");
        log_output("✓ Tabela 'utilizadores' criada", 'success');
        $operations[] = 'create_utilizadores';
        $userTable = 'utilizadores';
        $userColumn = 'id';
    }
}

// normalizar possíveis valores antigos e assegurar DEFAULT
if ($userTable === 'utilizadores') {
    // força atributo NOT NULL e padrão cliente
    $pdo->exec("ALTER TABLE utilizadores MODIFY role ENUM('cliente','admin') NOT NULL DEFAULT 'cliente'");
    // normalizar registos que ainda contenham o legado 'user'
    $pdo->exec("UPDATE utilizadores SET role='cliente' WHERE role='user' OR role IS NULL");
    log_output("✔️ Coluna 'role' de 'utilizadores' preparada com DEFAULT 'cliente' e valores antigos corrigidos", 'info');

    // Garantir colunas de recuperação de password
    try {
        $cols = [];
        $stmt = $pdo->query("DESCRIBE utilizadores");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $cols[] = $row['Field'];
        }
        if (!in_array('reset_token', $cols)) {
            $pdo->exec("ALTER TABLE utilizadores ADD COLUMN reset_token VARCHAR(128) NULL");
            log_output("✓ Coluna 'reset_token' adicionada a 'utilizadores'", 'success');
        }
        if (!in_array('reset_token_expires', $cols)) {
            $pdo->exec("ALTER TABLE utilizadores ADD COLUMN reset_token_expires DATETIME NULL");
            log_output("✓ Coluna 'reset_token_expires' adicionada a 'utilizadores'", 'success');
        }
    } catch (PDOException $e) {
        log_output("⚠ Não foi possível garantir colunas de recuperação de password em 'utilizadores'", 'warning');
    }
} elseif ($userTable === 'users') {
    $pdo->exec("ALTER TABLE users MODIFY role ENUM('cliente','admin') NOT NULL DEFAULT 'cliente'");
    $pdo->exec("UPDATE users SET role='cliente' WHERE role='user' OR role IS NULL");
    log_output("✔️ Coluna 'role' de 'users' preparada com DEFAULT 'cliente' e valores antigos corrigidos", 'info');

    // Garantir colunas de recuperação de password
    try {
        $cols = [];
        $stmt = $pdo->query("DESCRIBE users");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $cols[] = $row['Field'];
        }
        if (!in_array('reset_token', $cols)) {
            $pdo->exec("ALTER TABLE users ADD COLUMN reset_token VARCHAR(128) NULL");
            log_output("✓ Coluna 'reset_token' adicionada a 'users'", 'success');
        }
        if (!in_array('reset_token_expires', $cols)) {
            $pdo->exec("ALTER TABLE users ADD COLUMN reset_token_expires DATETIME NULL");
            log_output("✓ Coluna 'reset_token_expires' adicionada a 'users'", 'success');
        }
    } catch (PDOException $e) {
        log_output("⚠ Não foi possível garantir colunas de recuperação de password em 'users'", 'warning');
    }
}

// 2. Create trabalhos table
    try {
        $pdo->query("DESCRIBE trabalhos");
        log_output("Tabela 'trabalhos' já existe", 'info');
    } catch (PDOException $e) {
        $pdo->exec("CREATE TABLE trabalhos (
            id_trabalho INT AUTO_INCREMENT PRIMARY KEY,
            titulo VARCHAR(255) NOT NULL,
            imagem VARCHAR(255) NOT NULL,
            descricao TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        log_output("✓ Tabela 'trabalhos' criada", 'success');
        $operations[] = 'create_trabalhos';
    }

    // 3. Create bookings table with all required columns
    try {
        $result = $pdo->query("DESCRIBE bookings");
        $existing_cols = [];
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            $existing_cols[] = $row['Field'];
        }
        
        log_output("Tabela 'bookings' já existe (" . count($existing_cols) . " colunas)", 'info');
        
        // Check for missing columns
        $required_cols = ['servico', 'user_id', 'id_utilizador'];
        foreach ($required_cols as $col) {
            if (!in_array($col, $existing_cols)) {
                switch ($col) {
                    case 'servico':
                        $pdo->exec("ALTER TABLE bookings ADD COLUMN servico VARCHAR(150)");
                        log_output("✓ Coluna 'servico' adicionada a 'bookings'", 'success');
                        $operations[] = 'add_servico_column';
                        break;
                    case 'user_id':
                        $pdo->exec("ALTER TABLE bookings ADD COLUMN user_id INT");
                        log_output("✓ Coluna 'user_id' adicionada a 'bookings'", 'success');
                        $operations[] = 'add_user_id_column';
                        break;
                    case 'id_utilizador':
                        $pdo->exec("ALTER TABLE bookings ADD COLUMN id_utilizador INT");
                        log_output("✓ Coluna 'id_utilizador' adicionada a 'bookings'", 'success');
                        $operations[] = 'add_id_utilizador_column';
                        break;
                }
            }
        }
        
    } catch (PDOException $e) {
        // Create bookings table
        $fkUser = "$userTable($userColumn)";
        $pdo->exec("CREATE TABLE bookings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            id_utilizador INT,
            user_id INT,
            nome VARCHAR(255),
            email VARCHAR(255),
            data DATE NOT NULL,
            hora TIME NOT NULL,
            servico VARCHAR(150),
            status ENUM('pendente', 'confirmada', 'rejeitada', 'concluida') DEFAULT 'pendente',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (id_utilizador) REFERENCES $fkUser ON DELETE SET NULL,
            FOREIGN KEY (user_id) REFERENCES $fkUser ON DELETE SET NULL
        )");
        log_output("✓ Tabela 'bookings' criada com todas as colunas", 'success');
        $operations[] = 'create_bookings';
    }

    // 4. Create avaliacoes table (Critical for reviews!)
    try {
        $result = $pdo->query("DESCRIBE avaliacoes");
        $existing_cols = [];
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            $existing_cols[] = $row['Field'];
        }
        
        log_output("Tabela 'avaliacoes' já existe", 'info');
        
        // Check for missing columns and add them
        $required_columns = [
            'user_id' => "INT",
            'nome' => "VARCHAR(255)",
            'rating' => "INT CHECK (rating >= 1 AND rating <= 5)",
            'comentario' => "TEXT NOT NULL"
        ];
        
        foreach ($required_columns as $col => $type) {
            if (!in_array($col, $existing_cols)) {
                try {
                    $pdo->exec("ALTER TABLE avaliacoes ADD COLUMN $col $type");
                    log_output("✓ Coluna '$col' adicionada a 'avaliacoes'", 'success');
                    $operations[] = "add_" . $col . "_to_avaliacoes";
                } catch (PDOException $ex) {
                    log_output("⚠ Não foi possível adicionar '$col' a avaliacoes", 'warning');
                }
            }
        }
        
    } catch (PDOException $e) {
        $fkUser = "$userTable($userColumn)";
        $pdo->exec("CREATE TABLE avaliacoes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            nome VARCHAR(255),
            rating INT CHECK (rating >= 1 AND rating <= 5),
            comentario TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES $fkUser ON DELETE SET NULL
        )");
        log_output("✓ Tabela 'avaliacoes' criada (Sistema de Reviews)", 'success');
        $operations[] = 'create_avaliacoes';
    }

    log_output("", 'info');
    log_output("═════════════════════════════════════════", 'info');
    log_output("", 'info');

    if (empty($operations)) {
        log_output("✓ Base de dados já está completamente configurada!", 'success');
    } else {
        log_output("✓ " . count($operations) . " operação(ões) executada(s) com sucesso", 'success');
    }

    // Verify all tables
    log_output("", 'info');
    log_output("Verificando estado final...", 'info');
    log_output("", 'info');
    
    $tables = ['users', 'bookings', 'trabalhos', 'avaliacoes'];
    foreach ($tables as $t) {
        try {
            $result = $pdo->query("SELECT COUNT(*) as cnt FROM $t");
            $count = $result->fetch()['cnt'];
            log_output("✓ $t: OK ($count registos)", 'success');
        } catch (Exception $e) {
            log_output("✗ $t: ERRO - " . $e->getMessage(), 'error');
        }
    }

    log_output("", 'info');
    log_output("═════════════════════════════════════════", 'info');
    log_output("", 'info');
    log_output("✓ Inicialização Completa! O site está pronto para usar.", 'success');
    
} catch (Exception $e) {
    log_output("✗ ERRO CRÍTICO: " . $e->getMessage(), 'error');
}

echo "    </div>
    <div style='margin-top: 30px; text-align: center;'>
        <a href='../diagnostico.php' style='display: inline-block; padding: 12px 24px; background: #b0357c; color: white; text-decoration: none; border-radius: 6px; font-weight: 600;'>🔄 Voltar ao Diagnóstico</a>
        <a href='../index.php' style='display: inline-block; padding: 12px 24px; background: #28a745; color: white; text-decoration: none; border-radius: 6px; font-weight: 600; margin-left: 10px;'>🏠 Ir para Homepage</a>
    </div>
</div>
</body>
</html>";
?>
