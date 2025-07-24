{{-- resources/views/bundle-payment/form.blade.php --}}
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Pembayaran Bundel - PAMDes {{ $village->name }}</title>
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

        <!-- Payment Summary -->
        <div class="bg-gray-50 rounded-lg p-4 mb-6">
          <h3 class="font-semibold text-gray-800 mb-3">Detail Pembayaran</h3>
          <div class="space-y-2 text-sm">
            <div class="flex justify-between">
              <span class="text-gray-600">Pelanggan:</span>
              <span class="font-medium">{{ $bundlePayment->customer->name }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-600">Kode:</span>
              <span class="font-medium">{{ $bundlePayment->customer->customer_code }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-600">Jumlah Tagihan:</span>
              <span class="font-medium">{{ $bundlePayment->bill_count }} tagihan</span>
            </div>
            <hr class="my-2">
            @php
              $totalWaterCharge = $bundlePayment->bills->sum('water_charge');
              $totalMaintenanceFee = $bundlePayment->bills->sum('maintenance_fee');
              $singleAdminFee = $bundlePayment->bills->isNotEmpty() ? $bundlePayment->bills->first()->admin_fee : 0;
            @endphp
            <div class="flex justify-between">
              <span class="text-gray-600">Total Biaya Air:</span>
              <span>Rp {{ number_format($totalWaterCharge, 0, ',', '.') }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-600">Biaya Admin:</span>
              <span>Rp {{ number_format($singleAdminFee, 0, ',', '.') }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-600">Biaya Pemeliharaan:</span>
              <span>Rp {{ number_format($totalMaintenanceFee, 0, ',', '.') }}</span>
            </div>
            <hr class="my-2">
            <div class="flex justify-between font-bold text-lg">
              <span class="text-gray-800">Total:</span>
              <span class="text-blue-600">Rp {{ number_format($bundlePayment->total_amount, 0, ',', '.') }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-600">Status:</span>
              <span
                class="px-2 py-1 rounded-full text-xs {{ $bundlePayment->status === 'paid' ? 'bg-green-100 text-green-800' : ($bundlePayment->status === 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') }}">
                {{ match ($bundlePayment->status) {
                    'paid' => 'Sudah Dibayar',
                    'pending' => 'Menunggu Pembayaran',
                    'failed' => 'Gagal',
                    'expired' => 'Kedaluwarsa',
                    default => 'Belum Bayar',
                } }}
              </span>
            </div>
          </div>
        </div>


        @if (isset($bundlePayment->status) && $bundlePayment->status === 'form')
          <!-- Payment Form for Bundle -->
          <form action="{{ route('bundle.payment.process.direct', ['customer_code' => $customer->customer_code]) }}"
            method="POST" class="space-y-4">
            @csrf

            <!-- Hidden bill IDs -->
            @foreach ($bundlePayment->bill_ids as $billId)
              <input type="hidden" name="bill_ids[]" value="{{ $billId }}">
            @endforeach

            <div>
              <label for="customer_name" class="block text-sm font-medium text-gray-700 mb-1">
                Nama Lengkap <span class="text-red-500">*</span>
              </label>
              <input type="text" id="customer_name" name="customer_name"
                value="{{ old('customer_name', $customer->name) }}" required
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
                    <input type="radio" id="email_choice_village" name="email_choice" value="village" class="sr-only" checked>
                    <label for="email_choice_village" 
                      class="flex items-center p-4 border-2 border-gray-200 rounded-lg cursor-pointer hover:border-blue-300 transition-colors email-choice-card">
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
                      <div class="email-choice-indicator">
                        <div class="w-4 h-4 border-2 border-gray-300 rounded-full"></div>
                      </div>
                    </label>
                  </div>
                  
                  <div class="relative">
                    <input type="radio" id="email_choice_personal" name="email_choice" value="personal" class="sr-only">
                    <label for="email_choice_personal" 
                      class="flex items-center p-4 border-2 border-gray-200 rounded-lg cursor-pointer hover:border-blue-300 transition-colors email-choice-card">
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
                      <div class="email-choice-indicator">
                        <div class="w-4 h-4 border-2 border-gray-300 rounded-full"></div>
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
                <label for="customer_email" class="block text-sm font-medium text-gray-700 mb-1">
                  <span id="email_field_label">Email (Otomatis dari Desa)</span> <span class="text-red-500">*</span>
                </label>
                <input type="email" id="customer_email" name="customer_email" 
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
              <label for="customer_phone" class="block text-sm font-medium text-gray-700 mb-1">
                Nomor Telepon (Opsional)
              </label>
              <input type="tel" id="customer_phone" name="customer_phone"
                value="{{ old('customer_phone', $customer->phone_number) }}"
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
                    Anda akan membayar {{ $bundlePayment->bill_count }} tagihan dengan total 
                    <strong>Rp {{ number_format($bundlePayment->total_amount, 0, ',', '.') }}</strong>.
                    Setelah mengklik tombol bayar, Anda akan diarahkan ke halaman pembayaran Tripay.
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

        @elseif (isset($bundlePayment->status) && $bundlePayment->status === 'paid')
          <!-- Already Paid -->
          <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
            <div class="flex items-center">
              <svg class="w-5 h-5 text-green-600 mr-3" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd"
                  d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                  clip-rule="evenodd"></path>
              </svg>
              <div>
                <h4 class="text-sm font-medium text-green-800">Pembayaran Bundel Berhasil</h4>
                <p class="text-sm text-green-700 mt-1">
                  Semua tagihan dalam bundel telah dibayar pada {{ $bundlePayment->paid_at->format('d/m/Y H:i') }}
                </p>
              </div>
            </div>
          </div>
        @elseif($bundlePayment->status === 'pending')
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
                  Pembayaran bundel sedang diproses. Silakan selesaikan pembayaran atau cek status.
                </p>
              </div>
            </div>
          </div>

          <!-- Continue Payment -->
          <button onclick="continueBundlePayment()"
            class="w-full bg-green-600 text-white py-3 px-4 rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 font-medium transition duration-200 mb-4">
            Lanjutkan Pembayaran Bundel
          </button>

          <!-- Check Payment Status Button -->
          <button onclick="checkBundlePaymentStatus()"
            class="w-full bg-yellow-600 text-white py-3 px-4 rounded-md hover:bg-yellow-700 focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:ring-offset-2 font-medium transition duration-200 mb-4">
            Cek Status Pembayaran
          </button>
        @else
          <!-- Payment Form -->
          <form action="{{ route('bundle.payment.process', ['customer_code' => $customer->customer_code, 'bundle_reference' => $bundlePayment->bundle_reference]) }}"
            method="POST" class="space-y-4">
            @csrf

            <div>
              <label for="customer_name" class="block text-sm font-medium text-gray-700 mb-1">
                Nama Lengkap <span class="text-red-500">*</span>
              </label>
              <input type="text" id="customer_name" name="customer_name"
                value="{{ old('customer_name', $customer->name) }}" required
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
                    <input type="radio" id="email_choice_village" name="email_choice" value="village" class="sr-only" checked>
                    <label for="email_choice_village" 
                      class="flex items-center p-4 border-2 border-gray-200 rounded-lg cursor-pointer hover:border-blue-300 transition-colors email-choice-card">
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
                      <div class="email-choice-indicator">
                        <div class="w-4 h-4 border-2 border-gray-300 rounded-full"></div>
                      </div>
                    </label>
                  </div>
                  
                  <div class="relative">
                    <input type="radio" id="email_choice_personal" name="email_choice" value="personal" class="sr-only">
                    <label for="email_choice_personal" 
                      class="flex items-center p-4 border-2 border-gray-200 rounded-lg cursor-pointer hover:border-blue-300 transition-colors email-choice-card">
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
                      <div class="email-choice-indicator">
                        <div class="w-4 h-4 border-2 border-gray-300 rounded-full"></div>
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
                <label for="customer_email" class="block text-sm font-medium text-gray-700 mb-1">
                  <span id="email_field_label">Email (Otomatis dari Desa)</span> <span class="text-red-500">*</span>
                </label>
                <input type="email" id="customer_email" name="customer_email" 
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
              <label for="customer_phone" class="block text-sm font-medium text-gray-700 mb-1">
                Nomor Telepon (Opsional)
              </label>
              <input type="tel" id="customer_phone" name="customer_phone"
                value="{{ old('customer_phone', $customer->phone_number) }}"
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
                    Anda akan membayar {{ $bundlePayment->bill_count }} tagihan dengan total 
                    <strong>Rp {{ number_format($bundlePayment->total_amount, 0, ',', '.') }}</strong>.
                    Setelah mengklik tombol bayar, Anda akan diarahkan ke halaman pembayaran Tripay.
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
          <a href="{{ route('portal.bills', $customer->customer_code) }}"
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

  <!-- Alert Container -->
  <div id="alert-container" class="fixed top-4 right-4 z-50 space-y-2"></div>

  <script>
    // Email Selection Functionality
    document.addEventListener('DOMContentLoaded', function() {
      const villageEmailChoice = document.getElementById('email_choice_village');
      const personalEmailChoice = document.getElementById('email_choice_personal');
      const emailInput = document.getElementById('customer_email');
      const emailLabel = document.getElementById('email_field_label');
      const emailHelperText = document.getElementById('email_helper_text');
      const villageEmailInfo = document.getElementById('village_email_info');
      const villageEmail = '{{ $village->email ?: 'admin@' . $village->slug . '.pamdes.id' }}';
      
      // Update UI based on selection
      function updateEmailSelection(choice) {
        const allCards = document.querySelectorAll('.email-choice-card');
        const allIndicators = document.querySelectorAll('.email-choice-indicator div');
        
        // Reset all cards
        allCards.forEach(card => {
          card.classList.remove('border-blue-500', 'bg-blue-50');
          card.classList.add('border-gray-200');
        });
        
        // Reset all indicators
        allIndicators.forEach(indicator => {
          indicator.classList.remove('border-blue-500', 'bg-blue-500');
          indicator.classList.add('border-gray-300', 'bg-white');
          indicator.innerHTML = '';
        });
        
        if (choice === 'village') {
          // Update village card
          const villageCard = document.querySelector('label[for=\"email_choice_village\"]');
          const villageIndicator = villageCard.querySelector('.email-choice-indicator div');
          villageCard.classList.remove('border-gray-200');
          villageCard.classList.add('border-green-500', 'bg-green-50');
          villageIndicator.classList.remove('border-gray-300', 'bg-white');
          villageIndicator.classList.add('border-green-500', 'bg-green-500');
          villageIndicator.innerHTML = '<div class=\"w-2 h-2 bg-white rounded-full\"></div>';
          
          // Update email field
          emailInput.value = villageEmail;
          emailInput.readOnly = true;
          emailInput.classList.add('bg-gray-50');
          emailInput.classList.remove('bg-white');
          emailLabel.innerHTML = 'Email (Otomatis dari Desa)';
          emailHelperText.innerHTML = 'Email desa digunakan secara otomatis. Tim PAMDes akan menginformasikan status pembayaran kepada Anda.';
          
          // Show village info
          villageEmailInfo.style.display = 'block';
          
        } else if (choice === 'personal') {
          // Update personal card
          const personalCard = document.querySelector('label[for=\"email_choice_personal\"]');
          const personalIndicator = personalCard.querySelector('.email-choice-indicator div');
          personalCard.classList.remove('border-gray-200');
          personalCard.classList.add('border-blue-500', 'bg-blue-50');
          personalIndicator.classList.remove('border-gray-300', 'bg-white');
          personalIndicator.classList.add('border-blue-500', 'bg-blue-500');
          personalIndicator.innerHTML = '<div class=\"w-2 h-2 bg-white rounded-full\"></div>';
          
          // Update email field
          emailInput.value = '';
          emailInput.readOnly = false;
          emailInput.classList.remove('bg-gray-50');
          emailInput.classList.add('bg-white');
          emailInput.placeholder = 'Masukkan alamat email pribadi Anda';
          emailLabel.innerHTML = 'Email Pribadi';
          emailHelperText.innerHTML = 'Masukkan email pribadi yang aktif untuk menerima notifikasi pembayaran dan struk elektronik.';
          
          // Hide village info
          villageEmailInfo.style.display = 'none';
          
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
      document.querySelector('label[for=\"email_choice_village\"]').addEventListener('click', function() {
        villageEmailChoice.checked = true;
        updateEmailSelection('village');
      });
      
      document.querySelector('label[for=\"email_choice_personal\"]').addEventListener('click', function() {
        personalEmailChoice.checked = true;
        updateEmailSelection('personal');
      });
    });

    // Show alert function
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
      alert.className = `${colors[type]} border-l-4 px-6 py-4 rounded-lg shadow-lg max-w-md`;
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

      if (duration > 0) {
        setTimeout(() => closeAlert(alertId), duration);
      }
    }

    function closeAlert(alertId) {
      const alert = document.getElementById(alertId);
      if (alert) {
        alert.remove();
      }
    }

    @if ($bundlePayment->status === 'pending')
      function continueBundlePayment() {
        // This would typically redirect to continue the existing payment
        window.location.href = '{{ route("bundle.payment.form", ["customer_code" => $customer->customer_code, "bundle_reference" => $bundlePayment->bundle_reference]) }}';
      }

      function checkBundlePaymentStatus() {
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
        showAlert('info', 'Sedang mengecek status pembayaran bundel...', 0);

        // Make AJAX request to check status
        fetch(`{{ route('bundle.payment.status', ['customer_code' => $customer->customer_code, 'bundle_reference' => $bundlePayment->bundle_reference]) }}`)
          .then(response => response.json())
          .then(data => {
            if (data.success && data.status === 'paid') {
              // Show success message and reload page
              showAlert('success', 'Pembayaran bundel berhasil! Halaman akan dimuat ulang...', 2000);
              setTimeout(() => {
                location.reload();
              }, 2000);
            } else {
              // Reset button
              button.innerHTML = originalText;
              button.disabled = false;

              // Show current status
              let statusMessage = '';
              switch (data.status) {
                case 'pending':
                  statusMessage = 'Pembayaran bundel masih dalam proses. Silakan selesaikan pembayaran atau coba lagi dalam beberapa menit.';
                  showAlert('warning', statusMessage);
                  break;
                case 'failed':
                case 'expired':
                  statusMessage = 'Pembayaran bundel gagal atau kedaluwarsa. Silakan buat pembayaran baru.';
                  showAlert('error', statusMessage);
                  setTimeout(() => {
                    window.location.href = '{{ route("portal.bills", $customer->customer_code) }}';
                  }, 3000);
                  break;
                default:
                  statusMessage = `Status pembayaran: ${data.status}`;
                  showAlert('info', statusMessage);
              }
            }
          })
          .catch(error => {
            console.error('Error:', error);
            button.innerHTML = originalText;
            button.disabled = false;
            showAlert('error', 'Gagal mengecek status pembayaran. Silakan coba lagi.');
          });
      }
    @endif

    // Auto-hide alerts
    setTimeout(() => {
      document.querySelectorAll('[id^="alert-"]').forEach(alert => {
        closeAlert(alert.id);
      });
    }, 5000);
  </script>
</body>

</html>