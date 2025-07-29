<?php
// app/Providers/Filament/AdminPanelProvider.php - Tenant-aware version

namespace App\Providers\Filament;

use App\Http\Middleware\RequireSuperAdmin;
use App\Http\Middleware\SetVillageContext;
use App\Models\Village;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->brandName(function () {
                // Get subdomain
                $subdomain = request()->getHost();

                // Parse "pamdes-{village}" from subdomain
                $villageSlug = explode('.', preg_replace('/^pamdes-/', '', $subdomain))[0];
                // Find village by slug
                $village = Village::where('slug', $villageSlug)->first();

                if ($village) {
                    return 'PAMDes ' . $village->name . ' Admin';
                }

                return 'PAMDes Super Admin';
            })
            ->brandLogo(function () {
                // Get subdomain
                $subdomain = request()->getHost();

                // Parse "pamdes-{village}" from subdomain
                $villageSlug = explode('.', preg_replace('/^pamdes-/', '', $subdomain))[0];
                // Find village by slug
                $village = Village::where('slug', $villageSlug)->first();

                if ($village && $village->hasLogo()) {
                    return $village->getLogoUrl();
                }

                return asset('images/logo.png');
            })
            ->favicon(function () {
                // Get subdomain
                $subdomain = request()->getHost();

                // Parse "pamdes-{village}" from subdomain
                $villageSlug = explode('.', preg_replace('/^pamdes-/', '', $subdomain))[0];
                // Find village by slug
                $village = Village::where('slug', $villageSlug)->first();

                if ($village && $village->hasLogo()) {
                    return $village->getFaviconUrl();
                }

                return asset('favicon.ico');
            })
            ->colors([
                'primary' => Color::Blue,
                'gray' => Color::Slate,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,

                \App\Filament\Widgets\StatsOverview::class,
                \App\Filament\Widgets\OutstandingBillsWidget::class,
                \App\Filament\Widgets\RecentPaymentsWidget::class,
                \App\Filament\Widgets\TopWaterUsageWidget::class,
                \App\Filament\Widgets\MonthlyRevenueChartWidget::class,
            ])
            ->pages([
                Pages\Dashboard::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
