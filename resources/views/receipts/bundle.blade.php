{{-- resources/views/receipts/bundle.blade.php --}}
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Kwitansi Pembayaran Bundle - {{ $bundlePayment->customer->village->name ?? 'PAMDes' }}</title>
  <link rel="icon" type="image/x-icon" href="{{ $bundlePayment->customer->village->getFaviconUrl() }}">
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

    .bundle-items {
      background-color: #f5f5f5;
      padding: 10px;
      border-radius: 4px;
      margin: 10px 0;
      border-left: 4px solid #2196F3;
    }

    .bundle-items h5 {
      margin: 0 0 8px 0;
      font-size: 12px;
      color: #333;
    }

    .bundle-item {
      display: flex;
      justify-content: space-between;
      font-size: 11px;
      margin-bottom: 3px;
      color: #555;
      padding: 3px 0;
      border-bottom: 1px solid #e0e0e0;
    }

    .bundle-item:last-child {
      border-bottom: none;
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

    .bundle-badge {
      background-color: #2196F3;
      color: white;
      padding: 4px 8px;
      border-radius: 12px;
      font-size: 10px;
      font-weight: bold;
      display: inline-block;
      margin-bottom: 10px;
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
    @if ($bundlePayment->customer->village->hasLogo())
      <div style="margin-bottom: 10px;">
        <img src="{{ $bundlePayment->customer->village->getLogoUrl() }}" 
             alt="Logo {{ $bundlePayment->customer->village->name }}" 
             style="width: 60px; height: 60px; object-fit: contain; margin: 0 auto; display: block;">
      </div>
    @endif
    <h2>KWITANSI PEMBAYARAN BUNDLE</h2>
    <h3>PAMDes {{ $bundlePayment->customer->village->name ?? 'Desa' }}</h3>
    @if ($bundlePayment->customer->village->address)
      <p>{{ $bundlePayment->customer->village->address }}</p>
    @endif
    @if ($bundlePayment->customer->village->phone_number)
      <p>Telp: {{ $bundlePayment->customer->village->phone_number }}</p>
    @endif
  </div>

  {{-- Document Number --}}
  <div class="document-info">
    <span class="bundle-badge">BUNDLE {{ $bundlePayment->bill_count }} TAGIHAN</span>
    <p>No. KWT-BDL-{{ $bundlePayment->transaction_ref }}</p>
    <p>Tanggal: {{ $bundlePayment->payment_date ? \Carbon\Carbon::parse($bundlePayment->payment_date)->format('d/m/Y') : now()->format('d/m/Y') }}</p>
  </div>

  {{-- Customer Information --}}
  <div class="content">
    <div class="info-section">
      <h4>INFORMASI PELANGGAN</h4>
      <div class="row">
        <span class="label">Nama Pelanggan:</span>
        <span class="value">{{ $bundlePayment->customer->name }}</span>
      </div>
      <div class="row">
        <span class="label">Kode Pelanggan:</span>
        <span class="value">{{ $bundlePayment->customer->customer_code }}</span>
      </div>
      <div class="row">
        <span class="label">Alamat:</span>
        <span class="value">{{ $bundlePayment->customer->full_address }}</span>
      </div>
      @if ($bundlePayment->customer->phone_number)
        <div class="row">
          <span class="label">Telepon:</span>
          <span class="value">{{ $bundlePayment->customer->phone_number }}</span>
        </div>
      @endif
    </div>

    {{-- Bundle Information --}}
    <div class="info-section">
      <h4>INFORMASI BUNDLE PEMBAYARAN</h4>
      <div class="row">
        <span class="label">Referensi Bundle:</span>
        <span class="value">{{ $bundlePayment->transaction_ref }}</span>
      </div>
      <div class="row">
        <span class="label">Jumlah Tagihan:</span>
        <span class="value">{{ $bundlePayment->bill_count }} tagihan</span>
      </div>
      <div class="row">
        <span class="label">Tanggal Pembayaran:</span>
        <span class="value">{{ $bundlePayment->payment_date ? \Carbon\Carbon::parse($bundlePayment->payment_date)->format('d/m/Y H:i') : '-' }}</span>
      </div>
      {{-- Payment reference not used in new structure --}}
    </div>

    {{-- Bundle Items --}}
    <div class="bundle-items">
      <h5>Detail Tagihan dalam Bundle:</h5>
      @foreach ($bundlePayment->bills as $bill)
        <div class="bundle-item">
          <span>
            <strong>{{ $bill->waterUsage->billingPeriod->period_name }}</strong><br>
            <small>{{ $bill->waterUsage->total_usage_m3 }} m³ • {{ $bill->waterUsage->usage_date->format('d/m/Y') }}</small>
          </span>
          <span style="text-align: right;">
            Rp {{ number_format($bill->water_charge) }}<br>
            <small>+ Admin: Rp {{ number_format($bill->admin_fee) }} + Pemeliharaan: Rp {{ number_format($bill->maintenance_fee) }}</small>
          </span>
        </div>
      @endforeach
    </div>

    {{-- Bill Summary --}}
    <div class="bill-summary">
      @php
        $totalWaterCharge = $bundlePayment->bills->sum('water_charge');
        $totalMaintenanceFee = $bundlePayment->bills->sum('maintenance_fee');
        $singleAdminFee = $bundlePayment->bills->isNotEmpty() ? $bundlePayment->bills->first()->admin_fee : 0;
      @endphp
      <div class="row">
        <span class="label">Total Biaya Air:</span>
        <span class="value">Rp {{ number_format($totalWaterCharge) }}</span>
      </div>
      <div class="row">
        <span class="label">Biaya Admin (1× per bundle):</span>
        <span class="value">Rp {{ number_format($singleAdminFee) }}</span>
      </div>
      <div class="row">
        <span class="label">Biaya Pemeliharaan (akumulatif):</span>
        <span class="value">Rp {{ number_format($totalMaintenanceFee) }}</span>
      </div>
      <div class="row total-row">
        <span class="label">TOTAL PEMBAYARAN:</span>
        <span class="value">Rp {{ number_format($bundlePayment->total_amount) }}</span>
      </div>
    </div>

    {{-- Status Section --}}
    <div class="status-section status-paid">
      <h4 style="margin: 0 0 5px 0;">STATUS: LUNAS</h4>
      @if ($bundlePayment->payment_date)
        <p style="margin: 0;">Dibayar pada: {{ \Carbon\Carbon::parse($bundlePayment->payment_date)->format('d/m/Y H:i') }}</p>
      @endif
      <p style="margin: 0; font-size: 10px;">
        Metode: QRIS
        {{-- Payment reference not available in new structure --}}
      </p>
    </div>

    {{-- Payment Information --}}
    <div class="payment-info">
      <h4 style="margin: 0 0 8px 0;">INFORMASI PEMBAYARAN</h4>
      <div class="row">
        <span class="label">Tanggal Bayar:</span>
        <span class="value">{{ $bundlePayment->payment_date ? \Carbon\Carbon::parse($bundlePayment->payment_date)->format('d/m/Y H:i') : '-' }}</span>
      </div>
      <div class="row">
        <span class="label">Jumlah Bayar:</span>
        <span class="value">Rp {{ number_format($bundlePayment->total_amount) }}</span>
      </div>
      <div class="row">
        <span class="label">Metode Bayar:</span>
        <span class="value">QRIS (Payment Gateway)</span>
      </div>
      {{-- Tripay payment reference not available in new structure --}}
    </div>

    {{-- Period Coverage --}}
    <div class="info-section">
      <h4>PERIODE YANG DIBAYAR</h4>
      @php
        $periods = $bundlePayment->bills->pluck('waterUsage.billingPeriod.period_name')->unique()->sort();
      @endphp
      <div class="row">
        <span class="label">Periode Tercakup:</span>
        <span class="value">{{ $periods->implode(', ') }}</span>
      </div>
    </div>
  </div>

  {{-- Signature Section --}}
  <div class="signature-section">
    <div class="signature-box">
      <p>Mengetahui,</p>
      <div class="signature-line"></div>
      <p><strong>Kepala Desa</strong></p>
    </div>
    <div class="signature-box">
      <p>Petugas PAMDes,</p>
      <div class="signature-line"></div>
      <p><strong>Admin PAMDes</strong></p>
    </div>
  </div>

  {{-- Footer --}}
  <div class="footer">
    <p>Dicetak pada: {{ now()->format('d/m/Y H:i:s') }}</p>
    <p style="font-weight: bold; color: #2e7d32;">
      ✅ Pembayaran bundle telah berhasil. Semua tagihan dalam bundle ini telah lunas.
    </p>
    <p>Dokumen ini dicetak secara otomatis dan sah tanpa tanda tangan.</p>
    <p>Untuk informasi lebih lanjut, hubungi kantor PAMDes {{ $bundlePayment->customer->village->name }}.</p>
  </div>
</body>

</html>