<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <title>Pagamento Aprovado</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="assets/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/css/sweetalert2.min.css" rel="stylesheet">
  <script src="assets/js/sweetalert2.min.js"></script>
  <style>
    body {
      background: #121212;
      color: #ffffff;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      height: 100vh;
      font-family: sans-serif;
      text-align: center;
    }
    h1 {
      font-size: 2rem;
      margin-bottom: 10px;
    }
    i {
      font-size: 5rem;
      color: #00ff88;
    }
    audio {
      display: none;
    }
  </style>
</head>
<body>

  <i class="bi bi-check-circle-fill"></i>
  <h1>Pagamento aprovado com sucesso!</h1>
  <p>Obrigado pela sua compra.</p>

  <audio id="som" src="assets/audio/sucesso.mp3" autoplay></audio>

  <script>
    Swal.fire({
      icon: 'success',
      title: 'Pagamento Confirmado!',
      text: 'Você será redirecionado...',
      timer: 3000,
      showConfirmButton: false
    });

    setTimeout(() => {
      window.location.href = 'index.php';
    }, 3000);
  </script>

</body>
</html>