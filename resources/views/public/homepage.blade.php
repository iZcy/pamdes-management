{{-- resources/views/public/homepage.blade.php --}}
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>PAMDes - Pengelolaan Air Minum Desa</title>
  @vite(['resources/css/app.css'])
</head>

<body class="bg-gray-100">
  <div class="min-h-screen">
    <header class="bg-blue-600 text-white py-8">
      <div class="container mx-auto px-4 text-center">
        <h1 class="text-4xl font-bold mb-4">PAMDes</h1>
        <p class="text-xl">Sistem Pengelolaan Air Minum Desa</p>
      </div>
    </header>

    <main class="container mx-auto px-4 py-12">
      <div class="text-center mb-12">
        <h2 class="text-3xl font-bold text-gray-800 mb-4">Desa PAMDes Terdaftar</h2>
        <p class="text-gray-600">Pilih desa untuk mengakses layanan PAMDes</p>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse($villages as $village)
          <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-xl font-bold mb-2">{{ $village->name }}</h3>
            <p class="text-gray-600 mb-4">{{ $village->description ?? 'PAMDes ' . $village->name }}</p>
            <a href="{{ village_url($village->slug) }}"
              class="inline-block bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
              Kunjungi Website
            </a>
          </div>
        @empty
          <div class="col-span-full text-center text-gray-500">
            <p>Belum ada desa yang terdaftar.</p>
          </div>
        @endforelse
      </div>
    </main>
  </div>
</body>

</html>
