<?php
session_start();
require __DIR__ . "/api/ligacao.php";

$trabalhos = [];

// lista de serviços e duração em minutos
$servicos = [
    'Manicure' => 60,
    'Pedicure' => 60,
    'Gel & Acrílico' => 90,
    'Design Artístico' => 120,
    'Spa de Mãos' => 45,
    'Pacotes Premium' => 120,
];

// tentar carregar do banco de dados se tabela `servicos` existir
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'servicos'");
    if ($stmt->rowCount() > 0) {
        $stmt2 = $pdo->query("SELECT nome, duracao_min FROM servicos WHERE ativo = 1");
        $rows = $stmt2->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($rows)) {
            $servicos = [];
            foreach ($rows as $r) {
                $servicos[$r['nome']] = (int)$r['duracao_min'];
            }
        }
    }
} catch (Exception $e) {
    // ignorar se não existir ou falhar
}

try {
    $stmt = $pdo->query("SELECT * FROM trabalhos ORDER BY id_trabalho DESC");
    $trabalhos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $trabalhos = [];
}

// Fetch reviews from database
$avaliacoes = [];
try {
    $colRes = $pdo->query("DESCRIBE avaliacoes");
    $cols = array_column($colRes->fetchAll(PDO::FETCH_ASSOC), 'Field');
    
    $select = [];
    // rating/classificacao
    if (in_array('classificacao', $cols)) {
        $select[] = 'a.classificacao AS rating';
    } elseif (in_array('rating', $cols)) {
        $select[] = 'a.rating';
    }
    // comentario
    if (in_array('comentario', $cols)) {
        $select[] = 'a.comentario';
    }
    // nome field
    $hasName = in_array('nome', $cols);
    if ($hasName) {
        $select[] = 'a.nome';
    }
    // user id
    $hasUserId = in_array('id_utilizador', $cols) || in_array('user_id', $cols);
    if ($hasUserId && !$hasName) {
        // join later to get name
        $select[] = 'u.nome AS nome';
    }
    // timestamp
    if (in_array('criado_em', $cols)) {
        $select[] = 'a.criado_em AS created_at';
    } elseif (in_array('created_at', $cols)) {
        $select[] = 'a.created_at';
    }
    
    // decide user table for join if needed
    $userTable = 'utilizadores';
    $userIdCol = 'id';
    if ($hasUserId && !$hasName) {
        try {
            $pdo->query("SELECT 1 FROM utilizadores LIMIT 1");
        } catch (Exception $e) {
            $userTable = 'users';
            $userIdCol = 'user_id';
        }
    }

    // build base query
    $query = "SELECT " . implode(', ', $select) . " FROM avaliacoes a";
    if ($hasUserId && !$hasName) {
        $query .= " LEFT JOIN $userTable u ON a." . (in_array('id_utilizador', $cols) ? 'id_utilizador' : 'user_id') . " = u." . $userIdCol;
    }
    if (in_array('aprovado', $cols)) {
        $query .= " WHERE a.aprovado = 1";
    }
    $orderCol = in_array('criado_em', $cols) ? 'a.criado_em' : 'a.created_at';
    $query .= " ORDER BY $orderCol DESC LIMIT 6";
    
    $stmt = $pdo->query($query);
    $avaliacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $avaliacoes = [];
}

$logado = isset($_SESSION["user_id"]);
$nomeUser = $_SESSION["nome"] ?? "";
$roleUser = $_SESSION["role"] ?? "";
$fotoUser = $_SESSION["foto"] ?? "";

/* ===============================
   DETETAR LOGO AUTOMATICAMENTE
================================ */
$logo = "";

// Procurar ficheiros tipo logo.png, logo.jpg, etc.
$candidates = glob(__DIR__ . '/uploads/logo.*');

if (!empty($candidates)) {
    // usar caminho absoluto relativo à raiz para evitar problemas de contexto
    $logo = '/uploads/' . basename($candidates[0]);
} elseif (file_exists(__DIR__ . '/uploads/default.png')) {
    $logo = '/uploads/default.png';
} // se não houver nada, ficará vazio e a <img> é omitida


/* ===============================
   FOTO UTILIZADOR
================================ */
$fotoFinal = "uploads/default.png";

