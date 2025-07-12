<?php
// First, let's fix the ExportableResource trait - it was cut off
// app/Traits/ExportableResource.php

namespace App\Traits;

use App\Services\ExportService;
use Filament\Forms;
use Filament\Tables;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Builder;

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

            // Create query for selected records
            $primaryKey = (new static::$model)->getKeyName();
            $query = static::$model::whereIn($primaryKey, $records->pluck($primaryKey));

            $filters = [
                'selection' => 'Selected ' . $records->count() . ' records',
                'exported_by' => auth()->user()->name ?? 'System'
            ];

            $fileName = static::callExportMethod($exportService, $query, $format, $filters);

            static::sendBulkExportNotification($format, $fileName, $records->count());
        } catch (\Exception $e) {
            static::sendExportErrorNotification($e->getMessage());
        }
    }

    /**
     * Apply table filters to query - Override this in each resource
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
     * Apply search to query - Override in each resource
     */
    protected static function applySearchToQuery($query, string $search)
    {
        // Default implementation - should be overridden in specific resources
        return $query;
    }

    /**
     * Apply individual filter to query - Override in each resource
     */
    protected static function applyFilterToQuery($query, string $filterName, $filterValue)
    {
        // Default implementation - should be overridden in specific resources
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
                    $appliedFilters[$filterName] = implode(' - ', array_filter($filterValue));
                } else {
                    $appliedFilters[$filterName] = $filterValue;
                }
            }
        }

        return $appliedFilters;
    }

    /**
     * Call appropriate export method based on model
     */
    protected static function callExportMethod(ExportService $exportService, $query, string $format, array $filters): string
    {
        $modelClass = static::$model;
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
                    ->url(Storage::url("exports/{$fileName}"))
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
                    ->url(Storage::url("exports/{$fileName}"))
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
