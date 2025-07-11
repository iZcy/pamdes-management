@extends('layouts.app')

@section('title', 'Pembayaran QRIS')

@section('content')
  <div class="container mx-auto px-4 py-8 max-w-md">
    <div class="bg-white rounded-lg shadow-lg p-6">
      <!-- Header -->
      <div class="text-center mb-6">
        <h2 class="text-2xl font-bold text-gray-800">Pembayaran QRIS</h2>
        <p class="text-gray-600 mt-2">{{ $village->name }}</p>
      </div>

      <!-- Bill Details -->
      <div class="bg-gray-50 rounded-lg p-4 mb-6">
        <h3 class="font-semibold text-gray-800 mb-3">Detail Tagihan</h3>
        <div class="space-y-2 text-sm">
          <div class="flex justify-between">
            <span class="text-gray-600">ID Tagihan:</span>
            <span class="font-medium">#{{ $bill->id }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-600">Deskripsi:</span>
            <span class="font-medium">{{ $bill->description ?: 'Pembayaran Tagihan' }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-600">Jumlah:</span>
            <span class="font-bold text-lg text-blue-600">Rp {{ number_format($bill->amount, 0, ',', '.') }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-600">Status:</span>
            <span class="px-2 py-1 rounded-full text-xs bg-yellow-100 text-yellow-800">
              {{ ucfirst($bill->status) }}
            </span>
          </div>
        </div>
      </div>

      <!-- Payment Form -->
      <form action="{{ route('tripay.create', ['village' => $village->slug, 'bill' => $bill->id]) }}" method="POST"
        class="space-y-4">
        @csrf

        <div>
          <label for="customer_name" class="block text-sm font-medium text-gray-700 mb-1">
            Nama Lengkap <span class="text-red-500">*</span>
          </label>
          <input type="text" id="customer_name" name="customer_name" value="{{ old('customer_name') }}" required
            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('customer_name') border-red-500 @enderror"
            placeholder="Masukkan nama lengkap Anda">
          @error('customer_name')
            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
          @enderror
        </div>

        <div>
          <label for="customer_email" class="block text-sm font-medium text-gray-700 mb-1">
            Email <span class="text-red-500">*</span>
          </label>
          <input type="email" id="customer_email" name="customer_email" value="{{ old('customer_email') }}" required
            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('customer_email') border-red-500 @enderror"
            placeholder="contoh@email.com">
          @error('customer_email')
            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
          @enderror
        </div>

        <div>
          <label for="customer_phone" class="block text-sm font-medium text-gray-700 mb-1">
            Nomor Telepon (Opsional)
          </label>
          <input type="tel" id="customer_phone" name="customer_phone" value="{{ old('customer_phone') }}"
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
              <h4 class="text-sm font-medium text-blue-800">Informasi Pembayaran</h4>
              <p class="text-sm text-blue-700 mt-1">
                Setelah mengklik tombol bayar, Anda akan diarahkan ke halaman pembayaran Tripay.
                Scan QR code dengan aplikasi e-wallet atau mobile banking Anda.
              </p>
            </div>
          </div>
        </div>

        <!-- Submit Button -->
        <button type="submit"
          class="w-full bg-blue-600 text-white py-3 px-4 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 font-medium transition duration-200">
          Bayar dengan QRIS
        </button>
      </form>

      <!-- Back Link -->
      <div class="text-center mt-4">
        <a href="{{ route('village.bill.show', ['village' => $village->slug, 'bill' => $bill->id]) }}"
          class="text-gray-600 hover:text-gray-800 text-sm">
          ← Kembali ke Detail Tagihan
        </a>
      </div>
    </div>
  </div>

  @if (session('error'))
    <div class="fixed top-4 right-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded max-w-sm"
      role="alert">
      <span class="block sm:inline">{{ session('error') }}</span>
    </div>
  @endif

  @if (session('success'))
    <div class="fixed top-4 right-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded max-w-sm"
      role="alert">
      <span class="block sm:inline">{{ session('success') }}</span>
    </div>
  @endif
@endsection
