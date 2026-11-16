<?php

namespace JoliMardi\FilamentFocalPoint\Tests;

use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Schemas\Components\Group;
use JoliMardi\FilamentFocalPoint\FocalPoint;
use JoliMardi\FilamentFocalPoint\MyFocalPoint;
use JoliMardi\FilamentFocalPoint\Tests\Fixtures\Post;
use LogicException;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\Conversions\ConversionCollection;

class FocalPointTest extends TestCase {

    public function test_normalize_keeps_integer_percentages_between_0_and_100(): void {
        $this->assertSame(['x' => 35, 'y' => 0], FocalPoint::normalize(['x' => '34.6', 'y' => -12]));
        $this->assertSame(['x' => 100, 'y' => 50], FocalPoint::normalize(['x' => 140, 'y' => 50]));
        $this->assertNull(FocalPoint::normalize(null));
        $this->assertNull(FocalPoint::normalize(['x' => 10]));
        $this->assertNull(FocalPoint::normalize(['x' => 'a', 'y' => 10]));
    }


    public function test_position_in_crop_matches_spatie_focal_crop(): void {
        // 3000 × 2000 recadré en 3,2:1 → 3000 × 938 ; le point (20 %, 15 %) fait buter le recadrage en haut
        [$x, $y] = FocalPoint::positionInCrop(['x' => 20, 'y' => 15], 3000, 2000, 3.2);
        $this->assertSame(20.0, round($x, 2));
        $this->assertSame(31.98, round($y, 2));

        // Point au centre : il reste au centre
        $this->assertSame([50.0, 50.0], FocalPoint::positionInCrop(['x' => 50, 'y' => 50], 3000, 2000, 3.2));

        // Image en hauteur : toute la largeur est gardée
        $this->assertSame([80.0, 50.0], array_map(fn ($v) => round($v, 2), FocalPoint::positionInCrop(['x' => 80, 'y' => 50], 1000, 2000, 464 / 256)));
    }


    public function test_crop_ratio_is_read_from_the_model_conversions(): void {
        $this->assertSame(3.2, FocalPoint::cropRatio(Post::class, 'header', 'header'));
        $this->assertSame(464 / 256, FocalPoint::cropRatio(Post::class, 'thumbnail', 'thumb'));
        $this->assertNull(FocalPoint::cropRatio(Post::class, 'thumbnail', 'contain'));
        $this->assertNull(FocalPoint::cropRatio(Post::class, 'thumbnail', 'unknown'));
    }


    public function test_crop_uses_the_focal_point_when_there_is_one(): void {
        $withPoint = ConversionCollection::createForMedia($this->media('header', ['x' => 74, 'y' => 27]))->getByName('header');
        $this->assertSame([2048, 640, 74, 27], $withPoint->getManipulations()->getManipulationArgument('focalCropAndResize'));

        $withoutPoint = ConversionCollection::createForMedia($this->media('header'))->getByName('header');
        $this->assertNull($withoutPoint->getManipulations()->getManipulationArgument('focalCropAndResize'));
        $this->assertSame([Fit::Crop, 2048, 640], $withoutPoint->getManipulations()->getManipulationArgument('fit'));
    }


    public function test_focal_position_in_conversion_and_original(): void {
        // 4256 × 2832 recadré en 3,2:1 → 4256 × 1330 ; le point (74 %, 27 %) se retrouve au milieu de la hauteur
        $media = $this->media('header', ['x' => 74, 'y' => 27]);

        $this->assertSame('74% 50%', $media->focalPosition('header'));
        $this->assertSame('74% 27%', $media->focalPosition());
        $this->assertSame('object-position: 74% 50%;', $media->focalStyle('header'));
        $this->assertSame('background-position: 74% 50%;', $media->focalStyle('header', 'background-position'));

        $this->assertNull($this->media('header')->focalPosition('header'));
        $this->assertNull($this->media('header')->focalStyle('header'));
    }


    public function test_img_with_focal_adds_the_object_position(): void {
        $attributes = fn ($html): array => (fn () => $this->extraAttributes)->call($html);
        $media = $this->media('header', ['x' => 74, 'y' => 27]);

        $this->assertSame(['alt' => 'Titre', 'style' => 'object-position: 74% 50%;'], $attributes($media->imgWithFocal('header', ['alt' => 'Titre'])));
        $this->assertSame('opacity: .5; object-position: 74% 50%;', $attributes($media->imgWithFocal('header', ['style' => 'opacity: .5;']))['style']);
        $this->assertSame(['alt' => 'Titre'], $attributes($this->media('header')->imgWithFocal('header', ['alt' => 'Titre'])));
    }


    public function test_as_background_with_focal(): void {
        $style = $this->media('header', ['x' => 74, 'y' => 27])->asBackgroundWithFocal('header');

        $this->assertStringStartsWith("background-image: url('", $style);
        $this->assertStringContainsString('/12/conversions/photo-header.jpg', $style);
        $this->assertStringEndsWith("'); background-position: 74% 50%;", $style);

        $this->assertStringEndsWith("/12/photo.jpg');", $this->media('header')->asBackgroundWithFocal());
    }


    public function test_my_focal_point_wraps_the_upload_with_its_hidden_state(): void {
        $group = MyFocalPoint::make(SpatieMediaLibraryFileUpload::make('header')->collection('header')->image());

        $components = $group->getDefaultChildComponents();
        $this->assertInstanceOf(Group::class, $group);
        $this->assertInstanceOf(SpatieMediaLibraryFileUpload::class, $components[0]);
        $this->assertInstanceOf(Hidden::class, $components[1]);
        $this->assertSame('header_focal_point', $components[1]->getName());
    }


    public function test_my_focal_point_refuses_multiple_uploads(): void {
        $this->expectException(LogicException::class);

        MyFocalPoint::make(SpatieMediaLibraryFileUpload::make('photos')->multiple());
    }
}
