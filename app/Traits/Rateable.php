<?php

    namespace App\Traits;

    use App\Models\Rating;

    trait Rateable
    {
        /**
         * This model has many ratings.
         *
         * @param mixed $rating
         * @param mixed $value
         * @param string $comment
         *
         * @return Rating
         */
        public function rate($value, $comment = null)
        {
            $rating = new Rating();
            $rating->rating = $value;
            $rating->comment = $comment;
            $rating->user_id = auth()->id();

            $this->ratings()->save($rating);
            return $rating;
        }

        public function rateOnce($value, $comment = null)
        {
            $rating = Rating::query()
                ->where('rateable_type', '=', $this->getMorphClass())
                ->where('rateable_id', '=', $this->id)
                ->where('user_id', '=', auth()->id())
                ->first();

            if ($rating) {
                $rating->rating = $value;
                $rating->comment = $comment;
                $rating->save();
            } else {
                $rating = $this->rate($value, $comment);
            }

            return $rating;
        }

        public function ratings()
        {
            return $this->morphMany(Rating::class, 'rateable');
        }

        public function averageRating()
        {
            return $this->ratings->avg('rating');
        }

        public function sumRating()
        {
            return $this->ratings->sum('rating');
        }

        public function timesRated()
        {
            return $this->ratings->count();
        }

        public function usersRated()
        {
            return $this->ratings->groupBy('user_id')->pluck('user_id')->count();
        }

        public function userAverageRating()
        {
            return $this->ratings->where('user_id', auth()->id())->avg('rating');
        }

        public function userSumRating()
        {
            return $this->ratings->where('user_id', auth()->id())->sum('rating');
        }

        public function ratingPercent($max = 5)
        {
            $quantity = $this->ratings->count();
            $total = $this->sumRating();

            return ($quantity * $max) > 0 ? $total / (($quantity * $max) / 100) : 0;
        }

        // Getters

        public function getAverageRatingAttribute()
        {
            return $this->averageRating();
        }

        public function getSumRatingAttribute()
        {
            return $this->sumRating();
        }

        public function getUserAverageRatingAttribute()
        {
            return $this->userAverageRating();
        }

        public function getUserSumRatingAttribute()
        {
            return $this->userSumRating();
        }

        public function itemInfo()
        {
            return $this;
        }
    }
