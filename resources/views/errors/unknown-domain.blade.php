{{-- resources/views/errors/unknown-domain.blade.php --}}
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Domain Tidak Dikenal - PAMDes</title>
  @vite(['resources/css/app.css'])
</head>

<body class="bg-gray-100">
  <div class="min-h-screen flex items-center justify-center">
    <div class="max-w-md mx-auto text-center">
      <div class="bg-white p-8 rounded-lg shadow-md">
        <div class="text-6xl text-gray-400 mb-4">🌐</div>
        <h1 class="text-2xl font-bold text-gray-800 mb-4">Domain Tidak Dikenal</h1>
        <p class="text-gray-600 mb-6">
          Domain yang Anda akses tidak terdaftar dalam sistem PAMDes.
        </p>
        <div class="space-y-3">
          <a href="{{ main_pamdes_url() }}" class="block bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700">
            Kunjungi PAMDes Official
          </a>
          <p class="text-sm text-gray-500">
            Pastikan Anda mengakses alamat yang benar.
          </p>
        </div>
      </div>
    </div>
  </div>
</body>

</html>
