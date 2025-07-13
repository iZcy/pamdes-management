<?php
// app/Traits/ExportableResource.php - Simplified version without filters

namespace App\Traits;

use App\Models\User;
use App\Services\ExportService;
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
                ->tooltip('Export all data to PDF')
                ->action(function () {
                    return static::handleExport('pdf');
                }),

            Tables\Actions\Action::make('export_csv')
                ->label('Export CSV')
                ->icon('heroicon-o-table-cells')
                ->color('success')
                ->tooltip('Export all data to CSV')
                ->action(function () {
                    return static::handleExport('csv');
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
     * Handle export without filters - exports all data
     */
    protected static function handleExport(string $format)
    {
        try {
            // Get the base query without any filters
            $query = static::getEloquentQuery();

            // Simple metadata for documentation
            $metadata = [
                'export_type' => 'complete_data',
                'exported_by' => User::find(Auth::id())->name ?? 'System',
                'exported_at' => now()->toISOString(),
            ];

            // Determine export method based on model
            $exportService = app(ExportService::class);
            $fileName = static::callExportMethod($exportService, $query, $format, $metadata);

            static::sendExportNotification($format, $fileName);
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
}
