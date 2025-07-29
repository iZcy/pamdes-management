<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Tagihan {{ $customer->name }} - PAMDes {{ $village['name'] ?? '' }}</title>
  <link rel="icon" type="image/x-icon" href="{{ $villageModel->getFaviconUrl() }}">
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

    /* New styles for due date header */
    .due-date-header {
      background: linear-gradient(135deg, rgba(255, 255, 255, 0.15) 0%, rgba(255, 255, 255, 0.05) 100%);
      backdrop-filter: blur(10px);
      border: 1px solid rgba(255, 255, 255, 0.1);
    }

    .pulse-warning {
      animation: pulseWarning 2s ease-in-out infinite;
    }

    @keyframes pulseWarning {

      0%,
      100% {
        box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7);
      }

      50% {
        box-shadow: 0 0 0 10px rgba(239, 68, 68, 0);
      }
    }

    /* Bundle Payment Checkbox Styles */
    .checkbox-display {
      transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .checkbox-display:hover {
      transform: scale(1.05);
      box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    .bill-card {
      transition: all 0.3s ease;
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
            <p class="text-lg font-bold text-gray-800">{{ $totalUnpaidBills }} tagihan</p>
          </div>
        </div>
      </div>

      @php
        // Bills are now already separated:
        // $bills = unpaid/overdue bills (for selection)
        // $pendingBills = pending bills (for continue/status)
        $payableBills = $bills; // All bills in $bills are now payable
      @endphp

      @if ($pendingBills->count() > 0 || (isset($pendingBundles) && $pendingBundles->count() > 0))
        <!-- Pending Transactions Section -->
        <div class="card-gradient rounded-2xl shadow-xl overflow-hidden mb-8 animate-slide-up">
          <!-- Header -->
          <div class="bg-gradient-to-r from-purple-500 to-indigo-500 px-6 py-4 text-white">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between">
              <div class="mb-4 md:mb-0">
                <h3 class="text-xl font-bold mb-1 flex items-center">
                  <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                  </svg>
                  Transaksi Sedang Diproses
                </h3>
                @php
                  $totalPendingTransactions = $pendingBills->count() + (isset($pendingBundles) ? $pendingBundles->count() : 0);
                @endphp
                <p class="text-purple-100">{{ $totalPendingTransactions }} pembayaran menunggu konfirmasi</p>
              </div>
              @php
                $totalPendingAmount = $pendingBills->sum('total_amount') + (isset($pendingBundles) ? $pendingBundles->sum('total_amount') : 0);
              @endphp
              <div class="text-right">
                <p class="text-sm text-purple-100">Total Pending</p>
                <p class="text-2xl font-bold">Rp {{ number_format($totalPendingAmount) }}</p>
              </div>
            </div>
          </div>

          <!-- Pending Bills List -->
          <div class="p-6">
            <div class="bg-purple-50 border border-purple-200 rounded-lg p-4 mb-4">
              <div class="flex items-center">
                <svg class="w-5 h-5 text-purple-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <p class="text-purple-800 font-medium">Pembayaran di bawah ini sedang dalam proses. Mohon tunggu konfirmasi atau cek status pembayaran Anda.</p>
              </div>
            </div>

            <div class="space-y-4">
              {{-- Individual Pending Bills --}}
              @foreach ($pendingBills as $bill)
                <div class="bg-white rounded-lg border-2 border-purple-200 p-4">
                  <div class="flex items-center justify-between">
                    <div class="flex-1">
                      <div class="flex items-center mb-2">
                        <div class="w-8 h-8 bg-purple-100 rounded-lg flex items-center justify-center mr-3">
                          <svg class="w-4 h-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                          </svg>
                        </div>
                        <div>
                          <h4 class="font-bold text-gray-800">{{ $bill->waterUsage->billingPeriod->period_name }}</h4>
                          <p class="text-sm text-gray-600">{{ $bill->waterUsage->total_usage_m3 }} m³ • Sedang diproses</p>
                        </div>
                      </div>
                    </div>
                    <div class="text-right">
                      <span class="text-lg font-bold text-purple-600">Rp {{ number_format($bill->total_amount) }}</span>
                      <div class="mt-2 space-y-2">
                        <div>
                          <span class="px-3 py-1 bg-purple-100 text-purple-800 rounded-full text-sm font-medium">
                            ⏳ Menunggu Pembayaran
                          </span>
                        </div>
                        <!-- Individual Payment - Continue -->
                        <a href="{{ route('tripay.form', ['bill' => $bill->bill_id]) }}"
                          class="inline-flex items-center px-3 py-1.5 bg-purple-600 hover:bg-purple-700 text-white text-xs font-medium rounded-lg transition-colors duration-200">
                          <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m-2 0h-2m2-4v-3m2 3V9l-6 6 2-2z"></path>
                          </svg>
                          Lanjutkan Pembayaran
                        </a>
                      </div>
                    </div>
                  </div>
                </div>
              @endforeach

              {{-- Pending Bundle Payments --}}
              @if(isset($pendingBundles))
                @foreach ($pendingBundles as $bundle)
                  <div class="bg-white rounded-lg border-2 border-indigo-200 p-4">
                    <div class="flex items-center justify-between">
                      <div class="flex-1">
                        <div class="flex items-center mb-2">
                          <div class="w-8 h-8 bg-indigo-100 rounded-lg flex items-center justify-center mr-3">
                            <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                            </svg>
                          </div>
                          <div>
                            <h4 class="font-bold text-gray-800 flex items-center">
                              Bundle Payment
                              <span class="ml-2 px-2 py-0.5 bg-indigo-100 text-indigo-800 rounded-full text-xs font-medium">
                                {{ $bundle->bill_count }} tagihan
                              </span>
                            </h4>
                            <p class="text-sm text-gray-600">
                              @php
                                $periods = $bundle->bills->map(function($bill) {
                                  return $bill->waterUsage->billingPeriod->period_name;
                                })->unique()->take(2);
                              @endphp
                              {{ $periods->implode(', ') }}
                              @if($bundle->bills->count() > 2)
                                + {{ $bundle->bills->count() - 2 }} lainnya
                              @endif
                              • Sedang diproses
                            </p>
                          </div>
                        </div>
                      </div>
                      <div class="text-right">
                        <span class="text-lg font-bold text-indigo-600">Rp {{ number_format($bundle->total_amount) }}</span>
                        <div class="mt-2 space-y-2">
                          <div>
                            <span class="px-3 py-1 bg-indigo-100 text-indigo-800 rounded-full text-sm font-medium">
                              📦 Bundle Payment
                            </span>
                          </div>
                          <!-- Bundle Payment - Continue -->
                          <a href="{{ route('tripay.bundle.form', ['paymentId' => $bundle->payment_id]) }}"
                            class="inline-flex items-center px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-medium rounded-lg transition-colors duration-200">
                            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m-2 0h-2m2-4v-3m2 3V9l-6 6 2-2z"></path>
                            </svg>
                            Lanjutkan Bundle Payment
                          </a>
                        </div>
                      </div>
                    </div>
                  </div>
                @endforeach
              @endif
            </div>
          </div>
        </div>
      @endif

      @if ($payableBills->count() > 0)
        <!-- Outstanding Bills Section - Checkout Style Layout -->
        <div class="card-gradient rounded-2xl shadow-xl overflow-hidden mb-8 animate-slide-up">
          <!-- Header -->
          <div class="bg-gradient-to-r from-orange-500 to-red-500 px-6 py-4 text-white">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between">
              <div class="mb-4 md:mb-0">
                <h3 class="text-xl font-bold mb-1">Tagihan Menunggu Pembayaran</h3>
                <p class="text-orange-100">{{ $payableBills->count() }} tagihan belum dibayar</p>
              </div>
              @php
                $totalOutstanding = $payableBills->sum('total_amount');
              @endphp
              <div class="text-right">
                <p class="text-sm text-orange-100">Total Outstanding</p>
                <p class="text-2xl font-bold">Rp {{ number_format($totalOutstanding) }}</p>
              </div>
            </div>
          </div>

          <!-- 2-Column Checkout Layout -->
          <div class="grid grid-cols-1 lg:grid-cols-3 gap-0">
            <style>
              /* Mobile responsive adjustments */
              @media (max-width: 1024px) {
                .lg\\:col-span-2 {
                  border-right: none !important;
                  border-bottom: 1px solid #e5e7eb;
                }
                .lg\\:col-span-1 {
                  background: white !important;
                  border-top: 1px solid #e5e7eb;
                }
                .sticky {
                  position: static !important;
                }
              }
            </style>
            <!-- Left Column: Bills List (2/3 width) -->
            <div class="lg:col-span-2 p-6 border-r border-gray-200">
              <!-- Payment Instructions -->
              <div class="bg-gradient-to-r from-blue-500 to-purple-500 rounded-xl p-4 mb-6 text-white">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                  <div class="flex-1">
                    <div class="flex items-center mb-2">
                      <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                      </svg>
                      <h4 class="font-bold">Pembayaran QRIS</h4>
                    </div>
                    <p class="text-blue-100 text-sm">Pilih tagihan yang ingin dibayar, lalu klik tombol bayar</p>
                  </div>
                  <div class="flex flex-col sm:flex-row gap-2">
                    <button id="selectAllBills" 
                      class="bg-white bg-opacity-20 hover:bg-opacity-30 text-white py-2 px-3 rounded-lg text-sm font-medium transition-all duration-200 flex items-center justify-center">
                      <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                      </svg>
                      <span>Pilih Semua</span>
                    </button>
                  </div>
                </div>
              </div>

              <!-- Bills List Header -->
              <div class="flex items-center justify-between mb-4">
                <h4 class="font-semibold text-gray-800 flex items-center">
                  <svg class="w-5 h-5 mr-2 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                  </svg>
                  Daftar Tagihan
                </h4>
                <span class="text-sm text-gray-500">{{ $payableBills->count() }} item</span>
              </div>

              <!-- Scrollable Bills Container -->
              <div class="max-h-[600px] overflow-y-auto space-y-4 pr-2 scrollable-bills">
                <style>
                  .scrollable-bills::-webkit-scrollbar {
                    width: 6px;
                  }
                  .scrollable-bills::-webkit-scrollbar-track {
                    background: #f1f5f9;
                    border-radius: 3px;
                  }
                  .scrollable-bills::-webkit-scrollbar-thumb {
                    background: #cbd5e1;
                    border-radius: 3px;
                  }
                  .scrollable-bills::-webkit-scrollbar-thumb:hover {
                    background: #94a3b8;
                  }
                </style>
              @foreach ($payableBills as $index => $bill)
                <!-- Compact Bill Card for Checkout Style -->
                <div class="bg-white rounded-lg border border-gray-200 p-4 bill-card cursor-pointer hover:border-blue-300 hover:shadow-md transition-all duration-200"
                  data-bill-id="{{ $bill->bill_id }}" data-bill-amount="{{ $bill->total_amount }}"
                  data-bill-period="{{ $bill->waterUsage->billingPeriod->period_name }}"
                  data-water-charge="{{ $bill->water_charge }}"
                  data-admin-fee="{{ $bill->admin_fee }}"
                  data-maintenance-fee="{{ $bill->maintenance_fee }}">
                  
                  <div class="flex items-start gap-4">
                    <!-- Payment Selection Checkbox -->
                    <div class="flex items-center mt-1">
                      <label class="inline-flex items-center cursor-pointer">
                        <input type="checkbox" class="bill-checkbox hidden" data-bill-id="{{ $bill->bill_id }}">
                        <div class="checkbox-display w-5 h-5 border-2 border-gray-300 rounded bg-white flex items-center justify-center hover:border-blue-500 transition-all duration-200">
                          <svg class="checkmark w-3 h-3 text-white opacity-0 transition-opacity duration-200" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                          </svg>
                        </div>
                      </label>
                    </div>

                    <!-- Bill Info -->
                    <div class="flex-1">
                      <div class="flex items-center justify-between mb-2">
                        <h4 class="font-semibold text-gray-800">{{ $bill->waterUsage->billingPeriod->period_name }}</h4>
                        <span class="text-lg font-bold text-blue-600">Rp {{ number_format($bill->total_amount) }}</span>
                      </div>
                      
                      <div class="flex items-center justify-between text-sm text-gray-600 mb-2">
                        <span>{{ $bill->waterUsage->total_usage_m3 }} m³</span>
                        <span class="px-2 py-1 rounded-full text-xs font-medium {{ $bill->status === 'overdue' ? 'bg-red-100 text-red-800' : ($bill->status === 'pending' ? 'bg-purple-100 text-purple-800' : 'bg-yellow-100 text-yellow-800') }}">
                          {{ match ($bill->status) {
                              'overdue' => 'Terlambat',
                              'pending' => 'Menunggu Pembayaran',
                              default => 'Belum Bayar',
                          } }}
                        </span>
                      </div>

                      <!-- Due Date -->
                      @if ($bill->due_date)
                        <div class="flex items-center text-xs text-gray-500">
                          <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                          </svg>
                          Jatuh tempo: {{ $bill->due_date->format('d M Y') }}
                          @if ($bill->is_overdue)
                            @php
                              $daysOverdue = now()->startOfDay()->diffInDays($bill->due_date->startOfDay());
                            @endphp
                            <span class="text-red-600 font-medium ml-1">({{ $daysOverdue }} hari terlambat)</span>
                          @else
                            @php
                              $daysUntilDue = $bill->due_date->startOfDay()->diffInDays(now()->startOfDay());
                            @endphp
                            @if ($daysUntilDue > 0)
                              <span class="text-orange-600 font-medium ml-1">({{ $daysUntilDue }} hari lagi)</span>
                            @endif
                          @endif
                        </div>
                      @endif

                      <!-- Expandable Details -->
                      <div class="mt-3">
                        <button class="text-blue-600 text-sm hover:text-blue-800 flex items-center bill-details-toggle" data-bill-id="{{ $bill->bill_id }}">
                          <span>Lihat detail</span>
                          <svg class="w-4 h-4 ml-1 transform transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                          </svg>
                        </button>
                        
                        <!-- Collapsible Details -->
                        <div class="bill-details hidden mt-3 p-4 bg-gray-50 rounded-lg border" id="details-{{ $bill->bill_id }}">
                          <!-- Usage Information -->
                          <div class="mb-4 pb-3 border-b border-gray-200">
                            <h5 class="font-medium text-gray-800 mb-2">Informasi Pemakaian</h5>
                            <div class="grid grid-cols-2 gap-3 text-sm">
                              <div class="flex justify-between">
                                <span class="text-gray-600">Periode:</span>
                                <span class="font-medium">{{ $bill->waterUsage->billingPeriod->period_name }}</span>
                              </div>
                              <div class="flex justify-between">
                                <span class="text-gray-600">Pemakaian:</span>
                                <span class="font-medium">{{ $bill->waterUsage->total_usage_m3 }} m³</span>
                              </div>
                              <div class="flex justify-between">
                                <span class="text-gray-600">Meter Awal:</span>
                                <span class="font-medium">{{ number_format($bill->waterUsage->initial_meter) }}</span>
                              </div>
                              <div class="flex justify-between">
                                <span class="text-gray-600">Meter Akhir:</span>
                                <span class="font-medium">{{ number_format($bill->waterUsage->final_meter) }}</span>
                              </div>
                            </div>
                          </div>
                          
                          <!-- Calculation Process -->
                          <div class="mb-4 pb-3 border-b border-gray-200">
                            <h5 class="font-medium text-gray-800 mb-2">Proses Perhitungan</h5>
                            <div class="space-y-2 text-sm">
                              @php
                                $usage = $bill->waterUsage->total_usage_m3;
                                $baseCost = $bill->water_charge;
                                $adminFee = $bill->admin_fee;
                                $maintenanceFee = $bill->maintenance_fee;
                              @endphp
                              
                              <!-- Water Charge Calculation -->
                              <div class="bg-white p-3 rounded border-l-4 border-blue-500">
                                <div class="flex justify-between items-center mb-2">
                                  <span class="text-gray-700 font-medium">Biaya Air ({{ $usage }} m³):</span>
                                  <span class="font-bold text-blue-600">Rp {{ number_format($baseCost) }}</span>
                                </div>
                                
                                <!-- Tariff Breakdown -->
                                @php
                                  $tariffs = \App\Models\WaterTariff::where('village_id', $bill->waterUsage->customer->village_id)
                                    ->where('is_active', true)
                                    ->orderBy('usage_min')
                                    ->get();
                                  
                                  $remainingUsage = $usage;
                                  $totalCalculated = 0;
                                @endphp
                                
                                @if($tariffs->count() > 0 && $usage > 0)
                                  <div class="text-xs space-y-1 mt-2 pl-2 border-l-2 border-blue-200">
                                    <div class="font-medium text-gray-600 mb-1">Perhitungan Tarif Progresif:</div>
                                    @foreach($tariffs as $tariff)
                                      @php
                                        if ($remainingUsage <= 0) break;
                                        
                                        $tierMin = $tariff->usage_min;
                                        $tierMax = $tariff->usage_max;
                                        $rate = $tariff->price_per_m3;
                                        
                                        // Calculate usage for this tier
                                        $usageInTier = 0;
                                        
                                        if ($usage > $tierMin) {
                                          if ($tierMax !== null) {
                                            // Has upper limit
                                            $tierCapacity = $tierMax - $tierMin + 1;
                                            $usageInTier = min($remainingUsage, $tierCapacity);
                                          } else {
                                            // Unlimited tier (highest tier)
                                            $usageInTier = $remainingUsage;
                                          }
                                        }
                                        
                                        $tierCost = $usageInTier * $rate;
                                        $remainingUsage -= $usageInTier;
                                        $totalCalculated += $tierCost;
                                      @endphp
                                      
                                      @if($usageInTier > 0)
                                        <div class="flex justify-between items-center py-1">
                                          <span class="text-gray-600">
                                            • {{ $tierMin }}{{ $tariff->usage_max ? '-'.$tariff->usage_max : '+' }} m³: 
                                            {{ number_format($usageInTier) }} m³ × Rp {{ number_format($rate) }}
                                          </span>
                                          <span class="text-blue-600 font-medium">Rp {{ number_format($tierCost) }}</span>
                                        </div>
                                      @endif
                                    @endforeach
                                    
                                    @if($totalCalculated != $baseCost)
                                      <div class="text-xs text-orange-600 mt-1">
                                        * Tarif mungkin telah berubah sejak tagihan dibuat
                                      </div>
                                    @endif
                                  </div>
                                @elseif($usage > 0)
                                  <div class="text-xs text-gray-500 mt-1">
                                    ≈ Rp {{ number_format($baseCost / $usage) }} per m³ (rata-rata)
                                  </div>
                                @endif
                              </div>
                              
                              <!-- Admin Fee -->
                              <div class="bg-white p-2 rounded border-l-4 border-green-500">
                                <div class="flex justify-between items-center">
                                  <span class="text-gray-700">Biaya Admin:</span>
                                  <span class="font-medium text-green-600">Rp {{ number_format($adminFee) }}</span>
                                </div>
                                <div class="text-xs text-gray-500 mt-1">Biaya administrasi tetap</div>
                              </div>
                              
                              <!-- Maintenance Fee -->
                              <div class="bg-white p-2 rounded border-l-4 border-orange-500">
                                <div class="flex justify-between items-center">
                                  <span class="text-gray-700">Biaya Pemeliharaan:</span>
                                  <span class="font-medium text-orange-600">Rp {{ number_format($maintenanceFee) }}</span>
                                </div>
                                <div class="text-xs text-gray-500 mt-1">Biaya perawatan infrastruktur</div>
                              </div>
                            </div>
                          </div>
                          
                          <!-- Total Calculation -->
                          <div class="bg-blue-50 p-3 rounded-lg">
                            <div class="flex justify-between items-center text-sm mb-2">
                              <span class="text-gray-700">Subtotal:</span>
                              <span>Rp {{ number_format($baseCost + $adminFee + $maintenanceFee) }}</span>
                            </div>
                            <div class="flex justify-between items-center font-bold text-lg border-t pt-2">
                              <span class="text-gray-800">Total Bayar:</span>
                              <span class="text-blue-600">Rp {{ number_format($bill->total_amount) }}</span>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              @endforeach
              </div>
            </div>

            <!-- Right Column: Checkout Summary (1/3 width) -->
            <div class="lg:col-span-1 p-6 bg-gray-50">
              <div class="sticky top-6">

                <!-- Quick Actions (Always at top) -->
                <div class="bg-white rounded-lg p-4 mb-4">
                  <h5 class="font-medium text-gray-800 mb-3">Aksi Cepat</h5>
                  <div class="space-y-2">
                    <a href="{{ route('portal.index') }}" class="w-full text-left text-sm text-gray-600 hover:text-gray-800 flex items-center py-2">
                      <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                      </svg>
                      Kembali ke Portal
                    </a>
                  </div>
                </div>

                <!-- No Selection Message -->
                <div id="noSelectionMessage" class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">
                  <div class="flex items-center">
                    <svg class="w-5 h-5 text-yellow-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <div>
                      <h6 class="font-medium text-yellow-800">Belum Ada Tagihan Dipilih</h6>
                      <p class="text-sm text-yellow-700 mt-1">Pilih tagihan yang ingin dibayar terlebih dahulu</p>
                    </div>
                  </div>
                </div>

                <!-- Payment Summary -->
                <div id="paymentSummary" class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4 hidden">
                  <h5 class="font-medium text-blue-800 mb-3">Detail Pembayaran</h5>
                  <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                      <span class="text-blue-600">Tagihan Terpilih:</span>
                      <span class="font-medium" id="selectedCount">0</span>
                    </div>
                    <div class="flex justify-between">
                      <span class="text-blue-600">Total Biaya Air:</span>
                      <span class="font-medium text-blue-800" id="totalWaterCharge">Rp 0</span>
                    </div>
                    <div class="flex justify-between">
                      <span class="text-blue-600">Biaya Admin:</span>
                      <span class="font-medium text-blue-800" id="totalAdminFee">Rp 0</span>
                    </div>
                    <div class="flex justify-between">
                      <span class="text-blue-600">Biaya Pemeliharaan:</span>
                      <span class="font-medium text-blue-800" id="totalMaintenanceFee">Rp 0</span>
                    </div>
                    <div class="border-t border-blue-300 pt-2 mt-2">
                      <div class="flex justify-between">
                        <span class="font-bold text-blue-800">Total Pembayaran:</span>
                        <span class="font-bold text-blue-800" id="selectedTotal">Rp 0</span>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Action Buttons -->
                <div class="space-y-3">
                  <button id="paymentButton" 
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white py-3 px-4 rounded-lg font-medium transition-all duration-200 flex items-center justify-center opacity-50 cursor-not-allowed" 
                    disabled>
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m-2 0h-2m2-4v-3m2 3V9l-6 6 2-2z"></path>
                    </svg>
                    <span id="paymentButtonText">Bayar dengan QRIS (0)</span>
                  </button>
                  
                  <!-- Invoice Button -->
                  <button id="invoiceButton" 
                    class="w-full bg-gray-600 hover:bg-gray-700 text-white py-3 px-4 rounded-lg font-medium transition-all duration-200 flex items-center justify-center mb-3 opacity-50 cursor-not-allowed" 
                    disabled>
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    <span id="invoiceButtonText">Cetak Invoice (0)</span>
                  </button>

                </div>
              </div>
            </div>
          </div>
        </div>
      @endif

      @if ($payableBills->count() == 0 && $pendingBills->count() == 0)
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

      @if ($paidBills->count() > 0 || $paidBundles->count() > 0)
        <!-- Payment History Section -->
        <div class="card-gradient rounded-2xl shadow-xl overflow-hidden animate-slide-up">
          <div class="bg-gradient-to-r from-green-500 to-emerald-500 px-6 py-4 text-white">
            <div class="flex items-center justify-between">
              <div>
                <h3 class="text-xl font-bold mb-1">Riwayat Pembayaran</h3>
                <p class="text-green-100">{{ $paidBills->count() + $paidBundles->count() }} pembayaran terakhir</p>
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
              @php
                // Combine paid bills and bundle payments, then sort by payment date
                $allPayments = collect();
                
                // Add individual bills
                foreach ($paidBills as $bill) {
                  $allPayments->push([
                    'type' => 'bill',
                    'data' => $bill,
                    'date' => $bill->payment_date
                  ]);
                }
                
                // Add bundle payments
                foreach ($paidBundles as $bundle) {
                  $allPayments->push([
                    'type' => 'bundle',
                    'data' => $bundle,
                    'date' => $bundle->payment_date
                  ]);
                }
                
                // Sort by payment date (newest first)
                $allPayments = $allPayments->sortByDesc('date');
              @endphp

              @foreach ($allPayments as $index => $payment)
                @if ($payment['type'] === 'bill')
                  @php $bill = $payment['data']; @endphp
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
                @elseif ($payment['type'] === 'bundle')
                  @php $bundle = $payment['data']; @endphp
                  <div class="bg-white rounded-xl shadow-md border border-gray-100 p-6 card-hover"
                    style="animation-delay: {{ $index * 0.1 }}s">
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                      <div class="mb-4 md:mb-0">
                        <div class="flex items-center mb-2">
                          <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                            <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor"
                              viewBox="0 0 24 24">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 9a2 2 0 00-2 2m0 0V7a2 2 0 012-2h12a2 2 0 012 2v2M7 7V3a2 2 0 012-2h6a2 2 0 012 2v4"></path>
                            </svg>
                          </div>
                          <div>
                            <h4 class="font-bold text-gray-800 flex items-center">
                              Bundle Payment
                              <span class="ml-2 bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded-full font-medium">
                                {{ $bundle->bill_count }} tagihan
                              </span>
                            </h4>
                            <p class="text-sm text-gray-600">
                              Dibayar {{ \Carbon\Carbon::parse($bundle->payment_date)->format('d/m/Y') }} • 
                              @php
                                $periods = $bundle->bills ? $bundle->bills->pluck('waterUsage.billingPeriod.period_name')->unique() : collect();
                              @endphp
                              {{ $periods->implode(', ') }}
                            </p>
                          </div>
                        </div>

                        <div class="flex items-center space-x-4 text-sm text-gray-600">
                          <span>Rp {{ number_format($bundle->total_amount) }}</span>
                          <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded-full text-xs font-medium">
                            QRIS
                          </span>
                        </div>
                      </div>

                      <div class="flex items-center space-x-3">
                        <span class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-sm font-medium">
                          ✓ Lunas
                        </span>
                        @if($bundle->transaction_ref)
                        <a href="{{ route('receipt.bundle', ['bundle_reference' => $bundle->transaction_ref, 'customer_code' => $customer->customer_code]) }}"
                          target="_blank"
                          class="btn-secondary text-white py-2 px-4 rounded-lg text-sm font-medium flex items-center">
                        @else
                        <span class="btn-secondary bg-gray-400 text-white py-2 px-4 rounded-lg text-sm font-medium flex items-center cursor-not-allowed">
                        @endif
                          <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z">
                            </path>
                          </svg>
                          Kwitansi Bundle
                        @if($bundle->transaction_ref)
                        </a>
                        @else
                        </span>
                        @endif
                      </div>
                    </div>
                  </div>
                @endif
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
              <h3 class="text-xl font-bold mb-1">Tarif Air {{ $customer->village->name ?? 'Desa' }}</h3>
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

            $adminFee = $customer->village?->getDefaultAdminFee() ?? 5000;
            $maintenanceFee = $customer->village?->getDefaultMaintenanceFee() ?? 2000;

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

    // Retry payment function for pending bills without active payment
    function retryPayment(billId) {
      // Show confirmation dialog
      if (!confirm('Apakah Anda yakin ingin mencoba pembayaran lagi untuk tagihan ini?')) {
        return;
      }

      // Redirect to payment form for retry
      window.location.href = `/{{ $customer->village->slug }}/bill/${billId}/payment`;
    }

    // Add dynamic alert function
    function showAlert(type, message, duration = 5000) {
      const alertContainer = document.getElementById('alert-container');
      
      // If no alert container exists, fall back to basic alert
      if (!alertContainer) {
        alert(message);
        return;
      }
      
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

    // Bill Details Toggle Functionality
    document.addEventListener('DOMContentLoaded', function() {
      // Handle bill details toggle
      document.querySelectorAll('.bill-details-toggle').forEach(button => {
        button.addEventListener('click', function() {
          const billId = this.dataset.billId;
          const detailsDiv = document.getElementById(`details-${billId}`);
          const icon = this.querySelector('svg');
          
          if (detailsDiv.classList.contains('hidden')) {
            detailsDiv.classList.remove('hidden');
            icon.style.transform = 'rotate(180deg)';
            this.querySelector('span').textContent = 'Sembunyikan detail';
          } else {
            detailsDiv.classList.add('hidden');
            icon.style.transform = 'rotate(0deg)';
            this.querySelector('span').textContent = 'Lihat detail';
          }
        });
      });
    });

    // Payment Functionality
    document.addEventListener('DOMContentLoaded', function() {
      const selectAllBtn = document.getElementById('selectAllBills');
      const paymentBtn = document.getElementById('paymentButton');
      const paymentBtnText = document.getElementById('paymentButtonText');
      const paymentSummary = document.getElementById('paymentSummary');
      const selectedCount = document.getElementById('selectedCount');
      const selectedTotal = document.getElementById('selectedTotal');
      const billCheckboxes = document.querySelectorAll('.bill-checkbox');
      const billCards = document.querySelectorAll('.bill-card');

      let selectedBills = new Map(); // Use Map instead of Set for better tracking

      // Initialize checkbox styles
      function initializeCheckboxes() {
        billCheckboxes.forEach(checkbox => {
          const label = checkbox.closest('label');
          const checkboxDisplay = label.querySelector('.checkbox-display');
          const checkmark = checkboxDisplay.querySelector('.checkmark');
          
          // Handle checkbox state change
          checkbox.addEventListener('change', function() {
            const billCard = this.closest('.bill-card');
            const billId = this.dataset.billId;
            const billAmount = parseFloat(billCard.dataset.billAmount);
            const billPeriod = billCard.dataset.billPeriod;
            const waterCharge = parseFloat(billCard.dataset.waterCharge);
            const adminFee = parseFloat(billCard.dataset.adminFee);
            const maintenanceFee = parseFloat(billCard.dataset.maintenanceFee);
            
            console.log('Checkbox changed:', billId, 'checked:', this.checked);
            
            updateCheckboxAppearance(this, checkboxDisplay, checkmark);
            
            if (this.checked) {
              selectedBills.set(billId, {
                id: billId,
                amount: billAmount,
                period: billPeriod,
                waterCharge: waterCharge,
                adminFee: adminFee,
                maintenanceFee: maintenanceFee
              });
              
              billCard.classList.add('ring-2', 'ring-blue-500', 'ring-opacity-50');
            } else {
              selectedBills.delete(billId);
              billCard.classList.remove('ring-2', 'ring-blue-500', 'ring-opacity-50');
            }
            
            updateBundleSummary();
          });

          // Handle clicking on the label/checkbox display
          label.addEventListener('click', function(e) {
            e.preventDefault();
            console.log('Label clicked for bill:', checkbox.dataset.billId);
            checkbox.checked = !checkbox.checked;
            checkbox.dispatchEvent(new Event('change'));
          });
        });
      }

      // Make entire card clickable
      function initializeCardClicks() {
        billCards.forEach(card => {
          card.addEventListener('click', function(e) {
            // Don't trigger if clicking on the details toggle button or checkbox area
            if (e.target.closest('.bill-details-toggle') || e.target.closest('label')) {
              return;
            }
            
            const billId = this.dataset.billId;
            const checkbox = this.querySelector(`.bill-checkbox[data-bill-id="${billId}"]`);
            
            if (checkbox) {
              checkbox.checked = !checkbox.checked;
              checkbox.dispatchEvent(new Event('change'));
            }
          });
        });
      }

      // Update checkbox visual appearance
      function updateCheckboxAppearance(checkbox, checkboxDisplay, checkmark) {
        console.log('Updating appearance - checked:', checkbox.checked);
        
        if (checkbox.checked) {
          checkboxDisplay.classList.remove('border-gray-300', 'bg-white');
          checkboxDisplay.classList.add('border-blue-500', 'bg-blue-500');
          checkmark.classList.remove('opacity-0');
          checkmark.classList.add('opacity-100');
        } else {
          checkboxDisplay.classList.remove('border-blue-500', 'bg-blue-500');
          checkboxDisplay.classList.add('border-gray-300', 'bg-white');
          checkmark.classList.remove('opacity-100');
          checkmark.classList.add('opacity-0');
        }
      }

      // Update bundle summary with exact cost calculation
      function updateBundleSummary() {
        const count = selectedBills.size;
        const selectedBillsArray = Array.from(selectedBills.values());
        
        // Get UI elements
        const noSelectionMessage = document.getElementById('noSelectionMessage');
        
        // Show/hide no selection message
        if (count === 0) {
          noSelectionMessage.style.display = 'block';
          paymentSummary.classList.add('hidden');
        } else {
          noSelectionMessage.style.display = 'none';
          paymentSummary.classList.remove('hidden');
        }
        
        // Calculate exact total for bundle payment following backend logic
        let totalWaterCharge = 0;
        let totalMaintenanceFee = 0;
        let totalAdminFee = 0;
        
        selectedBillsArray.forEach((bill, index) => {
          // Use exact amounts from bill data
          totalWaterCharge += bill.waterCharge;
          totalMaintenanceFee += bill.maintenanceFee; // Accumulative
          totalAdminFee += bill.adminFee; // Accumulative
        });
        
        const correctedTotal = totalWaterCharge + totalAdminFee + totalMaintenanceFee;
        
        // Update display elements
        selectedCount.textContent = `${count} tagihan`;
        document.getElementById('totalWaterCharge').textContent = `Rp ${new Intl.NumberFormat('id-ID').format(totalWaterCharge)}`;
        document.getElementById('totalAdminFee').textContent = `Rp ${new Intl.NumberFormat('id-ID').format(totalAdminFee)}`;
        document.getElementById('totalMaintenanceFee').textContent = `Rp ${new Intl.NumberFormat('id-ID').format(totalMaintenanceFee)}`;
        selectedTotal.textContent = `Rp ${new Intl.NumberFormat('id-ID').format(correctedTotal)}`;
        paymentBtnText.textContent = `Bayar dengan QRIS (${count})`;
        
        // Update invoice button
        const invoiceBtn = document.getElementById('invoiceButton');
        const invoiceBtnText = document.getElementById('invoiceButtonText');
        if (invoiceBtn && invoiceBtnText) {
          invoiceBtnText.textContent = `Cetak Invoice (${count})`;
        }
        
        // Update button states
        if (count > 0) {
          paymentBtn.disabled = false;
          paymentBtn.classList.remove('opacity-50', 'cursor-not-allowed');
          paymentBtn.classList.add('hover:bg-blue-700');
          
          // Enable invoice button
          if (invoiceBtn) {
            invoiceBtn.disabled = false;
            invoiceBtn.classList.remove('opacity-50', 'cursor-not-allowed');
            invoiceBtn.classList.add('hover:bg-gray-700');
          }
        } else {
          paymentBtn.disabled = true;
          paymentBtn.classList.add('opacity-50', 'cursor-not-allowed');
          paymentBtn.classList.remove('hover:bg-blue-700');
          
          // Disable invoice button
          if (invoiceBtn) {
            invoiceBtn.disabled = true;
            invoiceBtn.classList.add('opacity-50', 'cursor-not-allowed');
            invoiceBtn.classList.remove('hover:bg-gray-700');
          }
        }
      }

      // Select all functionality
      selectAllBtn.addEventListener('click', function() {
        const allChecked = Array.from(billCheckboxes).every(cb => cb.checked);
        const newState = !allChecked;
        
        billCheckboxes.forEach(checkbox => {
          if (checkbox.checked !== newState) {
            checkbox.checked = newState;
            checkbox.dispatchEvent(new Event('change'));
          }
        });
        
        // Update button text
        const buttonText = selectAllBtn.querySelector('span') || selectAllBtn;
        buttonText.textContent = newState ? 'Batal Pilih' : 'Pilih Semua';
      });

      // Payment button functionality
      paymentBtn.addEventListener('click', function() {
        if (selectedBills.size === 0) {
          showAlert('warning', 'Pilih minimal satu tagihan untuk pembayaran');
          return;
        }

        // Create form data for payment
        const billIds = Array.from(selectedBills.keys());

        // Show loading state
        const originalText = this.innerHTML;
        this.innerHTML = `
          <svg class="animate-spin w-5 h-5 mr-2" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 714 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          Memproses...
        `;
        this.disabled = true;

        // Create form and submit to bundle payment form (email selection)
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '{{ route("bundle.payment.form", ["customer_code" => $customer->customer_code]) }}';
        
        // Add CSRF token
        const csrfToken = document.createElement('input');
        csrfToken.type = 'hidden';
        csrfToken.name = '_token';
        csrfToken.value = '{{ csrf_token() }}';
        form.appendChild(csrfToken);

        // Add bill IDs
        billIds.forEach(billId => {
          const input = document.createElement('input');
          input.type = 'hidden';
          input.name = 'bill_ids[]';
          input.value = billId;
          form.appendChild(input);
        });

        document.body.appendChild(form);
        form.submit();
      });

      // Invoice button functionality
      const invoiceBtn = document.getElementById('invoiceButton');
      if (invoiceBtn) {
        invoiceBtn.addEventListener('click', function() {
          if (selectedBills.size === 0) {
            showAlert('warning', 'Pilih minimal satu tagihan untuk cetak invoice');
            return;
          }

          // Create form data for invoice
          const billIds = Array.from(selectedBills.keys());

          // Show loading state
          const originalText = this.innerHTML;
          this.innerHTML = `
            <svg class="animate-spin w-5 h-5 mr-2" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 714 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            Memproses...
          `;
          this.disabled = true;

          // Create form and submit to invoice generation
          const form = document.createElement('form');
          form.method = 'POST';
          form.action = '{{ route("receipt.invoice.multiple", ["customer_code" => $customer->customer_code]) }}';
          form.target = '_blank'; // Open in new tab
          
          // Add CSRF token
          const csrfToken = document.createElement('input');
          csrfToken.type = 'hidden';
          csrfToken.name = '_token';
          csrfToken.value = '{{ csrf_token() }}';
          form.appendChild(csrfToken);

          // Add bill IDs
          billIds.forEach(billId => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'bill_ids[]';
            input.value = billId;
            form.appendChild(input);
          });

          document.body.appendChild(form);
          form.submit();

          // Reset button state after a short delay
          setTimeout(() => {
            this.innerHTML = originalText;
            this.disabled = false;
            updateBundleSummary(); // This will restore proper state
          }, 1000);
        });
      }

      // Initialize
      initializeCheckboxes();
      initializeCardClicks();
      updateBundleSummary(); // Initialize UI state
    });
  </script>
</body>

</html>
