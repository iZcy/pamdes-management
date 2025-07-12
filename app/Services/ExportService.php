<?php
// app/Services/ExportService.php - Universal export service for all tables

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use Carbon\Carbon;

class ExportService
{
    /**
     * Export data to PDF format
     */
    public function exportToPdf($data, string $title, array $columns, array $filters = []): string
    {
        $fileName = $this->generateFileName($title, 'pdf');

        $pdf = Pdf::loadView('exports.pdf-template', [
            'title' => $title,
            'data' => $data,
            'columns' => $columns,
            'filters' => $filters,
            'exported_at' => now(),
            'village' => $this->getCurrentVillageInfo(),
        ]);

        $pdf->setPaper('A4', 'landscape');

        Storage::put("exports/{$fileName}", $pdf->output());

        return $fileName;
    }

    /**
     * Export data to CSV format
     */
    public function exportToCsv($data, string $title, array $columns, array $filters = []): string
    {
        $fileName = $this->generateFileName($title, 'csv');

        $csvData = [];

        // Add header with filters info
        if (!empty($filters)) {
            $csvData[] = ['Export Information'];
            $csvData[] = ['Title', $title];
            $csvData[] = ['Exported At', now()->format('d/m/Y H:i:s')];
            $csvData[] = ['Village', $this->getCurrentVillageInfo()['name'] ?? 'All Villages'];
            $csvData[] = [];

            // Add filter information
            $csvData[] = ['Applied Filters'];
            foreach ($filters as $key => $value) {
                if (!empty($value)) {
                    $csvData[] = [ucfirst(str_replace('_', ' ', $key)), $value];
                }
            }
            $csvData[] = [];
        }

        // Add column headers
        $csvData[] = array_values($columns);

        // Add data rows
        foreach ($data as $row) {
            $csvRow = [];
            foreach (array_keys($columns) as $key) {
                $csvRow[] = $this->formatCellValue($row, $key);
            }
            $csvData[] = $csvRow;
        }

        // Create CSV content
        $handle = fopen('php://temp', 'w');
        foreach ($csvData as $row) {
            fputcsv($handle, $row);
        }
        rewind($handle);
        $csvContent = stream_get_contents($handle);
        fclose($handle);

        Storage::put("exports/{$fileName}", $csvContent);

        return $fileName;
    }

    /**
     * Export Bills with all relationships
     */
    public function exportBills(Builder $query, string $format, array $filters = []): string
    {
        $bills = $query->with([
            'waterUsage.customer.village',
            'waterUsage.billingPeriod',
            'latestPayment.collector'
        ])->get();

        $columns = [
            'customer_code' => 'Kode Pelanggan',
            'customer_name' => 'Nama Pelanggan',
            'village_name' => 'Desa',
            'period_name' => 'Periode',
            'usage_m3' => 'Pemakaian (m³)',
            'water_charge' => 'Biaya Air',
            'admin_fee' => 'Biaya Admin',
            'maintenance_fee' => 'Biaya Pemeliharaan',
            'total_amount' => 'Total Tagihan',
            'status' => 'Status',
            'due_date' => 'Jatuh Tempo',
            'payment_date' => 'Tanggal Bayar',
            'payment_method' => 'Metode Bayar',
        ];

        $exportData = $bills->map(function ($bill) {
            return [
                'customer_code' => $bill->waterUsage->customer->customer_code,
                'customer_name' => $bill->waterUsage->customer->name,
                'village_name' => $bill->waterUsage->customer->village->name ?? '',
                'period_name' => $bill->waterUsage->billingPeriod->period_name,
                'usage_m3' => $bill->waterUsage->total_usage_m3,
                'water_charge' => 'Rp ' . number_format($bill->water_charge),
                'admin_fee' => 'Rp ' . number_format($bill->admin_fee),
                'maintenance_fee' => 'Rp ' . number_format($bill->maintenance_fee),
                'total_amount' => 'Rp ' . number_format($bill->total_amount),
                'status' => $this->formatBillStatus($bill->status),
                'due_date' => $bill->due_date?->format('d/m/Y') ?? '',
                'payment_date' => $bill->payment_date?->format('d/m/Y') ?? '',
                'payment_method' => $bill->latestPayment?->getPaymentMethodLabel() ?? '',
            ];
        });

        if ($format === 'pdf') {
            return $this->exportToPdf($exportData, 'Laporan Tagihan', $columns, $filters);
        } else {
            return $this->exportToCsv($exportData, 'Laporan Tagihan', $columns, $filters);
        }
    }

