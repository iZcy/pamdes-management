<?php
// resources/views/filament/pages/dashboard.blade.php - Enhanced with comprehensive resource stats
?>
<x-filament-panels::page>
  {{-- Stats Overview - Already handled by StatsOverview widget --}}

  {{-- Village Context Information --}}
  @if (config('pamdes.current_village'))
    <div class="mb-6">
      <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold mb-4 flex items-center">
          <svg class="w-5 h-5 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
          </svg>
          Informasi Desa
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
          <div>
            <p class="text-sm text-gray-600">Nama Desa</p>
            <p class="font-medium text-lg">{{ config('pamdes.current_village.name') }}</p>
          </div>
          <div>
            <p class="text-sm text-gray-600">Biaya Admin</p>
            <p class="font-medium">Rp {{ number_format(config('pamdes.current_village.default_admin_fee', 0)) }}</p>
          </div>
          <div>
            <p class="text-sm text-gray-600">Biaya Pemeliharaan</p>
            <p class="font-medium">Rp {{ number_format(config('pamdes.current_village.default_maintenance_fee', 0)) }}
            </p>
          </div>
          <div>
            <p class="text-sm text-gray-600">Status</p>
            <span
              class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
              Aktif
            </span>
          </div>
        </div>
      </div>
    </div>
  @endif

  {{-- Resource Overview Stats --}}
  <div class="mb-6">
    <h3 class="text-lg font-semibold mb-4 flex items-center">
      <svg class="w-5 h-5 mr-2 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
      </svg>
      Ringkasan Sistem
    </h3>

    @php
      $user = \App\Models\User::find(Auth::id());
      $currentVillage = $user?->getCurrentVillageContext();

      // Get comprehensive stats
      if ($currentVillage) {
          // Customers
          $totalCustomers = \App\Models\Customer::where('village_id', $currentVillage)->count();
          $activeCustomers = \App\Models\Customer::where('village_id', $currentVillage)
              ->where('status', 'active')
              ->count();
          $newCustomersThisMonth = \App\Models\Customer::where('village_id', $currentVillage)
              ->whereMonth('created_at', now()->month)
              ->whereYear('created_at', now()->year)
              ->count();

          // Water Tariffs
          $totalTariffs = \App\Models\WaterTariff::where('village_id', $currentVillage)->count();
          $activeTariffs = \App\Models\WaterTariff::where('village_id', $currentVillage)
              ->where('is_active', true)
              ->count();

          // Billing Periods
          $totalPeriods = \App\Models\BillingPeriod::where('village_id', $currentVillage)->count();
          $activePeriods = \App\Models\BillingPeriod::where('village_id', $currentVillage)
              ->where('status', 'active')
              ->count();
          $completedPeriods = \App\Models\BillingPeriod::where('village_id', $currentVillage)
              ->where('status', 'completed')
              ->count();

          // Water Usage
          $totalUsages = \App\Models\WaterUsage::whereHas('customer', function ($q) use ($currentVillage) {
              $q->where('village_id', $currentVillage);
          })->count();
          $thisMonthUsages = \App\Models\WaterUsage::whereHas('customer', function ($q) use ($currentVillage) {
              $q->where('village_id', $currentVillage);
          })
              ->whereMonth('usage_date', now()->month)
              ->whereYear('usage_date', now()->year)
              ->count();

          // Bills
          $totalBills = \App\Models\Bill::whereHas('waterUsage.customer', function ($q) use ($currentVillage) {
              $q->where('village_id', $currentVillage);
          })->count();
          $paidBills = \App\Models\Bill::whereHas('waterUsage.customer', function ($q) use ($currentVillage) {
              $q->where('village_id', $currentVillage);
          })
              ->where('status', 'paid')
              ->count();
          $overdueBills = \App\Models\Bill::whereHas('waterUsage.customer', function ($q) use ($currentVillage) {
              $q->where('village_id', $currentVillage);
          })
              ->where('status', 'overdue')
              ->count();

          // Payments
          $totalPayments = \App\Models\Payment::whereHas('bill.waterUsage.customer', function ($q) use (
              $currentVillage,
          ) {
              $q->where('village_id', $currentVillage);
          })->count();
          $thisMonthPayments = \App\Models\Payment::whereHas('bill.waterUsage.customer', function ($q) use (
              $currentVillage,
          ) {
              $q->where('village_id', $currentVillage);
          })
              ->whereMonth('payment_date', now()->month)
              ->whereYear('payment_date', now()->year)
              ->count();
          $todayPayments = \App\Models\Payment::whereHas('bill.waterUsage.customer', function ($q) use (
              $currentVillage,
          ) {
              $q->where('village_id', $currentVillage);
          })
              ->whereDate('payment_date', today())
              ->count();

          // Financial
          $totalRevenue = \App\Models\Payment::whereHas('bill.waterUsage.customer', function ($q) use (
              $currentVillage,
          ) {
              $q->where('village_id', $currentVillage);
          })->sum('amount_paid');
          $thisMonthRevenue = \App\Models\Payment::whereHas('bill.waterUsage.customer', function ($q) use (
              $currentVillage,
          ) {
              $q->where('village_id', $currentVillage);
          })
              ->whereMonth('payment_date', now()->month)
              ->whereYear('payment_date', now()->year)
              ->sum('amount_paid');
          $outstandingAmount = \App\Models\Bill::whereHas('waterUsage.customer', function ($q) use ($currentVillage) {
              $q->where('village_id', $currentVillage);
          })
              ->where('status', '!=', 'paid')
              ->sum('total_amount');
      } else {
          // Default values if no village context
          $totalCustomers = $activeCustomers = $newCustomersThisMonth = 0;
          $totalTariffs = $activeTariffs = 0;
          $totalPeriods = $activePeriods = $completedPeriods = 0;
          $totalUsages = $thisMonthUsages = 0;
          $totalBills = $paidBills = $overdueBills = 0;
          $totalPayments = $thisMonthPayments = $todayPayments = 0;
          $totalRevenue = $thisMonthRevenue = $outstandingAmount = 0;
      }
    @endphp

    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
      {{-- Customers Stats --}}
      <div class="bg-blue-50 p-4 rounded-lg border border-blue-200">
        <div class="text-center">
          <p class="text-2xl font-bold text-blue-600">{{ $totalCustomers }}</p>
          <p class="text-sm text-blue-700">Total Pelanggan</p>
          <p class="text-xs text-blue-600 mt-1">{{ $activeCustomers }} aktif</p>
        </div>
      </div>

      {{-- Tariffs Stats --}}
      <div class="bg-purple-50 p-4 rounded-lg border border-purple-200">
        <div class="text-center">
          <p class="text-2xl font-bold text-purple-600">{{ $totalTariffs }}</p>
          <p class="text-sm text-purple-700">Tarif Air</p>
          <p class="text-xs text-purple-600 mt-1">{{ $activeTariffs }} aktif</p>
        </div>
      </div>

      {{-- Billing Periods Stats --}}
      <div class="bg-indigo-50 p-4 rounded-lg border border-indigo-200">
        <div class="text-center">
          <p class="text-2xl font-bold text-indigo-600">{{ $totalPeriods }}</p>
          <p class="text-sm text-indigo-700">Periode Tagihan</p>
          <p class="text-xs text-indigo-600 mt-1">{{ $activePeriods }} aktif</p>
        </div>
      </div>

      {{-- Water Usage Stats --}}
      <div class="bg-cyan-50 p-4 rounded-lg border border-cyan-200">
        <div class="text-center">
          <p class="text-2xl font-bold text-cyan-600">{{ $totalUsages }}</p>
          <p class="text-sm text-cyan-700">Pembacaan Meter</p>
          <p class="text-xs text-cyan-600 mt-1">{{ $thisMonthUsages }} bulan ini</p>
        </div>
      </div>

      {{-- Bills Stats --}}
      <div class="bg-orange-50 p-4 rounded-lg border border-orange-200">
        <div class="text-center">
          <p class="text-2xl font-bold text-orange-600">{{ $totalBills }}</p>
          <p class="text-sm text-orange-700">Total Tagihan</p>
          <p class="text-xs text-orange-600 mt-1">{{ $paidBills }} lunas</p>
        </div>
      </div>

      {{-- Payments Stats --}}
      <div class="bg-green-50 p-4 rounded-lg border border-green-200">
        <div class="text-center">
          <p class="text-2xl font-bold text-green-600">{{ $totalPayments }}</p>
          <p class="text-sm text-green-700">Total Pembayaran</p>
          <p class="text-xs text-green-600 mt-1">{{ $todayPayments }} hari ini</p>
        </div>
      </div>
    </div>

    {{-- Financial Summary --}}
    <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4">
      <div class="bg-green-100 p-4 rounded-lg border border-green-300">
        <div class="text-center">
          <p class="text-xl font-bold text-green-700">Rp {{ number_format($totalRevenue) }}</p>
          <p class="text-sm text-green-600">Total Pendapatan</p>
        </div>
      </div>

      <div class="bg-blue-100 p-4 rounded-lg border border-blue-300">
        <div class="text-center">
          <p class="text-xl font-bold text-blue-700">Rp {{ number_format($thisMonthRevenue) }}</p>
          <p class="text-sm text-blue-600">Pendapatan Bulan Ini</p>
        </div>
      </div>

      <div class="bg-red-100 p-4 rounded-lg border border-red-300">
        <div class="text-center">
          <p class="text-xl font-bold text-red-700">Rp {{ number_format($outstandingAmount) }}</p>
          <p class="text-sm text-red-600">Tunggakan ({{ $overdueBills + ($totalBills - $paidBills) }} tagihan)</p>
        </div>
      </div>
    </div>
  </div>

  {{-- Main Dashboard Content Grid --}}
  <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-6">

    {{-- Payment Collection Report --}}
    <div class="bg-white rounded-lg shadow p-6">
      <h3 class="text-lg font-semibold mb-4 flex items-center">
        <svg class="w-5 h-5 mr-2 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1" />
        </svg>
        Pembayaran Bulan Ini
      </h3>

      @php
        $collections = collect();
        if ($currentVillage) {
            $collections = \App\Models\Payment::whereHas('bill.waterUsage.customer', function ($q) use (
                $currentVillage,
            ) {
                $q->where('village_id', $currentVillage);
            })
                ->whereMonth('payment_date', now()->month)
                ->whereYear('payment_date', now()->year)
                ->get()
                ->groupBy('payment_method');
        }
      @endphp

      <div class="space-y-3">
        @forelse($collections as $method => $payments)
          <div class="flex justify-between items-center py-2 border-b border-gray-100">
            <span class="font-medium capitalize">
              @switch($method)
                @case('cash')
                  Tunai
                @break

                @case('transfer')
                  Transfer
                @break

                @case('qris')
                  QRIS
                @break

                @default
                  {{ ucfirst($method) }}
              @endswitch
            </span>
            <div class="text-right">
              <div class="font-semibold text-green-600">Rp {{ number_format($payments->sum('amount_paid')) }}</div>
              <div class="text-sm text-gray-500">{{ $payments->count() }} transaksi</div>
            </div>
          </div>
          @empty
            <p class="text-gray-500 text-center py-4">Belum ada pembayaran bulan ini</p>
          @endforelse

          @if ($collections->count() > 0)
            <div class="flex justify-between items-center py-2 border-t-2 border-gray-200 font-bold">
              <span>Total</span>
              <span class="text-green-600">Rp {{ number_format($collections->flatten()->sum('amount_paid')) }}</span>
            </div>
          @endif
        </div>
      </div>

      {{-- Outstanding Bills --}}
      <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold mb-4 flex items-center">
          <svg class="w-5 h-5 mr-2 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" />
          </svg>
          Tagihan Menunggak
        </h3>

        @php
          $outstanding = collect();
          if ($currentVillage) {
              $outstanding = \App\Models\Bill::whereHas('waterUsage.customer', function ($q) use ($currentVillage) {
                  $q->where('village_id', $currentVillage);
              })
                  ->where('status', '!=', 'paid')
                  ->with(['waterUsage.customer', 'waterUsage.billingPeriod'])
                  ->orderBy('due_date', 'asc')
                  ->get();
          }

          $totalOutstanding = $outstanding->sum('total_amount');
          $overdueCount = $outstanding->where('status', 'overdue')->count();
        @endphp

        <div class="mb-4 p-3 bg-red-50 rounded-lg">
          <div class="flex justify-between items-center">
            <span class="text-sm font-medium text-red-800">Total Menunggak:</span>
            <span class="font-bold text-red-800">Rp {{ number_format($totalOutstanding) }}</span>
          </div>
          @if ($overdueCount > 0)
            <div class="flex justify-between items-center mt-1">
              <span class="text-xs text-red-600">Terlambat:</span>
              <span class="text-xs font-medium text-red-600">{{ $overdueCount }} tagihan</span>
            </div>
          @endif
        </div>

        <div class="max-h-64 overflow-y-auto">
          @foreach ($outstanding->take(8) as $bill)
            <div class="flex justify-between items-center py-2 border-b border-gray-100">
              <div class="flex-1 min-w-0">
                <div class="font-medium truncate">{{ $bill->waterUsage->customer->name }}</div>
                <div class="text-sm text-gray-500 truncate">
                  {{ $bill->waterUsage->customer->customer_code }} -
                  {{ $bill->waterUsage->billingPeriod->period_name }}
                </div>
              </div>
              <div class="text-right ml-2">
                <div class="font-semibold">Rp {{ number_format($bill->total_amount) }}</div>
                <div class="text-xs {{ $bill->status === 'overdue' ? 'text-red-600' : 'text-yellow-600' }}">
                  {{ $bill->status === 'overdue' ? 'Terlambat' : 'Belum Bayar' }}
                </div>
              </div>
            </div>
          @endforeach

          @if ($outstanding->isEmpty())
            <p class="text-gray-500 text-center py-4">Semua tagihan sudah dibayar! 🎉</p>
          @endif
        </div>

        @if ($outstanding->count() > 8)
          <div class="text-center text-sm text-gray-500 mt-4">
            Dan {{ $outstanding->count() - 8 }} tagihan lainnya...
          </div>
        @endif
      </div>

      {{-- Recent Activity --}}
      <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold mb-4 flex items-center">
          <svg class="w-5 h-5 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          Aktivitas Terbaru
        </h3>
        <div class="space-y-3 max-h-64 overflow-y-auto">
          @php
            $recentActivities = collect();
            if ($currentVillage) {
                // Combine recent payments and bills
                $recentPayments = \App\Models\Payment::whereHas('bill.waterUsage.customer', function ($q) use (
                    $currentVillage,
                ) {
                    $q->where('village_id', $currentVillage);
                })
                    ->with(['bill.waterUsage.customer'])
                    ->latest()
                    ->limit(3)
                    ->get()
                    ->map(function ($payment) {
                        return [
                            'type' => 'payment',
                            'title' => 'Pembayaran diterima',
                            'description' =>
                                $payment->bill->waterUsage->customer->name . ' - ' . $payment->getPaymentMethodLabel(),
                            'amount' => $payment->amount_paid,
                            'time' => $payment->created_at,
                            'icon' => 'check-circle',
                            'color' => 'green',
                        ];
                    });

                $recentBills = \App\Models\Bill::whereHas('waterUsage.customer', function ($q) use ($currentVillage) {
                    $q->where('village_id', $currentVillage);
                })
                    ->with(['waterUsage.customer', 'waterUsage.billingPeriod'])
                    ->latest()
                    ->limit(3)
                    ->get()
                    ->map(function ($bill) {
                        return [
                            'type' => 'bill',
                            'title' => 'Tagihan dibuat',
                            'description' =>
                                $bill->waterUsage->customer->name .
                                ' - ' .
                                $bill->waterUsage->billingPeriod->period_name,
                            'amount' => $bill->total_amount,
                            'time' => $bill->created_at,
                            'icon' => 'document-text',
                            'color' => 'blue',
                        ];
                    });

                $recentActivities = $recentPayments->concat($recentBills)->sortByDesc('time')->take(6);
            }
          @endphp

          @forelse($recentActivities as $activity)
            <div class="flex items-start space-x-3 py-2">
              <div class="flex-shrink-0">
                @if ($activity['type'] === 'payment')
                  <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                    <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                  </div>
                @else
                  <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                    <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                  </div>
                @endif
              </div>
              <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-gray-900">{{ $activity['title'] }}</p>
                <p class="text-sm text-gray-500 truncate">{{ $activity['description'] }}</p>
                <p class="text-xs text-gray-400">{{ $activity['time']->diffForHumans() }}</p>
              </div>
              <div class="text-right">
                <p class="text-sm font-medium text-{{ $activity['color'] }}-600">
                  Rp {{ number_format($activity['amount']) }}
                </p>
              </div>
            </div>
          @empty
            <p class="text-gray-500 text-center py-4">Belum ada aktivitas terbaru</p>
          @endforelse
        </div>
      </div>

      {{-- Top Water Usage --}}
      <div class="bg-white rounded-lg shadow p-6 lg:col-span-2 xl:col-span-3">
        <h3 class="text-lg font-semibold mb-4 flex items-center">
          <svg class="w-5 h-5 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
          </svg>
          Top 10 Pemakaian Air (Periode Aktif)
        </h3>

        @php
          $topUsages = collect();
          if ($currentVillage) {
              $activePeriod = \App\Models\BillingPeriod::where('village_id', $currentVillage)
                  ->where('status', 'active')
                  ->first();

              if ($activePeriod) {
                  $topUsages = \App\Models\WaterUsage::where('period_id', $activePeriod->period_id)
                      ->with('customer')
                      ->orderBy('total_usage_m3', 'desc')
                      ->limit(10)
                      ->get();
              }
          }
        @endphp

        @if ($topUsages->count() > 0)
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ranking</th>
                  <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pelanggan
                  </th>
                  <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pemakaian
                  </th>
                  <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Meter Awal
                  </th>
                  <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Meter Akhir
                  </th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                @foreach ($topUsages as $index => $usage)
                  <tr class="{{ $index < 3 ? 'bg-blue-50' : '' }}">
                    <td class="px-4 py-3 whitespace-nowrap">
                      <div class="flex items-center">
                        @if ($index === 0)
                          <span class="text-yellow-500 font-bold text-lg">🥇</span>
                        @elseif($index === 1)
                          <span class="text-gray-400 font-bold text-lg">🥈</span>
                        @elseif($index === 2)
                          <span class="text-yellow-600 font-bold text-lg">🥉</span>
                        @else
                          <span class="text-gray-600 font-medium">{{ $index + 1 }}</span>
                        @endif
                      </div>
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap">
                      <div class="text-sm font-medium text-gray-900">{{ $usage->customer->name }}</div>
                      <div class="text-sm text-gray-500">{{ $usage->customer->customer_code }}</div>
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap">
                      <div class="text-sm font-bold text-blue-600">{{ $usage->total_usage_m3 }} m³</div>
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                      {{ number_format($usage->initial_meter) }}
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                      {{ number_format($usage->final_meter) }}
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        @else
          <div class="text-center py-8">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
            </svg>
            <p class="mt-2 text-sm text-gray-500">
              Belum ada data pemakaian untuk periode aktif.
              <br>
              Pastikan ada periode tagihan yang berstatus "aktif" dan sudah ada pembacaan meter.
            </p>
          </div>
        @endif
      </div>

      {{-- Monthly Collection Performance --}}
      <div class="bg-white rounded-lg shadow p-6 lg:col-span-2">
        <h3 class="text-lg font-semibold mb-4 flex items-center">
          <svg class="w-5 h-5 mr-2 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
          </svg>
          Performa Penagihan (6 Bulan Terakhir)
        </h3>

        @php
          $monthlyPerformance = collect();
          if ($currentVillage) {
              for ($i = 5; $i >= 0; $i--) {
                  $month = now()->subMonths($i);
                  $monthName = [
                      1 => 'Jan',
                      2 => 'Feb',
                      3 => 'Mar',
                      4 => 'Apr',
                      5 => 'Mei',
                      6 => 'Jun',
                      7 => 'Jul',
                      8 => 'Ags',
                      9 => 'Sep',
                      10 => 'Okt',
                      11 => 'Nov',
                      12 => 'Des',
                  ][$month->month];

                  $monthlyRevenue = \App\Models\Payment::whereHas('bill.waterUsage.customer', function ($q) use (
                      $currentVillage,
                  ) {
                      $q->where('village_id', $currentVillage);
                  })
                      ->whereMonth('payment_date', $month->month)
                      ->whereYear('payment_date', $month->year)
                      ->sum('amount_paid');

                  $monthlyTransactions = \App\Models\Payment::whereHas('bill.waterUsage.customer', function ($q) use (
                      $currentVillage,
                  ) {
                      $q->where('village_id', $currentVillage);
                  })
                      ->whereMonth('payment_date', $month->month)
                      ->whereYear('payment_date', $month->year)
                      ->count();

                  $monthlyPerformance->push([
                      'month' => $monthName . ' ' . $month->format('y'),
                      'revenue' => $monthlyRevenue,
                      'transactions' => $monthlyTransactions,
                  ]);
              }
          }
        @endphp

        @if ($monthlyPerformance->count() > 0)
          <div class="space-y-4">
            @foreach ($monthlyPerformance as $performance)
              <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                <div>
                  <p class="font-medium text-gray-900">{{ $performance['month'] }}</p>
                  <p class="text-sm text-gray-500">{{ $performance['transactions'] }} transaksi</p>
                </div>
                <div class="text-right">
                  <p class="font-bold text-green-600">Rp {{ number_format($performance['revenue']) }}</p>
                  <div class="w-20 bg-gray-200 rounded-full h-2 mt-1">
                    @php
                      $maxRevenue = $monthlyPerformance->max('revenue');
                      $percentage = $maxRevenue > 0 ? ($performance['revenue'] / $maxRevenue) * 100 : 0;
                    @endphp
                    <div class="bg-green-500 h-2 rounded-full" style="width: {{ $percentage }}%"></div>
                  </div>
                </div>
              </div>
            @endforeach
          </div>
        @else
          <p class="text-gray-500 text-center py-8">Belum ada data performa penagihan</p>
        @endif
      </div>

      {{-- Quick Actions --}}
      <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold mb-4 flex items-center">
          <svg class="w-5 h-5 mr-2 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
          </svg>
          Aksi Cepat
        </h3>

        <div class="space-y-3">
          <a href="{{ route('filament.admin.resources.customers.index') }}"
            class="flex items-center p-3 bg-blue-50 rounded-lg hover:bg-blue-100 transition-colors group">
            <svg class="w-5 h-5 text-blue-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-2.53a4 4 0 110 5.292" />
            </svg>
            <div>
              <p class="font-medium text-blue-900">Kelola Pelanggan</p>
              <p class="text-sm text-blue-600">Tambah atau edit data pelanggan</p>
            </div>
          </a>

          <a href="{{ route('filament.admin.resources.water-usages.index') }}"
            class="flex items-center p-3 bg-cyan-50 rounded-lg hover:bg-cyan-100 transition-colors group">
            <svg class="w-5 h-5 text-cyan-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
            </svg>
            <div>
              <p class="font-medium text-cyan-900">Baca Meter</p>
              <p class="text-sm text-cyan-600">Input pembacaan meter air</p>
            </div>
          </a>

          <a href="{{ route('filament.admin.resources.bills.index') }}"
            class="flex items-center p-3 bg-orange-50 rounded-lg hover:bg-orange-100 transition-colors group">
            <svg class="w-5 h-5 text-orange-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <div>
              <p class="font-medium text-orange-900">Kelola Tagihan</p>
              <p class="text-sm text-orange-600">Lihat dan kelola tagihan</p>
            </div>
          </a>

          <a href="{{ route('filament.admin.resources.payments.index') }}"
            class="flex items-center p-3 bg-green-50 rounded-lg hover:bg-green-100 transition-colors group">
            <svg class="w-5 h-5 text-green-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
            </svg>
            <div>
              <p class="font-medium text-green-900">Catat Pembayaran</p>
              <p class="text-sm text-green-600">Input pembayaran baru</p>
            </div>
          </a>
        </div>
      </div>

    </div>

    {{-- Collection Method Distribution --}}
    <div class="mt-6">
      <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold mb-4 flex items-center">
          <svg class="w-5 h-5 mr-2 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z" />
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z" />
          </svg>
          Distribusi Metode Pembayaran (Bulan Ini)
        </h3>

        @php
          $paymentMethods = collect();
          $total = 0;
          if ($currentVillage) {
              $paymentMethods = \App\Models\Payment::whereHas('bill.waterUsage.customer', function ($q) use (
                  $currentVillage,
              ) {
                  $q->where('village_id', $currentVillage);
              })
                  ->whereMonth('payment_date', now()->month)
                  ->whereYear('payment_date', now()->year)
                  ->selectRaw('payment_method, COUNT(*) as count, SUM(amount_paid) as total')
                  ->groupBy('payment_method')
                  ->get();

              $total = $paymentMethods->sum('total');
          }
        @endphp

        @if ($paymentMethods->count() > 0)
          <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            @foreach ($paymentMethods as $method)
              @php
                $percentage = $total > 0 ? ($method->total / $total) * 100 : 0;
                $methodLabel = match ($method->payment_method) {
                    'cash' => 'Tunai',
                    'transfer' => 'Transfer',
                    'qris' => 'QRIS',
                    default => ucfirst($method->payment_method),
                };
                $colors = [
                    'cash' => 'green',
                    'transfer' => 'blue',
                    'qris' => 'purple',
                    'other' => 'gray',
                ];
                $color = $colors[$method->payment_method] ?? 'gray';
              @endphp

              <div class="bg-{{ $color }}-50 p-4 rounded-lg border border-{{ $color }}-200">
                <div class="text-center">
                  <p class="text-lg font-bold text-{{ $color }}-600">{{ number_format($percentage, 1) }}%</p>
                  <p class="text-sm text-{{ $color }}-700 font-medium">{{ $methodLabel }}</p>
                  <p class="text-xs text-{{ $color }}-600 mt-1">
                    {{ $method->count }} transaksi<br>
                    Rp {{ number_format($method->total) }}
                  </p>
                </div>
              </div>
            @endforeach
          </div>
        @else
          <p class="text-gray-500 text-center py-8">Belum ada pembayaran bulan ini untuk analisis distribusi</p>
        @endif
      </div>
    </div>

  </x-filament-panels::page>
