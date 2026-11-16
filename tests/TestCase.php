<?php

namespace JoliMardi\FilamentFocalPoint\Tests;

use Filament\Actions\ActionsServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Support\SupportServiceProvider;
use JoliMardi\FilamentFocalPoint\FilamentFocalPointServiceProvider;
use JoliMardi\FilamentFocalPoint\Models\Media;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Spatie\MediaLibrary\MediaLibraryServiceProvider;

abstract class TestCase extends Orchestra {

    protected function getPackageProviders($app): array {
        return [
            LivewireServiceProvider::class,
            SupportServiceProvider::class,
            ActionsServiceProvider::class,
            SchemasServiceProvider::class,
            FormsServiceProvider::class,
            MediaLibraryServiceProvider::class,
            FilamentFocalPointServiceProvider::class,
        ];
    }


    protected function defineEnvironment($app): void {
        $app['config']->set('media-library.media_model', Media::class);
        $app['config']->set('media-library.disk_name', 'public');
        $app['config']->set('media-library.version_urls', false);
    }


    /** Média (non enregistré) d'un Post de test, avec éventuellement un point focal sur une image 4256 × 2832. */
    protected function media(string $collection, ?array $point = null): Media {
        return (new Media)->forceFill([
            'id' => 12,
            'model_type' => Fixtures\Post::class,
            'model_id' => 1,
            'collection_name' => $collection,
            'name' => 'photo',
            'file_name' => 'photo.jpg',
            'mime_type' => 'image/jpeg',
            'disk' => 'public',
            'conversions_disk' => 'public',
            'size' => 1000,
            'manipulations' => [],
            'custom_properties' => $point ? ['focal_point' => $point, 'width' => 4256, 'height' => 2832] : [],
            'generated_conversions' => [],
            'responsive_images' => [],
        ]);
    }
}
