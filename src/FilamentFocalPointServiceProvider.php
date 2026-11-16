<?php

namespace JoliMardi\FilamentFocalPoint;

use Filament\Support\Assets\AlpineComponent;
use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FilamentFocalPointServiceProvider extends PackageServiceProvider {

    public static string $name = 'filament-focal-point';


    // Vue de la popup : filament-focal-point::picker
    public function configurePackage(Package $package): void {
        $package->name(static::$name)->hasViews();
    }


    // Assets publiés dans public/ par php artisan filament:assets (lancé par filament:upgrade après composer update) :
    // - le composant Alpine de la popup, chargé seulement quand elle s'ouvre (x-load) ;
    // - ses styles, chargés dans tout le panel (quelques lignes).
    public function packageBooted(): void {
        FilamentAsset::register([
            AlpineComponent::make('filament-focal-point', __DIR__ . '/../resources/dist/filament-focal-point.js'),
            Css::make('filament-focal-point', __DIR__ . '/../resources/dist/filament-focal-point.css'),
        ], 'jolimardi/filament-focal-point');
    }
}
