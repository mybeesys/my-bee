<?php

namespace App\Filament\Concerns;

use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Illuminate\Database\Eloquent\Model;
use League\Flysystem\UnableToCheckFileExistence;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Spatie\MediaLibrary\HasMedia;
use Throwable;

trait ConfiguresSpatieMediaUpload
{
    protected static function configureSpatieMediaUpload(SpatieMediaLibraryFileUpload $upload): SpatieMediaLibraryFileUpload
    {
        return $upload->saveUploadedFileUsing(function (
            SpatieMediaLibraryFileUpload $component,
            TemporaryUploadedFile $file,
            ?Model $record
        ): ?string {
            if (! $record instanceof HasMedia) {
                return null;
            }

            try {
                if (! $file->exists()) {
                    return null;
                }
            } catch (UnableToCheckFileExistence) {
                return null;
            }

            $path = $file->getRealPath();

            if (! is_string($path) || ! is_readable($path)) {
                throw new \RuntimeException('Temporary upload file is missing or unreadable.');
            }

            $filename = $component->getUploadedFileNameForStorage($file);

            $media = $record
                ->addMedia($path)
                ->usingFileName($filename)
                ->usingName(
                    $component->getMediaName($file)
                        ?? pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)
                )
                ->toMediaCollection(
                    $component->getCollection() ?? 'default',
                    $component->getDiskName()
                );

            return $media->getAttributeValue('uuid');
        });
    }
}
