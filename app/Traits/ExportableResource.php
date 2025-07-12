<?php
// app/Traits/ExportableResource.php - Fixed notification URLs

namespace App\Traits;

use App\Models\User;
use App\Services\ExportService;
use Filament\Forms;
use Filament\Tables;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

trait ExportableResource
{
    /**
     * Get export actions for table header
     */
    public static function getExportHeaderActions(): array
    {
        return [
            Tables\Actions\Action::make('export_pdf')
                ->label('Export PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('danger')
                ->tooltip('Export filtered data to PDF')
                ->action(function ($livewire) {
                    return static::handleExport('pdf', $livewire);
                }),

            Tables\Actions\Action::make('export_csv')
                ->label('Export CSV')
                ->icon('heroicon-o-table-cells')
                ->color('success')
                ->tooltip('Export filtered data to CSV')
                ->action(function ($livewire) {
                    return static::handleExport('csv', $livewire);
                }),
        ];
    }

    /**
     * Get export bulk actions
     */
    public static function getExportBulkActions(): array
    {
        return [
            Tables\Actions\BulkAction::make('bulk_export_pdf')
                ->label('Export Terpilih ke PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('danger')
                ->tooltip('Export selected records to PDF')
                ->action(function ($records, $livewire) {
                    return static::handleBulkExport('pdf', $records, $livewire);
                })
                ->deselectRecordsAfterCompletion(),

            Tables\Actions\BulkAction::make('bulk_export_csv')
                ->label('Export Terpilih ke CSV')
                ->icon('heroicon-o-table-cells')
                ->color('success')
                ->tooltip('Export selected records to CSV')
                ->action(function ($records, $livewire) {
                    return static::handleBulkExport('csv', $records, $livewire);
                })
                ->deselectRecordsAfterCompletion(),
        ];
    }

    /**
     * Handle export with filters
     */
    protected static function handleExport(string $format, $livewire)
    {
        try {
            // Get the base query
            $query = static::getEloquentQuery();

            // Apply table filters and search
            $query = static::applyTableFiltersToQuery($query, $livewire);

            // Get applied filters for documentation
            $appliedFilters = static::getAppliedFilters($livewire);

            // Determine export method based on model
            $exportService = app(ExportService::class);
            $fileName = static::callExportMethod($exportService, $query, $format, $appliedFilters);

            static::sendExportNotification($format, $fileName);
        } catch (\Exception $e) {
            static::sendExportErrorNotification($e->getMessage());
        }
    }

    /**
     * Handle bulk export of selected records
     */
    protected static function handleBulkExport(string $format, $records, $livewire)
    {
        try {
            $exportService = app(ExportService::class);

            // Create query for selected records using the Resource's model
            $modelClass = static::getModel();
            $modelInstance = new $modelClass();
            $primaryKey = $modelInstance->getKeyName();
            $query = $modelClass::whereIn($primaryKey, $records->pluck($primaryKey));

            $filters = [
                'selection' => 'Selected ' . $records->count() . ' records',
                'exported_by' => User::find(Auth::id())->name ?? 'System'
            ];

            $fileName = static::callExportMethod($exportService, $query, $format, $filters);

            static::sendBulkExportNotification($format, $fileName, $records->count());
        } catch (\Exception $e) {
            static::sendExportErrorNotification($e->getMessage());
        }
    }

    /**
     * Apply table filters to query - Resource-specific implementations
     */
    protected static function applyTableFiltersToQuery($query, $livewire)
    {
        $tableFilters = $livewire->tableFilters ?? [];
        $search = $livewire->tableSearch ?? '';

        // Apply search
        if (!empty($search)) {
            $query = static::applySearchToQuery($query, $search);
        }

        // Apply filters
        foreach ($tableFilters as $filterName => $filterValue) {
            if (empty($filterValue)) continue;
            $query = static::applyFilterToQuery($query, $filterName, $filterValue);
        }

        return $query;
    }

    /**
     * Apply search to query - Resource-specific implementations
     */
    protected static function applySearchToQuery($query, string $search)
    {
        $modelClass = static::getModel();
        $modelName = class_basename($modelClass);

        return match ($modelName) {
            'Bill' => $query->where(function ($q) use ($search) {
                $q->whereHas('waterUsage.customer', function ($customerQuery) use ($search) {
                    $customerQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('customer_code', 'like', "%{$search}%");
                })
                    ->orWhereHas('waterUsage.billingPeriod', function ($periodQuery) use ($search) {
                        $periodQuery->where('period_name', 'like', "%{$search}%");
                    });
            }),

            'Customer' => $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('customer_code', 'like', "%{$search}%")
                    ->orWhere('phone_number', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%");
            }),

            'Payment' => $query->where(function ($q) use ($search) {
                $q->whereHas('bill.waterUsage.customer', function ($customerQuery) use ($search) {
                    $customerQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('customer_code', 'like', "%{$search}%");
                })
                    ->orWhere('payment_reference', 'like', "%{$search}%")
                    ->orWhereHas('collector', function ($collectorQuery) use ($search) {
                        $collectorQuery->where('name', 'like', "%{$search}%");
                    });
            }),

            'WaterUsage' => $query->where(function ($q) use ($search) {
                $q->whereHas('customer', function ($customerQuery) use ($search) {
                    $customerQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('customer_code', 'like', "%{$search}%");
                })
                    ->orWhereHas('billingPeriod', function ($periodQuery) use ($search) {
                        $periodQuery->where('period_name', 'like', "%{$search}%");
                    })
                    ->orWhere('reader_name', 'like', "%{$search}%");
            }),

            'WaterTariff' => $query->where(function ($q) use ($search) {
                $q->whereHas('village', function ($villageQuery) use ($search) {
                    $villageQuery->where('name', 'like', "%{$search}%");
                })
                    ->orWhere('price_per_m3', 'like', "%{$search}%");
            }),

            'BillingPeriod' => $query->where(function ($q) use ($search) {
                $q->whereHas('village', function ($villageQuery) use ($search) {
                    $villageQuery->where('name', 'like', "%{$search}%");
                })
                    ->orWhereRaw("CONCAT(CASE month
                    WHEN 1 THEN 'Januari'
                    WHEN 2 THEN 'Februari'
                    WHEN 3 THEN 'Maret'
                    WHEN 4 THEN 'April'
                    WHEN 5 THEN 'Mei'
                    WHEN 6 THEN 'Juni'
                    WHEN 7 THEN 'Juli'
                    WHEN 8 THEN 'Agustus'
                    WHEN 9 THEN 'September'
                    WHEN 10 THEN 'Oktober'
                    WHEN 11 THEN 'November'
                    WHEN 12 THEN 'Desember'
                END, ' ', year) LIKE ?", ["%{$search}%"]);
            }),

            'Village' => $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('phone_number', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%");
            }),

            'User' => $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('contact_info', 'like', "%{$search}%")
                    ->orWhereHas('villages', function ($villageQuery) use ($search) {
                        $villageQuery->where('name', 'like', "%{$search}%");
                    });
            }),

