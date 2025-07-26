{{-- resources/views/tripay/payment-form.blade.php --}}
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Pembayaran QRIS - PAMDes {{ $village->name }}</title>
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

    .btn-primary {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      transition: all 0.3s ease;
    }

    .btn-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
    }

    .animate-fade-in {
      animation: fadeIn 0.6s ease-out;
    }

    @keyframes fadeIn {
      from {
        opacity: 0;
      }
      to {
        opacity: 1;
      }
    }
  </style>
</head>

<body class="bg-gray-100 min-h-screen">
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

        <!-- Bill Overview Section (for bundle payment form with comprehensive view) -->
        @if(isset($showingBundleForm) && $showingBundleForm)
          <!-- Comprehensive Bill Overview -->
          <div class="space-y-4 mb-6">
            <!-- Customer Info -->
            <div class="bg-gray-50 rounded-lg p-4">
              <h3 class="font-semibold text-gray-800 mb-3">Informasi Pelanggan</h3>
              <div class="space-y-2 text-sm">
                <div class="flex justify-between">
                  <span class="text-gray-600">Pelanggan:</span>
                  <span class="font-medium">{{ $customer->name }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-gray-600">Kode:</span>
                  <span class="font-medium">{{ $customer->customer_code }}</span>
                </div>
              </div>
            </div>

            <!-- Selected Bills for Payment -->
            @if(isset($bills) && $bills->count() > 0)
              <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <h3 class="font-semibold text-blue-800 mb-3 flex items-center">
                  <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                  </svg>
                  Tagihan Dipilih untuk Pembayaran Bundle ({{ $bills->count() }} tagihan)
                </h3>
                <div class="space-y-2">
                  @foreach($bills as $bundleBill)
                    <div class="bg-white rounded-lg px-3 py-2 border border-blue-200">
                      <div class="flex justify-between items-center">
                        <div>
                          <div class="font-medium text-gray-800">{{ $bundleBill->waterUsage->billingPeriod->period_name }}</div>
                          <div class="text-xs text-gray-600">{{ $bundleBill->waterUsage->total_usage_m3 }} m³</div>
                        </div>
                        <div class="text-right">
                          <div class="font-semibold text-blue-600">Rp {{ number_format($bundleBill->total_amount) }}</div>
                          <div class="text-xs text-gray-500">{{ $bundleBill->due_date ? $bundleBill->due_date->format('d/m/Y') : 'Belum ditentukan' }}</div>
                        </div>
                      </div>
                    </div>
                  @endforeach
                </div>
                
                <!-- Bundle Total -->
                <div class="mt-3 pt-3 border-t border-blue-200">
                  <div class="flex justify-between font-bold text-lg">
                    <span class="text-blue-800">Total Bundle:</span>
                    <span class="text-blue-600">Rp {{ number_format($total_amount, 0, ',', '.') }}</span>
                  </div>
                </div>
              </div>
            @endif

            <!-- Recent Paid Bills Section -->
            @if(isset($recentPaidBills) && $recentPaidBills->count() > 0)
              <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                <h3 class="font-semibold text-green-800 mb-3 flex items-center">
                  <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                  </svg>
                  Riwayat Pembayaran Terbaru
                </h3>
                <div class="space-y-2 max-h-48 overflow-y-auto">
                  @foreach($recentPaidBills as $paidBill)
                    <div class="bg-white rounded px-3 py-2 border border-green-200">
                      <div class="flex justify-between items-center">
                        <div>
                          <div class="text-sm font-medium text-gray-800">{{ $paidBill->waterUsage->billingPeriod->period_name }}</div>
                          <div class="text-xs text-green-600">✓ Dibayar {{ $paidBill->payment_date ? $paidBill->payment_date->format('d/m/Y') : 'Tanggal tidak diketahui' }}</div>
                        </div>
                        <div class="text-right">
                          <div class="text-sm font-semibold text-gray-800">Rp {{ number_format($paidBill->total_amount) }}</div>
                        </div>
                      </div>
                    </div>
                  @endforeach
                </div>
              </div>
            @endif

            <!-- Recent Paid Bundles Section -->
            @if(isset($recentPaidBundles) && $recentPaidBundles->count() > 0)
              <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                <h3 class="font-semibold text-green-800 mb-3 flex items-center">
                  <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                  </svg>
                  Riwayat Bundle Payment Terbaru
                </h3>
                <div class="space-y-2 max-h-48 overflow-y-auto">
                  @foreach($recentPaidBundles as $paidBundle)
                    <div class="bg-white rounded px-3 py-2 border border-green-200">
                      <div class="flex justify-between items-center">
                        <div>
                          <div class="text-sm font-medium text-gray-800">Bundle Payment</div>
                          <div class="text-xs text-green-600">✓ {{ $paidBundle->bill_count }} tagihan - Dibayar {{ $paidBundle->paid_at ? $paidBundle->paid_at->format('d/m/Y') : 'Tanggal tidak diketahui' }}</div>
                        </div>
                        <div class="text-right">
                          <div class="text-sm font-semibold text-gray-800">Rp {{ number_format($paidBundle->total_amount) }}</div>
                        </div>
                      </div>
                    </div>
                  @endforeach
                </div>
              </div>
            @endif

            <!-- Available Unpaid Bills Section -->
            @if(isset($availableUnpaidBills) && $availableUnpaidBills->count() > 0)
              <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                <h3 class="font-semibold text-yellow-800 mb-3 flex items-center">
                  <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                  </svg>
                  Tagihan Belum Bayar Lainnya
                </h3>
                <div class="space-y-2 max-h-48 overflow-y-auto">
                  @foreach($availableUnpaidBills as $unpaidBill)
                    <div class="bg-white rounded px-3 py-2 border border-yellow-200">
                      <div class="flex justify-between items-center">
                        <div>
                          <div class="text-sm font-medium text-gray-800">{{ $unpaidBill->waterUsage->billingPeriod->period_name }}</div>
                          <div class="text-xs text-gray-600">{{ $unpaidBill->waterUsage->total_usage_m3 }} m³ • Jatuh tempo: {{ $unpaidBill->due_date ? $unpaidBill->due_date->format('d/m/Y') : 'Belum ditentukan' }}</div>
                        </div>
                        <div class="text-right">
                          <div class="text-sm font-semibold text-gray-800">Rp {{ number_format($unpaidBill->total_amount) }}</div>
                          <div class="text-xs {{ $unpaidBill->status === 'overdue' ? 'text-red-600' : 'text-yellow-600' }}">
                            {{ $unpaidBill->status === 'overdue' ? 'Terlambat' : 'Belum Bayar' }}
                          </div>
                        </div>
                      </div>
                    </div>
                  @endforeach
                </div>
                <div class="mt-3 text-xs text-yellow-700">
                  <p>💡 Tip: Anda dapat membayar tagihan ini dengan kembali ke halaman tagihan dan memilih bundle baru atau pembayaran individual.</p>
                </div>
              </div>
            @endif

            <!-- Pending Individual Bills Section -->
            @if(isset($pendingIndividualBills) && $pendingIndividualBills->count() > 0)
              <div class="bg-orange-50 border border-orange-200 rounded-lg p-4">
                <h3 class="font-semibold text-orange-800 mb-3 flex items-center">
                  <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                  </svg>
                  Pembayaran Individual Sedang Diproses
                </h3>
                <div class="space-y-2">
                  @foreach($pendingIndividualBills as $pendingBill)
                    <div class="bg-white rounded px-3 py-2 border border-orange-200">
                      <div class="flex justify-between items-center">
                        <div>
                          <div class="text-sm font-medium text-gray-800">{{ $pendingBill->waterUsage->billingPeriod->period_name }}</div>
                          <div class="text-xs text-orange-600">⏳ Menunggu pembayaran</div>
                        </div>
                        <div class="text-right">
                          <div class="text-sm font-semibold text-gray-800">Rp {{ number_format($pendingBill->total_amount) }}</div>
                        </div>
                      </div>
                    </div>
                  @endforeach
                </div>
              </div>
            @endif

            <!-- Pending Bundles Section -->
            @if(isset($pendingBundles) && $pendingBundles->count() > 0)
              <div class="bg-indigo-50 border border-indigo-200 rounded-lg p-4">
                <h3 class="font-semibold text-indigo-800 mb-3 flex items-center">
                  <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                  </svg>
                  Bundle Payment Sedang Diproses
                </h3>
                <div class="space-y-2">
                  @foreach($pendingBundles as $pendingBundle)
                    <div class="bg-white rounded px-3 py-2 border border-indigo-200">
                      <div class="flex justify-between items-center">
                        <div>
                          <div class="text-sm font-medium text-gray-800">Bundle Payment</div>
                          <div class="text-xs text-indigo-600">⏳ {{ $pendingBundle->bill_count }} tagihan - Menunggu pembayaran</div>
                        </div>
                        <div class="text-right">
                          <div class="text-sm font-semibold text-gray-800">Rp {{ number_format($pendingBundle->total_amount) }}</div>
                        </div>
                      </div>
                    </div>
                  @endforeach
                </div>
              </div>
            @endif
          </div>
        @else
          <!-- Single Bill Details (original format) -->
          <div class="bg-gray-50 rounded-lg p-4 mb-6">
            <h3 class="font-semibold text-gray-800 mb-3">Detail Tagihan</h3>
            <div class="space-y-2 text-sm">
              <div class="flex justify-between">
                <span class="text-gray-600">Pelanggan:</span>
                <span class="font-medium">{{ isset($customer) ? $customer->name : $bill->waterUsage->customer->name }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-600">Kode:</span>
                <span class="font-medium">{{ isset($customer) ? $customer->customer_code : $bill->waterUsage->customer->customer_code }}</span>
              </div>
              
              @if(isset($bills))
                <div class="flex justify-between">
                  <span class="text-gray-600">Jenis Pembayaran:</span>
                  <span class="font-medium">Bundle ({{ $bills->count() }} tagihan)</span>
                </div>
                <div class="mt-3">
                  <span class="text-gray-600">Tagihan yang dibayar:</span>
                  <div class="mt-1 space-y-1">
                    @foreach($bills as $bundleBill)
                      <div class="text-xs bg-white rounded px-2 py-1">
                        {{ $bundleBill->waterUsage->billingPeriod->period_name }} - Rp {{ number_format($bundleBill->total_amount) }}
                      </div>
                    @endforeach
                  </div>
                </div>
              @else
                @if(isset($bill))
                <div class="flex justify-between">
                  <span class="text-gray-600">Periode:</span>
                  <span class="font-medium">{{ $bill->waterUsage->billingPeriod->period_name }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-gray-600">Pemakaian:</span>
                  <span class="font-medium">{{ $bill->waterUsage->total_usage_m3 }} m³</span>
                </div>
                @endif
              @endif
              <hr class="my-2">
              @if(isset($bills))
                @php
                  $totalWaterCharge = $bills->sum('water_charge');
                  $totalAdminFee = $bills->sum('admin_fee');
                  $totalMaintenanceFee = $bills->sum('maintenance_fee');
                @endphp
                <div class="flex justify-between">
                  <span class="text-gray-600">Total Biaya Air:</span>
                  <span>Rp {{ number_format($totalWaterCharge, 0, ',', '.') }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-gray-600">Total Biaya Admin:</span>
                  <span>Rp {{ number_format($totalAdminFee, 0, ',', '.') }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-gray-600">Total Biaya Pemeliharaan:</span>
                  <span>Rp {{ number_format($totalMaintenanceFee, 0, ',', '.') }}</span>
                </div>
              @elseif(isset($bill))
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
              @endif
              <hr class="my-2">
              <div class="flex justify-between font-bold text-lg">
                <span class="text-gray-800">Total:</span>
                <span class="text-blue-600">Rp {{ number_format(isset($total_amount) ? $total_amount : (isset($bill) ? $bill->total_amount : 0), 0, ',', '.') }}</span>
              </div>
              @if(!isset($bills) && isset($bill))
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
                    class="font-medium {{ $bill->due_date && $bill->due_date->isPast() && $bill->status !== 'paid' ? 'text-red-600' : '' }}">
                    {{ $bill->due_date ? $bill->due_date->format('d/m/Y') : 'Belum ditentukan' }}
                  </span>
                </div>
                @endif
              @endif
            </div>
          </div>
        @endif

        <!-- Payment Form Section -->
        @if(isset($showingBundleForm) && $showingBundleForm)
          <!-- Bundle Payment Form -->
          <form action="{{ $action_url }}" method="POST" class="space-y-4">
            @csrf
            @foreach($bills as $bundleBill)
              <input type="hidden" name="bill_ids[]" value="{{ $bundleBill->bill_id }}">
            @endforeach
        @elseif (!isset($bills) && isset($bill))
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
            onclick="location.href='{{ isset($bill) ? route('tripay.continue', ['village' => $village->slug, 'bill' => $bill->bill_id]) : '#' }}'"
            class="w-full bg-blue-600 text-white py-3 px-4 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 font-medium transition duration-200 mb-4">
            Lanjutkan Pembayaran
          </button>

          <!-- Check Payment Status Button -->
          <button onclick="checkPaymentStatus()"
            class="w-full bg-yellow-600 text-white py-3 px-4 rounded-md hover:bg-yellow-700 focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:ring-offset-2 font-medium transition duration-200 mb-4">
            Cek Status Pembayaran
          </button>
          @else
            <!-- Single Bill Payment Form -->
            <form action="{{ isset($bill) ? route('tripay.create', ['village' => $village->slug, 'bill' => $bill->bill_id, 'return' => route('portal.bills', $bill->waterUsage->customer->customer_code)]) : '#' }}" method="POST" class="space-y-4">
              @csrf
          @endif
        @endif

        @if ((isset($showingBundleForm) && $showingBundleForm) || (!isset($bills) && isset($bill) && $bill->status !== 'paid' && $bill->status !== 'pending'))

            <div>
              <label for="customer_name" class="block text-sm font-medium text-gray-700 mb-1">
                Nama Lengkap <span class="text-red-500">*</span>
              </label>
              <input type="text" id="customer_name" name="customer_name"
                value="{{ old('customer_name', isset($customer) ? $customer->name : $bill->waterUsage->customer->name) }}"
                required
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('customer_name') border-red-500 @enderror"
                placeholder="Masukkan nama lengkap">
              @error('customer_name')
                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
              @enderror
            </div>

            <!-- Email Selection with Great UX for Villagers -->
            <div class="space-y-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-3">
                  Pilihan Email <span class="text-red-500">*</span>
                </label>
                <p class="text-sm text-gray-600 mb-4">Pilih email untuk menerima notifikasi pembayaran dan struk elektronik</p>
                
                <div class="space-y-3">
                  <div class="relative">
                    <input type="radio" id="{{ isset($showingBundleForm) && $showingBundleForm ? 'email_choice_village_bundle' : 'email_choice_village' }}" name="email_choice" value="village" class="sr-only" checked>
                    <label for="{{ isset($showingBundleForm) && $showingBundleForm ? 'email_choice_village_bundle' : 'email_choice_village' }}" 
                      class="flex items-center justify-between p-4 border-2 border-gray-200 rounded-lg cursor-pointer hover:border-blue-300 transition-colors email-choice-card">
                      <div class="flex items-center flex-1">
                        <div class="flex items-center justify-center w-10 h-10 bg-green-100 rounded-full mr-4">
                          <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                          </svg>
                        </div>
                        <div class="flex-1">
                          <h3 class="font-medium text-gray-800">Gunakan Email Desa</h3>
                          <p class="text-sm text-gray-600">Email resmi PAMDes {{ $village->name }}</p>
                          <p class="text-xs text-green-600 mt-1">📧 {{ $village->email ?: 'admin@' . $village->slug . '.pamdes.id' }}</p>
                        </div>
                      </div>
                      <div class="email-choice-indicator flex items-center justify-center">
                        <div class="w-4 h-4 border-2 border-gray-300 rounded-full flex items-center justify-center"></div>
                      </div>
                    </label>
                  </div>
                  
                  <div class="relative">
                    <input type="radio" id="{{ isset($showingBundleForm) && $showingBundleForm ? 'email_choice_personal_bundle' : 'email_choice_personal' }}" name="email_choice" value="personal" class="sr-only">
                    <label for="{{ isset($showingBundleForm) && $showingBundleForm ? 'email_choice_personal_bundle' : 'email_choice_personal' }}" 
                      class="flex items-center justify-between p-4 border-2 border-gray-200 rounded-lg cursor-pointer hover:border-blue-300 transition-colors email-choice-card">
                      <div class="flex items-center flex-1">
                        <div class="flex items-center justify-center w-10 h-10 bg-blue-100 rounded-full mr-4">
                          <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                          </svg>
                        </div>
                        <div class="flex-1">
                          <h3 class="font-medium text-gray-800">Gunakan Email Pribadi</h3>
                          <p class="text-sm text-gray-600">Masukkan alamat email pribadi Anda</p>
                          <p class="text-xs text-blue-600 mt-1">✏️ Anda dapat mengetik email sendiri</p>
                        </div>
                      </div>
                      <div class="email-choice-indicator flex items-center justify-center">
                        <div class="w-4 h-4 border-2 border-gray-300 rounded-full flex items-center justify-center"></div>
                      </div>
                    </label>
                  </div>
                </div>
              </div>

              <!-- Village Email Info (shown when village email is selected) -->
              <div id="village_email_info" class="bg-green-50 border border-green-200 rounded-lg p-4">
                <div class="flex items-start">
                  <div class="flex items-center justify-center w-8 h-8 bg-green-100 rounded-full mr-3 mt-0.5">
                    <svg class="w-4 h-4 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                    </svg>
                  </div>
                  <div>
                    <h4 class="text-sm font-medium text-green-800">Email Desa Dipilih</h4>
                    <p class="text-sm text-green-700 mt-1">
                      Notifikasi pembayaran akan dikirim ke email resmi PAMDes {{ $village->name }}.
                      Tim PAMDes akan menginformasikan status pembayaran kepada Anda.
                    </p>
                    <div class="mt-2 text-xs text-green-600">
                      <p>📧 <strong>{{ $village->email ?: 'admin@' . $village->slug . '.pamdes.id' }}</strong></p>
                      <p>🏘️ PAMDes {{ $village->name }}</p>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Email Input Field -->
              <div>
                <label for="{{ isset($showingBundleForm) && $showingBundleForm ? 'customer_email_bundle' : 'customer_email' }}" class="block text-sm font-medium text-gray-700 mb-1">
                  <span id="email_field_label">Email (Otomatis dari Desa)</span> <span class="text-red-500">*</span>
                </label>
                <input type="email" id="{{ isset($showingBundleForm) && $showingBundleForm ? 'customer_email_bundle' : 'customer_email' }}" name="customer_email" 
                  value="{{ old('customer_email', $village->email ?: 'admin@' . $village->slug . '.pamdes.id') }}"
                  required readonly
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-gray-50 @error('customer_email') border-red-500 @enderror"
                  placeholder="Email akan diisi otomatis">
                <p id="email_helper_text" class="text-xs text-gray-600 mt-1">
                  Email desa digunakan secara otomatis. Tim PAMDes akan menginformasikan status pembayaran kepada Anda.
                </p>
                @error('customer_email')
                  <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
              </div>
            </div>

            <div>
              <label for="{{ isset($showingBundleForm) && $showingBundleForm ? 'customer_phone_bundle' : 'customer_phone' }}" class="block text-sm font-medium text-gray-700 mb-1">
                Nomor Telepon (Opsional)
              </label>
              <input type="tel" id="{{ isset($showingBundleForm) && $showingBundleForm ? 'customer_phone_bundle' : 'customer_phone' }}" name="customer_phone"
                value="{{ old('customer_phone', isset($customer) ? $customer->phone_number : (isset($bill) ? $bill->waterUsage->customer->phone_number : '')) }}"
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
                  @if(isset($showingBundleForm) && $showingBundleForm)
                    <h4 class="text-sm font-medium text-blue-800">Informasi Pembayaran Bundle QRIS</h4>
                    <p class="text-sm text-blue-700 mt-1">
                      Setelah mengklik tombol bayar, Anda akan diarahkan ke halaman pembayaran Tripay.
                      Scan QR code dengan aplikasi e-wallet atau mobile banking Anda untuk membayar semua tagihan sekaligus.
                    </p>
                    <ul class="text-xs text-blue-600 mt-2 space-y-1">
                      <li>• Pembayaran aman dan terenkripsi</li>
                      <li>• Mendukung semua e-wallet dan mobile banking</li>
                      <li>• Konfirmasi pembayaran otomatis</li>
                      <li>• Bayar {{ isset($bills) ? $bills->count() : 0 }} tagihan sekaligus</li>
                    </ul>
                  @else
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
                  @endif
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
              @if(isset($showingBundleForm) && $showingBundleForm)
                Bayar Bundle dengan QRIS ({{ isset($bills) ? $bills->count() : 0 }} tagihan)
              @else
                Bayar dengan QRIS
              @endif
            </button>
          </form>
        @endif


        <!-- Back Link -->
        <div class="text-center mt-6">
          <a href="{{ route('portal.bills', isset($customer) ? $customer->customer_code : (isset($bill) ? $bill->waterUsage->customer->customer_code : '#')) }}"
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
    // Email Selection Functionality
    document.addEventListener('DOMContentLoaded', function() {
      const villageEmail = '{{ $village->email ?: 'admin@' . $village->slug . '.pamdes.id' }}';
      
      // Function to initialize email selection for a form
      function initializeEmailSelection(formContext) {
        const villageEmailChoice = formContext.querySelector('input[name="email_choice"][value="village"]');
        const personalEmailChoice = formContext.querySelector('input[name="email_choice"][value="personal"]');
        const emailInput = formContext.querySelector('input[name="customer_email"]');
        const emailLabel = formContext.querySelector('label[for*="customer_email"]');
        const emailHelperText = formContext.querySelector('#email_helper_text');
        const villageEmailInfo = formContext.querySelector('#village_email_info');
        
        if (!villageEmailChoice || !personalEmailChoice || !emailInput) {
          return; // Skip if elements not found
        }
        
        // Update UI based on selection
        function updateEmailSelection(choice) {
          const allCards = formContext.querySelectorAll('.email-choice-card');
          const allIndicators = formContext.querySelectorAll('.email-choice-indicator div');
          
          // Reset all cards
          allCards.forEach(card => {
            card.classList.remove('border-blue-500', 'bg-blue-50', 'border-green-500', 'bg-green-50');
            card.classList.add('border-gray-200');
          });
          
          // Reset all indicators
          allIndicators.forEach(indicator => {
            indicator.classList.remove('border-blue-500', 'bg-blue-500', 'border-green-500', 'bg-green-500');
            indicator.classList.add('border-gray-300', 'bg-white');
            indicator.innerHTML = '';
          });
          
          if (choice === 'village') {
            // Update village card
            const villageCard = formContext.querySelector('label[for*="email_choice_village"]');
            if (villageCard) {
              const villageIndicator = villageCard.querySelector('.email-choice-indicator div');
              villageCard.classList.remove('border-gray-200');
              villageCard.classList.add('border-green-500', 'bg-green-50');
              if (villageIndicator) {
                villageIndicator.classList.remove('border-gray-300', 'bg-white');
                villageIndicator.classList.add('border-green-500', 'bg-green-500');
                villageIndicator.innerHTML = '<div class="w-2 h-2 bg-white rounded-full"></div>';
              }
            }
            
            // Update email field
            emailInput.value = villageEmail;
            emailInput.readOnly = true;
            emailInput.classList.add('bg-gray-50');
            emailInput.classList.remove('bg-white');
            if (emailLabel) emailLabel.innerHTML = 'Email (Otomatis dari Desa) <span class="text-red-500">*</span>';
            if (emailHelperText) emailHelperText.innerHTML = 'Email desa digunakan secara otomatis. Tim PAMDes akan menginformasikan status pembayaran kepada Anda.';
            
            // Show village info
            if (villageEmailInfo) villageEmailInfo.style.display = 'block';
            
          } else if (choice === 'personal') {
            // Update personal card
            const personalCard = formContext.querySelector('label[for*="email_choice_personal"]');
            if (personalCard) {
              const personalIndicator = personalCard.querySelector('.email-choice-indicator div');
              personalCard.classList.remove('border-gray-200');
              personalCard.classList.add('border-blue-500', 'bg-blue-50');
              if (personalIndicator) {
                personalIndicator.classList.remove('border-gray-300', 'bg-white');
                personalIndicator.classList.add('border-blue-500', 'bg-blue-500');
                personalIndicator.innerHTML = '<div class="w-2 h-2 bg-white rounded-full"></div>';
              }
            }
            
            // Update email field
            emailInput.value = '';
            emailInput.readOnly = false;
            emailInput.classList.remove('bg-gray-50');
            emailInput.classList.add('bg-white');
            emailInput.placeholder = 'Masukkan alamat email pribadi Anda';
            if (emailLabel) emailLabel.innerHTML = 'Email Pribadi <span class="text-red-500">*</span>';
            if (emailHelperText) emailHelperText.innerHTML = 'Masukkan email pribadi yang aktif untuk menerima notifikasi pembayaran dan struk elektronik.';
            
            // Hide village info
            if (villageEmailInfo) villageEmailInfo.style.display = 'none';
            
            // Focus on email input
            setTimeout(() => emailInput.focus(), 100);
          }
        }
        
        // Initialize with village email (default)
        updateEmailSelection('village');
        
        // Event listeners
        villageEmailChoice.addEventListener('change', function() {
          if (this.checked) {
            updateEmailSelection('village');
          }
        });
        
        personalEmailChoice.addEventListener('change', function() {
          if (this.checked) {
            updateEmailSelection('personal');
          }
        });
        
        // Handle clicking on the entire card
        const villageCard = formContext.querySelector('label[for*="email_choice_village"]');
        const personalCard = formContext.querySelector('label[for*="email_choice_personal"]');
        
        if (villageCard) {
          villageCard.addEventListener('click', function() {
            villageEmailChoice.checked = true;
            updateEmailSelection('village');
          });
        }
        
        if (personalCard) {
          personalCard.addEventListener('click', function() {
            personalEmailChoice.checked = true;
            updateEmailSelection('personal');
          });
        }
      }
      
      // Initialize email selection for all forms on the page
      const forms = document.querySelectorAll('form');
      forms.forEach(form => {
        initializeEmailSelection(form);
      });
    });

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

    // AJAX form submission for bundle payments
    @if(isset($showingBundleForm) && $showingBundleForm)
    document.addEventListener('DOMContentLoaded', function() {
      const bundleForm = document.querySelector('form[action="{{ $action_url }}"]');
      
      if (bundleForm) {
        bundleForm.addEventListener('submit', function(e) {
          e.preventDefault();
          
          const submitButton = this.querySelector('button[type="submit"]');
          const originalText = submitButton.innerHTML;
          
          // Show loading state
          submitButton.disabled = true;
          submitButton.innerHTML = `
            <div class="flex items-center justify-center">
              <svg class="animate-spin w-5 h-5 mr-2" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
              Memproses Pembayaran...
            </div>
          `;
          
          // Show loading toast
          const loadingAlertId = createAlert('info', 'Sedang membuat bundle payment...', 0);
          
          // Prepare form data
          const formData = new FormData(this);
          
          // Make AJAX request
          fetch(this.action, {
            method: 'POST',
            body: formData,
            headers: {
              'X-Requested-With': 'XMLHttpRequest',
              'Accept': 'application/json'
            }
          })
          .then(response => {
            // Handle both success and error responses
            return response.json().then(data => ({
              status: response.status,
              ok: response.ok,
              data: data
            }));
          })
          .then(({status, ok, data}) => {
            // Close loading alert
            closeAlert(loadingAlertId);
            
            if (ok && data.success) {
              // Show success toast
              createAlert('success', data.message, 2000);
              
              // Redirect to payment URL after short delay
              setTimeout(() => {
                window.location.href = data.checkout_url;
              }, 1500);
            } else {
              // Reset button
              submitButton.disabled = false;
              submitButton.innerHTML = originalText;
              
              // Show error toast with server message
              createAlert('error', data.message || `HTTP ${status}: Gagal membuat bundle payment`);
            }
          })
          .catch(error => {
            console.error('Error:', error);
            
            // Close loading alert
            closeAlert(loadingAlertId);
            
            // Reset button
            submitButton.disabled = false;
            submitButton.innerHTML = originalText;
            
            // Show error toast with actual error message
            createAlert('error', error.message || 'Terjadi kesalahan saat memproses pembayaran. Silakan coba lagi.');
          });
        });
      }
    });
    @endif

    @if (!isset($payment_type) || $payment_type !== 'bundle')
      @if (isset($bill) && $bill->status === 'pending')
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
          fetch(`{{ isset($bill) ? route('tripay.status', ['village' => $village->slug, 'bill' => $bill->bill_id]) : '#' }}`)
            .then(response => response.json())
            .then(data => {
              // Close checking alert
              closeAlert(checkingAlertId);

              if (data.success && data.status === 'paid') {
                // Show success message and reload page
                createAlert('success', 'Pembayaran berhasil! Halaman akan dikembalikan...', 2000);
                setTimeout(() => {
                  window.location.href = `{{ isset($bill) ? route('portal.bills', $bill->waterUsage->customer->customer_code) : (isset($customer) ? route('portal.bills', $customer->customer_code) : '#') }}`;
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
    @endif
  </script>
</body>

</html>
