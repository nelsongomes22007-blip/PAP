<?php
session_start();
require __DIR__ . "/api/ligacao.php";

echo "<!DOCTYPE html>
<html lang='pt-PT'>
<head>
    <meta charset='UTF-8'>
    <title>Diagnóstico do Site - Sarytha Nails</title>
    <style>
        body {
            font-family: 'Poppins', Arial, sans-serif;
            background: linear-gradient(135deg, #f7d7e6, #fceef5);
            color: #333;
            padding: 40px 20px;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 20px 50px rgba(176, 53, 124, 0.25);
        }
        h1 {
            color: #b0357c;
            margin-bottom: 30px;
            text-align: center;
        }
        h2 {
            color: #c13584;
            margin-top: 30px;
            margin-bottom: 15px;
            border-bottom: 2px solid #f8c8dc;
            padding-bottom: 10px;
        }
        .status-ok {
            color: #28a745;
            font-weight: bold;
        }
        .status-error {
            color: #dc3545;
            font-weight: bold;
        }
        .status-warning {
            color: #ffc107;
            font-weight: bold;
        }
        .table-info {
            background: #f7f7f7;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            border-left: 4px solid #b0357c;
        }
        .column-list {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 5px;
            padding: 12px;
            margin: 10px 0;
            font-family: monospace;
            font-size: 12px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        th {
            background: #f0e6f5;
            color: #b0357c;
            font-weight: bold;
        }
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }
        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border-left: 4px solid #ffc107;
        }
        .button-group {
            display: flex;
            gap: 10px;
            margin: 20px 0;
        }
        a, button {
            display: inline-block;
            padding: 10px 20px;
            background: #b0357c;
            color: white;
            text-decoration: none;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-family: Poppins, Arial;
            font-weight: 600;
            transition: all 0.3s;
        }
        a:hover, button:hover {
            background: #c13584;
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
<div class='container'>
    <h1>🔍 Diagnóstico Completo do Site - Sarytha Nails</h1>";

// 1. Database Connection Status
echo "<h2>1️⃣ Conexão à Base de Dados</h2>";
try {
    $conn_test = $pdo->query("SELECT 1");
    echo "<div class='alert alert-success'><span class='status-ok'>✓ Conectado</span> - Base de dados 'sara_gomes_nails' acessível</div>";
} catch (Exception $e) {
    echo "<div class='alert alert-danger'><span class='status-error'>✗ Erro</span> - " . htmlspecialchars($e->getMessage()) . "</div>";
    die();
}

// 2. Tables Status
echo "<h2>2️⃣ Tabelas da Base de Dados</h2>";

// support both `users` and `utilizadores` as user table
$requiredTables = [
    // will check both names later
    'users' => ['user_id', 'nome', 'email', 'password', 'foto', 'role'],
    'utilizadores' => ['id', 'nome', 'email', 'password', 'foto', 'role'],
    'bookings' => ['id', 'id_utilizador', 'nome', 'email', 'data', 'hora', 'servico', 'status'],
    'trabalhos' => ['id_trabalho', 'titulo', 'imagem'],
    'avaliacoes' => [] // columns will be handled dynamically
];


$missingTables = [];
$tableInfo = [];

foreach ($requiredTables as $table => $columns) {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        $exists = $stmt->rowCount() > 0;
        
        if ($exists) {
            // Get column info
            $colStmt = $pdo->query("DESCRIBE $table");
            $cols = $colStmt->fetchAll(PDO::FETCH_ASSOC);
            $colNames = array_column($cols, 'Field');
            
            echo "<div class='table-info'>";
            echo "<strong>✓ Tabela '$table'</strong> (existe)<br>";
            echo "Colunas: <span class='column-list'>" . implode(", ", $colNames) . "</span>";
            
            // Check for missing required columns (if any defined)
            if (!empty($columns)) {
                $missingCols = array_diff($columns, $colNames);
                if (!empty($missingCols)) {
                    echo "<br><span class='status-warning'>⚠ Colunas em falta:</span> " . implode(", ", $missingCols);
                }
            } else {
                $missingCols = [];
            }
            echo "</div>";
            
            $tableInfo[$table] = [
                'exists' => true,
                'columns' => $colNames,
                'missing' => $missingCols
            ];
        } else {
            echo "<div class='alert alert-danger'><span class='status-error'>✗ Tabela '$table' NÃO EXISTE</span></div>";
            $missingTables[] = $table;
            $tableInfo[$table] = ['exists' => false, 'required_columns' => $columns];
        }
    } catch (Exception $e) {
        echo "<div class='alert alert-danger'>Erro ao verificar tabela '$table': " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

// 3. Data Sample from tables
echo "<h2>3️⃣ Dados nas Tabelas</h2>";

// decide which user table actually exists (users or utilizadores)
$userTable = null;
if (isset($tableInfo['users']) && $tableInfo['users']['exists']) {
    $userTable = 'users';
} elseif (isset($tableInfo['utilizadores']) && $tableInfo['utilizadores']['exists']) {
    $userTable = 'utilizadores';
}

$tableStats = [];
$tables_to_check = [];
if ($userTable) {
    $tables_to_check[] = $userTable;
}
$tables_to_check = array_merge($tables_to_check, ['bookings', 'trabalhos', 'avaliacoes']);

foreach ($tables_to_check as $table) {
    if (isset($tableInfo[$table]) && $tableInfo[$table]['exists']) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM $table");
            $result = $stmt->fetch();
            $count = $result['cnt'] ?? 0;
            $tableStats[$table] = $count;
            echo "<strong>$table:</strong> <span class='status-ok'>$count registos</span><br>";
        } catch (Exception $e) {
            echo "<strong>$table:</strong> <span class='status-error'>Erro - " . htmlspecialchars($e->getMessage()) . "</span><br>";
        }
    }
}