    /**
     * Export Customers
     */
    public function exportCustomers(Builder $query, string $format, array $filters = []): string
    {
        $customers = $query->with('village')->get();

        $columns = [
            'customer_code' => 'Kode Pelanggan',
            'name' => 'Nama',
            'phone_number' => 'Telepon',
            'address' => 'Alamat',
            'village_name' => 'Desa',
            'status' => 'Status',
            'created_at' => 'Terdaftar',
        ];

        $exportData = $customers->map(function ($customer) {
            return [
                'customer_code' => $customer->customer_code,
                'name' => $customer->name,
                'phone_number' => $customer->phone_number ?? '',
                'address' => $customer->full_address,
                'village_name' => $customer->village->name ?? '',
                'status' => $customer->status === 'active' ? 'Aktif' : 'Tidak Aktif',
                'created_at' => $customer->created_at->format('d/m/Y'),
            ];
        });

        if ($format === 'pdf') {
            return $this->exportToPdf($exportData, 'Laporan Pelanggan', $columns, $filters);
        } else {
            return $this->exportToCsv($exportData, 'Laporan Pelanggan', $columns, $filters);
        }
    }

    /**
     * Export Payments
     */
    public function exportPayments(Builder $query, string $format, array $filters = []): string
    {
        $payments = $query->with([
            'bill.waterUsage.customer.village',
            'bill.waterUsage.billingPeriod',
            'collector'
        ])->get();

        $columns = [
            'payment_date' => 'Tanggal Bayar',
            'customer_code' => 'Kode Pelanggan',
            'customer_name' => 'Nama Pelanggan',
            'village_name' => 'Desa',
            'period_name' => 'Periode',
            'amount_paid' => 'Jumlah Bayar',
            'change_given' => 'Kembalian',
            'payment_method' => 'Metode',
            'collector_name' => 'Petugas',
            'payment_reference' => 'Referensi',
        ];

        $exportData = $payments->map(function ($payment) {
            return [
                'payment_date' => $payment->payment_date->format('d/m/Y H:i'),
                'customer_code' => $payment->bill->waterUsage->customer->customer_code,
                'customer_name' => $payment->bill->waterUsage->customer->name,
                'village_name' => $payment->bill->waterUsage->customer->village->name ?? '',
                'period_name' => $payment->bill->waterUsage->billingPeriod->period_name,
                'amount_paid' => 'Rp ' . number_format($payment->amount_paid),
                'change_given' => 'Rp ' . number_format($payment->change_given),
                'payment_method' => $payment->getPaymentMethodLabel(),
                'collector_name' => $payment->collector->name ?? '',
                'payment_reference' => $payment->payment_reference ?? '',
            ];
        });

        if ($format === 'pdf') {
            return $this->exportToPdf($exportData, 'Laporan Pembayaran', $columns, $filters);
        } else {
            return $this->exportToCsv($exportData, 'Laporan Pembayaran', $columns, $filters);
        }
    }

    /**
     * Export Water Usage
     */
    public function exportWaterUsage(Builder $query, string $format, array $filters = []): string
    {
        $usages = $query->with([
            'customer.village',
            'billingPeriod'
        ])->get();

        $columns = [
            'customer_code' => 'Kode Pelanggan',
            'customer_name' => 'Nama Pelanggan',
            'village_name' => 'Desa',
            'period_name' => 'Periode',
            'usage_date' => 'Tanggal Baca',
            'initial_meter' => 'Meter Awal',
            'final_meter' => 'Meter Akhir',
            'total_usage_m3' => 'Pemakaian (m³)',
            'reader_name' => 'Petugas Baca',
        ];

        $exportData = $usages->map(function ($usage) {
            return [
                'customer_code' => $usage->customer->customer_code,
                'customer_name' => $usage->customer->name,
                'village_name' => $usage->customer->village->name ?? '',
                'period_name' => $usage->billingPeriod->period_name,
                'usage_date' => $usage->usage_date->format('d/m/Y'),
                'initial_meter' => number_format($usage->initial_meter),
                'final_meter' => number_format($usage->final_meter),
                'total_usage_m3' => $usage->total_usage_m3,
                'reader_name' => $usage->reader_name ?? '',
            ];
        });

        if ($format === 'pdf') {
            return $this->exportToPdf($exportData, 'Laporan Pemakaian Air', $columns, $filters);
        } else {
            return $this->exportToCsv($exportData, 'Laporan Pemakaian Air', $columns, $filters);
        }
    }

