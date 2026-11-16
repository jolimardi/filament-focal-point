<?php

namespace JoliMardi\FilamentFocalPoint;

use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\Conversions\Conversion;
use Spatie\MediaLibrary\Conversions\ConversionCollection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Point focal d'une image de la media library : la partie qui doit rester visible quand elle est recadrée.
 *
 * Il est rangé dans les custom properties du média (posé par MyFocalPoint dans Filament) :
 * - focal_point = {x, y} en % de l'original (entiers 0..100) ;
 * - width / height = dimensions de l'original (après rotation EXIF), pour les calculs ci-dessous.
 *
 * Les images sont recadrées deux fois :
 * - côté serveur, par les conversions : FocalPoint::crop() recadre autour du point ;
 * - côté CSS (object-fit / background-size: cover), dans des cadres dont le ratio change selon l'écran :
 *   FocalPoint::position() donne la position CSS du point dans la conversion, pour qu'il reste visible.
 * Sans point focal, rien ne change (recadrage centré, position CSS par défaut).
 */
class FocalPoint {

    public const PROPERTY = 'focal_point';
    public const WIDTH = 'width';
    public const HEIGHT = 'height';

    /** @var array<string, ?float> ratios des conversions recadrées, par type de modèle|collection|conversion */
    private static array $cropRatios = [];


    /** Point focal {x, y} en % du média, null s'il n'en a pas (= centre). */
    public static function of(?Media $media): ?array {
        return $media ? self::normalize($media->getCustomProperty(self::PROPERTY)) : null;
    }


    /**
     * Recadrage d'une conversion au format $width × $height, autour du point focal du média s'il en a un, sinon
     * centré (Fit::Crop). À utiliser dans registerMediaConversions() :
     *   FocalPoint::crop($this->addMediaConversion('header')->quality(65), 2048, 640, $media);
     */
    public static function crop(Conversion $conversion, int $width, int $height, ?Media $media): Conversion {
        $point = self::of($media);

        return $point
            ? $conversion->focalCropAndResize($width, $height, $point['x'], $point['y'])
            : $conversion->fit(Fit::Crop, $width, $height);
    }


    /**
     * Position CSS du point focal du média dans la conversion $conversion ('' = l'original), par exemple
     * "35% 42.5%", pour un object-position ou un background-position. Null sans point focal.
     */
    public static function position(Media $media, string $conversion = ''): ?string {
        $point = self::of($media);
        $width = (int) $media->getCustomProperty(self::WIDTH);
        $height = (int) $media->getCustomProperty(self::HEIGHT);

        if (! $point || ! $width || ! $height) {
            return null;
        }

        [$x, $y] = [$point['x'], $point['y']];

        if ($conversion !== '' && $ratio = self::cropRatio($media->model_type, $media->collection_name, $conversion)) {
            [$x, $y] = self::positionInCrop($point, $width, $height, $ratio);
        }

        return round($x, 1) . '% ' . round($y, 1) . '%';
    }


    /**
     * Ratio (largeur / hauteur) de la conversion $conversion des médias de la collection $collection du modèle
     * $modelType, si elle est recadrée (FocalPoint::crop() ou fit(Fit::Crop)), sinon null.
     * Lu dans les conversions déclarées par le modèle, comme le fait Spatie (instance vide du modèle, sans requête).
     */
    public static function cropRatio(string $modelType, string $collection, string $conversion): ?float {
        $key = "{$modelType}|{$collection}|{$conversion}";

        if (! array_key_exists($key, self::$cropRatios)) {
            $mediaClass = config('media-library.media_model');
            $media = (new $mediaClass)->forceFill([
                'model_type' => $modelType,
                'collection_name' => $collection,
                'manipulations' => [],
                'custom_properties' => [],
            ]);

            $manipulations = ConversionCollection::createForMedia($media)
                ->first(fn (Conversion $item) => $item->getName() === $conversion)
                ?->getManipulations();

            $focal = $manipulations?->getManipulationArgument('focalCropAndResize');
            $fit = $manipulations?->getManipulationArgument('fit');

            self::$cropRatios[$key] = match (true) {
                is_array($focal) => $focal[0] / $focal[1],
                is_array($fit) && ($fit[0] ?? null) === Fit::Crop => $fit[1] / $fit[2],
                default => null,
            };
        }

        return self::$cropRatios[$key];
    }


    /**
     * Position (en %) du point focal dans l'image recadrée au ratio $ratio autour de ce point : au centre, sauf quand
     * le recadrage bute sur un bord. Même calcul que spatie/image (CalculatesFocalCropAndResizeCoordinates), et que
     * les aperçus de la popup (resources/dist/filament-focal-point.js).
     *
     * @return array{0: float, 1: float}
     */
    public static function positionInCrop(array $point, int $width, int $height, float $ratio): array {
        if ($width / $height > $ratio) {
            $cropHeight = $height;
            $cropWidth = (int) round($height * $ratio);
        } else {
            $cropWidth = $width;
            $cropHeight = (int) round($width / $ratio);
        }

        $focalX = $width * $point['x'] / 100;
        $focalY = $height * $point['y'] / 100;

        $cropX = max(0, min((int) ($focalX - $cropWidth / 2), $width - $cropWidth));
        $cropY = max(0, min((int) ($focalY - $cropHeight / 2), $height - $cropHeight));

        return [($focalX - $cropX) / $cropWidth * 100, ($focalY - $cropY) / $cropHeight * 100];
    }


    /** {x, y} en entiers 0..100, ou null si $point n'est pas un point valide. */
    public static function normalize(mixed $point): ?array {
        if (! is_array($point) || ! is_numeric($point['x'] ?? null) || ! is_numeric($point['y'] ?? null)) {
            return null;
        }

        return [
            'x' => (int) round(max(0, min(100, (float) $point['x']))),
            'y' => (int) round(max(0, min(100, (float) $point['y']))),
        ];
    }


    /**
     * Dimensions [largeur, hauteur] de l'image $path telle qu'elle s'affiche (rotation EXIF appliquée, comme dans les
     * conversions et les navigateurs), null si ce n'est pas une image lisible.
     */
    public static function imageSize(string $path): ?array {
        $size = @getimagesize($path);

        if (! $size || ! $size[0] || ! $size[1]) {
            return null;
        }

        $orientation = function_exists('exif_read_data') ? (@exif_read_data($path)['Orientation'] ?? 1) : 1;

        return in_array($orientation, [5, 6, 7, 8]) ? [$size[1], $size[0]] : [$size[0], $size[1]];
    }
}
