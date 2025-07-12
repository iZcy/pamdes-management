{{-- resources/views/exports/pdf-template.blade.php - Fixed version --}}
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
  <title>{{ $title }}</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      font-size: 10px;
      margin: 0;
      padding: 15px;
      color: #333;
    }

    .header {
      text-align: center;
      margin-bottom: 20px;
      border-bottom: 2px solid #333;
      padding-bottom: 10px;
    }

    .header h1 {
      margin: 0 0 5px 0;
      font-size: 16px;
      font-weight: bold;
    }

    .header h2 {
      margin: 0 0 10px 0;
      font-size: 14px;
      color: #666;
    }

    .header .subtitle {
      font-size: 11px;
      color: #888;
    }

    .info-section {
      margin-bottom: 15px;
      background-color: #f9f9f9;
      padding: 8px;
      border-radius: 4px;
    }

    .info-row {
      display: inline-block;
      margin-right: 20px;
      margin-bottom: 3px;
    }

    .info-label {
      font-weight: bold;
      color: #555;
    }

    .filters {
      margin-bottom: 15px;
      padding: 8px;
      background-color: #e3f2fd;
      border-radius: 4px;
      border: 1px solid #bbdefb;
    }

    .filters h4 {
      margin: 0 0 5px 0;
      font-size: 11px;
      color: #1976d2;
    }

    .filter-item {
      display: inline-block;
      margin-right: 15px;
      font-size: 9px;
      color: #666;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 10px;
    }

    th {
      background-color: #f5f5f5;
      border: 1px solid #ddd;
      padding: 6px 4px;
      text-align: left;
      font-weight: bold;
      font-size: 9px;
      color: #333;
    }

    td {
      border: 1px solid #ddd;
      padding: 5px 4px;
      font-size: 8px;
      vertical-align: top;
      word-wrap: break-word;
    }

    tr:nth-child(even) {
      background-color: #f9f9f9;
    }

    .footer {
      position: fixed;
      bottom: 10px;
      left: 15px;
      right: 15px;
      text-align: center;
      font-size: 8px;
      color: #666;
      border-top: 1px solid #ddd;
      padding-top: 5px;
    }

    .page-number {
      float: right;
    }

    .summary {
      margin-top: 15px;
      padding: 8px;
      background-color: #f0f8ff;
      border-radius: 4px;
      border: 1px solid #b3d9ff;
    }

    .summary h4 {
      margin: 0 0 5px 0;
      font-size: 11px;
      color: #0066cc;
    }

    .summary-item {
      display: inline-block;
      margin-right: 20px;
      font-size: 9px;
    }

    .text-right {
      text-align: right;
    }

    .text-center {
      text-align: center;
    }

    .font-bold {
      font-weight: bold;
    }

    /* Responsive column widths */
    .col-narrow {
      width: 8%;
    }

    .col-medium {
      width: 12%;
    }

    .col-wide {
      width: 15%;
    }

    .col-extra-wide {
      width: 20%;
    }

    @page {
      margin: 10mm;
      footer: html_footer;
    }

    /* Print specific styles */
    @media print {
      body {
        font-size: 9px;
      }

      th {
        font-size: 8px;
      }

      td {
        font-size: 7px;
      }
    }
  </style>
</head>

