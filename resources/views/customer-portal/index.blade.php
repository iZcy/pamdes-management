<!DOCTYPE html>
@php
  $villageModel = \App\Models\Village::find($village['id']);
@endphp

<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Portal Pelanggan - PAMDes {{ $village['name'] ?? 'Desa' }}</title>
  <link rel="icon" type="image/x-icon" href="{{ $villageModel->getFaviconUrl() }}">
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');

    body {
      font-family: 'Inter', sans-serif;
    }

    .glass-effect {
      background: rgba(255, 255, 255, 0.25);
      backdrop-filter: blur(10px);
      border: 1px solid rgba(255, 255, 255, 0.18);
    }

    .hero-gradient {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }

    .card-gradient {
      background: linear-gradient(145deg, #ffffff 0%, #f8fafc 100%);
    }

    .animate-float {
      animation: float 6s ease-in-out infinite;
    }

    .animate-float-delayed {
      animation: float 6s ease-in-out infinite;
      animation-delay: 2s;
    }

    @keyframes float {

      0%,
      100% {
        transform: translateY(0px);
      }

      50% {
        transform: translateY(-20px);
      }
    }

    .wave-bg {
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 1200 120' preserveAspectRatio='none'%3E%3Cpath d='M321.39,56.44c58-10.79,114.16-30.13,172-41.86,82.39-16.72,168.19-17.73,250.45-.39C823.78,31,906.67,72,985.66,92.83c70.05,18.48,146.53,26.09,214.34,3V0H0V27.35A600.21,600.21,0,0,0,321.39,56.44Z' fill='%23ffffff'%3E%3C/path%3E%3C/svg%3E");
      background-repeat: no-repeat;
      background-position: bottom;
      background-size: cover;
    }

    .input-glow:focus {
      box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
      border-color: #667eea;
    }

    .btn-primary {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      transition: all 0.3s ease;
    }

    .btn-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
    }

    .feature-card {
      transition: all 0.3s ease;
    }

    .feature-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
    }

    .water-drop {
      animation: drop 3s ease-in-out infinite;
    }

    @keyframes drop {

      0%,
      100% {
        transform: translateY(0) scale(1);
        opacity: 0.7;
      }

      50% {
        transform: translateY(10px) scale(1.1);
        opacity: 1;
      }
    }
  </style>
</head>

