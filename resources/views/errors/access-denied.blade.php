{{-- resources/views/errors/access-denied.blade.php --}}
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Akses Ditolak - PAMDes</title>
  @vite(['resources/css/app.css'])
</head>

<body class="bg-gray-100">
  <div class="min-h-screen flex items-center justify-center">
    <div class="max-w-md mx-auto text-center">
      <div class="bg-white p-8 rounded-lg shadow-md">
        <div class="text-6xl text-red-400 mb-4">🚫</div>
        <h1 class="text-2xl font-bold text-gray-800 mb-4">Akses Ditolak</h1>
        <p class="text-gray-600 mb-6">
          @if ($tenant_type === 'village_website' && $village)
            Anda tidak memiliki izin untuk mengakses administrasi PAMDes {{ $village['name'] }}.
          @else
            Anda tidak memiliki izin untuk mengakses halaman ini.
          @endif
        </p>
        <div class="space-y-3">
          <button onclick="history.back()"
            class="block w-full bg-gray-600 text-white px-6 py-2 rounded hover:bg-gray-700">
            Kembali
          </button>
          <a href="{{ url('/') }}" class="block bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700">
            Ke Halaman Utama
          </a>
        </div>
      </div>
    </div>
  </div>
</body>

</html>
