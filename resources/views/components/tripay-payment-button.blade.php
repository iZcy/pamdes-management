{{-- Tripay Payment Button Component --}}
@props(['village', 'bill'])

@if ($bill->status === 'unpaid')
  <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
    <div class="flex items-center justify-between">
      <div class="flex items-center">
        <svg class="w-8 h-8 text-blue-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
        </svg>
        <div>
          <h3 class="text-lg font-semibold text-blue-800">Pembayaran QRIS</h3>
          <p class="text-sm text-blue-600">Bayar dengan scan QR code menggunakan e-wallet atau mobile banking</p>
        </div>
      </div>
      <a href="{{ route('tripay.form', ['village' => $village->slug, 'bill' => $bill->id]) }}"
        class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-md font-medium transition duration-200">
        Bayar Sekarang
      </a>
    </div>
  </div>
@elseif($bill->status === 'pending')
  <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">
    <div class="flex items-center justify-between">
      <div class="flex items-center">
        <svg class="w-8 h-8 text-yellow-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
        </svg>
        <div>
          <h3 class="text-lg font-semibold text-yellow-800">Pembayaran Sedang Diproses</h3>
          <p class="text-sm text-yellow-600">Silakan selesaikan pembayaran atau tunggu konfirmasi</p>
        </div>
      </div>
      <button onclick="checkPaymentStatus({{ $bill->id }})"
        class="bg-yellow-600 hover:bg-yellow-700 text-white px-4 py-2 rounded-md font-medium transition duration-200">
        Cek Status
      </button>
    </div>
  </div>
@elseif($bill->status === 'paid')
  <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-4">
    <div class="flex items-center">
      <svg class="w-8 h-8 text-green-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
      </svg>
      <div>
        <h3 class="text-lg font-semibold text-green-800">Pembayaran Berhasil</h3>
        <p class="text-sm text-green-600">
          Dibayar pada: {{ $bill->paid_at ? $bill->paid_at->format('d/m/Y H:i') : '-' }}
        </p>
      </div>
    </div>
  </div>
@else
  <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-4">
    <div class="flex items-center">
      <svg class="w-8 h-8 text-red-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16c-.77.833.192 2.5 1.732 2.5z">
        </path>
      </svg>
      <div>
        <h3 class="text-lg font-semibold text-red-800">Pembayaran Gagal</h3>
        <p class="text-sm text-red-600">Silakan coba lagi atau hubungi administrator</p>
      </div>
    </div>
  </div>
@endif

<script>
  function checkPaymentStatus(billId) {
    const button = event.target;
    const originalText = button.textContent;

    button.textContent = 'Mengecek...';
    button.disabled = true;

    fetch(`{{ route('tripay.status', ['village' => $village->slug, 'bill' => ':billId']) }}`.replace(':billId', billId))
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          if (data.data.status === 'paid') {
            location.reload();
          } else {
            alert('Status: ' + data.data.status.toUpperCase());
          }
        } else {
          alert('Gagal mengecek status pembayaran');
        }
      })
      .catch(error => {
        console.error('Error:', error);
        alert('Terjadi kesalahan saat mengecek status');
      })
      .finally(() => {
        button.textContent = originalText;
        button.disabled = false;
      });
  }
</script>
