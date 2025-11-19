<?php
session_start();
require 'config/conexao.php';
ini_set('display_errors', 1);
error_reporting(E_ALL);

$access_token = 'TOKEN_AQUI';

$valor = 0.01;
$produto = "CYBER ATLAS";
$external_reference = uniqid("ref_");

// Expiração no formato exigido pelo Mercado Pago
$expiracao = date('Y-m-d\TH:i:s', time() + 600) . '.000-04:00'; // 10 minutos à frente
$expiracao_js = date('Y-m-d H:i:s', time() + 600); // Para contagem no JavaScript

// Inserir registro no banco (QR ainda será atualizado depois)
try {
    $stmt = $pdo->prepare("INSERT INTO pagamentos_pix (produto, valor, external_reference, qr_code, qr_code_base64) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$produto, $valor, $external_reference, null, null]);
} catch (PDOException $e) {
    die("Erro ao inserir no banco: " . $e->getMessage());
}

// Requisição para Mercado Pago
$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL => "https://api.mercadopago.com/v1/payments",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer $access_token",
        "Content-Type: application/json",
        "X-Idempotency-Key: $external_reference"
    ],
    CURLOPT_POSTFIELDS => json_encode([
        "transaction_amount" => floatval($valor),
        "description" => $produto,
        "payment_method_id" => "pix",
        "external_reference" => $external_reference,
        "payer" => ["email" => "cybercoari@gmail.com"],
        "notification_url" => "https://cybercoari.com.br/notifica.php",
        "date_of_expiration" => $expiracao
    ])
]);

$response = curl_exec($curl);
curl_close($curl);
$resp = json_decode($response, true);

// Erro da API
if (isset($resp['message'])) {
    echo "<pre>Erro Mercado Pago:\n";
    print_r($resp);
    echo "</pre>";
    exit;
}

// Pega QR gerado
$qr_base64 = $resp['point_of_interaction']['transaction_data']['qr_code_base64'];
$qr_copia = $resp['point_of_interaction']['transaction_data']['qr_code'];

// Atualiza o registro com os QR codes
$stmt = $pdo->prepare("UPDATE pagamentos_pix SET qr_code = ?, qr_code_base64 = ? WHERE external_reference = ?");
$stmt->execute([$qr_copia, $qr_base64, $external_reference]);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <title>Pagamento PIX</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="assets/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <link href="assets/css/sweetalert2.min.css" rel="stylesheet">
  <script src="assets/js/sweetalert2.min.js"></script>

  <style>
    body { background: #f1f1f1; padding: 30px; text-align: center; }
    .qrcode { max-width: 200px; margin: 20px auto; }
    .info { text-align: justify; max-width: 500px; margin: 20px auto; }
    h2 { text-align: center; }
  </style>
</head>
<body>

  <h2><?= $produto ?> - <span class="text-success">R$ <?= number_format($valor, 2, ',', '.') ?></span></h2>

  <img src="data:image/png;base64,<?= $qr_base64 ?>" class="qrcode rounded shadow" id="qr-img"><br>

  <input type="text" id="pixcop" class="form-control text-center" readonly value="<?= $qr_copia ?>">
  <button onclick="copiarPIX()" class="btn btn-primary mt-3">
    <i class="bi bi-clipboard"></i> Copiar código PIX
  </button>

  <div id="tempo-restante" class="mt-3 fw-bold text-danger fs-5"></div>

  <div class="info">
    <h2><i class="bi bi-info-circle-fill me-2"></i>Como pagar com Pix:</h2>
    <ol>
      <li><i class="bi bi-phone-fill me-2 text-primary"></i>Abra o app do seu banco.</li>
      <li><i class="bi bi-cash-coin me-2 text-success"></i>Escolha <strong>Pix</strong> e <strong>Pagar com QR Code</strong>.</li>
      <li><i class="bi bi-camera-fill me-2 text-warning"></i>Aponte a câmera para o QR Code acima.</li>
      <li><i class="bi bi-clipboard-check me-2 text-info"></i>Ou cole o código Pix Copia e Cola.</li>
      <li><i class="bi bi-check-circle-fill me-2 text-success"></i>Confirme os dados e finalize o pagamento.</li>
    </ol>
  </div>

  <audio id="som" src="assets/audio/sucesso.mp3" preload="auto"></audio>

  <script>
    const expiracao = new Date("<?= $expiracao_js ?>");

    function copiarPIX() {
      const campo = document.getElementById("pixcop");
      campo.select();
      campo.setSelectionRange(0, 99999);
      document.execCommand("copy");
      document.getElementById("som").play();
      Swal.fire('PIX copiado!', 'Cole no app do banco para pagar.', 'success');
    }

    function atualizarTempo() {
      const agora = new Date();
      const restante = expiracao - agora;
      const tempoEl = document.getElementById("tempo-restante");

      if (restante > 0) {
        const min = Math.floor(restante / 60000);
        const seg = Math.floor((restante % 60000) / 1000);
        tempoEl.innerText = `⏳ Tempo restante: ${min}m ${seg}s`;
      } else {
        tempoEl.innerText = "⛔ Tempo expirado.";
        clearInterval(verificadorTempo);
        clearInterval(verificadorPagamento);
        Swal.fire({
          icon: 'warning',
          title: 'Cobrança expirada!',
          text: 'Este código PIX não é mais válido. Recarregue para gerar outro.',
          confirmButtonText: 'OK'
        });
      }
    }

    const verificadorTempo = setInterval(atualizarTempo, 1000);
    atualizarTempo();

    const verificadorPagamento = setInterval(() => {
      fetch("verifica.php?ref=<?= $external_reference ?>")
        .then(res => res.json())
        .then(data => {
          if (data.status === 'aprovado') {
            clearInterval(verificadorTempo);
            clearInterval(verificadorPagamento);
            document.getElementById("som").play();
            Swal.fire({
              icon: 'success',
              title: 'Pagamento aprovado!',
              text: 'Redirecionando...',
              timer: 2000,
              showConfirmButton: false
            });
            setTimeout(() => {
              window.location.href = 'sucesso.php';
            }, 2000);
          }
        });
    }, 3000);
  </script>

</body>
</html>