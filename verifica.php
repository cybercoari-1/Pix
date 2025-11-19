<?php
require 'config/config.php';

$access_token = 'token_aqui';

$ref = $_GET['ref'] ?? '';

if (!$ref) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Referência não informada']);
    exit;
}

// Consulta no banco
$stmt = $pdo->prepare("SELECT status, payment_id FROM pagamentos_pix WHERE external_reference = ?");
$stmt->execute([$ref]);
$pagamento = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pagamento) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Pagamento não encontrado']);
    exit;
}

// Se já estiver aprovado, retorna
if ($pagamento['status'] === 'approved') {
    echo json_encode(['status' => 'aprovado']);
    exit;
}

// Se ainda pendente, verifica na API do Mercado Pago
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://api.mercadopago.com/v1/payments/search?external_reference=$ref");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer $access_token",
    "Content-Type: application/json"
]);
$response = curl_exec($ch);
curl_close($ch);

$dados = json_decode($response, true);

// Verifica resultado da API
if (!empty($dados['results'])) {
    $pagamento_mp = $dados['results'][0];
    $status = $pagamento_mp['status'];
    $payment_id = $pagamento_mp['id'];

    // Atualiza banco se mudou o status
    if ($status === 'approved') {
        $stmt = $pdo->prepare("UPDATE pagamentos_pix SET status = ?, payment_id = ? WHERE external_reference = ?");
        $stmt->execute([$status, $payment_id, $ref]);
        echo json_encode(['status' => 'aprovado']);
        exit;
    }
}

// Ainda pendente
echo json_encode(['status' => 'pendente']);