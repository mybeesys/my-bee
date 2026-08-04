<?php

namespace App\Http\Resources;

use App\Models\Acc4;

class Acc4OtherPartyResource extends BaseResource
{
    public function toArray($request): array
    {
        /** @var Acc4 $acc4 */
        $acc4 = $this->resource;

        return $this->filterFields([
            'code' => $acc4->code,
            'name' => $acc4->name,
            'acc3Code' => $acc4->acc3_code,
            'editable' => (bool) $acc4->editable,
            'deletable' => (bool) $acc4->deletable,
            'canEdit' => $acc4->canBeEdited(),
            'canDelete' => $acc4->canBeDeleted(),
        ]);
    }
}
