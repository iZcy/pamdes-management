{{-- resources/views/receipts/multiple-bills.blade.php --}}
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Invoice Tagihan Air - {{ $bills->first()->waterUsage->customer->village->name ?? 'PAMDes' }}</title>
  <link rel="icon" type="image/x-icon" href="{{ $bills->first()->waterUsage->customer->village->getFaviconUrl() }}">
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

    .bills-table {
      width: 100%;
      border-collapse: collapse;
      margin: 15px 0;
      font-size: 11px;
    }

    .bills-table th,
    .bills-table td {
      border: 1px solid #ddd;
      padding: 8px;
      text-align: left;
    }

    .bills-table th {
      background-color: #f5f5f5;
      font-weight: bold;
      text-align: center;
    }

    .bills-table .amount {
      text-align: right;
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

    .status-unpaid {
      background-color: #fff3e0;
      color: #f57c00;
      border: 2px solid #ff9800;
    }

    .status-mixed {
      background-color: #f3e5f5;
      color: #7b1fa2;
      border: 2px solid #9c27b0;
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

    .invoice-badge {
      background-color: #ff9800;
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
    @if ($bills->first()->waterUsage->customer->village->hasLogo())
      <div style="margin-bottom: 10px;">
        <img src="{{ $bills->first()->waterUsage->customer->village->getLogoUrl() }}" 
             alt="Logo {{ $bills->first()->waterUsage->customer->village->name }}" 
             style="width: 60px; height: 60px; object-fit: contain; margin: 0 auto; display: block;">
      </div>
    @endif
    <h2>INVOICE TAGIHAN AIR</h2>
    <h3>PAMDes {{ $bills->first()->waterUsage->customer->village->name ?? 'Desa' }}</h3>
    @if ($bills->first()->waterUsage->customer->village->address)
      <p>{{ $bills->first()->waterUsage->customer->village->address }}</p>
    @endif
    @if ($bills->first()->waterUsage->customer->village->phone_number)
      <p>Telp: {{ $bills->first()->waterUsage->customer->village->phone_number }}</p>
    @endif
  </div>

  {{-- Document Number --}}
  <div class="document-info">
    <span class="invoice-badge">INVOICE {{ $bills->count() }} TAGIHAN</span>
    <p>No. INV-{{ str_pad($customer->customer_id, 6, '0', STR_PAD_LEFT) }}-{{ now()->format('Ymd') }}</p>
    <p>Tanggal: {{ now()->format('d/m/Y') }}</p>
  </div>

  {{-- Customer Information --}}
  <div class="content">
    <div class="info-section">
      <h4>INFORMASI PELANGGAN</h4>
      <div class="row">
        <span class="label">Nama Pelanggan:</span>
        <span class="value">{{ $customer->name }}</span>
      </div>
      <div class="row">
        <span class="label">Kode Pelanggan:</span>
        <span class="value">{{ $customer->customer_code }}</span>
      </div>
      <div class="row">
        <span class="label">Alamat:</span>
        <span class="value">{{ $customer->full_address }}</span>
      </div>
      @if ($customer->phone_number)
        <div class="row">
          <span class="label">Telepon:</span>
          <span class="value">{{ $customer->phone_number }}</span>
        </div>
      @endif
    </div>

    {{-- Bills Details --}}
    <div class="info-section">
      <h4>DETAIL TAGIHAN</h4>
      <table class="bills-table">
        <thead>
          <tr>
            <th style="width: 25%;">Periode</th>
            <th style="width: 15%;">Pemakaian</th>
            <th style="width: 15%;">Biaya Air</th>
            <th style="width: 15%;">Biaya Admin</th>
            <th style="width: 15%;">Pemeliharaan</th>
            <th style="width: 15%;">Total</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($bills as $bill)
            <tr>
              <td>{{ $bill->waterUsage->billingPeriod->period_name }}</td>
              <td style="text-align: center;">{{ $bill->waterUsage->total_usage_m3 }} m³</td>
              <td class="amount">Rp {{ number_format($bill->water_charge) }}</td>
              <td class="amount">Rp {{ number_format($bill->admin_fee) }}</td>
              <td class="amount">Rp {{ number_format($bill->maintenance_fee) }}</td>
              <td class="amount"><strong>Rp {{ number_format($bill->total_amount) }}</strong></td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>

    {{-- Invoice Summary --}}
    <div class="bill-summary">
      @php
        $totalWaterCharge = $bills->sum('water_charge');
        $totalAdminFee = $bills->sum('admin_fee');
        $totalMaintenanceFee = $bills->sum('maintenance_fee');
        $grandTotal = $bills->sum('total_amount');
      @endphp
      <div class="row">
        <span class="label">Subtotal Biaya Air:</span>
        <span class="value">Rp {{ number_format($totalWaterCharge) }}</span>
      </div>
      <div class="row">
        <span class="label">Subtotal Biaya Admin:</span>
        <span class="value">Rp {{ number_format($totalAdminFee) }}</span>
      </div>
      <div class="row">
        <span class="label">Subtotal Biaya Pemeliharaan:</span>
        <span class="value">Rp {{ number_format($totalMaintenanceFee) }}</span>
      </div>
      <div class="row total-row">
        <span class="label">TOTAL TAGIHAN:</span>
        <span class="value">Rp {{ number_format($grandTotal) }}</span>
      </div>
    </div>

    {{-- Status Section --}}
    @php
      $paidCount = $bills->where('status', 'paid')->count();
      $unpaidCount = $bills->where('status', '!=', 'paid')->count();
    @endphp
    <div
      class="status-section {{ $unpaidCount === 0 ? 'status-paid' : ($paidCount === 0 ? 'status-unpaid' : 'status-mixed') }}">
      <h4 style="margin: 0 0 5px 0;">
        STATUS: 
        @if ($unpaidCount === 0)
          SEMUA LUNAS
        @elseif ($paidCount === 0)
          BELUM BAYAR
        @else
          {{ $paidCount }} LUNAS, {{ $unpaidCount }} BELUM BAYAR
        @endif
      </h4>

      @if ($unpaidCount > 0)
        <p style="margin: 0; font-size: 12px;">
          ⚠️ Terdapat {{ $unpaidCount }} tagihan yang belum dibayar
        </p>
      @endif
    </div>

    {{-- Payment Information --}}
    <div class="info-section">
      <h4>INFORMASI PEMBAYARAN</h4>
      <div class="row">
        <span class="label">Jumlah Tagihan:</span>
        <span class="value">{{ $bills->count() }} tagihan</span>
      </div>
      @php
        $periods = $bills->pluck('waterUsage.billingPeriod.period_name')->unique()->sort();
      @endphp
      <div class="row">
        <span class="label">Periode Tercakup:</span>
        <span class="value">{{ $periods->implode(', ') }}</span>
      </div>
      <div class="row">
        <span class="label">Total yang Harus Dibayar:</span>
        <span class="value"><strong>Rp {{ number_format($bills->where('status', '!=', 'paid')->sum('total_amount')) }}</strong></span>
      </div>
    </div>

    {{-- QR Code Section for Digital Payment --}}
    @if ($unpaidCount > 0)
      @php
        $variable = \App\Models\Variable::where('village_id', $bills->first()->waterUsage->customer->village_id)->first();
        $tripayConfigured = $variable && ($variable->tripay_use_main || $variable->isConfigured());
      @endphp

      @if ($tripayConfigured)
        <div style="background-color: #f0f8ff; border: 1px dashed #2196F3; border-radius: 8px; padding: 15px; margin: 20px 0; text-align: center;">
          <h4 style="margin: 0 0 8px 0;">PEMBAYARAN DIGITAL</h4>
          <p style="margin: 0; font-size: 11px;">
            Untuk kemudahan pembayaran, Anda dapat menggunakan sistem pembayaran digital PAMDes.
          </p>
          <p style="margin: 5px 0; font-size: 10px; color: #666;">
            Kunjungi portal pelanggan online untuk pembayaran dengan QRIS
          </p>
        </div>
      @endif
    @endif
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
    @if ($unpaidCount > 0)
      <p style="font-weight: bold; color: #f57c00;">
        ⚠️ Harap membayar tepat waktu untuk menghindari pemutusan layanan.
      </p>
    @endif
    <p>Dokumen ini dicetak secara otomatis dan sah tanpa tanda tangan.</p>
    <p>Untuk informasi lebih lanjut, hubungi kantor PAMDes {{ $bills->first()->waterUsage->customer->village->name }}.</p>
  </div>
</body>

</html>