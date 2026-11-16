<?php

namespace JoliMardi\FilamentFocalPoint;

use Spatie\MediaLibrary\MediaCollections\HtmlableMedia;

/**
 * Point focal sur le modèle Media de la media library (config media-library.media_model) : helpers pour le front.
 * Déjà utilisé par JoliMardi\FilamentFocalPoint\Models\Media ; à ajouter à votre propre modèle Media si vous en avez un.
 *
 *   {{ $artist->getFirstMedia('header')->imgWithFocal('header', ['alt' => $artist->title]) }}
 *   <div class="thumbnail" style="{{ $artist->getFirstMedia('thumbnail')?->asBackgroundWithFocal('thumb') }}">
 *
 * @mixin \Spatie\MediaLibrary\MediaCollections\Models\Media
 */
trait HasFocalPoint {

    /** Point focal {x, y} en %, null s'il n'est pas défini (= centre). */
    public function focalPoint(): ?array {
        return FocalPoint::of($this);
    }


    /** Position CSS du point focal dans la conversion ("35% 42.5%"), null sans point focal. */
    public function focalPosition(string $conversionName = ''): ?string {
        return FocalPoint::position($this, $conversionName);
    }


    /** Déclaration CSS du point focal ("object-position: 35% 42.5%;"), null sans point focal. */
    public function focalStyle(string $conversionName = '', string $property = 'object-position'): ?string {
        $position = $this->focalPosition($conversionName);

        return $position ? "{$property}: {$position};" : null;
    }


    /**
     * Comme img() de Spatie, avec en plus l'object-position du point focal (à combiner avec un object-fit: cover dans
     * le CSS). Le style passé dans $extraAttributes est gardé ; sans point focal, c'est exactement img().
     */
    public function imgWithFocal(string $conversionName = '', array $extraAttributes = []): HtmlableMedia {
        if ($position = $this->focalPosition($conversionName)) {
            $extraAttributes['style'] = is_array($extraAttributes['style'] ?? null)
                ? [...$extraAttributes['style'], "object-position: {$position}"]
                : trim(($extraAttributes['style'] ?? '') . " object-position: {$position};");
        }

        return $this->img($conversionName, $extraAttributes);
    }


    /**
     * Styles d'une image en fond (background-size: cover dans le CSS) :
     * "background-image: url('…'); background-position: 35% 42.5%;" (sans background-position s'il n'y a pas de point).
     */
    public function asBackgroundWithFocal(string $conversionName = ''): string {
        $url = str_replace("'", '%27', $this->getUrl($conversionName));
        $style = "background-image: url('{$url}');";

        if ($position = $this->focalPosition($conversionName)) {
            $style .= " background-position: {$position};";
        }

        return $style;
    }
}
