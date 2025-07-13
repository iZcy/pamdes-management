<?php
// app/Services/ExportService.php - Simplified implementation without filters

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ExportService
{
    /**
     * Export data to PDF format
     */
    public function exportToPdf($data, string $title, array $columns, array $metadata = []): string
    {
        try {
            $fileName = $this->generateFileName($title, 'pdf');

            // Ensure data is in the right format
            $processedData = $this->processDataForExport($data);

            $pdf = Pdf::loadView('exports.pdf-template', [
                'title' => $title,
                'data' => $processedData,
                'columns' => $columns,
                'metadata' => $metadata,
                'exported_at' => now(),
                'village' => $this->getCurrentVillageInfo(),
            ]);

            $pdf->setPaper('A4', 'landscape');
            $pdf->setOptions([
                'isHtml5ParserEnabled' => true,
                'isPhpEnabled' => true,
                'defaultFont' => 'Arial',
            ]);

            // Create exports directory if it doesn't exist
            $exportPath = 'exports';
            if (!Storage::disk('public')->exists($exportPath)) {
                Storage::disk('public')->makeDirectory($exportPath);
            }

            // Store file in public disk
            Storage::disk('public')->put("exports/{$fileName}", $pdf->output());

            Log::info('PDF export created successfully', [
                'filename' => $fileName,
                'title' => $title,
                'data_count' => is_countable($processedData) ? count($processedData) : 0,
            ]);

            return $fileName;
        } catch (\Exception $e) {
            Log::error('PDF export failed', [
                'title' => $title,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw new \Exception("PDF export failed: " . $e->getMessage());
        }
    }

    /**
     * Export data to CSV format
     */
    public function exportToCsv($data, string $title, array $columns, array $metadata = []): string
    {
        try {
            $fileName = $this->generateFileName($title, 'csv');
            $processedData = $this->processDataForExport($data);

            $csvData = [];

            // Add header with export info
            $csvData[] = ['Export Information'];
            $csvData[] = ['Title', $title];
            $csvData[] = ['Exported At', now()->format('d/m/Y H:i:s')];
            $csvData[] = ['Village', $this->getCurrentVillageInfo()['name'] ?? 'All Villages'];
            $csvData[] = ['Total Records', count($processedData)];

            // Add date range if available
            if (isset($metadata['date_range']) && $metadata['date_range'] !== 'Semua periode') {
                $csvData[] = ['Date Range', $metadata['date_range']];
            }

            if (isset($metadata['exported_by'])) {
                $csvData[] = ['Exported By', $metadata['exported_by']];
            }

            $csvData[] = [];

            // Add column headers
            $csvData[] = array_values($columns);

            // Add data rows
            foreach ($processedData as $row) {
                $csvRow = [];
                foreach (array_keys($columns) as $key) {
                    $csvRow[] = $this->formatCellValue($row, $key);
                }
                $csvData[] = $csvRow;
            }

            // Create exports directory if it doesn't exist
            $exportPath = 'exports';
            if (!Storage::disk('public')->exists($exportPath)) {
                Storage::disk('public')->makeDirectory($exportPath);
            }

            // Create CSV content
            $handle = fopen('php://temp', 'w');
            foreach ($csvData as $row) {
                fputcsv($handle, $row);
            }
            rewind($handle);
            $csvContent = stream_get_contents($handle);
            fclose($handle);

            // Store file in public disk
            Storage::disk('public')->put("exports/{$fileName}", $csvContent);

            Log::info('CSV export created successfully', [
                'filename' => $fileName,
                'title' => $title,
                'data_count' => count($processedData),
            ]);

            return $fileName;
        } catch (\Exception $e) {
            Log::error('CSV export failed', [
                'title' => $title,
                'error' => $e->getMessage(),
            ]);
            throw new \Exception("CSV export failed: " . $e->getMessage());
        }
    }

    /**
     * Process data for export - convert to array format
     */
    protected function processDataForExport($data): array
    {
        if (is_array($data)) {
            return $data;
        }

        if ($data instanceof Collection) {
            return $data->toArray();
        }

        if (is_object($data) && method_exists($data, 'toArray')) {
            return $data->toArray();
        }

        if (is_iterable($data)) {
            $result = [];
            foreach ($data as $item) {
                if (is_object($item) && method_exists($item, 'toArray')) {
                    $result[] = $item->toArray();
                } elseif (is_array($item)) {
                    $result[] = $item;
                } else {
                    $result[] = ['value' => (string) $item];
                }
            }
            return $result;
        }

        return [];
    }

    /**
     * Export Bills with all relationships
     */
    public function exportBills(Builder $query, string $format, array $metadata = []): string
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
                'customer_code' => $bill->waterUsage->customer->customer_code ?? '',
                'customer_name' => $bill->waterUsage->customer->name ?? '',
                'village_name' => $bill->waterUsage->customer->village->name ?? '',
                'period_name' => $bill->waterUsage->billingPeriod->period_name ?? '',
                'usage_m3' => $bill->waterUsage->total_usage_m3 ?? 0,
                'water_charge' => 'Rp ' . number_format($bill->water_charge ?? 0),
                'admin_fee' => 'Rp ' . number_format($bill->admin_fee ?? 0),
                'maintenance_fee' => 'Rp ' . number_format($bill->maintenance_fee ?? 0),
                'total_amount' => 'Rp ' . number_format($bill->total_amount ?? 0),
                'status' => $this->formatBillStatus($bill->status ?? 'unknown'),
                'due_date' => $bill->due_date?->format('d/m/Y') ?? '',
                'payment_date' => $bill->payment_date?->format('d/m/Y') ?? '',
                'payment_method' => $bill->latestPayment?->getPaymentMethodLabel() ?? '',
            ];
        })->toArray();

        if ($format === 'pdf') {
            return $this->exportToPdf($exportData, 'Laporan Tagihan', $columns, $metadata);
        } else {
            return $this->exportToCsv($exportData, 'Laporan Tagihan', $columns, $metadata);
        }
    }

    /**
     * Export Customers
     */
    public function exportCustomers(Builder $query, string $format, array $metadata = []): string
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
                'customer_code' => $customer->customer_code ?? '',
                'name' => $customer->name ?? '',
                'phone_number' => $customer->phone_number ?? '',
                'address' => $customer->full_address ?? '',
                'village_name' => $customer->village->name ?? '',
                'status' => $customer->status === 'active' ? 'Aktif' : 'Tidak Aktif',
                'created_at' => $customer->created_at->format('d/m/Y'),
            ];
        })->toArray();

        if ($format === 'pdf') {
            return $this->exportToPdf($exportData, 'Laporan Pelanggan', $columns, $metadata);
        } else {
            return $this->exportToCsv($exportData, 'Laporan Pelanggan', $columns, $metadata);
        }
    }

    /**
     * Export Payments
     */
    public function exportPayments(Builder $query, string $format, array $metadata = []): string
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
                'customer_code' => $payment->bill->waterUsage->customer->customer_code ?? '',
                'customer_name' => $payment->bill->waterUsage->customer->name ?? '',
                'village_name' => $payment->bill->waterUsage->customer->village->name ?? '',
                'period_name' => $payment->bill->waterUsage->billingPeriod->period_name ?? '',
                'amount_paid' => 'Rp ' . number_format($payment->amount_paid ?? 0),
                'change_given' => 'Rp ' . number_format($payment->change_given ?? 0),
                'payment_method' => $payment->getPaymentMethodLabel(),
                'collector_name' => $payment->collector->name ?? '',
                'payment_reference' => $payment->payment_reference ?? '',
            ];
        })->toArray();

        if ($format === 'pdf') {
            return $this->exportToPdf($exportData, 'Laporan Pembayaran', $columns, $metadata);
        } else {
            return $this->exportToCsv($exportData, 'Laporan Pembayaran', $columns, $metadata);
        }
    }

    /**
     * Export Water Usage
     */
    public function exportWaterUsage(Builder $query, string $format, array $metadata = []): string
    {
        $usages = $query->with([
            'customer.village',
            'billingPeriod',
            'reader'
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
                'customer_code' => $usage->customer->customer_code ?? '',
                'customer_name' => $usage->customer->name ?? '',
                'village_name' => $usage->customer->village->name ?? '',
                'period_name' => $usage->billingPeriod->period_name ?? '',
                'usage_date' => $usage->usage_date->format('d/m/Y'),
                'initial_meter' => number_format($usage->initial_meter ?? 0),
                'final_meter' => number_format($usage->final_meter ?? 0),
                'total_usage_m3' => $usage->total_usage_m3 ?? 0,
                'reader_name' => $usage->reader?->name ?? 'Unknown Reader',
            ];
        })->toArray();

        if ($format === 'pdf') {
            return $this->exportToPdf($exportData, 'Laporan Pemakaian Air', $columns, $metadata);
        } else {
            return $this->exportToCsv($exportData, 'Laporan Pemakaian Air', $columns, $metadata);
        }
    }

    /**
     * Export Water Tariffs
     */
    public function exportWaterTariffs(Builder $query, string $format, array $metadata = []): string
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
                'usage_range' => $tariff->usage_range ?? '',
                'price_per_m3' => 'Rp ' . number_format($tariff->price_per_m3 ?? 0),
                'is_active' => $tariff->is_active ? 'Aktif' : 'Tidak Aktif',
                'created_at' => $tariff->created_at->format('d/m/Y'),
            ];
        })->toArray();

        if ($format === 'pdf') {
            return $this->exportToPdf($exportData, 'Laporan Tarif Air', $columns, $metadata);
        } else {
            return $this->exportToCsv($exportData, 'Laporan Tarif Air', $columns, $metadata);
        }
    }

    /**
     * Export Billing Periods - Fixed version
     */
    public function exportBillingPeriods(Builder $query, string $format, array $metadata = []): string
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
            // Use the accessor methods directly
            $totalCustomers = $period->total_customers;
            $totalBilled = $period->total_billed;
            $collectionRate = $period->collection_rate;

            return [
                'village_name' => $period->village->name ?? '',
                'period_name' => $period->period_name ?? '',
                'status' => match ($period->status) {
                    'active' => 'Aktif',
                    'completed' => 'Selesai',
                    'inactive' => 'Tidak Aktif',
                    default => $period->status
                },
                'reading_start_date' => $period->reading_start_date?->format('d/m/Y') ?? '',
                'reading_end_date' => $period->reading_end_date?->format('d/m/Y') ?? '',
                'billing_due_date' => $period->billing_due_date?->format('d/m/Y') ?? '',
                'total_customers' => $totalCustomers,
                'total_billed' => 'Rp ' . number_format($totalBilled),
                'collection_rate' => number_format($collectionRate, 1) . '%',
            ];
        })->toArray();

        if ($format === 'pdf') {
            return $this->exportToPdf($exportData, 'Laporan Periode Tagihan', $columns, $metadata);
        } else {
            return $this->exportToCsv($exportData, 'Laporan Periode Tagihan', $columns, $metadata);
        }
    }

    /**
     * Export Villages
     */
    public function exportVillages(Builder $query, string $format, array $metadata = []): string
    {
        $villages = $query->withCount(['customers'])->get();

        $columns = [
            'name' => 'Nama Desa',
            'slug' => 'Slug',
            'phone_number' => 'Telepon',
            'email' => 'Email',
            'address' => 'Alamat',
            'customers_count' => 'Jumlah Pelanggan',
            'is_active' => 'Status',
            'established_at' => 'Didirikan',
        ];

        $exportData = $villages->map(function ($village) {
            return [
                'name' => $village->name ?? '',
                'slug' => $village->slug ?? '',
                'phone_number' => $village->phone_number ?? '',
                'email' => $village->email ?? '',
                'address' => $village->address ?? '',
                'customers_count' => $village->customers_count ?? 0,
                'is_active' => $village->is_active ? 'Aktif' : 'Tidak Aktif',
                'established_at' => $village->established_at?->format('d/m/Y') ?? '',
            ];
        })->toArray();

        if ($format === 'pdf') {
            return $this->exportToPdf($exportData, 'Laporan Desa', $columns, $metadata);
        } else {
            return $this->exportToCsv($exportData, 'Laporan Desa', $columns, $metadata);
        }
    }

    /**
     * Export Users
     */
    public function exportUsers(Builder $query, string $format, array $metadata = []): string
    {
        $users = $query->with(['villages'])->get();

        $columns = [
            'name' => 'Nama',
            'email' => 'Email',
            'role' => 'Role',
            'villages' => 'Desa',
            'contact_info' => 'Kontak',
            'is_active' => 'Status',
            'created_at' => 'Terdaftar',
        ];

        $exportData = $users->map(function ($user) {
            return [
                'name' => $user->name ?? '',
                'email' => $user->email ?? '',
                'role' => $user->display_role ?? '',
                'villages' => $user->isSuperAdmin() ? 'Semua Desa' : $user->villages->pluck('name')->join(', '),
                'contact_info' => $user->contact_info ?? '',
                'is_active' => $user->is_active ? 'Aktif' : 'Tidak Aktif',
                'created_at' => $user->created_at->format('d/m/Y'),
            ];
        })->toArray();

        if ($format === 'pdf') {
            return $this->exportToPdf($exportData, 'Laporan Pengguna', $columns, $metadata);
        } else {
            return $this->exportToCsv($exportData, 'Laporan Pengguna', $columns, $metadata);
        }
    }

    /**
     * Export Variables (Settings)
     */
    public function exportVariables(Builder $query, string $format, array $metadata = []): string
    {
        $variables = $query->with('village')->get();

        $columns = [
            'village_name' => 'Desa',
            'tripay_use_main' => 'Gunakan Config Global',
            'tripay_is_production' => 'Mode Produksi',
            'tripay_timeout_minutes' => 'Timeout (Menit)',
            'configuration_status' => 'Status Konfigurasi',
            'updated_at' => 'Terakhir Diupdate',
        ];

        $exportData = $variables->map(function ($variable) {
            return [
                'village_name' => $variable->village->name ?? '',
                'tripay_use_main' => $variable->tripay_use_main ? 'Ya' : 'Tidak',
                'tripay_is_production' => $variable->tripay_is_production ? 'Produksi' : 'Sandbox',
                'tripay_timeout_minutes' => $variable->tripay_timeout_minutes ?? '-',
                'configuration_status' => $variable->isConfigured() ? 'Terkonfigurasi' : 'Belum Lengkap',
                'updated_at' => $variable->updated_at->format('d/m/Y H:i'),
            ];
        })->toArray();

        if ($format === 'pdf') {
            return $this->exportToPdf($exportData, 'Laporan Pengaturan', $columns, $metadata);
        } else {
            return $this->exportToCsv($exportData, 'Laporan Pengaturan', $columns, $metadata);
        }
    }

    /**
     * Generate unique filename for exports
     */
    protected function generateFileName(string $title, string $format): string
    {
        $slug = str_replace(' ', '_', strtolower($title));
        $timestamp = now()->format('Y-m-d_H-i-s');
        $village = $this->getCurrentVillageInfo();
        $villageSlug = $village['slug'] ?? 'all';

        return "{$slug}_{$villageSlug}_{$timestamp}.{$format}";
    }

    /**
     * Get current village information
     */
    protected function getCurrentVillageInfo(): array
    {
        $village = config('pamdes.current_village');

        if ($village && is_array($village)) {
            return [
                'name' => $village['name'] ?? 'Unknown Village',
                'slug' => $village['slug'] ?? 'unknown',
            ];
        }

        return [
            'name' => 'All Villages',
            'slug' => 'all',
        ];
    }

    /**
     * Format cell value for export
     */
    protected function formatCellValue($row, string $key)
    {
        try {
            if (is_array($row)) {
                $value = $row[$key] ?? '';
            } else {
                $value = data_get($row, $key, '');
            }

            // Handle special formatting
            if (is_bool($value)) {
                return $value ? 'Ya' : 'Tidak';
            }

            if ($value instanceof Carbon) {
                return $value->format('d/m/Y');
            }

            if (is_array($value)) {
                return implode(', ', array_filter($value, function ($item) {
                    return !is_array($item) && !is_object($item);
                }));
            }

            if (is_object($value)) {
                return method_exists($value, '__toString') ? (string) $value : '[Object]';
            }

            return (string) $value;
        } catch (\Exception $e) {
            return '[Error]';
        }
    }

    /**
     * Format bill status
     */
    protected function formatBillStatus(string $status): string
    {
        return match ($status) {
            'paid' => 'Sudah Bayar',
            'unpaid' => 'Belum Bayar',
            'overdue' => 'Terlambat',
            'pending' => 'Menunggu Pembayaran',
            default => $status,
        };
    }
}
