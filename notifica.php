<?php
require 'config/config.php';

// Captura os dados recebidos
$input = file_get_contents('php://input');
file_put_contents('notifica_log.txt', $input . PHP_EOL, FILE_APPEND);
$data = json_decode($input, true);

// Verifica se é uma notificação de pagamento válida
if (!isset($data['type'], $data['data']['id']) || $data['type'] !== 'payment') {
    http_response_code(200);
    exit;
}

$payment_id = $data['data']['id'];
$access_token = 'TOKEN_AQUI';

// Consulta os detalhes do pagamento
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://api.mercadopago.com/v1/payments/$payment_id");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer $access_token"
]);
$response = curl_exec($ch);
curl_close($ch);

$pagamento = json_decode($response, true);

// Verifica se houve erro na resposta
if (!isset($pagamento['status']) || !isset($pagamento['external_reference'])) {
    file_put_contents('notifica_log.txt', "Erro na resposta: $response" . PHP_EOL, FILE_APPEND);
    http_response_code(400);
    exit;
}

// Se o pagamento foi aprovado, atualiza o banco
if ($pagamento['status'] === 'approved') {
    $external_reference = $pagamento['external_reference'];

    $stmt = $pdo->prepare("UPDATE pagamentos_pix SET status = ?, payment_id = ? WHERE external_reference = ?");
    $stmt->execute(['approved', $payment_id, $external_reference]);

    http_response_code(200);
    exit;
}

// Caso contrário, apenas retorna 200 sem atualizar
http_response_code(200);
exit;