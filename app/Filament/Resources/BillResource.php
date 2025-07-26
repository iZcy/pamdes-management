<?php
// app/Filament/Resources/BillResource.php - Updated with collector restrictions

namespace App\Filament\Resources;

use App\Filament\Resources\BillResource\Pages;
use App\Models\Bill;
use App\Models\Customer;
use App\Models\User;
use App\Models\WaterUsage;
use App\Traits\ExportableResource;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class BillResource extends Resource
{
    use ExportableResource;

    protected static ?string $model = Bill::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'Tagihan & Bundel';
    protected static ?string $modelLabel = 'Tagihan';
    protected static ?string $pluralModelLabel = 'Tagihan';
    protected static ?int $navigationSort = 4;
    protected static ?string $navigationGroup = 'Tagihan & Pembayaran';

    // Role-based access control
    public static function canCreate(): bool
    {
        // Bills should only be generated from water usage, not created manually
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        $user = User::find(Auth::user()->id);

        // Only super_admin and village_admin can edit bills
        return $user && in_array($user->role, ['super_admin', 'village_admin']);
    }

    public static function canDelete(Model $record): bool
    {
        $user = User::find(Auth::user()->id);

        // Only super_admin and village_admin can delete bills
        return $user && in_array($user->role, ['super_admin', 'village_admin']);
    }

    public static function canDeleteAny(): bool
    {
        $user = User::find(Auth::user()->id);

        // Only super_admin and village_admin can bulk delete
        return $user && in_array($user->role, ['super_admin', 'village_admin']);
    }

    public static function canViewAny(): bool
    {
        $user = User::find(Auth::user()->id);

        // All roles can view bills, but with different scopes
        return $user && in_array($user->role, ['super_admin', 'village_admin', 'collector', 'operator']);
    }

    /**
     * Get the current village ID with proper fallback logic
     */
    protected static function getCurrentVillageId(): ?string
    {
        $user = User::find(Auth::user()->id);

        if (!$user) {
            return null;
        }

        // For super admins, use village context if available
        if ($user->isSuperAdmin()) {
            return $user->getCurrentVillageContext();
        }

        // For village users, try multiple approaches
        $villageId = $user->getCurrentVillageContext();

        // If still no village, try fallbacks
        if (!$villageId) {
            // Try config directly
            $villageId = config('pamdes.current_village_id');

            if (!$villageId) {
                // Try primary village
                $villageId = $user->getPrimaryVillageId();
            }

            if (!$villageId) {
                // Try first accessible village
                $firstVillage = $user->getAccessibleVillages()->first();
                $villageId = $firstVillage?->id;
            }
        }

        // Verify user has access to this village
        if ($villageId && !$user->hasAccessToVillage($villageId)) {
            // Fall back to first accessible village
            $firstVillage = $user->getAccessibleVillages()->first();
            $villageId = $firstVillage?->id;
        }

        return $villageId;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['waterUsage.customer.village']);
        $user = User::find(Auth::user()->id);
        $currentVillage = static::getCurrentVillageId();

        if ($user?->isSuperAdmin() && $currentVillage) {
            // Super admin sees bills for current village context
            $query->whereHas('waterUsage.customer', function ($q) use ($currentVillage) {
                $q->where('village_id', $currentVillage);
            });
        } elseif ($user?->isVillageAdmin()) {
            // Village admin sees bills for their accessible villages
            $accessibleVillages = $user->getAccessibleVillages()->pluck('id');
            if ($accessibleVillages->isNotEmpty()) {
                $query->whereHas('waterUsage.customer', function ($q) use ($accessibleVillages) {
                    $q->whereIn('village_id', $accessibleVillages);
                });
            } else {
                // If no accessible villages, return empty result
                $query->whereRaw('1 = 0');
            }
        } elseif ($user?->isCollector()) {
            // Collector sees only unpaid/overdue bills in their villages
            $accessibleVillages = $user->getAccessibleVillages()->pluck('id');
            if ($accessibleVillages->isNotEmpty()) {
                $query->whereHas('waterUsage.customer', function ($q) use ($accessibleVillages) {
                    $q->whereIn('village_id', $accessibleVillages);
                })->where('status', 'unpaid');
            } else {
                // If no accessible villages, return empty result
                $query->whereRaw('1 = 0');
            }
        } elseif ($user?->role === 'operator') {
            // Operator has read-only access to bills in their villages
            $accessibleVillages = $user->getAccessibleVillages()->pluck('id');
            if ($accessibleVillages->isNotEmpty()) {
                $query->whereHas('waterUsage.customer', function ($q) use ($accessibleVillages) {
                    $q->whereIn('village_id', $accessibleVillages);
                });
            } else {
                // If no accessible villages, return empty result
                $query->whereRaw('1 = 0');
            }
        }

        return $query;
    }

    public static function form(Form $form): Form
    {
        $user = User::find(Auth::user()->id);

        // Collectors and operators cannot access the form
        if ($user && in_array($user->role, ['collector', 'operator'])) {
            return $form->schema([
                Forms\Components\Placeholder::make('access_denied')
                    ->label('Akses Ditolak')
                    ->content('Anda tidak memiliki izin untuk mengubah data tagihan.')
                    ->columnSpanFull(),
            ]);
        }

        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Tagihan')
                    ->schema([
                        Forms\Components\Placeholder::make('village_info')
                            ->label('Desa')
                            ->content(function (?Bill $record = null) {
                                if ($record && $record->waterUsage?->customer?->village) {
                                    return $record->waterUsage->customer->village->name;
                                }
                                $user = User::find(Auth::user()->id);
                                $currentVillage = $user?->getCurrentVillageContext();
                                if ($currentVillage) {
                                    $village = \App\Models\Village::find($currentVillage);
                                    return $village?->name ?? 'Unknown Village';
                                }
                                return 'No Village Context';
                            })
                            ->columnSpanFull(),

                        Forms\Components\Select::make('usage_id')
                            ->label('Pembacaan Meter')
                            ->options(function () {
                                $user = User::find(Auth::user()->id);
                                $currentVillage = $user?->getCurrentVillageContext();

                                if (!$currentVillage) {
                                    return [];
                                }

                                return WaterUsage::whereHas('customer', function ($q) use ($currentVillage) {
                                    $q->where('village_id', $currentVillage);
                                })->with(['customer', 'billingPeriod'])
                                    ->get()
                                    ->mapWithKeys(function ($usage) {
                                        return [
                                            $usage->usage_id => "{$usage->customer->customer_code} - {$usage->customer->name} ({$usage->billingPeriod->period_name})"
                                        ];
                                    });
                            })
                            ->disabledOn('edit')
                            ->searchable()
                            ->required(),

                        Forms\Components\TextInput::make('water_charge')
                            ->label('Biaya Air')
                            ->required()
                            ->numeric()
                            ->prefix('Rp')
                            ->live()
                            ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get, $state) {
                                // Convert non-numeric input to 0
                                $water = is_numeric($state) ? (float) $state : 0;
                                if (!is_numeric($state)) {
                                    $set('water_charge', 0);
                                }

                                $admin = is_numeric($get('admin_fee')) ? (float) $get('admin_fee') : 0;
                                $maintenance = is_numeric($get('maintenance_fee')) ? (float) $get('maintenance_fee') : 0;
                                $set('total_amount', $water + $admin + $maintenance);
                            }),

                        Forms\Components\TextInput::make('admin_fee')
                            ->label('Biaya Admin')
                            ->required()
                            ->numeric()
                            ->prefix('Rp')
                            ->default(function () {
                                $user = User::find(Auth::user()->id);
                                $village = \App\Models\Village::find($user?->getCurrentVillageContext());
                                return $village?->getDefaultAdminFee() ?? 5000;
                            })
                            ->live()
                            ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get, $state) {
                                // Convert non-numeric input to 0
                                $admin = is_numeric($state) ? (float) $state : 0;
                                if (!is_numeric($state)) {
                                    $set('admin_fee', 0);
                                }

                                $water = is_numeric($get('water_charge')) ? (float) $get('water_charge') : 0;
                                $maintenance = is_numeric($get('maintenance_fee')) ? (float) $get('maintenance_fee') : 0;
                                $set('total_amount', $water + $admin + $maintenance);
                            }),

                        Forms\Components\TextInput::make('maintenance_fee')
                            ->label('Biaya Pemeliharaan')
                            ->required()
                            ->numeric()
                            ->prefix('Rp')
                            ->default(function () {
                                $user = User::find(Auth::user()->id);
                                $village = \App\Models\Village::find($user?->getCurrentVillageContext());
                                return $village?->getDefaultMaintenanceFee() ?? 2000;
                            })
                            ->live()
                            ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get, $state) {
                                // Convert non-numeric input to 0
                                $maintenance = is_numeric($state) ? (float) $state : 0;
                                if (!is_numeric($state)) {
                                    $set('maintenance_fee', 0);
                                }

                                $water = is_numeric($get('water_charge')) ? (float) $get('water_charge') : 0;
                                $admin = is_numeric($get('admin_fee')) ? (float) $get('admin_fee') : 0;
                                $set('total_amount', $water + $admin + $maintenance);
                            }),

                        Forms\Components\TextInput::make('total_amount')
                            ->label('Total Tagihan')
                            ->required()
                            ->numeric()
                            ->prefix('Rp')
                            ->disabled(function ($operation) {
                                // Disable in edit mode, enable in create mode
                                return $operation === 'edit';
                            })
                            ->dehydrated(function ($operation) {
                                // Always save the value, even when disabled
                                return true;
                            })
                            ->live()
                            ->afterStateHydrated(function (Forms\Set $set, Forms\Get $get, $operation) {
                                // Auto-calculate total when form is loaded in edit mode
                                if ($operation === 'edit') {
                                    $water = is_numeric($get('water_charge')) ? (float) $get('water_charge') : 0;
                                    $admin = is_numeric($get('admin_fee')) ? (float) $get('admin_fee') : 0;
                                    $maintenance = is_numeric($get('maintenance_fee')) ? (float) $get('maintenance_fee') : 0;
                                    $set('total_amount', $water + $admin + $maintenance);
                                }
                            })
                            ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get, $state) {
                                // Convert non-numeric input to 0 if user somehow manages to edit total_amount
                                if (!is_numeric($state)) {
                                    $set('total_amount', 0);
                                }
                            }),

                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'unpaid' => 'Belum Bayar',
                                'paid' => 'Sudah Bayar',
                            ])
                            ->searchable()
                            ->default('unpaid')
                            ->required(),

                        Forms\Components\DatePicker::make('due_date')
                            ->label('Tanggal Jatuh Tempo')
                            ->required(),

                        Forms\Components\DatePicker::make('payment_date')
                            ->label('Tanggal Pembayaran')
                            ->visible(fn(Forms\Get $get) => $get('status') === 'paid'),
                    ])
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                        'lg' => 3,
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        $user = Auth::user();
        $user = User::find($user->id);
        $isSuperAdmin = $user?->isSuperAdmin();
        $isCollector = $user?->isCollector();
        $isOperator = $user?->role === 'operator';

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('waterUsage.customer.village.name')
                    ->label('Desa')
                    ->searchable()
                    ->sortable()
                    ->visible($isSuperAdmin)
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('waterUsage.customer.customer_code')
                    ->label('Kode Pelanggan')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('waterUsage.customer.name')
                    ->label('Nama Pelanggan')
                    ->searchable()
                    ->limit(25),

                Tables\Columns\TextColumn::make('waterUsage.billingPeriod.period_name')
                    ->label('Periode')
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->join('water_usages', 'bills.usage_id', '=', 'water_usages.usage_id')
                            ->join('billing_periods', 'water_usages.period_id', '=', 'billing_periods.period_id')
                            ->orderBy('billing_periods.year', $direction)
                            ->orderBy('billing_periods.month', $direction)
                            ->select('bills.*');
                    }),

                Tables\Columns\TextColumn::make('waterUsage.total_usage_m3')
                    ->label('Pemakaian')
                    ->suffix(' m³')
                    ->numeric(),

                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Total Tagihan')
                    ->money('IDR')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->colors([
                        'warning' => 'unpaid',
                        'success' => 'paid',
                    ])
                    ->formatStateUsing(fn(string $state, $record): string => match ($state) {
                        'unpaid' => $record->is_overdue ? 'Terlambat' : 'Belum Bayar',
                        'paid' => 'Sudah Bayar',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('due_date')
                    ->label('Jatuh Tempo')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('bundle_info')
                    ->label('Bundel')
                    ->badge()
                    ->color('warning')
                    ->formatStateUsing(function (?Bill $record = null) {
                        if (!$record || !$record->transaction_ref) return null;
                        
                        // Count bills with same transaction_ref
                        $bundleCount = Bill::where('transaction_ref', $record->transaction_ref)->count();
                        if ($bundleCount > 1) {
                            return "Bundle: {$record->transaction_ref} ({$bundleCount} tagihan)";
                        } else {
                            return "Pembayaran tertunda: {$record->transaction_ref}";
                        }
                    })
                    ->visible(fn (?Bill $record = null) => $record && $record->transaction_ref)
                    ->tooltip(function (?Bill $record = null) {
                        if (!$record || !$record->transaction_ref) return null;
                        
                        $bundledBills = Bill::where('transaction_ref', $record->transaction_ref)->get();
                        if ($bundledBills->count() > 1) {
                            return $bundledBills->map(function ($bill) {
                                $period = $bill->waterUsage?->billingPeriod?->period_name ?? 'Unknown';
                                return "Periode: {$period} - Rp " . number_format($bill->total_amount);
                            })->join('\n');
                        }
                        return null;
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('village')
                    ->label('Desa')
                    ->relationship('waterUsage.customer.village', 'name')
                    ->visible($isSuperAdmin),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'unpaid' => 'Belum Bayar',
                        'paid' => 'Sudah Bayar',
                    ])
                    ->searchable(),

                Tables\Filters\Filter::make('overdue')
                    ->label('Tagihan Terlambat')
                    ->query(fn($query) => $query->overdue()),

                Tables\Filters\Filter::make('date_range')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('Dari Tanggal'),
                        Forms\Components\DatePicker::make('until')
                            ->label('Sampai Tanggal'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn(Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn(Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    })
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),

                    // Edit only for super_admin and village_admin
                    Tables\Actions\EditAction::make()
                        ->visible(fn() => !$isCollector && !$isOperator),

                    // Print Receipt - available for all roles
                    Tables\Actions\Action::make('print_receipt')
                        ->label('Cetak Kwitansi')
                        ->icon('heroicon-o-printer')
                        ->color('primary')
                        ->url(fn(?Bill $record = null): string => $record ? route('bill.receipt', $record) : '#')
                        ->openUrlInNewTab()
                        ->tooltip('Cetak/Lihat kwitansi tagihan')
                        ->visible(fn(?Bill $record = null) => $record !== null),

                    // Mark as Paid - only for collectors and above
                    Tables\Actions\Action::make('mark_paid')
                        ->label('Tandai Lunas')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->visible(
                            fn(?Bill $record = null): bool =>
                            $record && $record->canBePaid() && ($isCollector || !$isOperator)
                        )
                        ->form([
                            Forms\Components\DatePicker::make('payment_date')
                                ->label('Tanggal Pembayaran')
                                ->default(now())
                                ->required(),
                            Forms\Components\TextInput::make('amount_paid')
                                ->label('Jumlah Dibayar')
                                ->numeric()
                                ->prefix('Rp')
                                ->required(),
                            Forms\Components\Select::make('payment_method')
                                ->label('Metode Pembayaran')
                                ->options([
                                    'cash' => 'Tunai',
                                    'transfer' => 'Transfer',
                                    'qris' => 'QRIS',
                                    'other' => 'Lainnya',
                                ])
                                ->searchable()
                                ->default('cash')
                                ->required(),
                        ])
                        ->action(function (?Bill $record, array $data) {
                            if ($record) {
                                $record->markAsPaid($data);
                            }
                        }),

                    // Create Bundle Payment - only for collectors and above
                    Tables\Actions\Action::make('create_bundle')
                        ->label('Buat Bundel')
                        ->icon('heroicon-o-banknotes')
                        ->color('warning')
                        ->visible(
                            fn(?Bill $record = null): bool =>
                            $record && $record->canBePaid() && ($isCollector || !$isOperator)
                        )
                        ->form([
                            Forms\Components\Section::make('Pilih Tagihan untuk Bundel')
                                ->description('Pilih tagihan lain dari pelanggan yang sama untuk digabung dalam satu pembayaran')
                                ->schema([
                                    Forms\Components\CheckboxList::make('selected_bills')
                                        ->label('Tagihan Tersedia')
                                        ->options(function (?Bill $record = null) {
                                            if (!$record) return [];
                                            
                                            $customerId = $record->waterUsage?->customer_id ?? $record->customer_id;
                                            if (!$customerId) return [];
                                            
                                            // Use same logic as frontend - get customer and available bills
                                            $customer = \App\Models\Customer::find($customerId);
                                            if (!$customer) return [];
                                            
                                            return \App\Http\Controllers\BundlePaymentController::getAvailableBillsForBundle($customer)
                                            ->where('bill_id', '!=', $record->bill_id) // Exclude current bill
                                            ->mapWithKeys(function ($bill) {
                                                $period = $bill->waterUsage?->billingPeriod?->period_name ?? 'Unknown';
                                                return [
                                                    $bill->bill_id => "Periode: {$period} - Rp " . number_format($bill->total_amount)
                                                ];
                                            });
                                        })
                                        ->required()
                                        ->columns(1),

                                    Forms\Components\Select::make('payment_method')
                                        ->label('Metode Pembayaran')
                                        ->options([
                                            'cash' => 'Tunai',
                                            'transfer' => 'Transfer Bank',
                                            'qris' => 'QRIS',
                                            'other' => 'Lainnya',
                                        ])
                                        ->default('cash')
                                        ->required(),

                                    Forms\Components\Textarea::make('notes')
                                        ->label('Catatan')
                                        ->placeholder('Catatan tambahan untuk bundel pembayaran (opsional)')
                                        ->maxLength(500),
                                ])
                        ])
                        ->action(function (?Bill $record, array $data) {
                            if (!$record) return;
                            
                            $selectedBills = collect($data['selected_bills']);
                            $selectedBills->push($record->bill_id);
                            
                            // Use same validation logic as frontend
                            $customer = $record->customer ?? $record->waterUsage?->customer;
                            if (!$customer) {
                                throw new \Exception('Customer not found for this bill');
                            }
                            
                            $billIds = array_merge([$record->bill_id], $selectedBills->toArray());
                            $controller = new \App\Http\Controllers\BundlePaymentController();
                            $bills = $controller->validateBillsForBundle($customer, $billIds);
                            
                            if ($bills->count() !== count($billIds)) {
                                throw new \Exception('Some bills are not valid for bundling');
                            }
                            
                            // Generate transaction reference and mark bills as bundle using bulk update
                            $transactionRef = 'ADM-' . strtoupper($customer->village->slug) . '-' . now()->format('YmdHis') . '-' . uniqid();
                            Bill::whereIn('bill_id', $bills->pluck('bill_id'))
                                ->update(['transaction_ref' => $transactionRef]);
                            
                            // Create payment record using Payment model
                            $payment = \App\Models\Payment::payBills($bills->pluck('bill_id')->toArray(), [
                                'payment_method' => $data['payment_method'],
                                'collector_id' => auth()->user()->isCollector() ? auth()->id() : null,
                                'notes' => $data['notes'] ?? 'Bundle payment created via admin panel',
                                'transaction_ref' => $transactionRef,
                            ]);
                            
                            \Filament\Notifications\Notification::make()
                                ->title('Bundle pembayaran berhasil dibuat')
                                ->body("Bundle {$transactionRef} dengan {$bills->count()} tagihan senilai Rp " . number_format($bills->sum('total_amount')))
                                ->success()
                                ->send();
                        }),
                ])
            ])
            ->headerActions([
                // Export actions only for admin roles
                ...($isCollector || $isOperator ? [] : static::getExportHeaderActions()),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    // Delete only for admin roles
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn() => !$isCollector && !$isOperator),

                    // Bulk Print - available for all roles
                    Tables\Actions\BulkAction::make('bulk_print')
                        ->label('Cetak Kwitansi Terpilih')
                        ->icon('heroicon-o-printer')
                        ->color('primary')
                        ->action(function ($records) {
                            $urls = $records->map(fn(Bill $bill) => route('bill.receipt', $bill))->toArray();
                            return redirect()->back()->with('openUrls', $urls);
                        })
                        ->deselectRecordsAfterCompletion(),

                    // Create Bundle Payment from selected bills - for collectors and above
                    Tables\Actions\BulkAction::make('create_bundle_bulk')
                        ->label('Buat Bundel dari Terpilih')
                        ->icon('heroicon-o-banknotes')
                        ->color('warning')
                        ->visible(fn() => $isCollector || !$isOperator)
                        ->requiresConfirmation()
                        ->modalHeading('Buat Bundel dari Tagihan Terpilih')
                        ->modalDescription('Tagihan harus dari pelanggan yang sama untuk dapat dibundel')
                        ->form([
                            Forms\Components\Select::make('payment_method')
                                ->label('Metode Pembayaran')
                                ->options([
                                    'cash' => 'Tunai',
                                    'transfer' => 'Transfer Bank',
                                    'qris' => 'QRIS',
                                    'other' => 'Lainnya',
                                ])
                                ->default('cash')
                                ->required(),
                            Forms\Components\Textarea::make('notes')
                                ->label('Catatan')
                                ->placeholder('Catatan tambahan untuk bundel pembayaran (opsional)')
                                ->maxLength(500),
                        ])
                        ->action(function ($records, array $data) {
                            // Group bills by customer
                            $billsByCustomer = $records->groupBy(function($bill) {
                                return $bill->waterUsage?->customer_id ?? $bill->customer_id;
                            });

                            $bundlesCreated = 0;
                            foreach ($billsByCustomer as $customerId => $bills) {
                                if ($bills->count() < 1) continue; // Skip empty groups
                                
                                try {
                                    $firstBill = $bills->first();
                                    $customer = $firstBill->customer ?? $firstBill->waterUsage?->customer;
                                    if (!$customer) continue;
                                    
                                    $billIds = $bills->pluck('bill_id')->toArray();
                                    
                                    // Use same validation logic as frontend
                                    $controller = new \App\Http\Controllers\BundlePaymentController();
                                    $validBills = $controller->validateBillsForBundle($customer, $billIds);
                                    
                                    if ($validBills->count() !== count($billIds)) continue; // Skip invalid bills
                                    
                                    // Generate transaction reference and mark bills as bundle using bulk update
                                    $transactionRef = 'BULK-' . strtoupper($customer->village->slug) . '-' . now()->format('YmdHis') . '-' . uniqid();
                                    Bill::whereIn('bill_id', $validBills->pluck('bill_id'))
                                        ->update(['transaction_ref' => $transactionRef]);
                                    
                                    // Create payment record using Payment model
                                    $payment = \App\Models\Payment::payBills($validBills->pluck('bill_id')->toArray(), [
                                        'payment_method' => $data['payment_method'],
                                        'collector_id' => auth()->user()->isCollector() ? auth()->id() : null,
                                        'notes' => $data['notes'] ?? 'Bundle payment created via bulk action',
                                        'transaction_ref' => $transactionRef,
                                    ]);
                                    
                                    $bundlesCreated++;
                                } catch (\Exception $e) {
                                    // Skip if bundle creation fails
                                    continue;
                                }
                            }
                            
                            if ($bundlesCreated > 0) {
                                \Filament\Notifications\Notification::make()
                                    ->title('Bundel berhasil dibuat')
                                    ->body("Berhasil membuat {$bundlesCreated} bundel pembayaran")
                                    ->success()
                                    ->send();
                            } else {
                                \Filament\Notifications\Notification::make()
                                    ->title('Tidak ada bundel yang dibuat')
                                    ->body('Pastikan memilih tagihan dari pelanggan yang sama')
                                    ->warning()
                                    ->send();
                            }
                        })
                        ->deselectRecordsAfterCompletion(),

                    // Export actions only for admin roles
                    ...($isCollector || $isOperator ? [] : static::getExportBulkActions()),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBills::route('/'),
            // Create removed - bills are generated from water usage only
            'edit' => Pages\EditBill::route('/{record}/edit'),
        ];
    }
}
