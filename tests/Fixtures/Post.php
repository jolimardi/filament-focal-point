<?php

namespace JoliMardi\FilamentFocalPoint\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use JoliMardi\FilamentFocalPoint\FocalPoint;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Post extends Model implements HasMedia {
    use InteractsWithMedia;

    public function registerMediaCollections(): void {
        $this->addMediaCollection('header')->singleFile();
        $this->addMediaCollection('thumbnail')->singleFile();
    }


    public function registerMediaConversions(?Media $media = null): void {
        if ($media === null || $media->collection_name === 'header') {
            FocalPoint::crop($this->addMediaConversion('header')->quality(65), 2048, 640, $media);
        }

        if ($media === null || $media->collection_name === 'thumbnail') {
            $this->addMediaConversion('thumb')->fit(Fit::Crop, 464, 256);
            $this->addMediaConversion('contain')->fit(Fit::Contain, 300, 300);
        }
    }
}
