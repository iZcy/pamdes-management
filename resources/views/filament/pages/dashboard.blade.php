{{-- resources/views/filament/pages/dashboard.blade.php --}}
<x-filament-panels::page>
  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
    @foreach ($this->getStats() as $stat)
      <x-filament::stats.overview.stat :chart="$stat->getChart()" :chart-color="$stat->getChartColor()" :color="$stat->getColor()" :description="$stat->getDescription()"
        :description-icon="$stat->getDescriptionIcon()" :description-icon-position="$stat->getDescriptionIconPosition()" :extra-attributes="$stat->getExtraAttributes()" :icon="$stat->getIcon()" :icon-color="$stat->getIconColor()" :icon-position="$stat->getIconPosition()"
        :label="$stat->getLabel()" :url="$stat->getUrl()" :value="$stat->getValue()" />
    @endforeach
  </div>

  @if (config('pamdes.current_village'))
    <div class="bg-white rounded-lg shadow p-6 mb-6">
      <h3 class="text-lg font-semibold mb-4">Informasi Desa</h3>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <p class="text-sm text-gray-600">Nama Desa</p>
          <p class="font-medium">{{ config('pamdes.current_village.name') }}</p>
        </div>
        <div>
          <p class="text-sm text-gray-600">Kode Desa</p>
          <p class="font-medium">{{ config('pamdes.current_village.code') }}</p>
        </div>
      </div>
    </div>
  @endif

  <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="bg-white rounded-lg shadow p-6">
      <h3 class="text-lg font-semibold mb-4">Tagihan Terbaru</h3>
      <div class="space-y-3">
        @php
          $recentBills = \App\Models\Bill::with(['waterUsage.customer', 'waterUsage.billingPeriod'])
              ->latest()
              ->limit(5)
              ->get();
        @endphp

        @forelse($recentBills as $bill)
          <div class="flex justify-between items-center py-2 border-b">
            <div>
              <p class="font-medium">{{ $bill->waterUsage->customer->name }}</p>
              <p class="text-sm text-gray-600">
                {{ $bill->waterUsage->customer->customer_code }} -
                {{ $bill->waterUsage->billingPeriod->period_name }}
              </p>
            </div>
            <div class="text-right">
              <p class="font-medium">Rp {{ number_format($bill->total_amount) }}</p>
              <span
                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                {{ $bill->status === 'paid'
                                    ? 'bg-green-100 text-green-800'
                                    : ($bill->status === 'overdue'
                                        ? 'bg-red-100 text-red-800'
                                        : 'bg-yellow-100 text-yellow-800') }}">
                {{ ucfirst($bill->status) }}
              </span>
            </div>
          </div>
        @empty
          <p class="text-gray-500 text-center py-4">Belum ada tagihan</p>
        @endforelse
      </div>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
      <h3 class="text-lg font-semibold mb-4">Pembayaran Hari Ini</h3>
      <div class="space-y-3">
        @php
          $todayPayments = \App\Models\Payment::with(['bill.waterUsage.customer'])
              ->whereDate('payment_date', today())
              ->latest()
              ->limit(5)
              ->get();
        @endphp

        @forelse($todayPayments as $payment)
          <div class="flex justify-between items-center py-2 border-b">
            <div>
              <p class="font-medium">{{ $payment->bill->waterUsage->customer->name }}</p>
              <p class="text-sm text-gray-600">
                {{ $payment->payment_method_label }} -
                {{ $payment->payment_date->format('H:i') }}
              </p>
            </div>
            <div class="text-right">
              <p class="font-medium text-green-600">Rp {{ number_format($payment->amount_paid) }}</p>
            </div>
          </div>
        @empty
          <p class="text-gray-500 text-center py-4">Belum ada pembayaran hari ini</p>
        @endforelse
      </div>
    </div>
  </div>
</x-filament-panels::page>