// Procurar imagem padrão de perfil
$fotoPadraoPatterns = ['perfil-padrao*', 'default-profile*', 'perfil-default*', 'avatar*'];
foreach ($fotoPadraoPatterns as $pattern) {
    $candidates = glob(__DIR__ . '/uploads/' . $pattern . '.{jpg,jpeg,png,webp}', GLOB_BRACE);
    if (!empty($candidates)) {
        $fotoFinal = 'uploads/' . basename($candidates[0]);
        break;
    }
}

if (!empty($fotoUser)) {
    // Se já inclui 'uploads/' e o ficheiro existe
    if (strpos($fotoUser, 'uploads/') === 0 && file_exists(__DIR__ . '/' . $fotoUser)) {
        $fotoFinal = $fotoUser;
    // Se for só o nome do ficheiro e existir dentro de uploads/
    } elseif (file_exists(__DIR__ . '/uploads/' . $fotoUser)) {
        $fotoFinal = 'uploads/' . $fotoUser;
    // Se for um caminho relativo já a partir do root
    } elseif (file_exists(__DIR__ . '/' . $fotoUser)) {
        $fotoFinal = $fotoUser;
    }
}

/* ===============================
   DETETAR IMAGEM DO SALÃO
================================ */
$imagemSalao = "";
$searchPatterns = ['espaco*', 'salon*', 'salao*', 'background*'];
foreach ($searchPatterns as $pattern) {
    $candidates = glob(__DIR__ . '/uploads/' . $pattern . '.{jpg,jpeg,png,webp}', GLOB_BRACE);
    if (!empty($candidates)) {
        $imagemSalao = 'uploads/' . basename($candidates[0]);
        break;
    }
}
?>

<!DOCTYPE html>
<html lang="pt-PT">
<head>
<meta charset="UTF-8">
<title>Sarytha Nails - Unhas de Luxo</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<?php if (!empty($logo)): ?>
<link rel="icon" type="image/png" href="<?php echo htmlspecialchars($logo); ?>">
<?php endif; ?>

<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

/* VARIABLES */
:root {
    --primary: #b0357c;
    --primary-dark: #8b1e5f;
    --primary-light: #f8c8dc;
    --secondary: #c13584;
    --success: #28a745;
    --danger: #dc3545;
    --light: #f7f7f7;
    --gray: #eaeaea;
    --dark: #333;
    --shadow: 0 10px 30px rgba(176, 53, 124, 0.15);
    --shadow-lg: 0 20px 50px rgba(176, 53, 124, 0.25);
}

/* BODY */
body {
    font-family: 'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    background: linear-gradient(135deg, #f7d7e6, #fceef5);
    color: #333;
    line-height: 1.6;
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
    position: sticky;
    top: 0;
    z-index: 1000;
    animation: slideDown 0.5s ease;
}

