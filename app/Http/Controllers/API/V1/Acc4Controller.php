<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\API\BaseController;
use App\Http\Requests\StoreAcc4OtherPartyRequest;
use App\Http\Requests\StoreAcc4Request;
use App\Http\Requests\UpdateAcc4OtherPartyRequest;
use App\Http\Requests\UpdateAcc4Request;
use App\Http\Resources\Acc4OtherPartyResource;
use App\Http\Resources\Acc4Resource;
use App\Models\Acc4;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class Acc4Controller extends BaseController
{
    public function index(Request $request)
    {
        $scope = $request->query('scope');

        $query = Acc4::query()->with(['acc3.acc2.acc1']);

        if ($scope === 'other_parties') {
            $query->userCreatedOtherPartyAccounts()->orderBy('name');

            return $this->responder(
                __('messages.api.retrieved'),
                200,
                Acc4OtherPartyResource::collection($query->get())
            )->respond();
        }

        $data = $query->excludeInventoryItems()->get();

        return $this->responder(__('messages.api.retrieved'), 200, Acc4Resource::collection($data))->respond();
    }

    public function otherPartiesIndex()
    {
        $data = Acc4::query()
            ->userCreatedOtherPartyAccounts()
            ->orderBy('name')
            ->get();

        return $this->responder(
            __('messages.api.retrieved'),
            200,
            Acc4OtherPartyResource::collection($data)
        )->respond();
    }

    public function otherPartiesStore(StoreAcc4OtherPartyRequest $request)
    {
        $item = Acc4::create([
            'tenant_id' => $this->getTenant()->id,
            'acc3_code' => '1217',
            'code' => Acc4::nextCodeForAcc3('1217'),
            'name' => $request->validated('name'),
            'editable' => true,
            'deletable' => true,
        ]);

        return $this->responder(
            __('messages.api.created'),
            201,
            new Acc4OtherPartyResource($item)
        )->respond();
    }

    public function otherPartiesUpdate(UpdateAcc4OtherPartyRequest $request, string $code)
    {
        $item = Acc4::query()
            ->userCreatedOtherPartyAccounts()
            ->where('code', $code)
            ->firstOrFail();

        if (! $item->canBeEdited()) {
            throw ValidationException::withMessages([
                'code' => __('fields.record_in_use_alert'),
            ]);
        }

        $item->update($request->only(['name']));

        return $this->responder(
            __('messages.api.updated'),
            200,
            new Acc4OtherPartyResource($item->fresh())
        )->respond();
    }

    public function otherPartiesDestroy(string $code)
    {
        $item = Acc4::query()
            ->userCreatedOtherPartyAccounts()
            ->where('code', $code)
            ->firstOrFail();

        if (! $item->canBeDeleted()) {
            return $this->responder(__('fields.record_in_use_alert'), 400)->respond();
        }

        $item->delete();

        return $this->responder(__('messages.api.deleted'), 200, [])->respond();
    }

    public function accountOptionsCollection()
    {
        return $this->responder(
            __('messages.api.retrieved'),
            200,
            Acc4::collectionAccountOptions()
        )->respond();
    }

    public function accountOptionsOtherParties()
    {
        return $this->responder(
            __('messages.api.retrieved'),
            200,
            Acc4::userCreatedOtherPartyAccountOptions()
        )->respond();
    }

    public function accountOptionsVoucherPayments()
    {
        return $this->responder(
            __('messages.api.retrieved'),
            200,
            Acc4::voucherOtherEntityPaymentAccountOptions()
        )->respond();
    }

    public function accountOptionsStatement()
    {
        return $this->responder(
            __('messages.api.retrieved'),
            200,
            Acc4::ledgerAccountOptions()
        )->respond();
    }

    public function store(StoreAcc4Request $request)
    {
        $data = $request->validated();
        $data['tenant_id'] = $this->getTenant()->id;

        $item = Acc4::create($data);
        $item->load(['acc3.acc2.acc1']);

        return $this->responder(__('messages.api.created'), 201, new Acc4Resource($item))->respond();
    }

    public function show(string $code)
    {
        $item = Acc4::with(['acc3.acc2.acc1'])->where('code', $code)->firstOrFail();

        return $this->responder(__('messages.api.retrieved'), 200, new Acc4Resource($item))->respond();
    }

    public function update(UpdateAcc4Request $request, string $code)
    {
        $item = Acc4::with(['acc3.acc2.acc1'])->where('code', $code)->firstOrFail();

        if ($item->isOtherPartyAccount() && ! $item->canBeEdited()) {
            throw ValidationException::withMessages([
                'code' => __('fields.record_in_use_alert'),
            ]);
        }

        $item->update($request->only(['name']));

        return $this->responder(__('messages.api.updated'), 200, new Acc4Resource($item))->respond();
    }

    public function destroy(string $code)
    {
        $item = Acc4::query()
            ->userCreatedOtherPartyAccounts()
            ->where('code', $code)
            ->firstOrFail();

        if (! $item->canBeDeleted()) {
            return $this->responder(__('fields.record_in_use_alert'), 400)->respond();
        }

        $item->delete();

        return $this->responder(__('messages.api.deleted'), 200, [])->respond();
    }
}