<body class="bg-gray-50 min-h-screen">
  <!-- Header -->
  <!-- Header -->
  <header class="hero-gradient text-black relative overflow-hidden wave-bg">
    <!-- Background Elements -->
    <div class="absolute inset-0">
      <div class="absolute top-10 left-10 w-20 h-20 bg-blue-500 bg-opacity-10 rounded-full animate-float"></div>
      <div class="absolute top-32 right-20 w-16 h-16 bg-blue-500 bg-opacity-10 rounded-full animate-float-delayed">
      </div>
      <div class="absolute bottom-20 left-1/4 w-12 h-12 bg-blue-500 bg-opacity-10 rounded-full animate-float"></div>
    </div>

    <div class="container mx-auto px-4 py-16 relative z-10">
      <div class="text-center">
        <!-- Village Logo or Default Icon -->
        <div class="mb-6">
          @if($villageModel->hasLogo())
            <div class="inline-flex items-center justify-center mb-4">
              <img src="{{ $villageModel->getLogoUrl() }}" alt="Logo {{ $village['name'] }}" 
                   class="w-20 h-20 object-contain rounded-full shadow-lg bg-white/20 p-2">
            </div>
          @else
            <div class="inline-flex items-center justify-center w-20 h-20 bg-blue-500 bg-opacity-20 rounded-full mb-4">
              <svg class="w-10 h-10 text-blue-900 water-drop" fill="currentColor" viewBox="0 0 24 24">
                <path d="M12 2c0 0-6 5.686-6 10a6 6 0 0 0 12 0c0-4.314-6-10-6-10z" />
              </svg>
            </div>
          @endif
        </div>

        <h1 class="text-4xl md:text-5xl font-bold mb-4 tracking-tight text-black">
          Portal Pelanggan PAMDes {{ $village['name'] ?? 'Sistem Pengelolaan Air Minum Desa' }}
        </h1>
      </div>
    </div>
  </header>

  <!-- Main Content -->
  <main class="container mx-auto px-4 pb-12">
    <div class="max-w-6xl mx-auto">

      <div class="max-w-4xl mx-auto">

        <!-- Single Column - Check Bills Form -->
        <div class="card-gradient rounded-3xl shadow-2xl p-8 border border-gray-100">
          
          <!-- Customer Code Input Form Section -->
            <div class="text-center mb-8">
              <div
                class="inline-flex items-center justify-center w-16 h-16 bg-gradient-to-r from-blue-500 to-purple-600 rounded-2xl mb-4">
                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                  </path>
                </svg>
              </div>
              <h2 class="text-2xl font-bold text-gray-800 mb-2">Cek Tagihan Air</h2>
              <p class="text-gray-600">Masukkan kode pelanggan untuk melihat tagihan Anda</p>
            </div>

            <!-- Error Messages -->
            @if ($errors->any())
              <div class="mb-6 p-4 bg-red-50 border-l-4 border-red-400 rounded-lg">
                <div class="flex">
                  <svg class="w-5 h-5 text-red-400 mr-3 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd"
                      d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                      clip-rule="evenodd"></path>
                  </svg>
                  <div>
                    <h4 class="text-red-800 font-medium mb-1">Terjadi kesalahan:</h4>
                    <ul class="text-red-700 text-sm space-y-1">
                      @foreach ($errors->all() as $error)
                        <li>• {{ $error }}</li>
                      @endforeach
                    </ul>
                  </div>
                </div>
              </div>
            @endif

            <!-- Form -->
            <div class="flex-grow flex flex-col justify-center">
              <form action="{{ route('portal.lookup') }}" method="POST" class="space-y-6">
                @csrf
                <div>
                  <label for="customer_code" class="block text-sm font-semibold text-gray-700 mb-3">
                    Kode Pelanggan
                  </label>
                  <div class="relative">
                    <input type="text" id="customer_code" name="customer_code" value="{{ old('customer_code') }}"
                      placeholder="Contoh: PAM001, C123456"
                      class="w-full px-4 py-4 border-2 border-gray-200 rounded-xl focus:outline-none input-glow transition-all duration-300 text-lg font-medium placeholder-gray-400"
                      required autocomplete="off">
                    <div class="absolute inset-y-0 right-0 flex items-center pr-4">
                      <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M15 7a2 2 0 012 2m0 0a2 2 0 012 2m-2-2h-3m-7 2a2 2 0 002 2h3m0 0a2 2 0 002-2M9 7a2 2 0 012-2m0 0a2 2 0 012 2m-6 2a2 2 0 002 2h3">
                        </path>
                      </svg>
                    </div>
                  </div>
                </div>

                <button type="submit"
                  class="w-full btn-primary text-white py-4 px-6 rounded-xl text-lg font-semibold shadow-lg hover:shadow-xl focus:outline-none focus:ring-4 focus:ring-blue-200">
                  <span class="flex items-center justify-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Cek Tagihan Saya
                  </span>
                </button>
              </form>
            </div>

          <!-- Help Text -->
          <div class="mt-8 p-4 bg-blue-50 rounded-xl border border-blue-100">
            <div class="flex items-start">
              <svg class="w-5 h-5 text-blue-500 mr-3 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd"
                  d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                  clip-rule="evenodd"></path>
              </svg>
              <div>
                <h4 class="text-blue-800 font-medium mb-1">Tidak tahu kode pelanggan?</h4>
                <p class="text-blue-700 text-sm leading-relaxed">
                  Hubungi petugas PAMDes atau lihat pada tagihan terakhir Anda.
                  Kode pelanggan biasanya terdiri dari huruf dan angka seperti PAM001 atau C123456.
                </p>
              </div>
            </div>
          </div>

          <!-- Payment Methods -->
          <div class="mt-8 feature-card bg-gradient-to-r from-green-50 to-emerald-50 p-6 rounded-2xl border border-green-100">
            <div class="flex items-center mb-3">
              <svg class="w-6 h-6 text-green-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z">
                </path>
              </svg>
              <h4 class="font-semibold text-green-800">Metode Pembayaran</h4>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
              <div class="flex items-center text-green-700">
                <div class="w-2 h-2 bg-green-500 rounded-full mr-3"></div>
                <span>💰 Tunai di kantor desa</span>
              </div>
              <div class="flex items-center text-green-700">
                <div class="w-2 h-2 bg-green-500 rounded-full mr-3"></div>
                <span>🏦 Transfer bank</span>
              </div>
              <div class="flex items-center text-green-700">
                <div class="w-2 h-2 bg-green-500 rounded-full mr-3"></div>
                <span>📱 QRIS (E-wallet & Mobile Banking)</span>
              </div>
            </div>
          </div>

        </div>
      </div>

      <!-- Bottom CTA Section -->
      <div class="mt-16">
        <div class="max-w-4xl mx-auto">
          <div
            class="bg-gradient-to-r from-blue-600 via-purple-600 to-blue-800 rounded-3xl p-8 text-white text-center relative overflow-hidden">
          <!-- Background Pattern -->
          <div class="absolute inset-0 opacity-10">
            <div class="absolute top-0 left-0 w-40 h-40 bg-white rounded-full -translate-x-20 -translate-y-20"></div>
            <div class="absolute bottom-0 right-0 w-32 h-32 bg-white rounded-full translate-x-16 translate-y-16"></div>
            <div class="absolute top-1/2 left-1/2 w-24 h-24 bg-white rounded-full -translate-x-12 -translate-y-12">
            </div>
          </div>

          <div class="relative z-10">
            <h3 class="text-2xl md:text-3xl font-bold mb-4">Butuh Bantuan?</h3>
            <p class="text-lg text-blue-100 mb-6 max-w-2xl mx-auto">
              Tim customer service kami siap membantu Anda 24/7 untuk pertanyaan seputar layanan air
            </p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center items-center">
              <a href="{{ $villageModel->getTel() }}"
                class="inline-flex items-center px-6 py-3 bg-white text-blue-600 rounded-xl font-semibold hover:bg-blue-50 transition-colors">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z">
                  </path>
                </svg>
                Hubungi Kami
              </a>
              <a href="{{ $villageModel->getWA() }}"
                class="inline-flex items-center px-6 py-3 bg-green-500 text-white rounded-xl font-semibold hover:bg-green-600 transition-colors">
                <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 24 24">
                  <path
                    d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893A11.821 11.821 0 0020.885 3.108" />
                </svg>
                WhatsApp
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  </main>

  <!-- Footer -->
  <footer class="bg-gray-900 text-white py-12 mt-16">
    <div class="container mx-auto px-4">
      <div class="text-center">
        <div class="mb-4">
          @if($villageModel->hasLogo())
            <div class="inline-flex items-center justify-center mb-4">
              <img src="{{ $villageModel->getLogoUrl() }}" alt="Logo {{ $village['name'] }}" 
                   class="w-12 h-12 object-contain rounded-xl bg-blue-600 p-2">
            </div>
          @else
            <div class="inline-flex items-center justify-center w-12 h-12 bg-blue-600 rounded-xl mb-4">
              <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 24 24">
                <path d="M12 2c0 0-6 5.686-6 10a6 6 0 0 0 12 0c0-4.314-6-10-6-10z" />
              </svg>
            </div>
          @endif
        </div>
        <h3 class="text-xl font-bold mb-2">PAMDes {{ $village['name'] ?? 'Desa' }}</h3>
        <p class="text-gray-400 mb-6">Sistem Pengelolaan Air Minum Desa</p>

        <div class="border-t border-gray-800 pt-6">
          <p class="text-gray-500 text-sm">
            © 2025 PAMDes {{ $village['name'] ?? 'Desa' }}. Semua hak dilindungi.
          </p>
          <p class="text-gray-600 text-xs mt-2">
            Dikembangkan untuk melayani masyarakat dengan transparansi dan akuntabilitas
          </p>
        </div>
      </div>
    </div>
  </footer>

  <script>
    // Add some interactive effects
    document.addEventListener('DOMContentLoaded', function() {
      // Auto-focus on customer code input
      const customerCodeInput = document.getElementById('customer_code');
      if (customerCodeInput) {
        customerCodeInput.focus();
      }

      // Add loading state to form submission
      const form = document.querySelector('form');
      const submitButton = form.querySelector('button[type="submit"]');

      form.addEventListener('submit', function() {
        submitButton.innerHTML = `
            <span class="flex items-center justify-center">
              <svg class="animate-spin w-5 h-5 mr-2" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
              Mencari Tagihan...
            </span>
          `;
        submitButton.disabled = true;
      });

      // Add smooth scroll for better UX
      document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
          e.preventDefault();
          const target = document.querySelector(this.getAttribute('href'));
          if (target) {
            target.scrollIntoView({
              behavior: 'smooth'
            });
          }
        });
      });

      // Format customer code input (uppercase and remove spaces)
      customerCodeInput.addEventListener('input', function(e) {
        let value = e.target.value.toUpperCase().replace(/\s/g, '');
        e.target.value = value;
      });

      // Add enter key support
      customerCodeInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
          form.submit();
        }
      });
    });
  </script>
</body>

</html>
