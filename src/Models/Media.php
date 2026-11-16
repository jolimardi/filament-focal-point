<?php

namespace JoliMardi\FilamentFocalPoint\Models;

use JoliMardi\FilamentFocalPoint\HasFocalPoint;
use Spatie\MediaLibrary\MediaCollections\Models\Media as BaseMedia;

/**
 * Modèle Media de Spatie avec les helpers du point focal (imgWithFocal(), asBackgroundWithFocal()…).
 * À déclarer dans config/media-library.php : 'media_model' => JoliMardi\FilamentFocalPoint\Models\Media::class
 */
class Media extends BaseMedia {
    use HasFocalPoint;
}
