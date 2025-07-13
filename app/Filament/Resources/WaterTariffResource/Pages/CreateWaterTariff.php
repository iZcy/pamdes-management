<?php
// app/Filament/Resources/WaterTariffResource/Pages/CreateWaterTariff.php - Enhanced with better feedback

namespace App\Filament\Resources\WaterTariffResource\Pages;

use App\Filament\Resources\WaterTariffResource;
use App\Services\TariffRangeService;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;

class CreateWaterTariff extends CreateRecord
{
    protected static string $resource = WaterTariffResource::class;

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        try {
            $service = app(TariffRangeService::class);

            $tariff = $service->createTariffRange(
                $data['village_id'],
                $data['usage_min'],
                $data['price_per_m3']
            );

            // Get the updated tariff structure to show user what happened
            $allTariffs = $service->getVillageTariffs($data['village_id']);
            $createdTariffInfo = collect($allTariffs)->firstWhere('usage_min', $data['usage_min']);

            if ($createdTariffInfo) {
                Notification::make()
                    ->title('Tarif berhasil dibuat')
                    ->body("Rentang {$createdTariffInfo['range_display']} dengan harga Rp " . number_format($tariff->price_per_m3) . " per m³")
                    ->success()
                    ->duration(8000) // Show longer to let user read
                    ->send();
            } else {
                Notification::make()
                    ->title('Tarif berhasil dibuat')
                    ->body('Rentang tarif telah disesuaikan secara otomatis')
                    ->success()
                    ->send();
            }

            return $tariff;
        } catch (\Exception $e) {
            Notification::make()
                ->title('Gagal membuat tarif')
                ->body($e->getMessage())
                ->danger()
                ->duration(10000) // Show error longer
                ->send();

            $this->halt();
            return $tariff;
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Add some helpful validation messages
        if (!isset($data['village_id']) || !$data['village_id']) {
            Notification::make()
                ->title('Gagal membuat tarif')
                ->body('Desa harus dipilih')
                ->danger()
                ->send();
            $this->halt();
        }

        if (!isset($data['usage_min']) || $data['usage_min'] < 0) {
            Notification::make()
                ->title('Gagal membuat tarif')
                ->body('Penggunaan minimum harus diisi dan tidak boleh negatif')
                ->danger()
                ->send();
            $this->halt();
        }

        if (!isset($data['price_per_m3']) || $data['price_per_m3'] <= 0) {
            Notification::make()
                ->title('Gagal membuat tarif')
                ->body('Harga per m³ harus diisi dan lebih besar dari 0')
                ->danger()
                ->send();
            $this->halt();
        }

        return $data;
    }
}
