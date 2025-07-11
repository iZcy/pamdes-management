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
                          @php
                            $breakdown = \App\Models\WaterTariff::calculateBill(
                                $bill->waterUsage->total_usage_m3,
                                $bill->waterUsage->customer->village_id,
                            );
                          @endphp
                          @if (count($breakdown['breakdown']) > 1)
                            <div class="mb-1">
                              @foreach ($breakdown['breakdown'] as $index => $tier)
                                {{ $tier['usage'] }} ×
                                Rp{{ number_format($tier['rate']) }}{{ $index < count($breakdown['breakdown']) - 1 ? ' + ' : '' }}
                              @endforeach
                            </div>
                          @endif
                          Air: Rp {{ number_format($bill->water_charge) }} +
                          Admin: Rp {{ number_format($bill->admin_fee) }} +
                          Pemeliharaan: Rp {{ number_format($bill->maintenance_fee) }}
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
          <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
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
                          @php
                            $breakdown = \App\Models\WaterTariff::calculateBill(
                                $bill->waterUsage->total_usage_m3,
                                $bill->waterUsage->customer->village_id,
                            );
                          @endphp
                          @if (count($breakdown['breakdown']) > 1)
                            <div class="mb-1">
                              @foreach ($breakdown['breakdown'] as $index => $tier)
                                {{ $tier['usage'] }} ×
                                Rp{{ number_format($tier['rate']) }}{{ $index < count($breakdown['breakdown']) - 1 ? ' + ' : '' }}
                              @endforeach
                            </div>
                          @endif
                          Air: Rp {{ number_format($bill->water_charge) }} +
                          Admin: Rp {{ number_format($bill->admin_fee) }} +
                          Pemeliharaan: Rp {{ number_format($bill->maintenance_fee) }}
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
                            {{ ucfirst($bill->latestPayment->payment_method) }}
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
          <div class="bg-white rounded-lg shadow-md p-8 text-center mb-6">
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

        <!-- Village Water Tariffs -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
          <div class="px-6 py-4 border-b bg-gray-50">
            <h3 class="text-lg font-semibold">Tarif Air {{ $customer->village?->name ?? 'Desa' }}</h3>
            <p class="text-sm text-gray-600">Struktur tarif air berdasarkan pemakaian per bulan</p>
          </div>

          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Rentang Pemakaian
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Tarif per m³
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Contoh Biaya
                  </th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                @php
                  $villageTariffs = \App\Models\WaterTariff::where('village_id', $customer->village_id)
                      ->where('is_active', true)
                      ->orderBy('usage_min')
                      ->get();

                  // Get village model for fees
                  $villageModel = \App\Models\Village::find($customer->village_id);
                  $adminFee = $villageModel?->getDefaultAdminFee() ?? 5000;
                  $maintenanceFee = $villageModel?->getDefaultMaintenanceFee() ?? 2000;

                  // Find the highest range for example calculation
                  $maxRange = $villageTariffs->max('usage_max');
                  $exampleUsage = $maxRange ? min($maxRange + 5, 40) : 30; // Use max + 5 or 40, whichever is smaller

                  // Calculate progressive example
                  $exampleCalculation = [];
                  $remainingUsage = $exampleUsage;

                  foreach ($villageTariffs as $tariff) {
                      if ($remainingUsage <= 0) {
                          break;
                      }

                      $tierMin = $tariff->usage_min;
                      $tierMax = $tariff->usage_max;

                      // Calculate usage that falls in this tier
                      $tierUsage = 0;
                      if ($exampleUsage >= $tierMin) {
                          if ($tierMax === null) {
                              // Last tier - takes all remaining usage
                              $tierUsage = $remainingUsage;
                          } else {
                              // Calculate how much usage falls in this tier
                              $usageStart = max($tierMin, $exampleUsage - $remainingUsage + 1);
                              $usageEnd = min($tierMax, $exampleUsage);
                              $tierUsage = max(0, $usageEnd - $usageStart + 1);
                          }
                      }

                      if ($tierUsage > 0) {
                          $exampleCalculation[$tariff->tariff_id] = [
                              'usage' => $tierUsage,
                              'cost' => $tierUsage * $tariff->price_per_m3,
                          ];
                          $remainingUsage -= $tierUsage;
                      }
                  }
                @endphp

                @forelse($villageTariffs as $tariff)
                  <tr>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <div class="text-sm font-medium text-gray-900">
                        {{ $tariff->usage_range }}
                      </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <div class="text-sm text-gray-900">
                        Rp {{ number_format($tariff->price_per_m3) }}
                      </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <div class="text-sm text-gray-500">
                        @if (isset($exampleCalculation[$tariff->tariff_id]))
                          <span class="text-blue-600 font-medium">
                            {{ $exampleCalculation[$tariff->tariff_id]['usage'] }}m³ ×
                            Rp{{ number_format($tariff->price_per_m3) }} =
                            Rp{{ number_format($exampleCalculation[$tariff->tariff_id]['cost']) }}
                          </span>
                        @else
                          <span class="text-gray-400">Tidak terpakai</span>
                        @endif
                      </div>
                    </td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="3" class="px-6 py-4 text-center text-gray-500">
                      Tarif belum tersedia
                    </td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>

          <div class="px-6 py-4 bg-gray-50 border-t">
            <div class="text-sm text-gray-600">
              <p><strong>Contoh Perhitungan untuk {{ $exampleUsage }}m³:</strong></p>
              <div class="mt-2 p-3 bg-blue-50 rounded-lg">
                <div class="space-y-1">
                  @foreach ($exampleCalculation as $calc)
                    <div class="text-sm">• {{ $calc['usage'] }}m³ = Rp {{ number_format($calc['cost']) }}</div>
                  @endforeach
                  @php $totalWaterCost = collect($exampleCalculation)->sum('cost'); @endphp
                  <div class="border-t pt-1 mt-2 font-semibold">
                    Biaya Air: Rp {{ number_format($totalWaterCost) }}
                  </div>
                  <div class="text-sm">
                    + Biaya Admin: Rp {{ number_format($adminFee) }}<br>
                    + Biaya Pemeliharaan: Rp {{ number_format($maintenanceFee) }}
                  </div>
                  <div class="border-t pt-1 mt-1 font-bold text-blue-700">
                    Total Tagihan: Rp {{ number_format($totalWaterCost + $adminFee + $maintenanceFee) }}
                  </div>
                </div>
              </div>

              <div class="mt-3 text-xs text-gray-500">
                <p><strong>Catatan:</strong></p>
                <ul class="list-disc list-inside mt-1 space-y-1">
                  <li>Tarif berlaku progresif - pemakaian lebih tinggi dikenakan tarif yang lebih tinggi</li>
                  <li>Biaya admin dan pemeliharaan ditambahkan ke biaya air untuk mendapatkan total tagihan</li>
                  <li>Contoh di atas menggunakan pemakaian {{ $exampleUsage }}m³ yang mencakup semua rentang tarif</li>
                </ul>
              </div>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>
</body>

</html>