<body>
  <!-- Header -->
  <div class="header">
    <h1>{{ $title }}</h1>
    <h2>PAMDes {{ isset($village['name']) ? $village['name'] : 'Sistem Pengelolaan Air Minum Desa' }}</h2>
    <div class="subtitle">
      Dicetak pada: {{ $exported_at->format('d/m/Y H:i:s') }}
    </div>
  </div>

  <!-- Export Information -->
  <div class="info-section">
    <div class="info-row">
      <span class="info-label">Desa:</span> {{ isset($village['name']) ? $village['name'] : 'Semua Desa' }}
    </div>
    <div class="info-row">
      <span class="info-label">Total Data:</span>
      {{ is_countable($data) ? count($data) : (is_object($data) && method_exists($data, 'count') ? $data->count() : 0) }}
      record
    </div>
    <div class="info-row">
      <span class="info-label">Periode Export:</span> {{ $exported_at->format('F Y') }}
    </div>
  </div>

  <!-- Applied Filters -->
  @if (!empty($filters) && is_array($filters) && array_filter($filters))
    <div class="filters">
      <h4>Filter yang Diterapkan:</h4>
      @foreach ($filters as $key => $value)
        @if (!empty($value) && !is_array($value))
          <div class="filter-item">
            <strong>{{ ucfirst(str_replace('_', ' ', $key)) }}:</strong> {{ $value }}
          </div>
        @endif
      @endforeach
    </div>
  @endif

  <!-- Data Table -->
  <table>
    <thead>
      <tr>
        @foreach ($columns as $key => $label)
          <th class="{{ getColumnClass($key) }}">{{ $label }}</th>
        @endforeach
      </tr>
    </thead>
    <tbody>
      @forelse($data as $row)
        <tr>
          @foreach (array_keys($columns) as $key)
            <td class="{{ getColumnClass($key) }}">
              {{ formatCellValueSafe($row, $key) }}
            </td>
          @endforeach
        </tr>
      @empty
        <tr>
          <td colspan="{{ count($columns) }}" class="text-center">
            Tidak ada data yang sesuai dengan filter yang diterapkan
          </td>
        </tr>
      @endforelse
    </tbody>
  </table>

  <!-- Summary Section for Financial Data -->
  @if (shouldShowSummary($columns))
    <div class="summary">
      <h4>Ringkasan:</h4>
      @if (isset($columns['total_amount']) || isset($columns['amount_paid']))
        @php
          $totalAmount = 0;
          $totalPaid = 0;
          foreach ($data as $row) {
              if (isset($columns['total_amount'])) {
                  $value = formatCellValueSafe($row, 'total_amount');
                  $totalAmount += extractNumericValue($value);
              }
              if (isset($columns['amount_paid'])) {
                  $value = formatCellValueSafe($row, 'amount_paid');
                  $totalPaid += extractNumericValue($value);
              }
          }
        @endphp

        @if ($totalAmount > 0)
          <div class="summary-item">
            <strong>Total Tagihan:</strong> Rp {{ number_format($totalAmount) }}
          </div>
        @endif

        @if ($totalPaid > 0)
          <div class="summary-item">
            <strong>Total Terbayar:</strong> Rp {{ number_format($totalPaid) }}
          </div>
        @endif

        @if ($totalAmount > 0 && $totalPaid > 0)
          @php $collectionRate = ($totalPaid / $totalAmount) * 100; @endphp
          <div class="summary-item">
            <strong>Tingkat Penagihan:</strong> {{ number_format($collectionRate, 1) }}%
          </div>
        @endif
      @endif

      <div class="summary-item">
        <strong>Total Record:</strong>
        {{ is_countable($data) ? count($data) : (is_object($data) && method_exists($data, 'count') ? $data->count() : 0) }}
      </div>
    </div>
  @endif

  <!-- Footer -->
  <div class="footer">
    <div style="float: left;">
      {{ $title }} - PAMDes {{ isset($village['name']) ? $village['name'] : 'Management System' }}
    </div>
    <div class="page-number">
      Halaman <span class="pagenum"></span>
    </div>
    <div style="clear: both; text-align: center; margin-top: 5px;">
      Dokumen ini digenerate otomatis oleh sistem PAMDes
    </div>
  </div>
</body>

</html>

@php
  // PHP Helper Functions for Template - Fixed to handle arrays safely
  function getColumnClass($key)
  {
      $narrowColumns = ['status', 'is_active', 'usage_m3'];
      $mediumColumns = ['customer_code', 'payment_method', 'due_date', 'payment_date'];
      $wideColumns = ['customer_name', 'period_name', 'village_name'];

      if (in_array($key, $narrowColumns)) {
          return 'col-narrow';
      }
      if (in_array($key, $mediumColumns)) {
          return 'col-medium';
      }
      if (in_array($key, $wideColumns)) {
          return 'col-wide';
      }
      if (str_contains($key, 'amount') || str_contains($key, 'fee')) {
          return 'col-medium text-right';
      }

      return '';
  }

  function formatCellValueSafe($row, $key)
  {
      try {
          if (is_array($row)) {
              $value = $row[$key] ?? '';
          } else {
              $value = data_get($row, $key, '');
          }

          // Handle arrays and objects safely
          if (is_array($value)) {
              return implode(
                  ', ',
                  array_filter($value, function ($item) {
                      return !is_array($item) && !is_object($item);
                  }),
              );
          }

          if (is_object($value)) {
              if (method_exists($value, '__toString')) {
                  return (string) $value;
              }
              return '[Object]';
          }

          return (string) $value;
      } catch (\Exception $e) {
          return '[Error]';
      }
  }

  function shouldShowSummary($columns)
  {
      return isset($columns['total_amount']) || isset($columns['amount_paid']) || isset($columns['water_charge']);
  }

  function extractNumericValue($value)
  {
      // Extract numeric value from formatted currency string
      return (float) preg_replace('/[^\d.]/', '', (string) $value);
  }
@endphp
