<?php

namespace JoliMardi\FilamentFocalPoint;

use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\ViewField;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use LogicException;
use Spatie\MediaLibrary\Conversions\FileManipulator;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Upload d'une image (un seul fichier : header, vignette…) avec un point focal, la partie de l'image qui doit rester
 * visible quand elle est recadrée. Il se choisit en popup (action « Point focal » à droite du label), dès le dépôt de
 * l'image, et s'enregistre avec la fiche. Les conversions sont recadrées autour du point (FocalPoint::crop() dans le
 * modèle) et le front le garde visible (imgWithFocal(), asBackgroundWithFocal() du modèle Media).
 *
 *   MyFocalPoint::make(
 *       SpatieMediaLibraryFileUpload::make('header')->collection('header')->conversion('header')->image(),
 *       previews: ['Ordinateur' => 2.4, 'Mobile' => 0.7],
 *   )
 *
 * Renvoie un Group [upload, champ caché] : le point attend l'enregistrement dans le champ caché, à côté de l'upload (un
 * champ dans l'upload aurait son état dans la liste des fichiers, et l'upload Spatie n'est pas déshydraté).
 * À l'enregistrement :
 * - nouvelle image : le point est passé dans ses custom properties à sa création, avant le calcul des conversions ;
 * - image existante : le point est mis à jour après l'enregistrement de l'upload, et ses conversions sont refaites.
 */
class MyFocalPoint {

    /**
     * @param array<string, float> $previews cadres d'affichage de l'image sur le front, montrés en aperçu dans la
     *                                       popup : libellé => ratio largeur / hauteur
     */
    public static function make(SpatieMediaLibraryFileUpload $upload, array $previews = []): Group {
        if ($upload->isMultiple()) {
            throw new LogicException('MyFocalPoint : un seul fichier par upload (' . $upload->getName() . ').');
        }

        $statePath = $upload->getName() . '_focal_point';
        $collection = $upload->getCollection() ?? 'default';
        $saveUpload = self::saveRelationshipsCallback($upload);

        $upload
            ->customProperties(fn (TemporaryUploadedFile $file, Get $get): array => self::properties($get($statePath), $file->getRealPath()))
            ->saveRelationshipsUsing(function (SpatieMediaLibraryFileUpload $component, Get $get) use ($saveUpload, $statePath): void {
                $component->evaluate($saveUpload);
                self::saveOnMedia($component, $get($statePath));
            })
            // Image retirée ou nouvelle image déposée : le point de l'ancienne ne vaut plus. À l'enregistrement, le
            // fichier temporaire devient l'uuid du média : on garde le point.
            ->afterStateUpdated(function (SpatieMediaLibraryFileUpload $component, Set $set) use ($statePath): void {
                if (! is_string(Arr::first($component->getRawState() ?? []))) {
                    $set($statePath, null);
                }
            })
            ->hintAction(self::action($statePath, $previews));

        return Group::make([
            $upload,

            // Le point en attendant l'enregistrement ({x, y} ou null). Pas affiché, jamais enregistré sur le modèle.
            Hidden::make($statePath)
                ->hidden()
                ->dehydrated(false)
                ->afterStateHydrated(fn (Hidden $component, ?Model $record) => $component->state(
                    FocalPoint::of($record instanceof HasMedia ? $record->getFirstMedia($collection) : null)
                )),
        ]);
    }


    private static function action(string $statePath, array $previews): Action {
        $point = fn (Get $get): ?array => FocalPoint::normalize($get($statePath));

        return Action::make('focalPoint')
            ->label(fn (Get $get): string => ($p = $point($get)) ? "Point focal : {$p['x']} % / {$p['y']} %" : 'Point focal')
            ->icon(Heroicon::OutlinedViewfinderCircle)
            ->color(fn (Get $get): string => $point($get) ? 'primary' : 'gray')
            ->visible(fn (SpatieMediaLibraryFileUpload $component): bool => filled($component->getRawState()))
            ->modalHeading('Point focal')
            ->modalDescription("Cliquez sur la partie de l'image qui doit rester visible quand elle est recadrée, ou faites glisser le point. Le recadrage est appliqué à l'enregistrement de la fiche.")
            ->modalWidth(Width::FourExtraLarge)
            ->modalSubmitActionLabel('Valider')
            ->fillForm(fn (Get $get): array => ['focal_point' => $point($get)])
            ->schema(fn (SpatieMediaLibraryFileUpload $component): array => [
                ViewField::make('focal_point')
                    ->hiddenLabel()
                    ->view('filament-focal-point::picker')
                    ->viewData([
                        'imageUrl' => self::imageUrl($component),
                        'cropRatio' => self::cropRatio($component),
                        'previews' => $previews,
                    ]),
            ])
            ->action(fn (array $data, Set $set) => $set($statePath, FocalPoint::normalize($data['focal_point'] ?? null)));
    }


    /**
     * Enregistrement d'origine de l'upload (fichiers supprimés, nouveaux fichiers), que MyFocalPoint complète. Il est
     * lu dans une propriété protégée de Filament : si elle change, une erreur claire vaut mieux que des images perdues.
     */
    private static function saveRelationshipsCallback(SpatieMediaLibraryFileUpload $upload): Closure {
        $callback = (fn (): ?Closure => $this->saveRelationshipsUsing ?? null)->call($upload);

        return $callback ?? throw new LogicException(
            "MyFocalPoint : enregistrement d'origine de l'upload introuvable (version de Filament non prise en charge ?)."
        );
    }


    /** Custom properties d'une nouvelle image $path pour le point $point (rien sans point). */
    private static function properties(mixed $point, string $path): array {
        $point = FocalPoint::normalize($point);
        $size = $point ? FocalPoint::imageSize($path) : null;

        return $size ? [FocalPoint::PROPERTY => $point, FocalPoint::WIDTH => $size[0], FocalPoint::HEIGHT => $size[1]] : [];
    }


    /** Image déjà enregistrée : met à jour son point s'il a changé, et refait ses conversions. */
    private static function saveOnMedia(SpatieMediaLibraryFileUpload $upload, mixed $point): void {
        $media = self::media($upload);
        $point = FocalPoint::normalize($point);

        if (! $media || FocalPoint::of($media) === $point) {
            return;
        }

        $size = $point ? FocalPoint::imageSize($media->getPath()) : null;

        if ($size) {
            $media->setCustomProperty(FocalPoint::PROPERTY, $point)
                ->setCustomProperty(FocalPoint::WIDTH, $size[0])
                ->setCustomProperty(FocalPoint::HEIGHT, $size[1]);
        } else {
            $media->forgetCustomProperty(FocalPoint::PROPERTY);
        }

        $media->save();

        app(FileManipulator::class)->createDerivedFiles($media);
    }


    /** Le média de l'upload, s'il est déjà enregistré (l'état de l'upload contient alors son uuid). */
    private static function media(SpatieMediaLibraryFileUpload $upload): ?Media {
        $file = Arr::first($upload->getRawState() ?? []);
        $mediaClass = config('media-library.media_model');

        return is_string($file) ? $mediaClass::query()->where('uuid', $file)->first() : null;
    }


    /** Image d'origine à afficher dans la popup : le fichier qu'on vient de déposer, ou le média enregistré. */
    private static function imageUrl(SpatieMediaLibraryFileUpload $upload): ?string {
        $file = Arr::first($upload->getRawState() ?? []);

        if ($file instanceof TemporaryUploadedFile) {
            return $file->isPreviewable() ? $file->temporaryUrl() : null;
        }

        return self::media($upload)?->getUrl();
    }


    /** Ratio de la conversion de l'upload (celle du front), recadrée côté serveur, pour les aperçus. */
    private static function cropRatio(SpatieMediaLibraryFileUpload $upload): ?float {
        $conversion = $upload->getConversion();

        return $conversion ? FocalPoint::cropRatio($upload->getModel(), $upload->getCollection() ?? 'default', $conversion) : null;
    }
}
