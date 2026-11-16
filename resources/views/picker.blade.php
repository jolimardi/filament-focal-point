{{-- Popup "Point focal" (JoliMardi\FilamentFocalPoint\MyFocalPoint) : clic ou glisser sur l'image, et aperçus des cadres
     du front. Composant Alpine : resources/dist/filament-focal-point.js, styles : resources/dist/filament-focal-point.css --}}
@php
    use Filament\Support\Facades\FilamentAsset;
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    @if ($imageUrl)
        <div
            class="focal-point"
            x-load
            x-load-src="{{ FilamentAsset::getAlpineComponentSrc('filament-focal-point', 'jolimardi/filament-focal-point') }}"
            x-data="focalPointPicker({ state: $wire.$entangle(@js($getStatePath())), cropRatio: @js($cropRatio) })"
            wire:ignore
            wire:key="focal-point-{{ md5($imageUrl) }}"
        >
            <div
                class="focal-point-surface"
                x-ref="surface"
                x-bind:style="surfaceStyle()"
                x-on:pointerdown.prevent="start($event)"
                x-on:pointermove="move($event)"
                x-on:pointerup="stop($event)"
                x-on:pointercancel="stop($event)"
            >
                <img src="{{ $imageUrl }}" alt="" draggable="false" x-on:load="loaded($event)">
                <span class="focal-point-marker" x-bind:class="{ 'is-unset': ! point }" x-bind:style="markerStyle()"></span>
            </div>

            <div class="focal-point-status" x-cloak>
                <span x-text="point ? `Point focal : ${point.x} % / ${point.y} %` : 'Pas de point focal : image recadrée au centre'"></span>
                <x-filament::link tag="button" type="button" size="sm" x-show="point" x-cloak x-on:click="reset()">
                    Retirer le point
                </x-filament::link>
            </div>

            @if ($previews)
                <div class="focal-point-previews" x-show="size" x-cloak>
                    @foreach ($previews as $label => $ratio)
                        <figure>
                            <div
                                class="focal-point-preview"
                                style="aspect-ratio: {{ $ratio }}; background-image: url('{{ $imageUrl }}');"
                                x-bind:style="previewStyle(@js($ratio))"
                            ></div>
                            <figcaption>{{ $label }}</figcaption>
                        </figure>
                    @endforeach
                </div>
            @endif
        </div>
    @else
        <p class="focal-point-status">Aperçu de l'image indisponible.</p>
    @endif
</x-dynamic-component>
