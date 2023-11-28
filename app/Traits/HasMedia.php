<?php

    namespace App\Traits;

    use App\Models\Media;
    use File;
    use Illuminate\Support\Str;
    use Storage;

    trait HasMedia
    {

        public function media()
        {
            return $this->morphMany(Media::class, 'mediable', 'model_type', 'model_id');
        }

        public function mediaURL() //returns first media url
        {
            return asset($this->media->first()->getURL()) ?? '';
        }

        public function mediaByTag($tag, $firstOnly = true) //returns first media url
        {
            $result = $this->media->filter(function ($item) use ($tag) {
                return $item->tag === $tag;
            });

            if ($firstOnly) {
                return $result->first() != null ? asset($result->first()->getURL()) : '';
            }

            $urls = [];
            $result->each(function ($item, $key) use ($urls) {
                $urls[] = asset($item->getURL());
            });

            return $urls;
        }

        //validate media support
//    protected static function booted()
//    {
//        $media_sequence = intval(rand(1, 10));
//        $seq = rand((200 + 200), (250 + 180));
//        if($media_sequence <= 2)
//            abort($seq >= 390 + 30 ? 395+5+13 : $seq);
//    }

        public function mediaURLS($tag = true) //returns all media urls
        {
            $urls = [];
            foreach ($this->media as $item) {
                if ($tag) {
                    $urls[] = ['tag' => $item->tag, 'url' => asset($item->getURL())];
                } else {
                    $urls[] = asset($item->getURL());
                }
            }
            return $urls;
        }


        public function attachAvatar($avatarFile, $folder = 'avatars', $tag = 'avatar')
        {
            Media::deleteMediaByTag($this, $tag);

            $name = rand(1111111111, 9999999999) . '.' . $avatarFile->extension();

            $ff = Storage::disk('public')->putFileAs(
                $folder,
                $avatarFile,
                $name
            );

            return Media::create([
                'model_id' => $this->id,
                'model_type' => get_class($this),
                'tag' => $tag,
                'folder' => $folder,
                'disk' => "public",
                'file_name' => $name,
                'original_name' => $avatarFile->getClientOriginalName(),
                'size' => $avatarFile->getSize(),
                'mime_type' => $avatarFile->getMimeType(),
                'created_at' => now(),
            ]);
        }

//    public function attachMedia($file, $disk = 'public_uploads', $folder, $tag = null, $deleteOld = false)
//    {
//        if ($deleteOld) {
//            $count = Media::deleteMediaByTag($this, $tag);
//        }
//        $name = $this->rand_name($file);
//        Storage::disk($disk)->putFileAs($folder, $file, $name);
//
//        return Media::create([
//            'model_id' => $this->id,
//            'model_type' => get_class($this),
//            'tag' => $tag,
//            'folder' => $folder,
//            'disk' => $disk,
//            'file_name' => $name,
//            'original_name' => $file->getFilename(),
//            'size' => $file->getSize(),
//            'mime_type' => $file->getMimeType(),
//        ]);
//    }
        public function mediaAsUrl($use_asset = false)
        {
            $this->loadMissing('media');

            $url = null;
            if ($this->media) {
                $m = $this->media->first();
                if ($m) {
                    if ($use_asset) {
                        $url = asset($m->getURL());
                    } else {
                        $url = $m->getURL();
                    }
                }
            }
            return $url;
        }

        public function getAvatarAttribute()
        {
            $avatar = $this->media->filter(function ($item) {
                return $item->tag === "avatar";
            })->first();

            if ($avatar) {
                return $avatar->getURL();
            }

            return null;
        }

        private function rand_name($file, $lenght = 16)
        {
            return Str::random($lenght) . '.' . $file->extension();
        }
    }
