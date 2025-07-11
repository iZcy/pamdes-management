{{-- resources/views/filament/auth/login.blade.php --}}
<x-filament-panels::page.simple>
  {{-- Auto-logout notification --}}
  @if (session('logout_message') || session('logout_type'))
    <div
      class="mb-6 p-4 rounded-lg border-l-4 {{ session('logout_type') === 'access_denied' ? 'bg-red-50 border-red-400' : 'bg-yellow-50 border-yellow-400' }}">
      <div class="flex">
        <div class="flex-shrink-0">
          @if (session('logout_type') === 'access_denied')
            <x-heroicon-s-x-circle class="h-5 w-5 text-red-400" />
          @else
            <x-heroicon-s-exclamation-triangle class="h-5 w-5 text-yellow-400" />
          @endif
        </div>
        <div class="ml-3">
          <p class="text-sm {{ session('logout_type') === 'access_denied' ? 'text-red-800' : 'text-yellow-800' }}">
            <strong>Session Ended:</strong>
            {{ session('logout_message', 'You have been logged out for security reasons.') }}
          </p>
          @if (session('logout_type') === 'access_denied')
            <p class="text-xs text-red-600 mt-1">
              Please contact your administrator if you believe this is an error.
            </p>
          @endif
        </div>
      </div>
    </div>
  @endif

  {{-- Regular error messages --}}
  @if (session('error'))
    <div class="mb-6 p-4 rounded-lg border-l-4 bg-red-50 border-red-400">
      <div class="flex">
        <div class="flex-shrink-0">
          <x-heroicon-s-x-circle class="h-5 w-5 text-red-400" />
        </div>
        <div class="ml-3">
          <p class="text-sm text-red-800">{{ session('error') }}</p>
        </div>
      </div>
    </div>
  @endif

  {{-- Success messages --}}
  @if (session('success'))
    <div class="mb-6 p-4 rounded-lg border-l-4 bg-green-50 border-green-400">
      <div class="flex">
        <div class="flex-shrink-0">
          <x-heroicon-s-check-circle class="h-5 w-5 text-green-400" />
        </div>
        <div class="ml-3">
          <p class="text-sm text-green-800">{{ session('success') }}</p>
        </div>
      </div>
    </div>
  @endif

  {{-- Village context information --}}
  @if (config('pamdes.current_village'))
    <div class="mb-6 p-3 rounded-lg bg-blue-50 border border-blue-200">
      <div class="flex items-center">
        <x-heroicon-s-map-pin class="h-4 w-4 text-blue-500 mr-2" />
        <p class="text-sm text-blue-800">
          <strong>Desa:</strong> {{ config('pamdes.current_village.name') }}
        </p>
      </div>
    </div>
  @elseif (config('pamdes.is_super_admin_domain'))
    <div class="mb-6 p-3 rounded-lg bg-purple-50 border border-purple-200">
      <div class="flex items-center">
        <x-heroicon-s-shield-check class="h-4 w-4 text-purple-500 mr-2" />
        <p class="text-sm text-purple-800">
          <strong>Super Admin Portal</strong>
        </p>
      </div>
    </div>
  @endif

  {{-- Login form --}}
  <x-filament-panels::form wire:submit="authenticate">
    {{ $this->form }}

    <x-filament-panels::form.actions :actions="$this->getCachedFormActions()" :full-width="$this->hasFullWidthFormActions()" />
  </x-filament-panels::form>

  {{-- Additional information for users --}}
  <div class="mt-6 text-center">
    <div class="text-xs text-gray-500 space-y-1">
      <p>Sistem PAMDes - Pengelolaan Air Minum Desa</p>
      @if (config('pamdes.current_village'))
        <p>{{ config('pamdes.current_village.name') }}</p>
      @endif
      <p class="text-xs text-gray-400">
        Akses Anda dipantau untuk keamanan sistem
      </p>
    </div>
  </div>

  {{-- JavaScript for auto-refresh on access issues --}}
  <script>
    // Check if user was logged out due to access issues
    @if (session('logout_type') === 'access_denied')
      // Add a small delay before allowing form submission to ensure user reads the message
      document.addEventListener('DOMContentLoaded', function() {
        const form = document.querySelector('form');
        const submitButtons = form.querySelectorAll('button[type="submit"]');

        // Disable submit for 3 seconds to ensure user reads the message
        submitButtons.forEach(button => {
          button.disabled = true;
          button.style.opacity = '0.5';
        });

        setTimeout(() => {
          submitButtons.forEach(button => {
            button.disabled = false;
            button.style.opacity = '1';
          });
        }, 3000);
      });
    @endif

    // Add session timeout warning (optional enhancement)
    let sessionWarningShown = false;
    const checkSessionTimeout = () => {
      if (sessionWarningShown) return;

      // Warning 5 minutes before session expires (Laravel default is 120 minutes)
      const sessionLifetime = {{ config('session.lifetime', 120) }} * 60 * 1000; // Convert to milliseconds
      const warningTime = sessionLifetime - (5 * 60 * 1000); // 5 minutes before expiry

      setTimeout(() => {
        if (!sessionWarningShown) {
          sessionWarningShown = true;
          alert('Your session will expire in 5 minutes. Please save your work and refresh the page if needed.');
        }
      }, warningTime);
    };

    // Only run session timeout check if user is logged in
    @auth
    checkSessionTimeout();
    @endauth
  </script>
</x-filament-panels::page.simple>
