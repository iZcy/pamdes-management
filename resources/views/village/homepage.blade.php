{{-- resources/views/village/homepage.blade.php --}}
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>PAMDes {{ $village['name'] ?? 'Desa' }}</title>
  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="bg-gray-100">
  <!-- Header -->
  <header class="bg-blue-600 text-white">
    <div class="container mx-auto px-4 py-6">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold">PAMDes {{ $village['name'] }}</h1>
          <p class="text-blue-100">Pengelolaan Air Minum Desa</p>
        </div>
        <nav class="hidden md:flex space-x-4">
          <a href="{{ route('village.home') }}" class="hover:text-blue-200">Beranda</a>
          <a href="{{ route('village.portal') }}" class="hover:text-blue-200">Cek Tagihan</a>
          <a href="{{ url('/admin') }}" class="bg-blue-500 hover:bg-blue-400 px-4 py-2 rounded">Admin</a>
        </nav>
      </div>
    </div>
  </header>

  <!-- Hero Section -->
  <section class="bg-white py-12">
    <div class="container mx-auto px-4 text-center">
      <h2 class="text-3xl font-bold text-gray-800 mb-4">
        Selamat Datang di PAMDes {{ $village['name'] }}
      </h2>
      <p class="text-gray-600 mb-8 max-w-2xl mx-auto">
        Sistem pengelolaan air minum desa yang modern dan transparan.
        Cek tagihan air Anda dengan mudah dan kelola pembayaran secara online.
      </p>
      <div class="flex flex-col sm:flex-row gap-4 justify-center">
        <a href="{{ route('village.portal') }}"
          class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition">
          Cek Tagihan Air
        </a>
        <a href="#info" class="border border-blue-600 text-blue-600 px-6 py-3 rounded-lg hover:bg-blue-50 transition">
          Informasi Layanan
        </a>
      </div>
    </div>
  </section>

  <!-- Stats Section -->
  <section class="py-12 bg-gray-50">
    <div class="container mx-auto px-4">
      <h3 class="text-2xl font-bold text-center mb-8">Statistik PAMDes</h3>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-white p-6 rounded-lg shadow text-center">
          <div class="text-3xl font-bold text-blue-600 mb-2">{{ number_format($stats['total_customers']) }}</div>
          <div class="text-gray-600">Total Pelanggan</div>
        </div>
        <div class="bg-white p-6 rounded-lg shadow text-center">
          <div class="text-3xl font-bold text-green-600 mb-2">{{ number_format($stats['active_customers']) }}</div>
          <div class="text-gray-600">Pelanggan Aktif</div>
        </div>
        <div class="bg-white p-6 rounded-lg shadow text-center">
          <div class="text-3xl font-bold text-orange-600 mb-2">Rp {{ number_format($stats['total_outstanding']) }}</div>
          <div class="text-gray-600">Total Tagihan</div>
        </div>
      </div>
    </div>
  </section>

  <!-- Information Section -->
  <section id="info" class="py-12">
    <div class="container mx-auto px-4">
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- Contact Info -->
        <div class="bg-white p-6 rounded-lg shadow">
          <h4 class="text-xl font-bold mb-4">Informasi Kontak</h4>
          <div class="space-y-3">
            @if ($village['phone_number'])
              <div class="flex items-center">
                <svg class="w-5 h-5 text-blue-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z">
                  </path>
                </svg>
                <span>{{ $village['phone_number'] }}</span>
              </div>
            @endif

            @if ($village['email'])
              <div class="flex items-center">
                <svg class="w-5 h-5 text-blue-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z">
                  </path>
                </svg>
                <span>{{ $village['email'] }}</span>
              </div>
            @endif

            @if ($village['address'])
              <div class="flex items-start">
                <svg class="w-5 h-5 text-blue-600 mr-3 mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                </svg>
                <span>{{ $village['address'] }}</span>
              </div>
            @endif
          </div>
        </div>

        <!-- Service Hours -->
        <div class="bg-white p-6 rounded-lg shadow">
          <h4 class="text-xl font-bold mb-4">Jam Pelayanan</h4>
          <div class="space-y-2">
            <div class="flex justify-between">
              <span>Senin - Jumat</span>
              <span class="font-medium">08:00 - 16:00</span>
            </div>
            <div class="flex justify-between">
              <span>Sabtu</span>
              <span class="font-medium">08:00 - 12:00</span>
            </div>
            <div class="flex justify-between">
              <span>Minggu</span>
              <span class="font-medium text-red-600">Tutup</span>
            </div>
          </div>

          <div class="mt-6 p-4 bg-blue-50 rounded-lg">
            <h5 class="font-medium text-blue-800 mb-2">Layanan Darurat</h5>
            <p class="text-blue-600 text-sm">
              Untuk gangguan air mendesak, hubungi petugas jaga 24 jam.
            </p>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Footer -->
  <footer class="bg-gray-800 text-white py-8">
    <div class="container mx-auto px-4">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
        <div>
          <h5 class="text-lg font-bold mb-4">PAMDes {{ $village['name'] }}</h5>
          <p class="text-gray-300">
            Pengelolaan Air Minum Desa yang transparan dan berkelanjutan
            untuk kesejahteraan masyarakat.
          </p>
        </div>
        <div>
          <h5 class="text-lg font-bold mb-4">Menu Cepat</h5>
          <ul class="space-y-2">
            <li><a href="{{ route('village.portal') }}" class="text-gray-300 hover:text-white">Cek Tagihan</a></li>
            <li><a href="{{ url('/admin') }}" class="text-gray-300 hover:text-white">Login Admin</a></li>
            <li><a href="#info" class="text-gray-300 hover:text-white">Informasi Kontak</a></li>
          </ul>
        </div>
      </div>
      <div class="border-t border-gray-700 mt-8 pt-8 text-center">
        <p class="text-gray-400">
          &copy; {{ date('Y') }} PAMDes {{ $village['name'] }}. Semua hak dilindungi.
        </p>
      </div>
    </div>
  </footer>
</body>

</html>
