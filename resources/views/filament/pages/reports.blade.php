<x-filament-panels::page>
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- Collection Report -->
    <div class="bg-transparent rounded-lg shadow p-6">
      <h3 class="text-lg font-semibold mb-4">Laporan Pembayaran Bulan Ini</h3>
      @php $collections = $this->getCollectionReport(); @endphp

      <div class="space-y-3">
        @foreach ($collections as $method => $payments)
          <div class="flex justify-between items-center py-2 border-b">
            <span class="font-medium">{{ ucfirst($method) }}</span>
            <div class="text-right">
              <div class="font-semibold">Rp {{ number_format($payments->sum('amount_paid')) }}</div>
              <div class="text-sm text-transparent">{{ $payments->count() }} transaksi</div>
            </div>
          </div>
        @endforeach

        <div class="flex justify-between items-center py-2 border-t-2 border-transparent font-bold">
          <span>Total</span>
          <span>Rp {{ number_format($collections->flatten()->sum('amount_paid')) }}</span>
        </div>
      </div>
    </div>

    <!-- Outstanding Bills -->
    <div class="bg-transparent rounded-lg shadow p-6">
      <h3 class="text-lg font-semibold mb-4">Tagihan Belum Bayar</h3>
      @php $outstanding = $this->getOutstandingReport(); @endphp

      <div class="max-h-64 overflow-y-auto">
        @foreach ($outstanding->take(10) as $bill)
          <div class="flex justify-between items-center py-2 border-b">
            <div>
              <div class="font-medium">{{ $bill->waterUsage->customer->name }}</div>
              <div class="text-sm text-transparent">
                {{ $bill->waterUsage->customer->customer_code }} -
                {{ $bill->waterUsage->billingPeriod->period_name }}
              </div>
            </div>
            <div class="text-right">
              <div class="font-semibold">Rp {{ number_format($bill->total_amount) }}</div>
              <div class="text-xs {{ $bill->status === 'overdue' ? 'text-red-600' : 'text-yellow-600' }}">
                {{ $bill->status === 'overdue' ? 'Terlambat' : 'Belum Bayar' }}
              </div>
            </div>
          </div>
        @endforeach
      </div>

      @if ($outstanding->count() > 10)
        <div class="text-center text-sm text-transparent mt-4">
          Dan {{ $outstanding->count() - 10 }} tagihan lainnya...
        </div>
      @endif
    </div>

    <!-- Usage Report -->
    <div class="bg-transparent rounded-lg shadow p-6 lg:col-span-2">
      <h3 class="text-lg font-semibold mb-4">Top 10 Pemakaian Air</h3>
      @php $usages = $this->getUsageReport(); @endphp

      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-transparent">
          <thead class="bg-transparent">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-transparent uppercase">Pelanggan</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-transparent uppercase">Pemakaian</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-transparent uppercase">Meter Awal</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-transparent uppercase">Meter Akhir</th>
            </tr>
          </thead>
          <tbody class="bg-transparent divide-y divide-transparent">
            @foreach ($usages as $usage)
              <tr>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="text-sm font-medium text-gray-900">{{ $usage->customer->name }}</div>
                  <div class="text-sm text-transparent">{{ $usage->customer->customer_code }}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="text-sm font-bold text-blue-600">{{ $usage->total_usage_m3 }} m³</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  {{ number_format($usage->initial_meter) }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  {{ number_format($usage->final_meter) }}
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </div>
</x-filament-panels::page>
