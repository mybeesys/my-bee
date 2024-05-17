<?php

namespace App\Traits;

trait HideResourceFieldsFromRequest
{
    protected $withoutFields = [];
    protected $onlyFields = [];

    /**
     * Set the keys that are supposed to be filtered out.
     *
     * @param array $fields
     * @return $this
     */
    public function hide(array $fields)
    {
        $this->withoutFields = $fields;

        return $this;
    }

    /**
     * Set the keys.
     *
     * @param array $fields
     * @return $this
     */
    public function only(array $fields)
    {
        $this->onlyFields = $fields;

        return $this;
    }

    /**
     * Remove the filtered keys.
     *
     * @param $array
     * @return array
     */
    protected function filterFields($array): array
    {
        return collect($array)->forget(array_merge($this->withoutFields, request()->input('hide', [])))->toArray();
    }
}