@keyframes slideDown {
    from {
        transform: translateY(-100%);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
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

/* BOTÕES TOPO */
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

/* CONTAINER */
.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
}

.container-lg {
    max-width: 900px;
    margin: 0 auto;
    padding: 0 20px;
}

/* HERO SECTION */
.hero {
    background: linear-gradient(135deg, rgba(176, 53, 124, 0.85) 0%, rgba(193, 53, 132, 0.85) 100%);
    background-size: cover;
    color: white;
    padding: 120px 40px 180px;
    text-align: center;
    animation: fadeIn 0.8s ease;
    min-height: 600px;
    display: flex;
    align-items: center;
    justify-content: center;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.hero h1 {
    font-size: 3em;
    font-weight: 900;
    margin-bottom: 15px;
    text-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
}

.hero p {
    font-size: 1.3em;
    margin-bottom: 30px;
    opacity: 0.95;
}

.hero-cta {
    display: flex;
    gap: 15px;
    justify-content: center;
    flex-wrap: wrap;
}

.btn-hero {
    padding: 14px 35px;
    font-size: 1em;
    font-weight: 700;
    border: none;
    border-radius: 30px;
    cursor: pointer;
    transition: all 0.3s;
    text-decoration: none;
    display: inline-block;
}

.btn-primary {
    background: white;
    color: var(--primary);
}

.btn-primary:hover {
    transform: translateY(-4px);
    box-shadow: 0 15px 30px rgba(255, 255, 255, 0.3);
}

.btn-secondary {
    background: transparent;
    color: white;
    border: 2px solid white;
}

.btn-secondary:hover {
    background: white;
    color: var(--primary);
    transform: translateY(-4px);
}

/* SECTION */
section {
    padding: 80px 0;
}

.section-title {
    text-align: center;
    font-size: 2.5em;
    color: var(--primary);
    margin-bottom: 20px;
    font-weight: 900;
}

.section-subtitle {
    text-align: center;
    color: #666;
    font-size: 1.1em;
    margin-bottom: 50px;
}

/* SERVICES SECTION */
.services {
    background: white;
}

.services-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 30px;
}

.service-card {
    background: linear-gradient(135deg, var(--light), white);
    padding: 35px;
    border-radius: 20px;
    text-align: center;
    box-shadow: var(--shadow);
    transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
    border: 2px solid transparent;
}

.service-card:hover {
    transform: translateY(-15px);
    box-shadow: var(--shadow-lg);
    border-color: var(--primary-light);
}

.service-card i {
    font-size: 3em;
    color: var(--primary);
    margin-bottom: 15px;
    transition: 0.3s;
}

.service-card:hover i {
    transform: scale(1.2) rotate(5deg);
}

.service-card h3 {
    color: var(--primary);
    font-size: 1.3em;
    margin-bottom: 10px;
}

.service-card p {
    color: #666;
    line-height: 1.8;
}

/* BOOKING SECTION */
.booking {
    background: linear-gradient(135deg, #f7d7e6, #fceef5);
}

.booking-form {
    background: white;
    padding: 45px;
    border-radius: 25px;
    box-shadow: var(--shadow-lg);
    max-width: 600px;
    margin: 0 auto;
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

.form-group input,
.form-group select {
    width: 100%;
    padding: 14px 16px;
    border-radius: 12px;
    border: 2px solid #e0e0e0;
    font-size: 14px;
    background: white;
    font-family: 'Poppins', sans-serif;
    transition: all 0.3s;
}

.form-group input:focus,
.form-group select:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(176, 53, 124, 0.1);
}

.form-group button {
    width: 100%;
    padding: 15px;
    background: linear-gradient(90deg, var(--primary), var(--secondary));
    border: none;
    color: white;
    border-radius: 16px;
    font-size: 16px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s;
    font-family: 'Poppins', sans-serif;
}

.form-group button:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 25px rgba(176, 53, 124, 0.3);
}

.form-group button:active {
    transform: translateY(-1px);
}

/* GALLERY SECTION */
.gallery {
    background: white;
}

.galeria {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 30px;
}

.foto-card {
    background: white;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: var(--shadow);
    transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
    position: relative;
}

.foto-card:hover {
    transform: translateY(-15px);
    box-shadow: var(--shadow-lg);
}

.foto-card img {
    width: 100%;
    height: 280px;
    object-fit: cover;
    transition: 0.4s;
}

.foto-card:hover img {
    transform: scale(1.1);
    filter: brightness(1.1);
}

.foto-card .titulo {
    padding: 20px;
    text-align: center;
    font-weight: 700;
    font-size: 15px;
    color: var(--primary);
    background: linear-gradient(135deg, var(--light), white);
}

/* TESTIMONIALS SECTION */
.testimonials {
    background: linear-gradient(135deg, #f7d7e6, #fceef5);
}

.testimonials-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 30px;
}

.testimonial-card {
    background: white;
    padding: 35px;
    border-radius: 20px;
    box-shadow: var(--shadow);
    border-left: 5px solid var(--primary);
    transition: all 0.3s;
}

.testimonial-card:hover {
    transform: translateX(5px);
    box-shadow: var(--shadow-lg);
}

.stars {
    color: #ffc107;
    margin-bottom: 15px;
    font-size: 1.2em;
}

.testimonial-text {
    color: #666;
    margin-bottom: 15px;
    font-style: italic;
    line-height: 1.8;
}

.testimonial-author {
    font-weight: 700;
    color: var(--primary);
}

/* FOOTER */
.footer {
    background: linear-gradient(90deg, #1a1a1a, #2a2a2a);
    color: white;
    padding: 60px 0 20px;
}

.footer-content {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 40px;
    margin-bottom: 40px;
}

.footer-section h3 {
    color: var(--primary-light);
    margin-bottom: 20px;
    font-size: 1.2em;
}

.footer-section ul {
    list-style: none;
}

.footer-section ul li {
    margin-bottom: 12px;
}

.footer-section a {
    color: #ddd;
    text-decoration: none;
    transition: 0.3s;
}

.footer-section a:hover {
    color: var(--primary-light);
    padding-left: 5px;
}

.social-links {
    display: flex;
    gap: 15px;
    margin-top: 20px;
}

.social-links a {
    width: 45px;
    height: 45px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--primary);
    border-radius: 50%;
    transition: all 0.3s;
}

.social-links a:hover {
    background: var(--secondary);
    transform: translateY(-5px);
}

.footer-bottom {
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    padding-top: 30px;
    text-align: center;
    color: #999;
}

/* SCROLL TO TOP */
.scroll-top {
    position: fixed;
    bottom: 30px;
    right: 30px;
    width: 50px;
    height: 50px;
    background: linear-gradient(90deg, var(--primary), var(--secondary));
    color: white;
    border: none;
    border-radius: 50%;
    cursor: pointer;
    font-size: 20px;
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 999;
    box-shadow: var(--shadow-lg);
    transition: all 0.3s;
}

.scroll-top:hover {
    transform: translateY(-5px);
}

.scroll-top.show {
    display: flex;
}

/* ALERTS */
.alert {
    padding: 15px 20px;
    border-radius: 12px;
    margin-bottom: 20px;
    border-left: 5px solid;
    animation: slideInDown 0.4s ease;
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

/* SLOT PICKER STYLES */
.slot {
    display: inline-block;
    padding: 6px 10px;
    margin: 4px 2px;
    border-radius: 4px;
    font-size: 0.9em;
    cursor: pointer;
}
.slot.available { background: #28a745; color: #fff; }
.slot.unavailable { background: #dc3545; color: #fff; cursor: not-allowed; }
.slot.selected { border: 2px solid #000; }

/* RESPONSIVE */
@media (max-width: 768px) {
    .topbar {
        padding: 0 20px;
    }

    .topbar .logo {
        font-size: 18px;
    }

    .topbar .logo img {
        width: 40px;
        height: 40px;
    }

    .hero {
        padding: 80px 20px;
        min-height: auto;
        background-attachment: scroll;
    }

    .hero h1 {
        font-size: 2em;
    }

    .hero p {
        font-size: 1.1em;
    }

    .hero > .container > div {
        grid-template-columns: 1fr !important;
        gap: 20px;
    }

    .section-title {
        font-size: 2em;
    }

    section {
        padding: 60px 0;
    }

    .services-grid {
        grid-template-columns: 1fr;
    }

    .booking-form {
        padding: 30px;
    }

    .galeria {
        grid-template-columns: 1fr;
    }

    .topbar .right {
        gap: 8px;
    }

    .btn-top {
        padding: 8px 12px;
        font-size: 12px;
    }
}

</style>
</head>

<body>

<!-- TOPBAR -->
<div class="topbar">
    <a class="logo" href="index.php">
        <?php if (!empty($logo)): ?>
            <img src="<?php echo htmlspecialchars($logo); ?>" alt="Logo" onerror="this.src='/uploads/default.png'">
        <?php endif; ?>
        <span>Sarytha Nails</span>
    </a>

    <div class="right">
        <?php if (!$logado): ?>
            <a class="btn-top" href="login.php"><i class="fas fa-sign-in-alt"></i> Login</a>
            <a class="btn-top" href="registar.php"><i class="fas fa-user-plus"></i> Registar</a>
        <?php else: ?>
            <a class="btn-top" href="minhas_marcacoes.php" style="background: linear-gradient(90deg, #b0357c, #c13584); border-color: #b0357c;"><i class="fas fa-calendar-check"></i> Minhas Marcações</a>
            <a class="perfil-box" href="editar_perfil.php">
                <img src="<?php echo htmlspecialchars($fotoFinal); ?>" alt="Perfil" onerror="this.src='uploads/default.png'">
                <span><?php echo htmlspecialchars($nomeUser); ?></span>
            </a>
            <?php if (strtolower($roleUser) === "admin"): ?>
                <a class="btn-top" href="Admin/index.php"><i class="fas fa-crown"></i> Admin</a>
            <?php endif; ?>
            <a class="btn-top" href="logout.php"><i class="fas fa-sign-out-alt"></i> Sair</a>
        <?php endif; ?>
    </div>
</div>

<!-- HERO SECTION -->
<div class="hero" style="<?php if (!empty($imagemSalao)): ?>background-image: linear-gradient(135deg, rgba(176, 53, 124, 0.85) 0%, rgba(193, 53, 132, 0.85) 100%), url('<?php echo htmlspecialchars($imagemSalao); ?>'); background-attachment: fixed; background-size: cover; background-position: center;<?php endif; ?>">
    <div class="container">
        <div style="text-align: center;">
            <h1>✨ Bem-vindo à Sarytha Nails</h1>
            <p>Unhas de luxo com o melhor atendimento personalizado</p>
            <div class="hero-cta">
                <?php if (!$logado): ?>
                    <a href="#booking" class="btn-hero btn-primary">Agendar Agora</a>
                    <a href="registar.php" class="btn-hero btn-secondary">Criar Conta</a>
                <?php else: ?>
                    <a href="#booking" class="btn-hero btn-primary">Agendar Agora</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- SERVICES SECTION -->
<section class="services">
    <div class="container">
        <h2 class="section-title">Nossos Serviços</h2>
        <p class="section-subtitle">Descubra a variedade de tratamentos que oferecemos</p>
        
        <div class="services-grid">
            <div class="service-card">
                <i class="fas fa-sparkles"></i>
                <h3>Manicure</h3>
                <p>Cuidados completos com as mãos e unhas, com acabamento perfeito e duradouro.</p>
            </div>
            <div class="service-card">
                <i class="fas fa-heart"></i>
                <h3>Pedicure</h3>
                <p>Relaxamento dos pés com tratamentos de qualidade superior e materiais premium.</p>
            </div>
            <div class="service-card">
                <i class="fas fa-gem"></i>
                <h3>Gel & Acrílico</h3>
                <p>Unhas gel e acrílico com designs exclusivos e cores vibrantes.</p>
            </div>
            <div class="service-card">
                <i class="fas fa-palette"></i>
                <h3>Design Artístico</h3>
                <p>Criações personalizadas e designs únicos para qualquer ocasião especial.</p>
            </div>
            <div class="service-card">
                <i class="fas fa-face-smile"></i>
                <h3>Spa de Mãos</h3>
                <p>Tratamento relaxante com hidratação profunda e massagem terapêutica.</p>
            </div>
            <div class="service-card">
                <i class="fas fa-crown"></i>
                <h3>Pacotes Premium</h3>
                <p>Combinações exclusivas de serviços com desconto especial para clientes.</p>
            </div>
        </div>
    </div>
</section>

<!-- BOOKING SECTION -->
<section class="booking" id="booking">
    <div class="container-lg">
        <h2 class="section-title">Agende a Sua Marcação</h2>
        <p class="section-subtitle">Reserve o seu horário em poucos cliques</p>

        <?php if (isset($_GET['marcacao_sucesso'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> Marcação realizada com sucesso! Entraremos em contacto para confirmar.
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['marcacao_erro'])): ?>
            <?php
            $msg = "Erro ao processar a marcação. Por favor, tente novamente.";
            switch ($_GET['marcacao_erro']) {
                case 'empty':
                    $msg = "Preencha data e hora para a marcação.";
                    break;
                case 'format':
                    $msg = "Formato de data/hora inválido.";
                    break;
                case 'past':
                    $msg = "Não é possível agendar para data/hora já passada.";
                    break;
                case 'service':
                    $msg = "Por favor selecione um serviço válido.";
                    break;
                case 'horario':
                    $msg = "Fora do horário de funcionamento. Segunda‑sexta 18‑21, sábado 9‑12 e 13‑16.";
                    break;
                case 'ocupada':
                    $msg = "O horário não está disponível — existe outra marcação que ocupa a hora solicitada.";
                    break;
                case 'server':
                default:
                    $msg = "Erro ao processar a marcação. Por favor, tente novamente.";
                    break;
            }
            ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo $msg; ?>
            </div>
        <?php endif; ?>

        <div class="booking-form">
            <form action="marcacoes.php" method="POST">
                <?php if (!$logado): ?>
                    <div class="form-group">
                        <label><i class="fas fa-user"></i> Nome</label>
                        <input type="text" name="nome" required placeholder="O seu nome completo">
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-envelope"></i> Email</label>
                        <input type="email" name="email" required placeholder="o.seu@email.com">
                    </div>
                <?php endif; ?>

                <div class="form-group">
                    <label><i class="fas fa-list"></i> Serviço</label>
                    <select name="servico" required>
                        <option value="">Escolha um serviço</option>
                        <?php foreach ($servicos as $s => $dur): ?>
                            <option value="<?= htmlspecialchars($s) ?>"><?= htmlspecialchars($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-calendar-alt"></i> Data da Marcação</label>
                    <input type="date" name="data" required>
                    <small class="form-text">Seg-sex 18‑21, sábado 9‑12/13‑16</small>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-clock"></i> Hora Preferida</label>
                    <input type="time" name="hora" required id="horaInput">
                </div>

                <!-- slot picker will be filled dynamically -->
                <div id="slotPicker" style="margin-top:15px;"></div>

                <div class="form-group">
                    <button type="submit" class="btn-submit"><i class="fas fa-check"></i> Confirmar Marcação</button>
                </div>
            </form>
        </div>
    </div>
</section>

<!-- GALLERY SECTION -->
<section class="gallery">
    <div class="container">
        <h2 class="section-title">Trabalhos Realizados</h2>
        <p class="section-subtitle">Veja a qualidade do nosso trabalho em cada projeto</p>

        <?php if (!empty($trabalhos)): ?>
            <div class="galeria">
                <?php foreach ($trabalhos as $t): ?>
                    <div class="foto-card">
                        <a href="#" class="open-lightbox" data-src="uploads/trabalhos/<?php echo htmlspecialchars($t["imagem"]); ?>">
                            <img src="uploads/trabalhos/<?php echo htmlspecialchars($t["imagem"]); ?>" alt="<?php echo htmlspecialchars($t["titulo"]); ?>" onerror="this.src='uploads/default.png'">
                            <div class="overlay"><?php echo htmlspecialchars($t["titulo"]); ?></div>
                        </a>
                        <div class="titulo"><?php echo htmlspecialchars($t["titulo"]); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p style="text-align:center; color:#999; padding:60px 0;">Galeria em breve...</p>
        <?php endif; ?>
    </div>
</section>

<!-- TESTIMONIALS SECTION -->
<section class="testimonials">
    <div class="container">
        <h2 class="section-title">Avaliações de Clientes</h2>
        <p class="section-subtitle">Veja o que nossos clientes dizem sobre os nossos serviços</p>

        <?php if (!empty($avaliacoes)): ?>
            <div class="testimonials-grid">
                <?php foreach ($avaliacoes as $av): ?>
                    <div class="testimonial-card">
                        <div class="stars">
                            <?php echo str_repeat('★', max(0, min(5, (int)$av['rating']))); ?>
                            <?php echo str_repeat('☆', 5 - max(0, min(5, (int)$av['rating']))); ?>
                        </div>
                        <div class="testimonial-text"><?php echo htmlspecialchars($av['comentario']); ?></div>
                        <div class="testimonial-author">
                            <strong><?php echo htmlspecialchars($av['nome']); ?></strong> 
                            <br><small style="color: #999;">
                                <?php echo date('d/m/Y', strtotime($av['created_at'])); ?>
                            </small>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p style="text-align:center; color:#999; padding:60px 0;">
                Ainda não existem avaliações. Seja o primeiro a avaliar!
            </p>
        <?php endif; ?>
    </div>
</section>

<!-- REVIEW SUBMISSION SECTION -->
<section class="booking" id="avaliar" style="background: white;">
    <div class="container-lg">
        <h2 class="section-title">Deixe a sua avaliação</h2>
        <p class="section-subtitle">Partilhe a sua experiência com a Sarytha Nails</p>

        <div class="booking-form">
            <form id="formAvaliacao" action="api/salvar_avaliacao.php" method="POST">
                <?php if (!$logado): ?>
                    <div class="form-group">
                        <label><i class="fas fa-user"></i> O seu nome</label>
                        <input type="text" name="nome" placeholder="Como deseja ser identificado?" required>
                    </div>
                <?php endif; ?>

                <div class="form-group">
                    <label><i class="fas fa-scissors"></i> Serviço</label>
                    <select name="id_servico" style="width: 100%; padding: 12px 14px; border: 2px solid #e0e0e0; border-radius: 12px; font-family: 'Poppins', sans-serif; font-size: 14px; background: white; cursor: pointer;">
                        <option value="">-- Selecione o serviço que realizou --</option>
                        <?php foreach ($servicos as $s => $d): ?>
                            <option value="<?php echo htmlspecialchars($s); ?>"><?php echo htmlspecialchars($s); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-star"></i> Avaliação (Estrelas)</label>
                    <div style="display: flex; gap: 10px; font-size: 2em; cursor: pointer;">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <label style="cursor: pointer;">
                                <input type="radio" name="rating" value="<?php echo $i; ?>" style="display: none;" onchange="updateStars(this.value)">
                                <span id="star-<?php echo $i; ?>" style="color: #ddd; transition: 0.2s;">★</span>
                            </label>
                        <?php endfor; ?>
                    </div>
                    <input type="hidden" name="rating" id="ratingValue" value="0">
                </div>

                <div class="form-group">
                    <label><i class="fas fa-comments"></i> O seu comentário</label>
                    <textarea name="comentario" placeholder="Partilhe a sua experiência... (máximo 1000 caracteres)" required style="width: 100%; min-height: 120px; padding: 14px 16px; border: 2px solid #e0e0e0; border-radius: 12px; font-family: 'Poppins', sans-serif; font-size: 14px; resize: vertical;" maxlength="1000"></textarea>
                </div>

                <div class="form-group">
                    <button type="submit" class="btn-submit"><i class="fas fa-paper-plane"></i> Enviar Avaliação</button>
                </div>

                <div id="mensagem-avaliacao"></div>
            </form>
        </div>
    </div>
</section>

<!-- FOOTER -->
<footer class="footer">
    <div class="container">
        <div class="footer-content">
            <div class="footer-section">
                <h3>Sarytha Nails</h3>
                <p>Transformando seus nails em obras de arte.</p>
                <div class="social-links">
                    <a href="https://www.instagram.com/sarytah_nails" target="_blank" title="Instagram">
                        <i class="fab fa-instagram"></i>
                    </a>
                    <a href="https://www.facebook.com/sarytahnails" target="_blank" title="Facebook">
                        <i class="fab fa-facebook"></i>
                    </a>
                    <a href="https://wa.me/917469865" target="_blank" title="WhatsApp">
                        <i class="fab fa-whatsapp"></i>
                    </a>
                </div>
            </div>

            <div class="footer-section">
                <h3>Navegação Rápida</h3>
                <ul>
                    <li><a href="#booking">Agendar</a></li>
                    <li><a href="./#gallery">Galeria</a></li>
                    <li><a href="./#services">Serviços</a></li>
                    <li><a href="editar_perfil.php">O Meu Perfil</a></li>
                </ul>
            </div>

            <div class="footer-section">
                <h3>Contactar</h3>
                <ul>
                    <li>
                        <i class="fas fa-phone"></i> 
                        <a href="tel:917469865">917 469 865</a>
                    </li>
                    <li>
                        <i class="fas fa-envelope"></i> 
                        <a href="mailto:info@sarytahnails.com">info@sarytahnails.com</a>
                    </li>
                    <li>
                        <i class="fas fa-map-marker-alt"></i> 
                        R. da Ameixoeira, barcelos, Portugal
                    </li>
                </ul>
            </div>

            <div class="footer-section">
                <h3>Horário</h3>
                <ul>
                    <li>Seg - Sex: 18h - 21h</li>
                    <li>Sábado: 09h - 12h ;13h - 16h</li>
                    <li>Domingo: Fechado</li>
                </ul>
            </div>
        </div>

        <div class="footer-bottom">
            <p>&copy; <?php echo date("Y"); ?> Sarytha Nails. Todos os direitos reservados. <a href="#" style="color:var(--primary-light);text-decoration:none;">Política de Privacidade</a></p>
        </div>
    </div>
</footer>

<!-- SCROLL TO TOP BUTTON -->
<button class="scroll-top" id="scrollTopBtn" onclick="scrollToTop()">
    <i class="fas fa-arrow-up"></i>
</button>

<script>
// Scroll to Top Button
const scrollTopBtn = document.getElementById('scrollTopBtn');

window.addEventListener('scroll', () => {
    if (window.scrollY > 300) {
        scrollTopBtn.classList.add('show');
    } else {
        scrollTopBtn.classList.remove('show');
    }
});

function scrollToTop() {
    window.scrollTo({
        top: 0,
        behavior: 'smooth'
    });
}

// Smooth Scroll for Anchor Links
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        const href = this.getAttribute('href');
        if (href !== '#' && document.querySelector(href)) {
            e.preventDefault();
            document.querySelector(href).scrollIntoView({
                behavior: 'smooth'
            });
        }
    });
});

// Show Phone Number on Click
function showPhone() {
    const phone = document.getElementById('phone-number');
    if (phone) {
        phone.style.display = phone.style.display === 'none' ? 'inline' : 'none';
    }
}

// Review Form - Star Rating
function updateStars(value) {
    document.getElementById('ratingValue').value = value;
    for (let i = 1; i <= 5; i++) {
        const star = document.getElementById('star-' + i);
        if (star) {
            if (i <= value) {
                star.style.color = '#ffc107';
            } else {
                star.style.color = '#ddd';
            }
        }
    }
}

// Review Form - Submission
document.addEventListener('DOMContentLoaded', function() {
    const formAvaliacao = document.getElementById('formAvaliacao');
    if (formAvaliacao) {
        formAvaliacao.addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            const mensagem = document.getElementById('mensagem-avaliacao');

            // Validate rating
            if (!formData.get('rating') || formData.get('rating') == 0) {
                mensagem.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> Seleccione uma avaliação entre 1 e 5 estrelas</div>';
                return;
            }

            fetch('api/salvar_avaliacao.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    mensagem.innerHTML = '<div class="alert alert-success"><i class="fas fa-check-circle"></i> ' + data.message + '</div>';
                    formAvaliacao.reset();
                    updateStars(0);
                    setTimeout(() => {
                        location.reload();
                    }, 2000);
                } else {
                    mensagem.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> ' + data.message + '</div>';
                }
            })
            .catch(error => {
                mensagem.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> Erro ao enviar avaliação. Tente novamente.</div>';
                console.error('Error:', error);
            });
        });
    }
});

