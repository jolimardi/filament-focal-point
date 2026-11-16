# Filament Focal Point

Un point focal pour les images de la media library Spatie dans Filament : la partie de l'image qui doit rester visible
quand elle est recadrée (header, vignette…).

- 

## Prérequis

- PHP 8.2+, Laravel 11 à 13
- Filament 5 avec `filament/spatie-laravel-media-library-plugin`
- spatie/laravel-medialibrary 11, spatie/image 3.8.5+

## Installation

```bash
composer require jolimardi/filament-focal-point
php artisan filament:assets
```

`filament:assets` publie le composant Alpine de la popup et ses styles dans `public/` (il est aussi lancé par
`php artisan filament:upgrade`, souvent présent dans le `post-autoload-dump` du `composer.json`).

### config/media-library.php

```php
// Modèle Media avec les helpers du point focal (ou le trait HasFocalPoint dans votre propre modèle, voir plus bas)
'media_model' => JoliMardi\FilamentFocalPoint\Models\Media::class,

// Quand le point change, les conversions sont refaites sous le même nom de fichier : le ?v= évite que le navigateur
// garde l'ancien recadrage en cache
'version_urls' => true,
```

Si vous avez déjà votre propre modèle Media, ajoutez-lui le trait :

```php
use JoliMardi\FilamentFocalPoint\HasFocalPoint;

class Media extends \Spatie\MediaLibrary\MediaCollections\Models\Media {
    use HasFocalPoint;
}
```

## Utilisation

### 1. Conversions du modèle

Remplacez le `fit(Fit::Crop, …)` des conversions recadrées par `FocalPoint::crop()` :

```php
use JoliMardi\FilamentFocalPoint\FocalPoint;

public function registerMediaConversions(?Media $media = null): void {
    if ($media === null || $media->collection_name === 'header') {
        FocalPoint::crop($this->addMediaConversion('header')->quality(65), 2048, 640, $media);
    }
}
```

Sans point focal, `FocalPoint::crop()` fait exactement un `fit(Fit::Crop, 2048, 640)`.

### 2. Formulaire Filament

Enveloppez l'upload (un seul fichier) avec `MyFocalPoint::make()` :

```php
use JoliMardi\FilamentFocalPoint\MyFocalPoint;

MyFocalPoint::make(
    SpatieMediaLibraryFileUpload::make('header')
        ->label('Header')
        ->collection('header')
        ->conversion('header')
        ->image(),
    previews: ['Grand écran' => 3.1, 'Ordinateur' => 2.4, 'Mobile' => 0.7],
),
```

- `previews` (facultatif) : les cadres où l'image s'affiche sur le front, en `libellé => largeur / hauteur`. La popup
  montre l'image telle qu'elle y apparaîtra (conversion de l'upload, puis recadrage CSS). Mesurez-les sur le site, à
  différentes tailles d'écran.
- La conversion de l'upload (`->conversion('header')`) doit être celle utilisée sur le front.
- `MyFocalPoint::make()` renvoie un `Group` (l'upload et un champ caché qui garde le point jusqu'à l'enregistrement).
  Configurez l'upload avant de l'envelopper ; la mise en page (`columnSpan()`…) se règle sur le `Group`.

### 3. Front

```blade
{{-- <img> : comme img() de Spatie, avec l'object-position du point --}}
{{ $artist->getFirstMedia('header')->imgWithFocal('header', ['alt' => $artist->title]) }}

{{-- Image en fond : background-image + background-position du point --}}
<div class="thumbnail" style="{{ $artist->getFirstMedia('thumbnail')?->asBackgroundWithFocal('thumb') }}">
```

À combiner avec `object-fit: cover` (ou `background-size: cover`) dans le CSS : seul, un `object-position` ne fait rien.

Autres helpers du modèle Media :

| Méthode                                      | Résultat                                                                            |
| -------------------------------------------- | ----------------------------------------------------------------------------------- |
| `focalPoint()`                               | `['x' => 35, 'y' => 20]` (en % de l'original), ou `null`                            |
| `focalPosition('header')`                    | `"35% 42.5%"` : position du point dans la conversion (`''` = l'original), ou `null` |
| `focalStyle('header')`                       | `"object-position: 35% 42.5%;"`, ou `null`                                          |
| `focalStyle('thumb', 'background-position')` | `"background-position: 35% 42.5%;"`, ou `null`                                      |

## Stockage

Dans les `custom_properties` du média, sans migration :

```json
{ "focal_point": { "x": 35, "y": 20 }, "width": 4256, "height": 2832 }
```

`x` et `y` sont en % de l'image d'origine. `width` et `height` sont ses dimensions (rotation EXIF appliquée), utilisées
pour calculer la position du point dans les conversions.

À l'enregistrement de la fiche :

- nouvelle image : le point est passé dans ses custom properties à sa création, avant le calcul des conversions ;
- image existante : le point est mis à jour et seules ses conversions sont refaites.

Retirer l'image ou en déposer une autre remet le point à zéro.

## Limites

- Uploads d'un seul fichier (`SpatieMediaLibraryFileUpload` sans `->multiple()`).
- Le point est appliqué aux conversions recadrées avec `FocalPoint::crop()` ; les autres ne changent pas.
- `MyFocalPoint` complète l'enregistrement d'origine de l'upload Filament (lu dans une propriété protégée) : si une
  version de Filament le change, une exception claire est levée à la construction du formulaire.

## Utilisation

- Dans Filament, un lien « Point focal » sur l'upload ouvre une popup : on clique (ou on fait glisser le point) sur la
  partie à garder, avec des aperçus aux formats du front. Le point se choisit dès le dépôt de l'image et s'enregistre
  avec la fiche.
- Les conversions Spatie sont recadrées autour du point (au lieu du centre).
- Le front garde le point visible quand le CSS recadre encore l'image (`object-fit: cover`, `background-size: cover`),
  quel que soit le format du cadre (ordinateur, mobile…).

Sans point focal, rien ne change : recadrage centré, position CSS par défaut.

## Pourquoi deux recadrages ?

Une image est souvent recadrée deux fois : par la conversion (ex. header en 2048 × 640), puis par le CSS dans un cadre
dont le ratio change selon l'écran (une bande étroite sur mobile, par exemple). Le point focal agit aux deux niveaux :

1. la conversion est recadrée autour du point (`focalCropAndResize` de spatie/image) ;
2. le front reçoit la position du point **dans la conversion** (`object-position` / `background-position`), pour qu'il
   reste dans le cadre.
