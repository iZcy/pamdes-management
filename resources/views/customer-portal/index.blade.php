{{-- resources/views/customer-portal/index.blade.php --}}
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Portal Pelanggan - PAMDes {{ $village['name'] ?? 'Desa' }}</title>
  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="bg-gray-100">
  <div class="min-h-screen">
    <header class="bg-blue-600 text-white">
      <div class="container mx-auto px-4 py-6">
        <h1 class="text-2xl font-bold">Portal Pelanggan PAMDes</h1>
        <p class="text-blue-100">{{ $village['name'] ?? 'Sistem Pengelolaan Air Minum Desa' }}</p>
      </div>
    </header>

    <main class="container mx-auto px-4 py-8">
      <div class="max-w-md mx-auto bg-white rounded-lg shadow-md p-6">
        <h2 class="text-xl font-semibold mb-6 text-center">Cek Tagihan Air</h2>

        <form action="{{ route('customer.bills', '') }}" method="GET" class="space-y-4">
          <div>
            <label for="customer_code" class="block text-sm font-medium text-gray-700 mb-2">
              Kode Pelanggan
            </label>
            <input type="text" id="customer_code" name="customer_code" placeholder="Masukkan kode pelanggan"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              required>
          </div>

          <button type="submit"
            class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
            Cek Tagihan
          </button>
        </form>

        <div class="mt-6 text-center text-sm text-gray-600">
          <p>Hubungi petugas PAMDes jika Anda tidak mengetahui kode pelanggan.</p>
        </div>
      </div>

      <div class="max-w-2xl mx-auto mt-8 bg-white rounded-lg shadow-md p-6">
        <h3 class="text-lg font-semibold mb-4">Informasi Layanan</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div class="bg-blue-50 p-4 rounded-lg">
            <h4 class="font-medium text-blue-800">Jam Pelayanan</h4>
            <p class="text-blue-600">Senin - Jumat: 08:00 - 16:00</p>
            <p class="text-blue-600">Sabtu: 08:00 - 12:00</p>
          </div>
          <div class="bg-green-50 p-4 rounded-lg">
            <h4 class="font-medium text-green-800">Metode Pembayaran</h4>
            <p class="text-green-600">• Tunai di kantor desa</p>
            <p class="text-green-600">• Transfer bank</p>
            <p class="text-green-600">• QRIS</p>
          </div>
        </div>
      </div>
    </main>
  </div>
</body>

</html>
