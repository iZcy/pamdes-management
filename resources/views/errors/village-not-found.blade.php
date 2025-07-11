{{-- resources/views/errors/village-not-found.blade.php --}}
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Desa Tidak Ditemukan - PAMDes</title>
  @vite(['resources/css/app.css'])
</head>

<body class="bg-gray-100">
  <div class="min-h-screen flex items-center justify-center">
    <div class="max-w-md mx-auto text-center">
      <div class="bg-white p-8 rounded-lg shadow-md">
        <div class="text-6xl text-gray-400 mb-4">🏘️</div>
        <h1 class="text-2xl font-bold text-gray-800 mb-4">Desa Tidak Ditemukan</h1>
        <p class="text-gray-600 mb-6">
          Maaf, desa yang Anda cari tidak ditemukan atau tidak aktif dalam sistem PAMDes.
        </p>
        <div class="space-y-3">
          <a href="{{ main_pamdes_url() }}" class="block bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700">
            Kembali ke Halaman Utama
          </a>
          <p class="text-sm text-gray-500">
            Atau hubungi administrator sistem jika Anda yakin alamat ini benar.
          </p>
        </div>
      </div>
    </div>
  </div>
</body>

</html>
