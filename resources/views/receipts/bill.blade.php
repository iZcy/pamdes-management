{{-- resources/views/receipts/bill.blade.php --}}
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{{ $bill->status === 'paid' ? 'Kwitansi Pembayaran' : 'Tagihan Air' }}</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      font-size: 12px;
      margin: 20px;
      line-height: 1.4;
    }

    .header {
      text-align: center;
      margin-bottom: 20px;
      border-bottom: 2px solid #000;
      padding-bottom: 15px;
    }

    .header h2 {
      margin: 0 0 5px 0;
      font-size: 18px;
      font-weight: bold;
    }

    .header h3 {
      margin: 0 0 10px 0;
      font-size: 16px;
      font-weight: normal;
    }

    .header p {
      margin: 0;
      font-size: 11px;
      color: #666;
    }

    .document-info {
      text-align: center;
      margin-bottom: 20px;
      font-weight: bold;
    }

    .content {
      margin-bottom: 20px;
    }

    .info-section {
      margin-bottom: 15px;
    }

    .info-section h4 {
      margin: 0 0 8px 0;
      font-size: 13px;
      font-weight: bold;
      color: #333;
      border-bottom: 1px solid #ddd;
      padding-bottom: 3px;
    }

    .row {
      display: flex;
      justify-content: space-between;
      margin-bottom: 6px;
      padding: 2px 0;
    }

    .row:nth-child(even) {
      background-color: #f9f9f9;
    }

    .label {
      font-weight: bold;
      width: 45%;
    }

    .value {
      width: 50%;
      text-align: right;
    }

    .usage-breakdown {
      background-color: #f5f5f5;
      padding: 10px;
      border-radius: 4px;
      margin: 10px 0;
      border-left: 4px solid #2196F3;
    }

    .usage-breakdown h5 {
      margin: 0 0 8px 0;
      font-size: 12px;
      color: #333;
    }

    .usage-breakdown .tier {
      display: flex;
      justify-content: space-between;
      font-size: 11px;
      margin-bottom: 3px;
      color: #555;
    }

    .bill-summary {
      border-top: 2px solid #000;
      border-bottom: 2px solid #000;
      padding: 10px 0;
      margin: 15px 0;
    }

    .total-row {
      font-size: 14px;
      font-weight: bold;
      background-color: #e3f2fd;
      padding: 8px;
      border-radius: 4px;
    }

    .status-section {
      text-align: center;
      margin: 20px 0;
      padding: 10px;
      border-radius: 5px;
    }

    .status-paid {
      background-color: #e8f5e8;
      color: #2e7d32;
      border: 2px solid #4caf50;
    }

    .status-unpaid {
      background-color: #fff3e0;
      color: #f57c00;
      border: 2px solid #ff9800;
    }

    .status-overdue {
      background-color: #ffebee;
      color: #c62828;
      border: 2px solid #f44336;
    }

    .payment-info {
      background-color: #e8f5e8;
      padding: 10px;
      border-radius: 4px;
      margin: 15px 0;
    }

    .footer {
      margin-top: 30px;
      font-size: 10px;
      color: #666;
      border-top: 1px solid #ddd;
      padding-top: 15px;
    }

    .signature-section {
      display: flex;
      justify-content: space-between;
      margin-top: 40px;
    }

    .signature-box {
      text-align: center;
      width: 45%;
    }

    .signature-line {
      border-bottom: 1px solid #000;
      height: 60px;
      margin-bottom: 5px;
    }

    .qr-section {
      text-align: center;
      margin: 20px 0;
      padding: 15px;
      background-color: #f0f8ff;
      border-radius: 8px;
      border: 1px dashed #2196F3;
    }

    @media print {
      body {
        margin: 0;
      }

      .no-print {
        display: none !important;
      }

      .page-break {
        page-break-before: always;
      }
    }

    @media screen {
      .print-controls {
        position: fixed;
        top: 10px;
        right: 10px;
        background: white;
        padding: 10px;
        border-radius: 5px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        z-index: 1000;
      }

      .print-controls button {
        margin-right: 10px;
        padding: 8px 16px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 12px;
      }

      .btn-print {
        background-color: #2196F3;
        color: white;
      }

      .btn-close {
        background-color: #666;
        color: white;
      }

      .btn-print:hover {
        background-color: #1976D2;
      }

      .btn-close:hover {
        background-color: #444;
      }
    }
  </style>
</head>

