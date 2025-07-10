{{-- resources/views/customer-portal/bills.blade.php --}}
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Tagihan {{ $customer->name }} - PAMDes</title>
  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="bg-gray-100">
  <div class="min-h-screen">
    <header class="bg-blue-600 text-white">
      <div class="container mx-auto px-4 py-6">
        <div class="flex items-center justify-between">
          <div>
            <h1 class="text-xl font-bold">Tagihan Air</h1>
            <p class="text-blue-100">{{ $customer->name }} ({{ $customer->customer_code }})</p>
          </div>
          <a href="{{ route('customer.portal') }}" class="bg-blue-500 hover:bg-blue-400 px-4 py-2 rounded">
            Kembali
          </a>
        </div>
      </div>
    </header>

    <main class="container mx-auto px-4 py-8">
      <div class="max-w-4xl mx-auto">
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
          <h2 class="text-lg font-semibold mb-4">Informasi Pelanggan</h2>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <p class="text-sm text-gray-600">Nama</p>
              <p class="font-medium">{{ $customer->name }}</p>
            </div>
            <div>
              <p class="text-sm text-gray-600">Kode Pelanggan</p>
              <p class="font-medium">{{ $customer->customer_code }}</p>
            </div>
            <div>
              <p class="text-sm text-gray-600">Alamat</p>
              <p class="font-medium">{{ $customer->full_address }}</p>
            </div>
            <div>
              <p class="text-sm text-gray-600">Status</p>
              <span
                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                {{ $customer->status === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                {{ $customer->status === 'active' ? 'Aktif' : 'Tidak Aktif' }}
              </span>
            </div>
          </div>
        </div>

        @if ($bills->count() > 0)
          <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 border-b bg-gray-50">
              <h3 class="text-lg font-semibold">Tagihan Belum Bayar</h3>
              <p class="text-sm text-gray-600">Total Outstanding:
                <span class="font-medium text-red-600">Rp {{ number_format($bills->sum('total_amount')) }}</span>
              </p>
            </div>

            <div class="overflow-x-auto">
              <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                  <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Periode
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Pemakaian
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Total Tagihan
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Jatuh Tempo
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Status
                    </th>
                  </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                  @foreach ($bills as $bill)
                    <tr>
                      <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm font-medium text-gray-900">
                          {{ $bill->waterUsage->billingPeriod->period_name }}
                        </div>
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm text-gray-900">{{ $bill->waterUsage->total_usage_m3 }} m³</div>
                        <div class="text-sm text-gray-500">
                          {{ number_format($bill->waterUsage->initial_meter) }} →
                          {{ number_format($bill->waterUsage->final_meter) }}
                        </div>
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm font-medium text-gray-900">
                          Rp {{ number_format($bill->total_amount) }}
                        </div>
                        <div class="text-xs text-gray-500">
                          Air: Rp {{ number_format($bill->water_charge) }} +
                          Admin: Rp {{ number_format($bill->admin_fee) }}
                        </div>
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm text-gray-900">{{ $bill->due_date->format('d/m/Y') }}</div>
                        @if ($bill->is_overdue)
                          <div class="text-xs text-red-600">
                            Terlambat {{ $bill->days_overdue }} hari
                          </div>
                        @endif
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap">
                        <span
                          class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                            {{ $bill->status === 'overdue' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800' }}">
                          {{ $bill->status === 'overdue' ? 'Terlambat' : 'Belum Bayar' }}
                        </span>
                      </td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
          </div>
        @else
          <div class="bg-white rounded-lg shadow-md p-8 text-center">
            <div class="text-green-600 mb-4">
              <svg class="mx-auto h-12 w-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
              </svg>
            </div>
            <h3 class="text-lg font-semibold text-gray-900 mb-2">Tidak Ada Tagihan</h3>
            <p class="text-gray-600">Semua tagihan Anda sudah lunas. Terima kasih!</p>
          </div>
        @endif
      </div>
    </main>
  </div>
</body>

</html>
<span>Rp {{ number_format($payment->bill->total_amount) }}</span>
</div>
<div class="row">
  <span class="label">Jumlah Dibayar:</span>
  <span>Rp {{ number_format($payment->amount_paid) }}</span>
</div>
@if ($payment->change_given > 0)
  <div class="row">
    <span class="label">Kembalian:</span>
    <span>Rp {{ number_format($payment->change_given) }}</span>
  </div>
@endif
<div class="row">
  <span class="label">Metode Pembayaran:</span>
  <span>{{ $payment->payment_method_label }}</span>
</div>
<div class="row">
  <span class="label">Tanggal Pembayaran:</span>
  <span>{{ $payment->payment_date->format('d/m/Y H:i') }}</span>
</div>
@if ($payment->collector_name)
  <div class="row">
    <span class="label">Petugas:</span>
    <span>{{ $payment->collector_name }}</span>
  </div>
@endif
</div>

<div class="footer">
  <p>{{ config('pamdes.current_village.name', 'Desa') }}, {{ now()->format('d F Y') }}</p>
  <div class="signature">
    <p>Petugas Kasir</p>
    <br><br><br>
    <p>(_________________________)</p>
  </div>
</div>

<script>
  // Auto print when opened
  window.onload = function() {
    setTimeout(function() {
      window.print();
    }, 500);
  }
</script>
</body>

</html>
