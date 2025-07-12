{{-- resources/views/filament/layouts/base.blade.php --}}
{{-- Extend the default Filament layout to include our custom scripts --}}

@extends(filament()->getLayout())

@push('scripts')
  <script>
    // Enhanced print receipt functionality for Filament admin
    document.addEventListener('DOMContentLoaded', function() {
      // Handle bulk print URLs from session
      @if (session('openUrls'))
        const urls = @json(session('openUrls'));
        if (urls && Array.isArray(urls)) {
          if (urls.length > 5) {
            if (confirm(`Akan membuka ${urls.length} tab kwitansi. Pastikan popup tidak diblokir. Lanjutkan?`)) {
              openMultipleReceipts(urls);
            }
          } else {
            openMultipleReceipts(urls);
          }
        }
      @endif

      // Function to open multiple receipt tabs
      function openMultipleReceipts(urls) {
        urls.forEach((url, index) => {
          setTimeout(() => {
            const newWindow = window.open(url, '_blank',
            'width=800,height=1000,scrollbars=yes,resizable=yes');
            if (!newWindow) {
              console.warn('Popup mungkin diblokir oleh browser untuk URL:', url);
              // Fallback: try opening in same tab
              if (index === 0) {
                window.location.href = url;
              }
            }
          }, index * 200); // 200ms delay between each tab
        });
      }

      // Enhanced individual print button handler
      document.addEventListener('click', function(e) {
        if (e.target.closest('a[href*="/receipt"]') || e.target.closest('a[href*="/bills"][href*="/receipt"]')) {
          const link = e.target.closest('a');
          if (link && link.href) {
            e.preventDefault();
            window.open(
              link.href,
              '_blank',
              'width=800,height=1000,scrollbars=yes,resizable=yes,toolbar=no,menubar=no'
            );
          }
        }
      });

      // Add print functionality to action buttons
      const printButtons = document.querySelectorAll('[data-action="print_receipt"]');
      printButtons.forEach(button => {
        button.addEventListener('click', function(e) {
          e.preventDefault();
          const url = this.getAttribute('data-url') || this.href;
          if (url) {
            window.open(
              url,
              '_blank',
              'width=800,height=1000,scrollbars=yes,resizable=yes'
            );
          }
        });
      });
    });

    // Utility function for manual print triggering
    window.printReceipt = function(url) {
      if (url) {
        window.open(
          url,
          '_blank',
          'width=800,height=1000,scrollbars=yes,resizable=yes,toolbar=no,menubar=no'
        );
      }
    };

    // Success notification for bulk operations
    @if (session('openUrls'))
      // Show success message after bulk print
      setTimeout(() => {
        const notification = document.createElement('div');
        notification.className =
          'fixed top-4 right-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded shadow-lg z-50';
        notification.innerHTML = `
            <div class="flex items-center">
                <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                </svg>
                <span>Kwitansi telah dibuka di tab baru</span>
                <button onclick="this.parentElement.parentElement.remove()" class="ml-2 text-green-500 hover:text-green-700">×</button>
            </div>
        `;
        document.body.appendChild(notification);

        // Auto-hide after 5 seconds
        setTimeout(() => {
          if (notification.parentElement) {
            notification.remove();
          }
        }, 5000);
      }, 1000);
    @endif
  </script>

  {{-- Add print-specific CSS --}}
  <style>
    .print-receipt-btn {
      transition: all 0.2s ease-in-out;
    }

    .print-receipt-btn:hover {
      transform: translateY(-1px);
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    /* Enhance Filament action buttons */
    [data-action="print_receipt"] {
      background-color: #6B7280 !important;
      border-color: #6B7280 !important;
    }

    [data-action="print_receipt"]:hover {
      background-color: #4B5563 !important;
      border-color: #4B5563 !important;
    }

    /* Print button icon styling */
    .print-icon {
      width: 16px;
      height: 16px;
      margin-right: 8px;
    }
  </style>
@endpush