// --- SLOT PICKER LOGIC --------------------------------------------------
(function() {
    const dateInput = document.querySelector('input[name="data"]');
    const slotPicker = document.getElementById('slotPicker');
    const timeInput = document.getElementById('horaInput');

    if (dateInput) {
        dateInput.addEventListener('change', loadSlots);
    }

    function loadSlots() {
        const date = this.value;
        slotPicker.innerHTML = '';
        if (!date) return;
        fetch('api/horarios.php?date=' + encodeURIComponent(date))
            .then(r => r.json())
            .then(data => {
                if (data.error) {
                    slotPicker.textContent = 'Erro ao carregar horários.';
                    return;
                }
                const periods = data.periods || [];
                const bookings = data.bookings || [];
                const allSlots = [];
                // generate slots from 06:00 to 22:00 so user sees out-of-hours times too
                const minDay = toMinutes('06:00');
                const maxDay = toMinutes('22:00');
                for (let m = minDay; m < maxDay; m += 30) {
                    allSlots.push(minutesToTime(m));
                }
                allSlots.forEach(ts => {
                    const tmin = toMinutes(ts);
                    // check if slot falls inside any business period
                    let inPeriod = periods.some(p => {
                        const ps = toMinutes(p.start);
                        const pe = toMinutes(p.end);
                        return tmin >= ps && tmin < pe;
                    });
                    let avail = !!inPeriod;
                    if (avail) {
                        bookings.forEach(b => {
                            const bstart = toMinutes(b.hora);
                            const bend = bstart + (b.dur || 0);
                            if (tmin >= bstart && tmin < bend) {
                                avail = false;
                            }
                        });
                    }
                    const span = document.createElement('span');
                    span.textContent = ts;
                    span.className = 'slot ' + (avail ? 'available' : 'unavailable');
                    if (avail) {
                        span.addEventListener('click', () => {
                            document.querySelectorAll('.slot').forEach(s=>s.classList.remove('selected'));
                            span.classList.add('selected');
                            timeInput.value = ts;
                        });
                    }
                    slotPicker.appendChild(span);
                });
                if (allSlots.length === 0) {
                    slotPicker.textContent = 'Não há horários de funcionamento para esse dia.';
                }
            })
            .catch(err => {
                console.error(err);
                slotPicker.textContent = 'Erro ao consultar horários.';
            });
    }

    function toMinutes(hhmm) {
        const [h,m] = hhmm.split(':').map(Number);
        return h*60 + m;
    }
    function minutesToTime(m) {
        const h = Math.floor(m/60);
        const mi = m%60;
        return String(h).padStart(2,'0') + ':' + String(mi).padStart(2,'0');
    }

    window.addEventListener('load', () => {
        if (dateInput && dateInput.value) {
            loadSlots.call(dateInput);
        }
    });

    if (timeInput) {
        timeInput.addEventListener('change', () => {
            document.querySelectorAll('.slot').forEach(s=>{
                s.classList.toggle('selected', s.textContent === timeInput.value);
            });
        });
    }
})();

</script>

</body>
</html>