<body>
  {{-- Print Controls (only visible on screen) --}}
  <div class="print-controls no-print">
    <button class="btn-print" onclick="window.print()">🖨️ Cetak</button>
    <button class="btn-close" onclick="window.close()">❌ Tutup</button>
  </div>

  {{-- Document Header --}}
  <div class="header">
    <h2>{{ $bill->status === 'paid' ? 'KWITANSI PEMBAYARAN AIR' : 'TAGIHAN AIR' }}</h2>
    <h3>PAMDes {{ $bill->waterUsage->customer->village->name ?? 'Desa' }}</h3>
    @if ($bill->waterUsage->customer->village->address)
      <p>{{ $bill->waterUsage->customer->village->address }}</p>
    @endif
    @if ($bill->waterUsage->customer->village->phone_number)
      <p>Telp: {{ $bill->waterUsage->customer->village->phone_number }}</p>
    @endif
  </div>

  {{-- Document Number --}}
  <div class="document-info">
    <p>No. {{ $bill->status === 'paid' ? 'KWT' : 'TAG' }}-{{ str_pad($bill->bill_id, 8, '0', STR_PAD_LEFT) }}</p>
    <p>Tanggal: {{ now()->format('d/m/Y') }}</p>
  </div>

  {{-- Customer Information --}}
  <div class="content">
    <div class="info-section">
      <h4>INFORMASI PELANGGAN</h4>
      <div class="row">
        <span class="label">Nama Pelanggan:</span>
        <span class="value">{{ $bill->waterUsage->customer->name }}</span>
      </div>
      <div class="row">
        <span class="label">Kode Pelanggan:</span>
        <span class="value">{{ $bill->waterUsage->customer->customer_code }}</span>
      </div>
      <div class="row">
        <span class="label">Alamat:</span>
        <span class="value">{{ $bill->waterUsage->customer->full_address }}</span>
      </div>
      @if ($bill->waterUsage->customer->phone_number)
        <div class="row">
          <span class="label">Telepon:</span>
          <span class="value">{{ $bill->waterUsage->customer->phone_number }}</span>
        </div>
      @endif
    </div>

    {{-- Billing Period Information --}}
    <div class="info-section">
      <h4>INFORMASI PERIODE</h4>
      <div class="row">
        <span class="label">Periode Tagihan:</span>
        <span class="value">{{ $bill->waterUsage->billingPeriod->period_name }}</span>
      </div>
      <div class="row">
        <span class="label">Tanggal Baca:</span>
        <span class="value">{{ $bill->waterUsage->usage_date->format('d/m/Y') }}</span>
      </div>
      @if ($bill->waterUsage->reader_name)
        <div class="row">
          <span class="label">Petugas Baca:</span>
          <span class="value">{{ $bill->waterUsage->reader_name }}</span>
        </div>
      @endif
    </div>

    {{-- Water Usage Information --}}
    <div class="info-section">
      <h4>PEMAKAIAN AIR</h4>
      <div class="row">
        <span class="label">Meter Awal:</span>
        <span class="value">{{ number_format($bill->waterUsage->initial_meter) }}</span>
      </div>
      <div class="row">
        <span class="label">Meter Akhir:</span>
        <span class="value">{{ number_format($bill->waterUsage->final_meter) }}</span>
      </div>
      <div class="row">
        <span class="label">Total Pemakaian:</span>
        <span class="value">{{ $bill->waterUsage->total_usage_m3 }} m³</span>
      </div>

      {{-- Usage Breakdown --}}
      @php
        try {
            $breakdown = \App\Models\WaterTariff::calculateBill(
                $bill->waterUsage->total_usage_m3,
                $bill->waterUsage->customer->village_id,
            );
        } catch (\Exception $e) {
            $breakdown = ['breakdown' => []];
        }
      @endphp

      @if (count($breakdown['breakdown']) > 1)
        <div class="usage-breakdown">
          <h5>Rincian Perhitungan Tarif:</h5>
          @foreach ($breakdown['breakdown'] as $tier)
            <div class="tier">
              <span>{{ $tier['usage'] }} m³ × Rp {{ number_format($tier['rate']) }}</span>
              <span>= Rp {{ number_format($tier['charge']) }}</span>
            </div>
          @endforeach
        </div>
      @endif
    </div>

    {{-- Bill Summary --}}
    <div class="bill-summary">
      <div class="row">
        <span class="label">Biaya Air:</span>
        <span class="value">Rp {{ number_format($bill->water_charge) }}</span>
      </div>
      <div class="row">
        <span class="label">Biaya Admin:</span>
        <span class="value">Rp {{ number_format($bill->admin_fee) }}</span>
      </div>
      <div class="row">
        <span class="label">Biaya Pemeliharaan:</span>
        <span class="value">Rp {{ number_format($bill->maintenance_fee) }}</span>
      </div>
      <div class="row total-row">
        <span class="label">TOTAL TAGIHAN:</span>
        <span class="value">Rp {{ number_format($bill->total_amount) }}</span>
      </div>
    </div>

    {{-- Status Section --}}
    <div
      class="status-section {{ $bill->status === 'paid'
          ? 'status-paid'
          : ($bill->status === 'overdue'
              ? 'status-overdue'
              : 'status-unpaid') }}">
      <h4 style="margin: 0 0 5px 0;">
        STATUS:
        {{ $bill->status === 'paid'
            ? 'LUNAS'
            : ($bill->status === 'overdue'
                ? 'TERLAMBAT'
                : ($bill->status === 'pending'
                    ? 'MENUNGGU PEMBAYARAN'
                    : 'BELUM BAYAR')) }}
      </h4>

      @if ($bill->status === 'paid' && $bill->payment_date)
        <p style="margin: 0;">Dibayar pada: {{ $bill->payment_date->format('d/m/Y') }}</p>
        @if ($bill->latestPayment)
          <p style="margin: 0; font-size: 10px;">
            Metode: {{ $bill->latestPayment->getPaymentMethodLabel() }}
            @if ($bill->latestPayment->payment_reference)
              | Ref: {{ $bill->latestPayment->payment_reference }}
            @endif
          </p>
        @endif
      @elseif($bill->due_date)
        <p style="margin: 0;">Jatuh Tempo: {{ $bill->due_date->format('d F Y') }}</p>
        @if ($bill->is_overdue)
          <p style="margin: 0; font-weight: bold;">Terlambat {{ $bill->days_overdue }} hari!</p>
        @endif
      @endif
    </div>

    {{-- Payment Information for Paid Bills --}}
    @if ($bill->status === 'paid' && $bill->latestPayment)
      <div class="payment-info">
        <h4 style="margin: 0 0 8px 0;">INFORMASI PEMBAYARAN</h4>
        <div class="row">
          <span class="label">Tanggal Bayar:</span>
          <span class="value">{{ $bill->latestPayment->payment_date->format('d/m/Y H:i') }}</span>
        </div>
        <div class="row">
          <span class="label">Jumlah Bayar:</span>
          <span class="value">Rp {{ number_format($bill->latestPayment->amount_paid) }}</span>
        </div>
        @if ($bill->latestPayment->change_given > 0)
          <div class="row">
            <span class="label">Kembalian:</span>
            <span class="value">Rp {{ number_format($bill->latestPayment->change_given) }}</span>
          </div>
        @endif
        @if ($bill->latestPayment->collector)
          <div class="row">
            <span class="label">Petugas:</span>
            <span class="value">{{ $bill->latestPayment->collector->name }}</span>
          </div>
        @endif
        @if ($bill->latestPayment->notes)
          <div class="row">
            <span class="label">Catatan:</span>
            <span class="value">{{ $bill->latestPayment->notes }}</span>
          </div>
        @endif
      </div>
    @endif

    {{-- QR Code Section for Unpaid Bills --}}
    @if ($bill->status !== 'paid')
      @php
        $variable = \App\Models\Variable::where('village_id', $bill->waterUsage->customer->village_id)->first();
        $tripayConfigured = $variable && ($variable->tripay_use_main || $variable->isConfigured());
      @endphp

      @if ($tripayConfigured)
        <div class="qr-section">
          <h4 style="margin: 0 0 8px 0;">PEMBAYARAN DIGITAL</h4>
          <p style="margin: 0; font-size: 11px;">
            Scan QR Code di bawah atau kunjungi link berikut untuk pembayaran digital:
          </p>
          @php
            $bill->waterUsage->customer->village->slug = $bill->waterUsage->customer->village->slug;
            $paymentUrl = route('tripay.form', [
                'village' => $bill->waterUsage->customer->village->slug,
                'bill' => $bill->bill_id,
            ]);
          @endphp
          <p style="margin: 5px 0; font-size: 10px; word-break: break-all;">
            {{ $paymentUrl }}
          </p>
          <p style="margin: 5px 0; font-size: 10px; color: #666;">
            Mendukung semua e-wallet dan mobile banking
          </p>
        </div>
      @endif
    @endif
  </div>

  {{-- Signature Section --}}
  @if ($bill->status === 'paid')
    <div class="signature-section">
      <div class="signature-box">
        <p>Mengetahui,</p>
        <div class="signature-line"></div>
        <p><strong>Kepala Desa</strong></p>
      </div>
      <div class="signature-box">
        <p>Petugas PAMDes,</p>
        <div class="signature-line"></div>
        <p><strong>{{ $bill->latestPayment->collector->name ?? 'Petugas' }}</strong></p>
      </div>
    </div>
  @endif

  {{-- Footer --}}
  <div class="footer">
    <p>Dicetak pada: {{ now()->format('d/m/Y H:i:s') }}</p>
    @if ($bill->status !== 'paid')
      <p style="font-weight: bold; color: #f57c00;">
        ⚠️ Harap membayar tepat waktu untuk menghindari pemutusan layanan.
      </p>
    @endif
    <p>Dokumen ini dicetak secara otomatis dan sah tanpa tanda tangan.</p>
    <p>Untuk informasi lebih lanjut, hubungi kantor PAMDes {{ $bill->waterUsage->customer->village->name }}.</p>
  </div>
</body>

</html>