    /**
     * Export Water Tariffs
     */
    public function exportWaterTariffs(Builder $query, string $format, array $filters = []): string
    {
        $tariffs = $query->with('village')->get();

        $columns = [
            'village_name' => 'Desa',
            'usage_range' => 'Rentang Pemakaian',
            'price_per_m3' => 'Harga per m³',
            'is_active' => 'Status',
            'created_at' => 'Dibuat',
        ];

        $exportData = $tariffs->map(function ($tariff) {
            return [
                'village_name' => $tariff->village->name ?? '',
                'usage_range' => $tariff->usage_range,
                'price_per_m3' => 'Rp ' . number_format($tariff->price_per_m3),
                'is_active' => $tariff->is_active ? 'Aktif' : 'Tidak Aktif',
                'created_at' => $tariff->created_at->format('d/m/Y'),
            ];
        });

        if ($format === 'pdf') {
            return $this->exportToPdf($exportData, 'Laporan Tarif Air', $columns, $filters);
        } else {
            return $this->exportToCsv($exportData, 'Laporan Tarif Air', $columns, $filters);
        }
    }

    /**
     * Export Billing Periods
     */
    public function exportBillingPeriods(Builder $query, string $format, array $filters = []): string
    {
        $periods = $query->with('village')->get();

        $columns = [
            'village_name' => 'Desa',
            'period_name' => 'Periode',
            'status' => 'Status',
            'reading_start_date' => 'Mulai Baca',
            'reading_end_date' => 'Selesai Baca',
            'billing_due_date' => 'Jatuh Tempo',
            'total_customers' => 'Jumlah Pelanggan',
            'total_billed' => 'Total Tagihan',
            'collection_rate' => 'Tingkat Penagihan',
        ];

        $exportData = $periods->map(function ($period) {
            return [
                'village_name' => $period->village->name ?? '',
                'period_name' => $period->period_name,
                'status' => match ($period->status) {
                    'active' => 'Aktif',
                    'completed' => 'Selesai',
                    'inactive' => 'Tidak Aktif',
                    default => $period->status
                },
                'reading_start_date' => $period->reading_start_date?->format('d/m/Y') ?? '',
                'reading_end_date' => $period->reading_end_date?->format('d/m/Y') ?? '',
                'billing_due_date' => $period->billing_due_date?->format('d/m/Y') ?? '',
                'total_customers' => $period->getTotalCustomers(),
                'total_billed' => 'Rp ' . number_format($period->getTotalBilled()),
                'collection_rate' => number_format($period->getCollectionRate(), 1) . '%',
            ];
        });

        if ($format === 'pdf') {
            return $this->exportToPdf($exportData, 'Laporan Periode Tagihan', $columns, $filters);
        } else {
            return $this->exportToCsv($exportData, 'Laporan Periode Tagihan', $columns, $filters);
        }
    }

    /**
     * Helper methods
     */
    private function generateFileName(string $title, string $extension): string
    {
        $slug = \Illuminate\Support\Str::slug($title);
        $timestamp = now()->format('Y-m-d_H-i-s');
        return "{$slug}_{$timestamp}.{$extension}";
    }

    private function getCurrentVillageInfo(): array
    {
        $village = config('pamdes.current_village');
        return $village ?: ['name' => 'All Villages', 'id' => null];
    }

    private function formatCellValue($row, string $key)
    {
        if (is_array($row)) {
            return $row[$key] ?? '';
        }

        return data_get($row, $key, '');
    }

    private function formatBillStatus(string $status): string
    {
        return match ($status) {
            'paid' => 'Sudah Bayar',
            'unpaid' => 'Belum Bayar',
            'overdue' => 'Terlambat',
            'pending' => 'Dalam Proses',
            default => $status
        };
    }
}
