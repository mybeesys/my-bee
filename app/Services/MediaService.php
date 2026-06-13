<?php

namespace App\Services;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use PhpParser\Node\Expr\AssignOp\Mod;

class MediaService
{
    public static function url($dir, $image)
    {
        return \Storage::disk('public')->url($dir . "/" . basename($image ?? ""));
    }

    public static function mediaUrls(Collection $media, $firstOnly = false)
    {
        $media = $media->filter(function ($item) {
            if (! $item) {
                return false;
            }

            try {
                return \Storage::disk($item->disk)->exists($item->getPathRelativeToRoot());
            } catch (\Throwable) {
                return false;
            }
        });

        if ($firstOnly) {
            return $media->first()?->getUrl();
        }

        $urls = [];
        foreach ($media as $item) {
            $urls[] = $item->getUrl();
        }

        return $urls;
    }

    public function exists($path, $disk = "public"): bool
    {
        return \Storage::disk($disk)->exists($path);

    }

    public function delete($path, $disk = "public"): bool
    {
        return \Storage::disk($disk)->delete($path);
    }

}
