{{-- resources/views/village/portal/index.blade.php --}}
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Portal Pelanggan - PAMDes {{ $village['name'] }}</title>
  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="bg-gray-100">
  <div class="min-h-screen">
    <!-- Header -->
    <header class="bg-blue-600 text-white">
      <div class="container mx-auto px-4 py-6">
        <div class="flex items-center justify-between">
          <div>
            <h1 class="text-2xl font-bold">Portal Pelanggan</h1>
            <p class="text-blue-100">PAMDes {{ $village['name'] }}</p>
          </div>
          <a href="{{ route('village.home') }}" class="bg-blue-500 hover:bg-blue-400 px-4 py-2 rounded">
            Kembali
          </a>
        </div>
      </div>
    </header>

    <main class="container mx-auto px-4 py-8">
      <div class="max-w-md mx-auto">
        <!-- Search Form -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
          <h2 class="text-xl font-semibold mb-6 text-center">Cek Tagihan Air</h2>

          @if ($errors->any())
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded mb-4">
              {{ $errors->first() }}
            </div>
          @endif

          <form action="{{ route('village.portal.lookup') }}" method="POST" class="space-y-4">
            @csrf
            <div>
              <label for="customer_code" class="block text-sm font-medium text-gray-700 mb-2">
                Kode Pelanggan
              </label>
              <input type="text" id="customer_code" name="customer_code" placeholder="Masukkan kode pelanggan"
                value="{{ old('customer_code') }}"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                required>
            </div>

            <button type="submit"
              class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
              Cek Tagihan
            </button>
          </form>

          <div class="mt-6 text-center text-sm text-gray-600">
            <p>Tidak tahu kode pelanggan Anda?</p>
            <p>Hubungi petugas PAMDes {{ $village['name'] }}</p>
          </div>
        </div>

        <!-- Information Card -->
        <div class="bg-white rounded-lg shadow-md p-6">
          <h3 class="text-lg font-semibold mb-4">Informasi Layanan</h3>

          <div class="grid grid-cols-1 gap-4">
            <div class="bg-blue-50 p-4 rounded-lg">
              <h4 class="font-medium text-blue-800 mb-2">Jam Pelayanan</h4>
              <div class="text-blue-600 text-sm space-y-1">
                <p>Senin - Jumat: 08:00 - 16:00</p>
                <p>Sabtu: 08:00 - 12:00</p>
                <p>Minggu: Tutup</p>
              </div>
            </div>

            <div class="bg-green-50 p-4 rounded-lg">
              <h4 class="font-medium text-green-800 mb-2">Metode Pembayaran</h4>
              <div class="text-green-600 text-sm space-y-1">
                <p>• Tunai di kantor desa</p>
                <p>• Transfer bank</p>
                <p>• QRIS (jika tersedia)</p>
              </div>
            </div>

            @if ($village['phone_number'] || $village['email'])
              <div class="bg-gray-50 p-4 rounded-lg">
                <h4 class="font-medium text-gray-800 mb-2">Kontak</h4>
                <div class="text-gray-600 text-sm space-y-1">
                  @if ($village['phone_number'])
                    <p>📞 {{ $village['phone_number'] }}</p>
                  @endif
                  @if ($village['email'])
                    <p>✉️ {{ $village['email'] }}</p>
                  @endif
                </div>
              </div>
            @endif
          </div>
        </div>
      </div>
    </main>
  </div>

  <script>
    // Auto-focus on customer code input
    document.addEventListener('DOMContentLoaded', function() {
      document.getElementById('customer_code').focus();
    });

    // Format customer code input (uppercase, remove spaces)
    document.getElementById('customer_code').addEventListener('input', function(e) {
      e.target.value = e.target.value.toUpperCase().replace(/\s/g, '');
    });
  </script>
</body>

</html>
