{{-- resources/views/public/villages.blade.php --}}
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Daftar Desa - PAMDes</title>
  @vite(['resources/css/app.css'])
</head>

<body class="bg-gray-100">
  <div class="min-h-screen">
    <header class="bg-blue-600 text-white py-8">
      <div class="container mx-auto px-4">
        <h1 class="text-3xl font-bold">Daftar Desa PAMDes</h1>
        <nav class="mt-4">
          <a href="{{ route('home') }}" class="text-blue-200 hover:text-white">← Kembali ke Beranda</a>
        </nav>
      </div>
    </header>

    <main class="container mx-auto px-4 py-12">
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @foreach ($villages as $village)
          <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-xl font-bold mb-2">{{ $village->name }}</h3>
            <p class="text-gray-600 mb-2">{{ $village->description }}</p>
            @if ($village->phone_number)
              <p class="text-sm text-gray-500 mb-2">📞 {{ $village->phone_number }}</p>
            @endif
            @if ($village->email)
              <p class="text-sm text-gray-500 mb-4">✉️ {{ $village->email }}</p>
            @endif
            <a href="{{ village_url($village->slug) }}"
              class="inline-block bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
              Kunjungi Website
            </a>
          </div>
        @endforeach
      </div>
    </main>
  </div>
</body>

</html>