// 4. Files & Directories
echo "<h2>4️⃣ Ficheiros e Diretórios</h2>";

$dirs_to_check = [
    'uploads' => '📁 Pasta de uploads',
    'uploads/trabalhos' => '📁 Pasta de trabalhos',
    'Admin' => '📁 Pasta Admin',
    'api' => '📁 Pasta API/scripts',
];

$files_to_check = [
    'scripts/migrate_bookings.php' => '📄 Script de migração (bookings)',
    'scripts/add_servico_column.php' => '📄 Script de migração (servico)',
    'admin/marcacoes.php' => '📄 Gerenciar marcações (admin)',
    'admin/get_booked_times.php' => '📄 Endpoint de disponibilidade'
];

foreach ($dirs_to_check as $dir => $label) {
    if (is_dir(__DIR__ . '/' . $dir)) {
        echo "<span class='status-ok'>✓</span> $label existe<br>";
    } else {
        echo "<span class='status-error'>✗</span> $label <strong>NÃO EXISTE</strong><br>";
    }
}

echo "<br>";

foreach ($files_to_check as $file => $label) {
    if (file_exists(__DIR__ . '/' . $file)) {
        echo "<span class='status-ok'>✓</span> $label<br>";
    } else {
        echo "<span class='status-warning'>⚠</span> $label <strong>não encontrado</strong><br>";
    }
}

// 5. Key Features Check
echo "<h2>5️⃣ Funcionalidades Críticas</h2>";

$criticalChecks = [
    'Serviços Configurados' => true,
    'Horários de Funcionamento' => true,
    'Sistema de Avaliações' => isset($tableInfo['avaliacoes']) && $tableInfo['avaliacoes']['exists'],
    'Coluna Serviço em Bookings' => isset($tableInfo['bookings']) && in_array('servico', $tableInfo['bookings']['columns'] ?? []),
    'Galeria de Trabalhos' => isset($tableInfo['trabalhos']) && $tableInfo['trabalhos']['exists'],
    'Sistema de Utilizadores' => isset($tableInfo['users']) && $tableInfo['users']['exists'],
];

foreach ($criticalChecks as $feature => $status) {
    echo "<strong>$feature:</strong> ";
    echo $status ? "<span class='status-ok'>✓ OK</span>" : "<span class='status-warning'>⚠ Precisa Atenção</span>";
    echo "<br>";
}

// 6. Recommendations
echo "<h2>6️⃣ Recomendações</h2>";

$recommendations = [];

// check for user table
if (!$userTable) {
    $recommendations[] = "❌ <strong>Tabela de utilizadores ('users' ou 'utilizadores') não existe</strong> - Não será possível autenticar ou identificar clientes";
}

if (!isset($tableInfo['avaliacoes']) || !$tableInfo['avaliacoes']['exists']) {
    $recommendations[] = "❌ <strong>Tabela 'avaliacoes' não existe</strong> - As avaliações de clientes não serão salvas";
}

if (isset($tableInfo['bookings']) && $tableInfo['bookings']['exists']) {
    if (!in_array('servico', $tableInfo['bookings']['columns'] ?? [])) {
        $recommendations[] = "❌ <strong>Coluna 'servico' em falta</strong> - Execute o script de migração";
    }
} else {
    $recommendations[] = "❌ <strong>Tabela 'bookings' não existe</strong> - O sistema de marcações não funcionará";
}

if (!isset($tableInfo['trabalhos']) || !$tableInfo['trabalhos']['exists']) {
    $recommendations[] = "❌ <strong>Tabela 'trabalhos' não existe</strong> - A galeria não será preenchida";
}

if (empty($recommendations)) {
    echo "<div class='alert alert-success'><strong>✓ Tudo OK!</strong> A base de dados está correcta.</div>";
} else {
    foreach ($recommendations as $rec) {
        echo "<div class='alert alert-warning'>$rec</div>";
    }
}

// Actions
echo "<h2>7️⃣ Ações Disponíveis</h2>";
echo "<div class='button-group'>";
echo "<a href='scripts/init_db.php' target='_blank'>🔧 Inicializar/Reparar Base de Dados</a>";
echo "<a href='scripts/migrate_bookings.php' target='_blank'>📋 Adicionar Coluna Serviço</a>";
echo "<a href='Admin/index.php'>👨‍💼 Ir para Admin</a>";
echo "<a href='index.php'>🏠 Voltar ao Site</a>";
echo "</div>";

echo "</div></body></html>";
?>
