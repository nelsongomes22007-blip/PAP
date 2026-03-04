<?php
session_start();
require __DIR__ . "/ligacao.php";

header('Content-Type: application/json; charset=utf-8');

$response = [
    'success' => false,
    'message' => ''
];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método não permitido');
    }

    // Get POST data
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $rating = (int)($_POST['rating'] ?? 0);
    $comentario = trim($_POST['comentario'] ?? '');
    $id_servico = trim($_POST['id_servico'] ?? '');
    $user_id = $_SESSION['user_id'] ?? null;

    // Validation
    if (empty($comentario)) {
        throw new Exception('Comentário é obrigatório');
    }

    if ($rating < 1 || $rating > 5) {
        throw new Exception('Avaliação deve ser entre 1 e 5 estrelas');
    }

    // If not logged in, name is required
    if (!$user_id && empty($nome)) {
        throw new Exception('Nome é obrigatório para avaliações anónimas');
    }

    // Limit comment length
    if (strlen($comentario) > 1000) {
        throw new Exception('Comentário muito longo (máximo 1000 caracteres)');
    }

    // Use session name if logged in, otherwise use provided name
    $nome_final = $user_id ? ($_SESSION['nome'] ?? $nome) : $nome;

    // Check if avaliacoes table exists and get its structure
    try {
        $colResult = $pdo->query("DESCRIBE avaliacoes");
        $cols = array_column($colResult->fetchAll(PDO::FETCH_ASSOC), 'Field');
    } catch (PDOException $e) {
        throw new Exception('Sistema de avaliações indisponível. Por favor, contacte o administrador.');
    }

    // Determine which column to use for rating/classificacao
    if (in_array('rating', $cols)) {
        $ratingCol = 'rating';
    } elseif (in_array('classificacao', $cols)) {
        $ratingCol = 'classificacao';
    } else {
        throw new Exception('Campo de avaliação não encontrado na tabela.');
    }

    // Build base parameters
    $insertColumns = [];
    $insertValues = [];
    $placeholders = [];

    // rating/classificacao always present
    $insertColumns[] = $ratingCol;
    $insertValues[] = $rating;
    $placeholders[] = '?';

    // comentario exists or equivalent
    if (in_array('comentario', $cols)) {
        $insertColumns[] = 'comentario';
        $insertValues[] = $comentario;
        $placeholders[] = '?';
    }

    // user id column may be named id_utilizador or user_id
    if (in_array('id_utilizador', $cols)) {
        $insertColumns[] = 'id_utilizador';
        $insertValues[] = $user_id;
        $placeholders[] = '?';
    } elseif (in_array('user_id', $cols)) {
        $insertColumns[] = 'user_id';
        $insertValues[] = $user_id;
        $placeholders[] = '?';
    }

    // name field (might not exist)
    if (in_array('nome', $cols)) {
        $insertColumns[] = 'nome';
        $insertValues[] = $nome_final;
        $placeholders[] = '?';
    }

    // service field (might be id_servico or just store the service name)
    if (in_array('id_servico', $cols) && !empty($id_servico)) {
        $insertColumns[] = 'id_servico';
        $insertValues[] = $id_servico;
        $placeholders[] = '?';
    }

    // approved flag default 1 so reviews appear immediately
    if (in_array('aprovado', $cols)) {
        $insertColumns[] = 'aprovado';
        $insertValues[] = 1; // auto-approve
        $placeholders[] = '?';
    }

    // build query
    $columnList = implode(', ', $insertColumns);
    $placeholderList = implode(', ', $placeholders);
    $query = "INSERT INTO avaliacoes ($columnList) VALUES ($placeholderList)";
    $stmt = $pdo->prepare($query);
    $stmt->execute($insertValues);

    $response['success'] = true;
    $response['message'] = 'Avaliação enviada com sucesso! Obrigado pelo seu feedback.';

} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = htmlspecialchars($e->getMessage());
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>
