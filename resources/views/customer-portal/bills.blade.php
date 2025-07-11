{{-- resources/views/customer-portal/bills.blade.php - Updated with Payment Button --}}
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

            <div class="space-y-4 p-6">
              @foreach ($bills as $bill)
                <div
                  class="border rounded-lg p-4 {{ $bill->status === 'overdue' ? 'border-red-200 bg-red-50' : ($bill->status === 'pending' ? 'border-yellow-200 bg-yellow-50' : 'border-gray-200') }}">
                  <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                    <!-- Bill Info -->
                    <div class="flex-1">
                      <div class="flex items-center gap-2 mb-2">
                        <h4 class="font-semibold text-gray-900">{{ $bill->waterUsage->billingPeriod->period_name }}
                        </h4>
                        <span
                          class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                          {{ $bill->status === 'overdue' ? 'bg-red-100 text-red-800' : ($bill->status === 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800') }}">
                          {{ match ($bill->status) {
                              'overdue' => 'Terlambat',
                              'pending' => 'Menunggu Pembayaran',
                              default => 'Belum Bayar',
                          } }}
                        </span>
                      </div>

                      <div class="grid grid-cols-2 md:grid-cols-4 gap-2 text-sm">
                        <div>
                          <span class="text-gray-600">Pemakaian:</span>
                          <span class="font-medium">{{ $bill->waterUsage->total_usage_m3 }} m³</span>
                        </div>
                        <div>
                          <span class="text-gray-600">Meter:</span>
                          <span class="font-medium">{{ number_format($bill->waterUsage->initial_meter) }} →
                            {{ number_format($bill->waterUsage->final_meter) }}</span>
                        </div>
                        <div>
                          <span class="text-gray-600">Jatuh Tempo:</span>
                          <span
                            class="font-medium {{ $bill->due_date->isPast() ? 'text-red-600' : '' }}">{{ $bill->due_date->format('d/m/Y') }}</span>
                        </div>
                        <div>
                          <span class="text-gray-600">Total:</span>
                          <span class="font-bold text-lg text-blue-600">Rp
                            {{ number_format($bill->total_amount) }}</span>
                        </div>
                      </div>

                      <!-- Bill Breakdown -->
                      <div class="mt-2 text-xs text-gray-500">
                        @php
                          $breakdown = \App\Models\WaterTariff::calculateBill(
                              $bill->waterUsage->total_usage_m3,
                              $bill->waterUsage->customer->village_id,
                          );
                        @endphp
                        @if (count($breakdown['breakdown']) > 1)
                          <div class="mb-1">
                            Tarif:
                            @foreach ($breakdown['breakdown'] as $index => $tier)
                              {{ $tier['usage'] }}m³ ×
                              Rp{{ number_format($tier['rate']) }}{{ $index < count($breakdown['breakdown']) - 1 ? ' + ' : '' }}
                            @endforeach
                          </div>
                        @endif
                        Air: Rp {{ number_format($bill->water_charge) }} + Admin: Rp
                        {{ number_format($bill->admin_fee) }} + Pemeliharaan: Rp
                        {{ number_format($bill->maintenance_fee) }}
                      </div>

                      @if ($bill->is_overdue)
                        <div class="mt-2 text-sm text-red-600 font-medium">
                          ⚠️ Terlambat {{ $bill->days_overdue }} hari
                        </div>
                      @endif
                    </div>

                    <!-- Payment Actions -->
                    <div class="flex flex-col gap-2 lg:w-48">
                      @if ($bill->status === 'paid')
                        <!-- Already Paid -->
                        <div class="text-center p-3 bg-green-100 text-green-800 rounded-lg">
                          <svg class="w-8 h-8 mx-auto mb-1" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                              d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                              clip-rule="evenodd"></path>
                          </svg>
                          <div class="text-sm font-medium">Sudah Dibayar</div>
                          @if ($bill->payment_date)
                            <div class="text-xs">{{ $bill->payment_date->format('d/m/Y') }}</div>
                          @endif
                        </div>
                      @elseif($bill->status === 'pending')
                        <!-- Pending Payment -->
                        <div class="text-center p-3 bg-yellow-100 text-yellow-800 rounded-lg mb-2">
                          <svg class="w-6 h-6 mx-auto mb-1" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                              d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z"
                              clip-rule="evenodd"></path>
                          </svg>
                          <div class="text-sm font-medium">Menunggu Pembayaran</div>
                        </div>

                        <button onclick="checkPaymentStatus('{{ $bill->bill_id }}')"
                          class="w-full bg-yellow-600 text-white py-2 px-4 rounded-lg hover:bg-yellow-700 focus:outline-none focus:ring-2 focus:ring-yellow-500 transition duration-200 text-sm">
                          Cek Status
                        </button>

                        <a href="{{ route('tripay.form', ['village' => $customer->village->slug, 'bill' => $bill->bill_id]) }}"
                          class="w-full bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 transition duration-200 text-center text-sm">
                          Lanjutkan Pembayaran
                        </a>
                      @else
                        <!-- Unpaid - Show Payment Options -->
                        @php
                          // Check if Tripay is configured for this village
                          $variable = \App\Models\Variable::where('village_id', $customer->village_id)->first();
                          $tripayConfigured = $variable && ($variable->tripay_use_main || $variable->isConfigured());
                        @endphp

                        @if ($tripayConfigured)
                          <a href="{{ route('tripay.form', ['village' => $customer->village->slug, 'bill' => $bill->bill_id]) }}"
                            class="w-full bg-blue-600 text-white py-3 px-4 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 transition duration-200 flex items-center justify-center text-sm font-medium">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 4v1m6 11h2m-6 0h-2v4m-2 0h-2m2-4v-3m2 3V9l-6 6 2-2z"></path>
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 4h4v4H4V4zm8 0h4v4h-4V4zm-8 8h4v4H4v-4zm8 8h4v4h-4v-4z"></path>
                            </svg>
                            Bayar dengan QRIS
                          </a>
                        @endif

                        <!-- Contact Info for Manual Payment -->
                        <div class="text-center p-3 bg-gray-100 rounded-lg">
                          <div class="text-sm text-gray-700">
                            <div class="font-medium mb-1">Bayar Langsung:</div>
                            <div class="text-xs">Kantor Desa</div>
                            @if ($customer->village->phone_number)
                              <div class="text-xs">{{ $customer->village->phone_number }}</div>
                            @endif
                          </div>
                        </div>
                      @endif
                    </div>
                  </div>
                </div>
              @endforeach
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
                            {{ match ($bill->latestPayment->payment_method) {
                                'cash' => 'Tunai',
                                'transfer' => 'Transfer',
                                'qris' => 'QRIS',
                                default => 'Lainnya',
                            } }}
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

  <!-- Alert Messages -->
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
        <button onclick="closeAlert('error-alert')" class="ml-2 text-red-500 hover:text-red-700">×</button>
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
        <button onclick="closeAlert('success-alert')" class="ml-2 text-green-500 hover:text-green-700">×</button>
      </div>
    </div>
  @endif

  <script>
    function closeAlert(alertId) {
      document.getElementById(alertId).style.display = 'none';
    }

    // Auto-hide alerts after 5 seconds
    setTimeout(() => {
      const alerts = document.querySelectorAll('[id$="-alert"]');
      alerts.forEach(alert => {
        if (alert) alert.style.display = 'none';
      });
    }, 5000);

    function checkPaymentStatus(billId) {
      const button = event.target;
      const originalText = button.innerHTML;

      // Show loading state
      button.innerHTML = 'Mengecek...';
      button.disabled = true;

      // Make AJAX request to check status
      fetch(`{{ route('tripay.status', ['village' => $customer->village->slug, 'bill' => '']) }}`.replace(/\/$/, '') +
          '/' + billId)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data.status === 'paid') {
              //
