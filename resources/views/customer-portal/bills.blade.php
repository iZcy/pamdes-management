@php
  // Parse village data from Village model at the beginning
  $villageModel = \App\Models\Village::find($customer->village_id);
  $villageName = $villageModel?->name ?? 'Desa';
  $villageSlug = $villageModel?->slug ?? 'unknown';
  $villagePhone = $villageModel?->phone_number ?? null;
  $villageEmail = $villageModel?->email ?? null;
  $villageAddress = $villageModel?->address ?? null;

  // Get current village from config as fallback
  $currentVillage = config('pamdes.current_village');
  if (!$villageSlug && $currentVillage) {
      $villageSlug = $currentVillage['slug'] ?? 'unknown';
  }
  if (!$villageName && $currentVillage) {
      $villageName = $currentVillage['name'] ?? 'Desa';
  }
@endphp

<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Tagihan {{ $customer->name }} - PAMDes</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');

    body {
      font-family: 'Inter', sans-serif;
    }

    .glass-effect {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      border: 1px solid rgba(255, 255, 255, 0.2);
    }

    .hero-gradient {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }

    .card-gradient {
      background: linear-gradient(145deg, #ffffff 0%, #f8fafc 100%);
    }

    .status-paid {
      background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    }

    .status-unpaid {
      background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    }

    .status-overdue {
      background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    }

    .status-pending {
      background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
    }

    .btn-primary {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      transition: all 0.3s ease;
    }

    .btn-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
    }

    .btn-success {
      background: linear-gradient(135deg, #10b981 0%, #059669 100%);
      transition: all 0.3s ease;
    }

    .btn-success:hover {
      transform: translateY(-2px);
      box-shadow: 0 10px 25px rgba(16, 185, 129, 0.3);
    }

    .btn-secondary {
      background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%);
      transition: all 0.3s ease;
    }

    .btn-secondary:hover {
      transform: translateY(-2px);
      box-shadow: 0 10px 25px rgba(107, 114, 128, 0.3);
    }

    .card-hover {
      transition: all 0.3s ease;
    }

    .card-hover:hover {
      transform: translateY(-4px);
      box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
    }

    .animate-fade-in {
      animation: fadeIn 0.6s ease-out;
    }

    .animate-slide-up {
      animation: slideUp 0.8s ease-out;
    }

    @keyframes fadeIn {
      from {
        opacity: 0;
      }

      to {
        opacity: 1;
      }
    }

    @keyframes slideUp {
      from {
        opacity: 0;
        transform: translateY(30px);
      }

      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .progress-bar {
      background: linear-gradient(90deg, #10b981 0%, #059669 100%);
      height: 6px;
      border-radius: 3px;
      transition: width 0.3s ease;
    }

    .tier-breakdown {
      background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
      border-left: 4px solid #0ea5e9;
    }

    .water-icon {
      animation: droplet 2s ease-in-out infinite;
    }

    @keyframes droplet {

      0%,
      100% {
        transform: scale(1) rotate(0deg);
      }

      50% {
        transform: scale(1.1) rotate(5deg);
      }
    }
  </style>
</head>

<body class="bg-gray-50 min-h-screen">
  <!-- Header -->
  <header class="hero-gradient text-white relative overflow-hidden">
    <div class="absolute inset-0">
      <div class="absolute top-10 left-10 w-20 h-20 bg-white bg-opacity-10 rounded-full"></div>
      <div class="absolute bottom-20 right-20 w-16 h-16 bg-white bg-opacity-10 rounded-full"></div>
    </div>

    <div class="container mx-auto px-4 py-8 relative z-10">
      <div class="flex flex-col md:flex-row items-center justify-between">
        <div class="mb-4 md:mb-0">
          <div class="flex items-center mb-2">
            <div class="w-12 h-12 bg-white bg-opacity-20 rounded-xl flex items-center justify-center mr-4">
              <svg class="w-6 h-6 text-white water-icon" fill="currentColor" viewBox="0 0 24 24">
                <path d="M12 2c0 0-6 5.686-6 10a6 6 0 0 0 12 0c0-4.314-6-10-6-10z" />
              </svg>
            </div>
            <div>
              <h1 class="text-2xl md:text-3xl font-bold">Tagihan Air</h1>
              <p class="text-blue-100 opacity-90">{{ $customer->name }} ({{ $customer->customer_code }})</p>
            </div>
          </div>
        </div>
        <div class="flex space-x-3">
          <a href="{{ route('portal.index') }}"
            class="glass-effect text-blue-900 px-6 py-3 rounded-xl hover:bg-white hover:text-white hover:bg-opacity-20 transition-all duration-300 flex items-center">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18">
              </path>
            </svg>
            Kembali
          </a>
        </div>
      </div>
    </div>
  </header>

  <main class="container mx-auto px-4 py-8">
    <div class="max-w-7xl mx-auto">

      <!-- Customer Information Card -->
      <div class="card-gradient rounded-2xl shadow-xl p-6 mb-8 card-hover animate-fade-in">
        <div class="flex items-center mb-6">
          <div
            class="w-12 h-12 bg-gradient-to-r from-blue-500 to-purple-600 rounded-xl flex items-center justify-center mr-4">
            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
            </svg>
          </div>
          <h2 class="text-xl font-bold text-gray-800">Informasi Pelanggan</h2>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
          <div class="bg-blue-50 p-4 rounded-xl border border-blue-100">
            <p class="text-sm text-blue-600 font-medium mb-1">Nama Pelanggan</p>
            <p class="text-lg font-bold text-blue-800">{{ $customer->name }}</p>
          </div>
          <div class="bg-purple-50 p-4 rounded-xl border border-purple-100">
            <p class="text-sm text-purple-600 font-medium mb-1">Kode Pelanggan</p>
            <p class="text-lg font-bold text-purple-800">{{ $customer->customer_code }}</p>
          </div>
          <div class="bg-green-50 p-4 rounded-xl border border-green-100">
            <p class="text-sm text-green-600 font-medium mb-1">Status Akun</p>
            <span
              class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium {{ $customer->status === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
              <div
                class="w-2 h-2 rounded-full mr-2 {{ $customer->status === 'active' ? 'bg-green-500' : 'bg-red-500' }}">
              </div>
              {{ $customer->status === 'active' ? 'Aktif' : 'Tidak Aktif' }}
            </span>
          </div>
          <div class="bg-gray-50 p-4 rounded-xl border border-gray-100">
            <p class="text-sm text-gray-600 font-medium mb-1">Total Tagihan</p>
            <p class="text-lg font-bold text-gray-800">{{ $bills->count() }} tagihan</p>
          </div>
        </div>
      </div>

      @if ($bills->count() > 0)
        <!-- Outstanding Bills Section -->
        <div class="card-gradient rounded-2xl shadow-xl overflow-hidden mb-8 animate-slide-up">
          <div class="bg-gradient-to-r from-orange-500 to-red-500 px-6 py-4 text-white">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between">
              <div class="mb-4 md:mb-0">
                <h3 class="text-xl font-bold mb-1">Tagihan Menunggu Pembayaran</h3>
                <p class="text-orange-100">{{ $bills->count() }} tagihan belum dibayar</p>
              </div>
              @php
                $totalOutstanding = $bills->sum('total_amount');
              @endphp
              <div class="text-right">
                <p class="text-sm text-orange-100">Total Outstanding</p>
                <p class="text-2xl font-bold">Rp {{ number_format($totalOutstanding) }}</p>
              </div>
            </div>
          </div>

          <div class="p-6">
            <div class="space-y-6">
              @foreach ($bills as $index => $bill)
                <div class="bg-white rounded-xl shadow-lg border border-gray-100 overflow-hidden card-hover"
                  style="animation-delay: {{ $index * 0.1 }}s">
                  <!-- Bill Header -->
                  <div class="bg-gradient-to-r from-gray-50 to-blue-50 px-6 py-4 border-b border-gray-100">
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                      <div>
                        <h4 class="text-lg font-bold text-gray-800 mb-1">
                          {{ $bill->waterUsage->billingPeriod->period_name }}
                        </h4>
                        <p class="text-sm text-gray-600">
                          Pemakaian: {{ $bill->waterUsage->total_usage_m3 }} m³
                          ({{ number_format($bill->waterUsage->initial_meter) }} →
                          {{ number_format($bill->waterUsage->final_meter) }})
                        </p>
                      </div>
                      <div class="mt-3 md:mt-0">
                        <div class="text-right">
                          <p class="text-2xl font-bold text-blue-600">Rp {{ number_format($bill->total_amount) }}</p>
                          <div class="flex items-center justify-end mt-1">
                            <span
                              class="px-3 py-1 rounded-full text-xs font-medium {{ $bill->status === 'overdue' ? 'bg-red-100 text-red-800' : ($bill->status === 'pending' ? 'bg-purple-100 text-purple-800' : 'bg-yellow-100 text-yellow-800') }}">
                              {{ match ($bill->status) {
                                  'overdue' => 'Terlambat',
                                  'pending' => 'Menunggu Pembayaran',
                                  default => 'Belum Bayar',
                              } }}
                            </span>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- Bill Details -->
                  <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                      <!-- Usage Breakdown -->
                      <div>
                        <h5 class="font-semibold text-gray-800 mb-3 flex items-center">
                          <svg class="w-5 h-5 mr-2 text-blue-500" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z">
                            </path>
                          </svg>
                          Rincian Biaya
                        </h5>

                        @php
                          $breakdown = \App\Models\WaterTariff::calculateBill(
                              $bill->waterUsage->total_usage_m3,
                              $bill->waterUsage->customer->village_id,
                          );
                        @endphp

                        @if (count($breakdown['breakdown']) > 1)
                          <div class="tier-breakdown p-4 rounded-lg mb-4">
                            <h6 class="text-sm font-medium text-blue-800 mb-2">Perhitungan Tarif Progresif:</h6>
                            @foreach ($breakdown['breakdown'] as $tier)
                              <div class="flex justify-between text-sm text-blue-700 mb-1">
                                <span>{{ $tier['usage'] }}m³ × Rp{{ number_format($tier['rate']) }}</span>
                                <span class="font-medium">Rp{{ number_format($tier['charge']) }}</span>
                              </div>
                            @endforeach
                          </div>
                        @endif

                        <div class="space-y-2">
                          <div class="flex justify-between items-center py-2 border-b border-gray-100">
                            <span class="text-gray-600">Biaya Air</span>
                            <span class="font-semibold">Rp {{ number_format($bill->water_charge) }}</span>
                          </div>
                          <div class="flex justify-between items-center py-2 border-b border-gray-100">
                            <span class="text-gray-600">Biaya Admin</span>
                            <span class="font-semibold">Rp {{ number_format($bill->admin_fee) }}</span>
                          </div>
                          <div class="flex justify-between items-center py-2 border-b border-gray-100">
                            <span class="text-gray-600">Biaya Pemeliharaan</span>
                            <span class="font-semibold">Rp {{ number_format($bill->maintenance_fee) }}</span>
                          </div>
                          <div class="flex justify-between items-center py-3 bg-blue-50 px-3 rounded-lg">
                            <span class="font-bold text-blue-800">Total Tagihan</span>
                            <span class="font-bold text-blue-800 text-lg">Rp
                              {{ number_format($bill->total_amount) }}</span>
                          </div>
                        </div>
                      </div>

                      <!-- Due Date & Status -->
                      <div>
                        <h5 class="font-semibold text-gray-800 mb-3 flex items-center">
                          <svg class="w-5 h-5 mr-2 text-orange-500" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                          </svg>
                          Informasi Jatuh Tempo
                        </h5>

                        <div class="bg-gray-50 p-4 rounded-lg">
                          <div class="text-center mb-4">
                            <p class="text-sm text-gray-600 mb-1">Jatuh Tempo</p>
                            <p
                              class="text-xl font-bold {{ $bill->due_date->isPast() ? 'text-red-600' : 'text-gray-800' }}">
                              {{ $bill->due_date->format('d F Y') }}
                            </p>
                            @if ($bill->is_overdue)
                              <div class="mt-2 px-3 py-1 bg-red-100 text-red-800 rounded-full text-sm font-medium">
                                ⚠️ Terlambat {{ $bill->days_overdue }} hari
                              </div>
                            @else
                              @php
                                $daysUntilDue = now()->diffInDays($bill->due_date, false);
                              @endphp
                              @if ($daysUntilDue >= 0)
                                <div class="mt-2 px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-sm font-medium">
                                  {{ $daysUntilDue }} hari lagi
                                </div>
                              @endif
                            @endif
                          </div>

                          @if ($bill->status === 'pending')
                            <div class="bg-purple-50 border border-purple-200 rounded-lg p-3">
                              <div class="flex items-center text-purple-800">
                                <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                  <path fill-rule="evenodd"
                                    d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z"
                                    clip-rule="evenodd"></path>
                                </svg>
                                <div>
                                  <p class="font-medium">Pembayaran Sedang Diproses</p>
                                  <p class="text-sm text-purple-600">Silakan selesaikan pembayaran</p>
                                </div>
                              </div>
                            </div>
                          @endif
                        </div>
                      </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex flex-col sm:flex-row gap-3">
                      @if ($bill->status === 'pending')
                        <a href="{{ route('tripay.form', ['village' => $villageSlug ?? 'default', 'bill' => $bill->bill_id]) }}"
                          class="btn-primary text-white py-3 px-6 rounded-xl font-semibold flex items-center justify-center text-center">
                          <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                          </svg>
                          Lanjutkan Pembayaran
                        </a>
                      @else
                        @php
                          $variable = \App\Models\Variable::where('village_id', $customer->village_id)->first();
                          $tripayConfigured = $variable && ($variable->tripay_use_main || $variable->isConfigured());
                        @endphp

                        @if ($tripayConfigured)
                          <a href="{{ route('tripay.form', ['village' => $villageSlug, 'bill' => $bill->bill_id]) }}"
                            class="btn-success text-white py-3 px-6 rounded-xl font-semibold flex items-center justify-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 4v1m6 11h2m-6 0h-2v4m-2 0h-2m2-4v-3m2 3V9l-6 6 2-2z"></path>
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 4h4v4H4V4zm8 0h4v4h-4V4zm-8 8h4v4H4v-4zm8 8h4v4h-4v-4z"></path>
                            </svg>
                            Bayar dengan QRIS
                          </a>
                        @else
                          <div class="text-center p-3 bg-red-50 text-red-600 rounded-xl text-sm">
                            Pembayaran QRIS tidak tersedia untuk desa ini
                          </div>
                        @endif
                      @endif

                      <a href="{{ route('receipt.bill', ['bill' => $bill->bill_id, 'customer_code' => $customer->customer_code]) }}"
                        target="_blank"
                        class="btn-secondary text-white py-3 px-6 rounded-xl font-semibold flex items-center justify-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                          </path>
                        </svg>
                        Lihat Tagihan
                      </a>
                    </div>
                  </div>
                </div>
              @endforeach
            </div>
          </div>
        </div>
      @else
        <!-- No Bills Message -->
        <div class="card-gradient rounded-2xl shadow-xl p-12 text-center mb-8 animate-fade-in">
          <div class="w-24 h-24 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-6">
            <svg class="w-12 h-12 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
          </div>
          <h3 class="text-2xl font-bold text-gray-800 mb-3">Tidak Ada Tagihan Tertunggak</h3>
          <p class="text-gray-600 text-lg mb-6">Selamat! Semua tagihan Anda sudah lunas.</p>
          <div class="bg-green-50 border border-green-200 rounded-xl p-4 inline-block">
            <p class="text-green-800 font-medium">✨ Terima kasih atas ketepatan pembayaran Anda!</p>
          </div>
        </div>
      @endif

      @if ($paidBills->count() > 0)
        <!-- Payment History Section -->
        <div class="card-gradient rounded-2xl shadow-xl overflow-hidden animate-slide-up">
          <div class="bg-gradient-to-r from-green-500 to-emerald-500 px-6 py-4 text-white">
            <div class="flex items-center justify-between">
              <div>
                <h3 class="text-xl font-bold mb-1">Riwayat Pembayaran</h3>
                <p class="text-green-100">{{ $paidBills->count() }} pembayaran terakhir</p>
              </div>
              <div class="w-12 h-12 bg-white bg-opacity-20 rounded-xl flex items-center justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
              </div>
            </div>
          </div>

          <div class="p-6">
            <div class="space-y-4">
              @foreach ($paidBills as $index => $bill)
                <div class="bg-white rounded-xl shadow-md border border-gray-100 p-6 card-hover"
                  style="animation-delay: {{ $index * 0.1 }}s">
                  <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                    <div class="mb-4 md:mb-0">
                      <div class="flex items-center mb-2">
                        <div class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center mr-3">
                          <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7">
                            </path>
                          </svg>
                        </div>
                        <div>
                          <h4 class="font-bold text-gray-800">{{ $bill->waterUsage->billingPeriod->period_name }}</h4>
                          <p class="text-sm text-gray-600">{{ $bill->waterUsage->total_usage_m3 }} m³ • Dibayar
                            {{ $bill->payment_date->format('d/m/Y') }}</p>
                        </div>
                      </div>

                      <div class="flex items-center space-x-4 text-sm text-gray-600">
                        <span>Rp {{ number_format($bill->total_amount) }}</span>
                        @if ($bill->latestPayment)
                          <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded-full text-xs font-medium">
                            {{ match ($bill->latestPayment->payment_method) {
                                'cash' => 'Tunai',
                                'transfer' => 'Transfer',
                                'qris' => 'QRIS',
                                default => 'Lainnya',
                            } }}
                          </span>
                        @endif
                        @if ($bill->latestPayment && $bill->latestPayment->collector_name)
                          <span class="text-xs">Petugas: {{ $bill->latestPayment->collector_name }}</span>
                        @endif
                      </div>
                    </div>

                    <div class="flex items-center space-x-3">
                      <span class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-sm font-medium">
                        ✓ Lunas
                      </span>
                      <a href="{{ route('receipt.bill', ['bill' => $bill->bill_id, 'customer_code' => $customer->customer_code]) }}"
                        target="_blank"
                        class="btn-secondary text-white py-2 px-4 rounded-lg text-sm font-medium flex items-center">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z">
                          </path>
                        </svg>
                        Kwitansi
                      </a>
                    </div>
                  </div>
                </div>
              @endforeach
            </div>

            @if ($paidBills->count() >= 10)
              <div class="mt-6 p-4 bg-blue-50 border border-blue-200 rounded-xl text-center">
                <p class="text-blue-700 text-sm">
                  📄 Menampilkan 10 pembayaran terakhir. Untuk riwayat lengkap, hubungi kantor desa.
                </p>
              </div>
            @endif
          </div>
        </div>
      @endif

      <!-- Water Tariff Information -->
      <div class="mt-8 card-gradient rounded-2xl shadow-xl overflow-hidden animate-slide-up">
        <div class="bg-gradient-to-r from-blue-500 to-cyan-500 px-6 py-4 text-white">
          <div class="flex items-center justify-between">
            <div>
              <h3 class="text-xl font-bold mb-1">Tarif Air {{ $villageName ?? 'Desa' }}</h3>
              <p class="text-blue-100">Struktur tarif air berdasarkan pemakaian per bulan</p>
            </div>
            <div class="w-12 h-12 bg-white bg-opacity-20 rounded-xl flex items-center justify-center">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z">
                </path>
              </svg>
            </div>
          </div>
        </div>

        <div class="p-6">
          @php
            $villageTariffs = \App\Models\WaterTariff::where('village_id', $customer->village_id)
                ->where('is_active', true)
                ->orderBy('usage_min')
                ->get();

            $villageModel = \App\Models\Village::find($customer->village_id);
            $adminFee = $villageModel?->getDefaultAdminFee() ?? 5000;
            $maintenanceFee = $villageModel?->getDefaultMaintenanceFee() ?? 2000;

            $maxRange = $villageTariffs->max('usage_max');
            $exampleUsage = $maxRange ? min($maxRange + 5, 40) : 30;

            $exampleCalculation = [];
            $remainingUsage = $exampleUsage;

            foreach ($villageTariffs as $tariff) {
                if ($remainingUsage <= 0) {
                    break;
                }

                $tierMin = $tariff->usage_min;
                $tierMax = $tariff->usage_max;
                $tierUsage = 0;

                if ($exampleUsage >= $tierMin) {
                    if ($tierMax === null) {
                        $tierUsage = $remainingUsage;
                    } else {
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

          <div class="overflow-x-auto mb-6">
            <table class="w-full">
              <thead>
                <tr class="bg-gray-50">
                  <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700 rounded-tl-lg">Rentang Pemakaian
                  </th>
                  <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Tarif per m³</th>
                  <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700 rounded-tr-lg">Contoh Biaya</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100">
                @forelse($villageTariffs as $index => $tariff)
                  <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-4 py-4">
                      <div class="flex items-center">
                        <div class="w-3 h-3 bg-blue-500 rounded-full mr-3"></div>
                        <span class="font-medium text-gray-800">{{ $tariff->usage_range }}</span>
                      </div>
                    </td>
                    <td class="px-4 py-4">
                      <span class="text-lg font-bold text-blue-600">Rp
                        {{ number_format($tariff->price_per_m3) }}</span>
                    </td>
                    <td class="px-4 py-4">
                      @if (isset($exampleCalculation[$tariff->tariff_id]))
                        <div class="bg-blue-50 px-3 py-2 rounded-lg">
                          <span class="text-blue-800 font-medium">
                            {{ $exampleCalculation[$tariff->tariff_id]['usage'] }}m³ ×
                            Rp{{ number_format($tariff->price_per_m3) }} =
                            Rp{{ number_format($exampleCalculation[$tariff->tariff_id]['cost']) }}
                          </span>
                        </div>
                      @else
                        <span class="text-gray-400 text-sm">Tidak terpakai</span>
                      @endif
                    </td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="3" class="px-4 py-8 text-center text-gray-500">
                      Tarif belum tersedia
                    </td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>

          <!-- Example Calculation -->
          @if (!empty($exampleCalculation))
            <div class="bg-gradient-to-r from-blue-50 to-cyan-50 p-6 rounded-xl border border-blue-200">
              <h4 class="font-bold text-blue-800 mb-4 flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z">
                  </path>
                </svg>
                Contoh Perhitungan untuk {{ $exampleUsage }}m³
              </h4>

              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                  <h5 class="font-medium text-blue-700 mb-3">Rincian Biaya Air:</h5>
                  <div class="space-y-2">
                    @foreach ($exampleCalculation as $calc)
                      <div class="flex justify-between text-sm">
                        <span class="text-blue-600">{{ $calc['usage'] }}m³</span>
                        <span class="font-medium text-blue-800">Rp {{ number_format($calc['cost']) }}</span>
                      </div>
                    @endforeach
                  </div>
                </div>

                <div>
                  <h5 class="font-medium text-blue-700 mb-3">Total Tagihan:</h5>
                  @php $totalWaterCost = collect($exampleCalculation)->sum('cost'); @endphp
                  <div class="space-y-2">
                    <div class="flex justify-between text-sm">
                      <span class="text-blue-600">Biaya Air</span>
                      <span class="font-medium">Rp {{ number_format($totalWaterCost) }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                      <span class="text-blue-600">Biaya Admin</span>
                      <span class="font-medium">Rp {{ number_format($adminFee) }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                      <span class="text-blue-600">Biaya Pemeliharaan</span>
                      <span class="font-medium">Rp {{ number_format($maintenanceFee) }}</span>
                    </div>
                    <div class="border-t border-blue-300 pt-2">
                      <div class="flex justify-between">
                        <span class="font-bold text-blue-800">Total</span>
                        <span class="font-bold text-blue-800 text-lg">Rp
                          {{ number_format($totalWaterCost + $adminFee + $maintenanceFee) }}</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <div class="mt-4 p-3 bg-blue-100 rounded-lg">
                <p class="text-sm text-blue-800">
                  <strong>💡 Catatan:</strong> Tarif berlaku progresif - pemakaian lebih tinggi dikenakan tarif yang
                  lebih tinggi.
                  Contoh di atas menggunakan pemakaian {{ $exampleUsage }}m³ yang mencakup semua rentang tarif.
                </p>
              </div>
            </div>
          @endif
        </div>
      </div>

    </div>
  </main>

  <!-- Alert Messages -->
  <div id="alert-container" class="fixed top-4 right-4 z-50 space-y-2"></div>

  @if (session('error'))
    <div id="error-alert"
      class="fixed top-4 right-4 bg-red-100 border-l-4 border-red-500 text-red-700 px-6 py-4 rounded-lg shadow-lg z-50 max-w-md">
      <div class="flex items-center">
        <svg class="w-5 h-5 mr-3" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd"
            d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
            clip-rule="evenodd"></path>
        </svg>
        <div>
          <p class="font-medium">{{ session('error') }}</p>
        </div>
        <button onclick="closeAlert('error-alert')" class="ml-4 text-red-500 hover:text-red-700">
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
      class="fixed top-4 right-4 bg-green-100 border-l-4 border-green-500 text-green-700 px-6 py-4 rounded-lg shadow-lg z-50 max-w-md">
      <div class="flex items-center">
        <svg class="w-5 h-5 mr-3" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd"
            d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
            clip-rule="evenodd"></path>
        </svg>
        <div>
          <p class="font-medium">{{ session('success') }}</p>
        </div>
        <button onclick="closeAlert('success-alert')" class="ml-4 text-green-500 hover:text-green-700">
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
    function closeAlert(alertId) {
      const alert = document.getElementById(alertId);
      if (alert) {
        alert.style.transform = 'translateX(100%)';
        alert.style.opacity = '0';
        setTimeout(() => {
          alert.remove();
        }, 300);
      }
    }

    // Auto-hide alerts after 5 seconds
    setTimeout(() => {
      const alerts = document.querySelectorAll('[id$="-alert"]');
      alerts.forEach(alert => {
        if (alert) closeAlert(alert.id);
      });
    }, 5000);

    // Add smooth scrolling
    document.addEventListener('DOMContentLoaded', function() {
      // Animate cards on scroll
      const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
      };

      const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
          if (entry.isIntersecting) {
            entry.target.style.opacity = '1';
            entry.target.style.transform = 'translateY(0)';
          }
        });
      }, observerOptions);

      // Observe all cards
      document.querySelectorAll('.card-hover').forEach(card => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        card.style.transition = 'all 0.6s ease-out';
        observer.observe(card);
      });

      // Add loading states to payment buttons
      document.querySelectorAll('a[href*="tripay"]').forEach(button => {
        button.addEventListener('click', function() {
          const originalContent = this.innerHTML;
          this.innerHTML = `
            <svg class="animate-spin w-5 h-5 mr-2" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            Memproses...
          `;
          this.style.pointerEvents = 'none';

          // Restore after 3 seconds if still on page
          setTimeout(() => {
            this.innerHTML = originalContent;
            this.style.pointerEvents = 'auto';
          }, 3000);
        });
      });
    });

    // Add dynamic alert function
    function showAlert(type, message, duration = 5000) {
      const alertContainer = document.getElementById('alert-container');
      const alertId = 'alert-' + Date.now();

      const colors = {
        success: 'bg-green-100 border-green-500 text-green-700',
        error: 'bg-red-100 border-red-500 text-red-700',
        warning: 'bg-yellow-100 border-yellow-500 text-yellow-700',
        info: 'bg-blue-100 border-blue-500 text-blue-700'
      };

      const alert = document.createElement('div');
      alert.id = alertId;
      alert.className =
        `${colors[type]} border-l-4 px-6 py-4 rounded-lg shadow-lg max-w-md transform translate-x-full transition-transform duration-300`;
      alert.innerHTML = `
        <div class="flex items-center">
          <div class="flex-1">${message}</div>
          <button onclick="closeAlert('${alertId}')" class="ml-4 opacity-70 hover:opacity-100">
            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
            </svg>
          </button>
        </div>
      `;

      alertContainer.appendChild(alert);

      // Animate in
      setTimeout(() => {
        alert.style.transform = 'translateX(0)';
      }, 100);

      // Auto remove
      if (duration > 0) {
        setTimeout(() => {
          closeAlert(alertId);
        }, duration);
      }
    }
  </script>
</body>

</html>
