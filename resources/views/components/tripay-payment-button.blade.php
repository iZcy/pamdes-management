@props(['village', 'bill'])

@if ($bill->status === 'unpaid')
  <div class="mt-4">
    <a href="{{ route('tripay.form', ['village' => $village->slug, 'bill' => $bill->id]) }}"
      class="inline-flex items-center px-6 py-3 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition duration-200 shadow-sm">
      <!-- QR Code Icon -->
      <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M12 4v1m6 11h2m-6 0h-2v4m-2 0h-2m2-4v-3m2 3V9l-6 6 2-2z"></path>
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M4 4h4v4H4V4zm8 0h4v4h-4V4zm-8 8h4v4H4v-4zm8 8h4v4h-4v-4z"></path>
      </svg>
      Bayar dengan QRIS
    </a>

    <p class="text-sm text-gray-600 mt-2">
      Pembayaran mudah dengan scan QR code menggunakan e-wallet atau mobile banking
    </p>
  </div>
@elseif($bill->status === 'pending')
  <div class="mt-4">
    <div class="inline-flex items-center px-4 py-2 bg-yellow-100 text-yellow-800 rounded-lg">
      <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
        <path fill-rule="evenodd"
          d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z"
          clip-rule="evenodd"></path>
      </svg>
      Menunggu Pembayaran
    </div>

    <button onclick="checkPaymentStatus()"
      class="ml-2 inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 transition duration-200">
      <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15">
        </path>
      </svg>
      Cek Status
    </button>

    <p class="text-sm text-gray-600 mt-2">
      Pembayaran sedang diproses. Klik "Cek Status" untuk memperbarui status pembayaran.
    </p>
  </div>
@elseif($bill->status === 'paid')
  <div class="mt-4">
    <div class="inline-flex items-center px-4 py-2 bg-green-100 text-green-800 rounded-lg">
      <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
        <path fill-rule="evenodd"
          d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
          clip-rule="evenodd"></path>
      </svg>
      Sudah Dibayar
    </div>

    @if ($bill->paid_at)
      <p class="text-sm text-gray-600 mt-2">
        Dibayar pada: {{ $bill->paid_at->format('d/m/Y H:i') }}
      </p>
    @endif
  </div>
@elseif($bill->status === 'failed')
  <div class="mt-4">
    <div class="inline-flex items-center px-4 py-2 bg-red-100 text-red-800 rounded-lg">
      <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
        <path fill-rule="evenodd"
          d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
          clip-rule="evenodd"></path>
      </svg>
      Pembayaran Gagal
    </div>

    <a href="{{ route('tripay.form', ['village' => $village->slug, 'bill' => $bill->id]) }}"
      class="ml-2 inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 transition duration-200">
      Coba Lagi
    </a>

    <p class="text-sm text-gray-600 mt-2">
      Pembayaran gagal atau kedaluwarsa. Silakan coba lagi untuk melakukan pembayaran.
    </p>
  </div>
@endif

@if ($bill->status === 'pending')
  <script>
    function checkPaymentStatus() {
      const button = event.target;
      const originalText = button.innerHTML;

      // Show loading state
      button.innerHTML = `
        <svg class="animate-spin w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        Mengecek...
    `;
      button.disabled = true;

      // Make AJAX request to check status
      fetch(`{{ route('tripay.status', ['village' => $village->slug, 'bill' => $bill->id]) }}`)
        .then(response => response.json())
        .then(data => {
          if (data.success && data.data.status === 'paid') {
            // Reload page to show updated status
            location.reload();
          } else {
            // Reset button
            button.innerHTML = originalText;
            button.disabled = false;

            // Show message
            alert('Status pembayaran masih pending. Silakan coba lagi dalam beberapa menit.');
          }
        })
        .catch(error => {
          console.error('Error:', error);
          button.innerHTML = originalText;
          button.disabled = false;
          alert('Gagal mengecek status pembayaran. Silakan coba lagi.');
        });
    }
  </script>
@endif
