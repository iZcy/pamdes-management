<?php
// app/Traits/ExportableResource.php - Simplified version with date range filter

namespace App\Traits;

use App\Models\User;
use App\Services\ExportService;
use Filament\Forms;
use Filament\Tables;
use Filament\Notifications\Notification;
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
                ->tooltip('Export data to PDF with date range')
                ->form([
                    Forms\Components\Section::make('Rentang Tanggal Export')
                        ->schema([
                            Forms\Components\DatePicker::make('start_date')
                                ->label('Tanggal Mulai')
                                ->placeholder('Pilih tanggal mulai')
                                ->helperText('Kosongkan untuk mengambil semua data dari awal'),

                            Forms\Components\DatePicker::make('end_date')
                                ->label('Tanggal Akhir')
                                ->placeholder('Pilih tanggal akhir')
                                ->helperText('Kosongkan untuk mengambil semua data sampai sekarang'),
                        ])
                        ->columns(2),
                ])
                ->action(function (array $data) {
                    return static::handleExport('pdf', $data);
                }),

            Tables\Actions\Action::make('export_csv')
                ->label('Export CSV')
                ->icon('heroicon-o-table-cells')
                ->color('success')
                ->tooltip('Export data to CSV with date range')
                ->form([
                    Forms\Components\Section::make('Rentang Tanggal Export')
                        ->schema([
                            Forms\Components\DatePicker::make('start_date')
                                ->label('Tanggal Mulai')
                                ->placeholder('Pilih tanggal mulai')
                                ->helperText('Kosongkan untuk mengambil semua data dari awal'),

                            Forms\Components\DatePicker::make('end_date')
                                ->label('Tanggal Akhir')
                                ->placeholder('Pilih tanggal akhir')
                                ->helperText('Kosongkan untuk mengambil semua data sampai sekarang'),
                        ])
                        ->columns(2),
                ])
                ->action(function (array $data) {
                    return static::handleExport('csv', $data);
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
                ->action(function ($records) {
                    return static::handleBulkExport('pdf', $records);
                })
                ->deselectRecordsAfterCompletion(),

            Tables\Actions\BulkAction::make('bulk_export_csv')
                ->label('Export Terpilih ke CSV')
                ->icon('heroicon-o-table-cells')
                ->color('success')
                ->tooltip('Export selected records to CSV')
                ->action(function ($records) {
                    return static::handleBulkExport('csv', $records);
                })
                ->deselectRecordsAfterCompletion(),
        ];
    }

    /**
     * Handle export with date range filter
     */
    protected static function handleExport(string $format, array $dateRange = [])
    {
        try {
            // Get the base query
            $query = static::getEloquentQuery();

            // Apply date range filter if provided
            if (!empty($dateRange['start_date']) || !empty($dateRange['end_date'])) {
                $query = static::applyDateRangeFilter($query, $dateRange);
            }

            // Prepare metadata with date range information
            $metadata = [
                'export_type' => !empty($dateRange['start_date']) || !empty($dateRange['end_date']) ? 'date_range_filtered' : 'complete_data',
                'exported_by' => User::find(Auth::id())->name ?? 'System',
                'exported_at' => now()->toISOString(),
                'date_range' => static::formatDateRangeForMetadata($dateRange),
            ];

            // Determine export method based on model
            $exportService = app(ExportService::class);
            $fileName = static::callExportMethod($exportService, $query, $format, $metadata);

            static::sendExportNotification($format, $fileName, $metadata);
        } catch (\Exception $e) {
            static::sendExportErrorNotification($e->getMessage());
        }
    }

    /**
     * Handle bulk export of selected records
     */
    protected static function handleBulkExport(string $format, $records)
    {
        try {
            $exportService = app(ExportService::class);

            // Create query for selected records using the Resource's model
            $modelClass = static::getModel();
            $modelInstance = new $modelClass();
            $primaryKey = $modelInstance->getKeyName();
            $query = $modelClass::whereIn($primaryKey, $records->pluck($primaryKey));

            $metadata = [
                'export_type' => 'selected_records',
                'selection_count' => $records->count(),
                'exported_by' => User::find(Auth::id())->name ?? 'System'
            ];

            $fileName = static::callExportMethod($exportService, $query, $format, $metadata);

            static::sendBulkExportNotification($format, $fileName, $records->count());
        } catch (\Exception $e) {
            static::sendExportErrorNotification($e->getMessage());
        }
    }

    /**
     * Call appropriate export method based on model
     */
    protected static function callExportMethod(ExportService $exportService, $query, string $format, array $metadata): string
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

        return $exportService->$method($query, $format, $metadata);
    }

    /**
     * Apply date range filter to query based on model
     */
    protected static function applyDateRangeFilter($query, array $dateRange)
    {
        $modelClass = static::getModel();
        $modelName = class_basename($modelClass);

        // Determine the appropriate date field for each model
        $dateField = match ($modelName) {
            'Payment' => 'payment_date',
            'WaterUsage' => 'usage_date',
            'Bill' => 'created_at',
            'Customer' => 'created_at',
            'BillingPeriod' => 'created_at',
            'WaterTariff' => 'created_at',
            'Village' => 'created_at',
            'User' => 'created_at',
            'Variable' => 'created_at',
            default => 'created_at',
        };

        if (!empty($dateRange['start_date'])) {
            $query->whereDate($dateField, '>=', $dateRange['start_date']);
        }

        if (!empty($dateRange['end_date'])) {
            $query->whereDate($dateField, '<=', $dateRange['end_date']);
        }

        return $query;
    }

    /**
     * Format date range for metadata display
     */
    protected static function formatDateRangeForMetadata(array $dateRange): string
    {
        $parts = [];

        if (!empty($dateRange['start_date'])) {
            $parts[] = 'Dari: ' . \Carbon\Carbon::parse($dateRange['start_date'])->format('d/m/Y');
        }

        if (!empty($dateRange['end_date'])) {
            $parts[] = 'Sampai: ' . \Carbon\Carbon::parse($dateRange['end_date'])->format('d/m/Y');
        }

        if (empty($parts)) {
            return 'Semua periode';
        }

        return implode(' - ', $parts);
    }
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
}
