{{-- resources/views/tripay/payment-form.blade.php --}}
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Pembayaran QRIS - PAMDes {{ $village->name }}</title>
  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="bg-gray-100">
  <div class="min-h-screen py-8">
    <div class="container mx-auto px-4 max-w-md">
      <div class="bg-white rounded-lg shadow-lg p-6">
        <!-- Header -->
        <div class="text-center mb-6">
          <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M12 4v1m6 11h2m-6 0h-2v4m-2 0h-2m2-4v-3m2 3V9l-6 6 2-2z"></path>
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M4 4h4v4H4V4zm8 0h4v4h-4V4zm-8 8h4v4H4v-4zm8 8h4v4h-4v-4z"></path>
            </svg>
          </div>
          <h2 class="text-2xl font-bold text-gray-800">Pembayaran QRIS</h2>
          <p class="text-gray-600 mt-2">PAMDes {{ $village->name }}</p>
        </div>

        <!-- Bill Details -->
        <div class="bg-gray-50 rounded-lg p-4 mb-6">
          <h3 class="font-semibold text-gray-800 mb-3">Detail Tagihan</h3>
          <div class="space-y-2 text-sm">
            <div class="flex justify-between">
              <span class="text-gray-600">Pelanggan:</span>
              <span class="font-medium">{{ $bill->waterUsage->customer->name }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-600">Kode:</span>
              <span class="font-medium">{{ $bill->waterUsage->customer->customer_code }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-600">Periode:</span>
              <span class="font-medium">{{ $bill->waterUsage->billingPeriod->period_name }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-600">Pemakaian:</span>
              <span class="font-medium">{{ $bill->waterUsage->total_usage_m3 }} m³</span>
            </div>
            <hr class="my-2">
            <div class="flex justify-between">
              <span class="text-gray-600">Biaya Air:</span>
              <span>Rp {{ number_format($bill->water_charge, 0, ',', '.') }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-600">Biaya Admin:</span>
              <span>Rp {{ number_format($bill->admin_fee, 0, ',', '.') }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-600">Biaya Pemeliharaan:</span>
              <span>Rp {{ number_format($bill->maintenance_fee, 0, ',', '.') }}</span>
            </div>
            <hr class="my-2">
            <div class="flex justify-between font-bold text-lg">
              <span class="text-gray-800">Total:</span>
              <span class="text-blue-600">Rp {{ number_format($bill->total_amount, 0, ',', '.') }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-600">Status:</span>
              <span
                class="px-2 py-1 rounded-full text-xs {{ $bill->status === 'paid' ? 'bg-green-100 text-green-800' : ($bill->status === 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') }}">
                {{ match ($bill->status) {
                    'paid' => 'Sudah Dibayar',
                    'pending' => 'Menunggu Pembayaran',
                    'overdue' => 'Terlambat',
                    default => 'Belum Bayar',
                } }}
              </span>
            </div>
            @if ($bill->due_date)
              <div class="flex justify-between">
                <span class="text-gray-600">Jatuh Tempo:</span>
                <span
                  class="font-medium {{ $bill->due_date->isPast() && $bill->status !== 'paid' ? 'text-red-600' : '' }}">
                  {{ $bill->due_date->format('d/m/Y') }}
                </span>
              </div>
            @endif
          </div>
        </div>

        @if ($bill->status === 'paid')
          <!-- Already Paid -->
          <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
            <div class="flex items-center">
              <svg class="w-5 h-5 text-green-600 mr-3" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd"
                  d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                  clip-rule="evenodd"></path>
              </svg>
              <div>
                <h4 class="text-sm font-medium text-green-800">Tagihan Sudah Dibayar</h4>
                <p class="text-sm text-green-700 mt-1">
                  Pembayaran telah diterima pada {{ $bill->payment_date->format('d/m/Y') }}
                </p>
              </div>
            </div>
          </div>
        @elseif($bill->status === 'pending')
          <!-- Pending Payment -->
          <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
            <div class="flex items-center">
              <svg class="w-5 h-5 text-yellow-600 mr-3" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd"
                  d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z"
                  clip-rule="evenodd"></path>
              </svg>
              <div>
                <h4 class="text-sm font-medium text-yellow-800">Menunggu Pembayaran</h4>
                <p class="text-sm text-yellow-700 mt-1">
                  Pembayaran sedang diproses. Silakan selesaikan pembayaran atau cek status.
                </p>
              </div>
            </div>
          </div>

          <!-- Continue Payment -->
          <button
            onclick="location.href='{{ route('tripay.continue', ['village' => $village->slug, 'bill' => $bill->bill_id]) }}'"
            class="w-full bg-blue-600 text-white py-3 px-4 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 font-medium transition duration-200 mb-4">
            Lanjutkan Pembayaran
          </button>

          <!-- Check Payment Status Button -->
          <button onclick="checkPaymentStatus()"
            class="w-full bg-yellow-600 text-white py-3 px-4 rounded-md hover:bg-yellow-700 focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:ring-offset-2 font-medium transition duration-200 mb-4">
            Cek Status Pembayaran
          </button>
        @else
          <!-- Payment Form -->
          <form action="{{ route('tripay.create', ['village' => $village->slug, 'bill' => $bill->bill_id]) }}"
            method="POST" class="space-y-4">
            @csrf

            <div>
              <label for="customer_name" class="block text-sm font-medium text-gray-700 mb-1">
                Nama Lengkap <span class="text-red-500">*</span>
              </label>
              <input type="text" id="customer_name" name="customer_name"
                value="{{ old('customer_name', $bill->waterUsage->customer->name) }}" required
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('customer_name') border-red-500 @enderror"
                placeholder="Masukkan nama lengkap">
              @error('customer_name')
                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
              @enderror
            </div>

            <div>
              <label for="customer_email" class="block text-sm font-medium text-gray-700 mb-1">
                Email <span class="text-red-500">*</span>
              </label>
              <input type="email" id="customer_email" name="customer_email" value="{{ old('customer_email') }}"
                required
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('customer_email') border-red-500 @enderror"
                placeholder="contoh@email.com">
              @error('customer_email')
                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
              @enderror
            </div>

            <div>
              <label for="customer_phone" class="block text-sm font-medium text-gray-700 mb-1">
                Nomor Telepon (Opsional)
              </label>
              <input type="tel" id="customer_phone" name="customer_phone"
                value="{{ old('customer_phone', $bill->waterUsage->customer->phone_number) }}"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                placeholder="08123456789">
            </div>

            <!-- Payment Info -->
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
              <div class="flex items-start">
                <svg class="w-5 h-5 text-blue-600 mt-0.5 mr-3" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd"
                    d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                    clip-rule="evenodd"></path>
                </svg>
                <div>
                  <h4 class="text-sm font-medium text-blue-800">Informasi Pembayaran QRIS</h4>
                  <p class="text-sm text-blue-700 mt-1">
                    Setelah mengklik tombol bayar, Anda akan diarahkan ke halaman pembayaran Tripay.
                    Scan QR code dengan aplikasi e-wallet atau mobile banking Anda.
                  </p>
                  <ul class="text-xs text-blue-600 mt-2 space-y-1">
                    <li>• Pembayaran aman dan terenkripsi</li>
                    <li>• Mendukung semua e-wallet dan mobile banking</li>
                    <li>• Konfirmasi pembayaran otomatis</li>
                  </ul>
                </div>
              </div>
            </div>

            <!-- Submit Button -->
            <button type="submit"
              class="w-full bg-blue-600 text-white py-3 px-4 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 font-medium transition duration-200 flex items-center justify-center">
              <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M12 4v1m6 11h2m-6 0h-2v4m-2 0h-2m2-4v-3m2 3V9l-6 6 2-2z"></path>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M4 4h4v4H4V4zm8 0h4v4h-4V4zm-8 8h4v4H4v-4zm8 8h4v4h-4v-4z"></path>
              </svg>
              Bayar dengan QRIS
            </button>
          </form>
        @endif

        <!-- Back Link -->
        <div class="text-center mt-6">
          <a href="{{ route('portal.bills', $bill->waterUsage->customer->customer_code) }}"
            class="text-gray-600 hover:text-gray-800 text-sm inline-flex items-center">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18">
              </path>
            </svg>
            Kembali ke Tagihan
          </a>
        </div>
      </div>
    </div>
  </div>

  <!-- Dynamic Alert Container -->
  <div id="dynamic-alerts" class="fixed top-4 right-4 z-50 space-y-2"></div>

  <!-- Static Alert Messages -->
  @if (session('error'))
    <div id="error-alert"
      class="fixed top-4 right-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded max-w-sm shadow-lg z-50">
      <div class="flex items-center">
        <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd"
            d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
            clip-rule="evenodd"></path>
        </svg>
        <span>{{ session('error') }}</span>
        <button onclick="closeAlert('error-alert')" class="ml-2 text-red-500 hover:text-red-700">
          <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd"
              d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
              clip-rule="evenodd"></path>
          </svg>
        </button>
      </div>
    </div>
  @endif

  @if (session('success'))
    <div id="success-alert"
      class="fixed top-4 right-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded max-w-sm shadow-lg z-50">
      <div class="flex items-center">
        <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd"
            d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
            clip-rule="evenodd"></path>
        </svg>
        <span>{{ session('success') }}</span>
        <button onclick="closeAlert('success-alert')" class="ml-2 text-green-500 hover:text-green-700">
          <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd"
              d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
              clip-rule="evenodd"></path>
          </svg>
        </button>
      </div>
    </div>
  @endif

  <script>
    // Utility functions for alerts
    function createAlert(type, message, duration = 5000) {
      const alertContainer = document.getElementById('dynamic-alerts');
      const alertId = 'dynamic-alert-' + Date.now();

      const alertColors = {
        success: 'bg-green-100 border-green-400 text-green-700',
        error: 'bg-red-100 border-red-400 text-red-700',
        warning: 'bg-yellow-100 border-yellow-400 text-yellow-700',
        info: 'bg-blue-100 border-blue-400 text-blue-700'
      };

      const alertIcons = {
        success: `<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>`,
        error: `<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>`,
        warning: `<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"></path>`,
        info: `<path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>`
      };

      const alertElement = document.createElement('div');
      alertElement.id = alertId;
      alertElement.className =
        `${alertColors[type]} px-4 py-3 rounded max-w-sm shadow-lg border transform transition-all duration-300 translate-x-full opacity-0`;
      alertElement.innerHTML = `
        <div class="flex items-center">
          <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
            ${alertIcons[type]}
          </svg>
          <span class="flex-1">${message}</span>
          <button onclick="closeAlert('${alertId}')" class="ml-2 text-current hover:opacity-70">
            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
            </svg>
          </button>
        </div>
      `;

      alertContainer.appendChild(alertElement);

      // Animate in
      setTimeout(() => {
        alertElement.classList.remove('translate-x-full', 'opacity-0');
      }, 100);

      // Auto hide
      if (duration > 0) {
        setTimeout(() => {
          closeAlert(alertId);
        }, duration);
      }

      return alertId;
    }

    function closeAlert(alertId) {
      const alert = document.getElementById(alertId);
      if (alert) {
        alert.classList.add('translate-x-full', 'opacity-0');
        setTimeout(() => {
          if (alert.parentNode) {
            alert.parentNode.removeChild(alert);
          }
        }, 300);
      }
    }

    // Auto-hide static alerts after 5 seconds
    setTimeout(() => {
      const alerts = document.querySelectorAll('[id$="-alert"]:not([id^="dynamic-"])');
      alerts.forEach(alert => {
        if (alert) closeAlert(alert.id);
      });
    }, 5000);

    @if ($bill->status === 'pending')
      function checkPaymentStatus() {
        const button = event.target;
        const originalText = button.innerHTML;

        // Show loading state
        button.innerHTML = `
          <div class="flex items-center justify-center">
            <svg class="animate-spin w-5 h-5 mr-2" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            Mengecek Status...
          </div>
        `;
        button.disabled = true;

        // Show info alert
        const checkingAlertId = createAlert('info', 'Sedang mengecek status pembayaran...', 0);

        // Make AJAX request to check status
        fetch(`{{ route('tripay.status', ['village' => $village->slug, 'bill' => $bill->bill_id]) }}`)
          .then(response => response.json())
          .then(data => {
            // Close checking alert
            closeAlert(checkingAlertId);

            if (data.success && data.status === 'paid') {
              // Show success message and reload page
              createAlert('success', 'Pembayaran berhasil! Halaman akan dimuat ulang...', 2000);
              setTimeout(() => {
                location.reload();
              }, 2000);
            } else {
              // Reset button
              button.innerHTML = originalText;
              button.disabled = false;

              // Show current status
              let statusText = data.status || 'pending';
              let statusMessage = '';

              switch (statusText) {
                case 'pending':
                  statusMessage =
                    'Pembayaran masih dalam proses. Silakan selesaikan pembayaran atau coba lagi dalam beberapa menit.';
                  createAlert('warning', statusMessage);
                  break;
                case 'unpaid':
                  statusMessage = 'Pembayaran belum dilakukan. Silakan lanjutkan pembayaran.';
                  createAlert('error', statusMessage);
                  break;
                case 'expired':
                  statusMessage = 'Pembayaran telah kedaluwarsa. Silakan buat pembayaran baru.';
                  createAlert('error', statusMessage);
                  // Reload page after 3 seconds to show updated form
                  setTimeout(() => {
                    location.reload();
                  }, 3000);
                  break;
                case 'failed':
                  statusMessage = 'Pembayaran gagal. Silakan coba lagi.';
                  createAlert('error', statusMessage);
                  // Reload page after 3 seconds to show updated form
                  setTimeout(() => {
                    location.reload();
                  }, 3000);
                  break;
                default:
                  statusMessage = `Status pembayaran: ${statusText}`;
                  createAlert('info', statusMessage);
              }
            }
          })
          .catch(error => {
            console.error('Error:', error);

            // Close checking alert
            closeAlert(checkingAlertId);

            // Reset button
            button.innerHTML = originalText;
            button.disabled = false;

            // Show error alert
            createAlert('error', 'Gagal mengecek status pembayaran. Silakan coba lagi.');
          });
      }
    @endif
  </script>
</body>

</html>