            'Variable' => $query->where(function ($q) use ($search) {
                $q->whereHas('village', function ($villageQuery) use ($search) {
                    $villageQuery->where('name', 'like', "%{$search}%");
                });
            }),

            default => $query
        };
    }

    /**
     * Apply individual filter to query - Resource-specific implementations
     */
    protected static function applyFilterToQuery($query, string $filterName, $filterValue)
    {
        $modelClass = static::getModel();
        $modelName = class_basename($modelClass);

        return match ([$modelName, $filterName]) {
            // Bill filters
            ['Bill', 'village'] => $query->whereHas('waterUsage.customer', function ($q) use ($filterValue) {
                $q->where('village_id', $filterValue);
            }),
            ['Bill', 'status'] => $query->where('status', $filterValue),
            ['Bill', 'overdue'] => $filterValue ? $query->overdue() : $query,
            ['Bill', 'date_range'] => static::applyDateRangeFilter($query, $filterValue, 'created_at'),

            // Customer filters
            ['Customer', 'village_id'] => $query->where('village_id', $filterValue),
            ['Customer', 'status'] => $query->where('status', $filterValue),
            ['Customer', 'date_range'] => static::applyDateRangeFilter($query, $filterValue, 'created_at'),

            // Payment filters
            ['Payment', 'village'] => $query->whereHas('bill.waterUsage.customer.village', function ($q) use ($filterValue) {
                $q->where('name', $filterValue);
            }),
            ['Payment', 'payment_method'] => $query->where('payment_method', $filterValue),
            ['Payment', 'collector_id'] => $query->where('collector_id', $filterValue),
            ['Payment', 'today'] => $filterValue ? $query->whereDate('payment_date', today()) : $query,
            ['Payment', 'this_week'] => $filterValue ? $query->whereBetween('payment_date', [
                now()->startOfWeek(),
                now()->endOfWeek()
            ]) : $query,
            ['Payment', 'this_month'] => $filterValue ? $query->whereMonth('payment_date', now()->month)
                ->whereYear('payment_date', now()->year) : $query,
            ['Payment', 'has_change'] => $filterValue ? $query->where('change_given', '>', 0) : $query,
            ['Payment', 'date_range'] => static::applyDateRangeFilter($query, $filterValue, 'payment_date'),

            // Water Usage filters
            ['WaterUsage', 'village'] => $query->whereHas('customer.village', function ($q) use ($filterValue) {
                $q->where('name', $filterValue);
            }),
            ['WaterUsage', 'has_bill'] => $filterValue ? $query->whereHas('bill') : $query,
            ['WaterUsage', 'no_bill'] => $filterValue ? $query->whereDoesntHave('bill') : $query,
            ['WaterUsage', 'date_range'] => static::applyDateRangeFilter($query, $filterValue, 'usage_date'),

            // Water Tariff filters
            ['WaterTariff', 'village_id'] => $query->where('village_id', $filterValue),
            ['WaterTariff', 'is_active'] => $query->where('is_active', $filterValue),

            // Billing Period filters
            ['BillingPeriod', 'village_id'] => $query->where('village_id', $filterValue),
            ['BillingPeriod', 'status'] => $query->where('status', $filterValue),

            // Village filters
            ['Village', 'is_active'] => $query->where('is_active', $filterValue),

            // User filters
            ['User', 'role'] => $query->where('role', $filterValue),
            ['User', 'is_active'] => $query->where('is_active', $filterValue),

            // Variable filters
            ['Variable', 'tripay_use_main'] => $query->where('tripay_use_main', $filterValue),
            ['Variable', 'tripay_is_production'] => $query->where('tripay_is_production', $filterValue),

            default => $query
        };
    }

    /**
     * Apply date range filter
     */
    protected static function applyDateRangeFilter($query, array $filterValue, string $field)
    {
        if (!empty($filterValue['from'])) {
            $query->whereDate($field, '>=', $filterValue['from']);
        }
        if (!empty($filterValue['until'])) {
            $query->whereDate($field, '<=', $filterValue['until']);
        }
        return $query;
    }

    /**
     * Get applied filters for documentation
     */
    protected static function getAppliedFilters($livewire): array
    {
        $tableFilters = $livewire->tableFilters ?? [];
        $search = $livewire->tableSearch ?? '';

        $appliedFilters = [];

        if (!empty($search)) {
            $appliedFilters['search'] = $search;
        }

        foreach ($tableFilters as $filterName => $filterValue) {
            if (!empty($filterValue)) {
                if (is_array($filterValue)) {
                    // Handle date range filters
                    if (isset($filterValue['from']) || isset($filterValue['until'])) {
                        $dateRange = [];
                        if (!empty($filterValue['from'])) {
                            $dateRange[] = 'From: ' . \Carbon\Carbon::parse($filterValue['from'])->format('d/m/Y');
                        }
                        if (!empty($filterValue['until'])) {
                            $dateRange[] = 'Until: ' . \Carbon\Carbon::parse($filterValue['until'])->format('d/m/Y');
                        }
                        $appliedFilters[$filterName] = implode(' - ', $dateRange);
                    } else {
                        $appliedFilters[$filterName] = implode(', ', array_filter($filterValue));
                    }
                } else {
                    // Convert filter values to readable format
                    $appliedFilters[$filterName] = static::formatFilterValue($filterName, $filterValue);
                }
            }
        }

        return $appliedFilters;
    }

    /**
     * Format filter value for display
     */
    protected static function formatFilterValue(string $filterName, $filterValue): string
    {
        return match ($filterName) {
            'status' => match ($filterValue) {
                'active' => 'Aktif',
                'inactive' => 'Tidak Aktif',
                'paid' => 'Sudah Bayar',
                'unpaid' => 'Belum Bayar',
                'overdue' => 'Terlambat',
                'pending' => 'Menunggu Pembayaran',
                'completed' => 'Selesai',
                default => $filterValue
            },
            'payment_method' => match ($filterValue) {
                'cash' => 'Tunai',
                'transfer' => 'Transfer Bank',
                'qris' => 'QRIS',
                'other' => 'Lainnya',
                default => $filterValue
            },
            'role' => match ($filterValue) {
                'super_admin' => 'Super Admin',
                'village_admin' => 'Admin Desa',
                'collector' => 'Penagih',
                'cashier' => 'Kasir',
                'operator' => 'Operator',
                default => $filterValue
            },
            'is_active' => $filterValue ? 'Aktif' : 'Tidak Aktif',
            'tripay_use_main' => $filterValue ? 'Ya' : 'Tidak',
            'tripay_is_production' => $filterValue ? 'Produksi' : 'Sandbox',
            default => (string) $filterValue
        };
    }

    /**
     * Call appropriate export method based on model
     */
    protected static function callExportMethod(ExportService $exportService, $query, string $format, array $filters): string
    {
        $modelClass = static::getModel();
        $modelName = class_basename($modelClass);

        // Map model names to export methods
        $exportMethods = [
            'Bill' => 'exportBills',
            'Customer' => 'exportCustomers',
            'Payment' => 'exportPayments',
            'WaterUsage' => 'exportWaterUsage',
            'WaterTariff' => 'exportWaterTariffs',
            'BillingPeriod' => 'exportBillingPeriods',
            'Village' => 'exportVillages',
            'User' => 'exportUsers',
            'Variable' => 'exportVariables',
        ];

        $method = $exportMethods[$modelName] ?? null;

        if (!$method || !method_exists($exportService, $method)) {
            throw new \Exception("Export method not found for model: {$modelName}");
        }

        return $exportService->$method($query, $format, $filters);
    }

    /**
     * Send export success notification
     */
    protected static function sendExportNotification(string $format, string $fileName)
    {
        Notification::make()
            ->title('Export Berhasil')
            ->body("File {$format} telah dibuat: " . basename($fileName))
            ->success()
            ->actions([
                \Filament\Notifications\Actions\Action::make('download')
                    ->label('Download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(route('export.download', ['filename' => $fileName]))
                    ->openUrlInNewTab(),
            ])
            ->duration(10000)
            ->send();
    }

    /**
     * Send bulk export success notification
     */
    protected static function sendBulkExportNotification(string $format, string $fileName, int $recordCount)
    {
        Notification::make()
            ->title('Bulk Export Berhasil')
            ->body("File {$format} telah dibuat untuk {$recordCount} data terpilih")
            ->success()
            ->actions([
                \Filament\Notifications\Actions\Action::make('download')
                    ->label('Download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(route('export.download', ['filename' => $fileName]))
                    ->openUrlInNewTab(),
            ])
            ->duration(10000)
            ->send();
    }

    /**
     * Send export error notification
     */
    protected static function sendExportErrorNotification(string $message)
    {
        Notification::make()
            ->title('Export Gagal')
            ->body("Error: {$message}")
            ->danger()
            ->duration(8000)
            ->send();
    }

    /**
     * Get common date range filter
     */
    public static function getDateRangeFilter(string $label = 'Rentang Tanggal', string $field = 'created_at'): Tables\Filters\Filter
    {
        return Tables\Filters\Filter::make('date_range')
            ->label($label)
            ->form([
                Forms\Components\DatePicker::make('from')
                    ->label('Dari Tanggal')
                    ->placeholder('Pilih tanggal mulai'),
                Forms\Components\DatePicker::make('until')
                    ->label('Sampai Tanggal')
                    ->placeholder('Pilih tanggal akhir'),
            ])
            ->query(function (Builder $query, array $data) use ($field): Builder {
                return $query
                    ->when(
                        $data['from'],
                        fn(Builder $query, $date): Builder => $query->whereDate($field, '>=', $date),
                    )
                    ->when(
                        $data['until'],
                        fn(Builder $query, $date): Builder => $query->whereDate($field, '<=', $date),
                    );
            })
            ->indicateUsing(function (array $data): array {
                $indicators = [];
                if ($data['from'] ?? null) {
                    $indicators['from'] = 'Dari: ' . \Carbon\Carbon::parse($data['from'])->format('d/m/Y');
                }
                if ($data['until'] ?? null) {
                    $indicators['until'] = 'Sampai: ' . \Carbon\Carbon::parse($data['until'])->format('d/m/Y');
                }
                return $indicators;
            });
    }

    /**
     * Get village filter (for super admin)
     */
    public static function getVillageFilter(string $relationship = 'village'): Tables\Filters\SelectFilter
    {
        return Tables\Filters\SelectFilter::make('village')
            ->label('Desa')
            ->relationship($relationship, 'name')
            ->searchable()
            ->preload()
            ->visible(function () {
                $user = \App\Models\User::find(\Illuminate\Support\Facades\Auth::user()->id);
                return $user?->isSuperAdmin() ?? false;
            });
    }

    /**
     * Get status filter
     */
    public static function getStatusFilter(array $options): Tables\Filters\SelectFilter
    {
        return Tables\Filters\SelectFilter::make('status')
            ->label('Status')
            ->options($options)
            ->placeholder('Semua Status');
    }
}
