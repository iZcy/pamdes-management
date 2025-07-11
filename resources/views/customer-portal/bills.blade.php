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
          <a href="{{ route('portal.index') }}" class="bg-blue-500 hover:bg-blue-400 px-4 py-2 rounded">
            Kembali
          </a>
        </div>
      </div>
    </header>

    <main class="container mx-auto px-4 py-8">
      <div class="max-w-4xl mx-auto">
        <!-- Customer Information -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
          <h2 class="text-lg font-semibold mb-4">Informasi Pelanggan</h2>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
              <p class="text-sm text-gray-600">Nama</p>
              <p class="font-medium">{{ $customer->name }}</p>
            </div>
            <div>
              <p class="text-sm text-gray-600">Kode Pelanggan</p>
              <p class="font-medium">{{ $customer->customer_code }}</p>
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

        <!-- Outstanding Bills -->
        @if ($bills->count() > 0)
          <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
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
          <div class="bg-white rounded-lg shadow-md p-8 text-center mb-6">
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

        <!-- Payment History -->
        @if ($paidBills->count() > 0)
          <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 border-b bg-gray-50">
              <h3 class="text-lg font-semibold">Riwayat Pembayaran</h3>
              <p class="text-sm text-gray-600">{{ $paidBills->count() }} pembayaran terakhir</p>
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
                      Tgl Bayar
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Metode
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Status
                    </th>
                  </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                  @foreach ($paidBills as $bill)
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
                        <div class="text-sm text-gray-900">{{ $bill->payment_date->format('d/m/Y') }}</div>
                        @if ($bill->latestPayment)
                          <div class="text-xs text-gray-500">
                            {{ $bill->latestPayment->collector_name ?? 'Sistem' }}
                          </div>
                        @endif
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap">
                        @if ($bill->latestPayment)
                          <span
                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                      {{ $bill->latestPayment->payment_method === 'cash'
                                          ? 'bg-green-100 text-green-800'
                                          : ($bill->latestPayment->payment_method === 'transfer'
                                              ? 'bg-blue-100 text-blue-800'
                                              : ($bill->latestPayment->payment_method === 'qris'
                                                  ? 'bg-yellow-100 text-yellow-800'
                                                  : 'bg-gray-100 text-gray-800')) }}">
                            {{ $bill->latestPayment->payment_method_label }}
                          </span>
                        @else
                          <span class="text-xs text-gray-500">-</span>
                        @endif
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap">
                        <span
                          class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                          Lunas
                        </span>
                      </td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>

            @if ($paidBills->count() >= 10)
              <div class="px-6 py-4 bg-gray-50 border-t">
                <p class="text-sm text-gray-600 text-center">
                  Menampilkan 10 pembayaran terakhir. Untuk riwayat lengkap, hubungi kantor desa.
                </p>
              </div>
            @endif
          </div>
        @else
          <div class="bg-white rounded-lg shadow-md p-8 text-center">
            <div class="text-gray-400 mb-4">
              <svg class="mx-auto h-12 w-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2">
                </path>
              </svg>
            </div>
            <h3 class="text-lg font-semibold text-gray-900 mb-2">Belum Ada Riwayat Pembayaran</h3>
            <p class="text-gray-600">Riwayat pembayaran akan muncul setelah Anda melakukan pembayaran pertama.</p>
          </div>
        @endif
      </div>
    </main>
  </div>
</body>

</html>
