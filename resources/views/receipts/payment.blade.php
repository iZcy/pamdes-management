{{-- resources/views/receipts/payment.blade.php --}}
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Kwitansi Pembayaran</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      font-size: 12px;
      margin: 20px;
    }

    .header {
      text-align: center;
      margin-bottom: 20px;
      border-bottom: 2px solid #000;
      padding-bottom: 10px;
    }

    .content {
      margin-bottom: 20px;
    }

    .row {
      display: flex;
      justify-content: space-between;
      margin-bottom: 8px;
    }

    .label {
      font-weight: bold;
    }

    .total {
      border-top: 2px solid #000;
      padding-top: 10px;
      font-size: 14px;
      font-weight: bold;
    }

    .footer {
      margin-top: 30px;
      text-align: right;
    }

    .signature {
      margin-top: 50px;
    }

    @media print {
      body {
        margin: 0;
      }

      .no-print {
        display: none;
      }
    }
  </style>
</head>

<body>
  <div class="no-print" style="margin-bottom: 20px;">
    <button onclick="window.print()">Cetak Kwitansi</button>
    <button onclick="window.close()">Tutup</button>
  </div>

  <div class="header">
    <h2>KWITANSI PEMBAYARAN AIR</h2>
    <h3>PAMDes {{ config('pamdes.current_village.name', 'Desa') }}</h3>
    <p>No. RCP-{{ str_pad($payment->payment_id, 8, '0', STR_PAD_LEFT) }}</p>
  </div>

  <div class="content">
    <div class="row">
      <span class="label">Nama Pelanggan:</span>
      <span>{{ $payment->customer->name }}</span>
    </div>
    <div class="row">
      <span class="label">Kode Pelanggan:</span>
      <span>{{ $payment->customer->customer_code }}</span>
    </div>
    <div class="row">
      <span class="label">Alamat:</span>
      <span>{{ $payment->customer->full_address }}</span>
    </div>
    <div class="row">
      <span class="label">Periode Tagihan:</span>
      <span>{{ $payment->bill->waterUsage->billingPeriod->period_name }}</span>
    </div>
    <div class="row">
      <span class="label">Pemakaian Air:</span>
      <span>{{ $payment->bill->waterUsage->total_usage_m3 }} m³</span>
    </div>
    <div class="row">
      <span class="label">Biaya Air:</span>
      <span>Rp {{ number_format($payment->bill->water_charge) }}</span>
    </div>
    <div class="row">
      <span class="label">Biaya Admin:</span>
      <span>Rp {{ number_format($payment->bill->admin_fee) }}</span>
    </div>
    <div class="row">
      <span class="label">Biaya Pemeliharaan:</span>
      <span>Rp {{ number_format($payment->bill->maintenance_fee) }}</span>
    </div>
    <div class="row total">
      <span class="label">Total Tagihan:</span>
      <span>Rp {{ number_format($bill->total_amount) }}</span>
    </div>
    <div class="row">
      <span class="label">Status:</span>
      <span style="color: {{ $bill->status === 'paid' ? 'green' : ($bill->status === 'overdue' ? 'red' : 'orange') }}">
        {{ $bill->status === 'paid' ? 'LUNAS' : ($bill->status === 'overdue' ? 'TERLAMBAT' : 'BELUM BAYAR') }}
      </span>
    </div>
    @if ($bill->status === 'paid' && $bill->payment_date)
      <div class="row">
        <span class="label">Tanggal Pembayaran:</span>
        <span>{{ $bill->payment_date->format('d/m/Y') }}</span>
      </div>
    @endif
  </div>

  @if ($bill->status !== 'paid')
    <div class="due-date">
      <strong>Jatuh Tempo: {{ $bill->due_date->format('d F Y') }}</strong>
      @if ($bill->is_overdue)
        <br><span style="color: red;">Tagihan sudah terlambat {{ $bill->days_overdue }} hari!</span>
      @endif
    </div>
  @endif

  <div style="margin-top: 30px; font-size: 10px; color: #666;">
    <p>Dicetak pada: {{ now()->format('d/m/Y H:i:s') }}</p>
    <p>Harap membayar tepat waktu untuk menghindari pemutusan layanan.</p>
  </div>
</body>

</html>
