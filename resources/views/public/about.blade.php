{{-- resources/views/public/about.blade.php --}}
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Tentang PAMDes</title>
  @vite(['resources/css/app.css'])
</head>

<body class="bg-gray-100">
  <div class="min-h-screen">
    <header class="bg-blue-600 text-white py-8">
      <div class="container mx-auto px-4">
        <h1 class="text-3xl font-bold">Tentang PAMDes</h1>
        <nav class="mt-4">
          <a href="{{ route('home') }}" class="text-blue-200 hover:text-white">← Kembali ke Beranda</a>
        </nav>
      </div>
    </header>

    <main class="container mx-auto px-4 py-12">
      <div class="max-w-4xl mx-auto">
        <div class="bg-white rounded-lg shadow-md p-8">
          <h2 class="text-2xl font-bold mb-6">Pengelolaan Air Minum Desa (PAMDes)</h2>

          <div class="prose max-w-none">
            <p class="mb-4">
              PAMDes adalah sistem pengelolaan air minum berbasis desa yang bertujuan untuk
              menyediakan akses air bersih yang berkelanjutan bagi masyarakat desa.
            </p>

            <h3 class="text-xl font-bold mt-6 mb-3">Fitur Utama</h3>
            <ul class="list-disc pl-6 mb-4">
              <li>Manajemen pelanggan air minum</li>
              <li>Sistem pembacaan meter dan penagihan</li>
              <li>Portal online untuk cek tagihan</li>
              <li>Laporan keuangan transparan</li>
              <li>Administrasi berbasis web</li>
            </ul>

            <h3 class="text-xl font-bold mt-6 mb-3">Manfaat</h3>
            <ul class="list-disc pl-6 mb-4">
              <li>Pengelolaan yang lebih transparan dan akuntabel</li>
              <li>Kemudahan akses informasi bagi pelanggan</li>
              <li>Efisiensi dalam proses administrasi</li>
              <li>Peningkatan kualitas layanan air minum desa</li>
            </ul>
          </div>
        </div>
      </div>
    </main>
  </div>
</body>

</html>
