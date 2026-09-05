<?php

namespace App\Http\Controllers\Web\Kitchen;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Kitchen\ListKitchenTicketsRequest;
use App\Http\Requests\Web\Kitchen\UpdateKitchenTicketItemStatusRequest;
use App\Http\Resources\Web\Kitchen\KitchenTicketResource;
use App\Models\Tenant\KitchenSection;
use App\Models\Tenant\KitchenTicket;
use App\Models\Tenant\KitchenTicketItem;
use App\Services\Tenant\Kitchen\KitchenProductionService;
use App\Support\ApiMessages;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class KitchenTicketController extends Controller
{
    public function __construct(private readonly KitchenProductionService $production) {}

    public function index(ListKitchenTicketsRequest $request): JsonResponse
    {
        $branch = $this->currentBranch();
        $this->authorize('viewAny', [KitchenTicket::class, $branch]);
        $data = $request->validated();
        $sectionId = isset($data['kitchen_section_uuid']) ? KitchenSection::query()->where('branch_id', $branch->id)->where('uuid', $data['kitchen_section_uuid'])->value('id') : null;
        $tickets = KitchenTicket::query()->where('branch_id', $branch->id)->with(['kitchenSection:id,uuid,name', 'order:id,uuid,order_number,status'])
            ->when(isset($data['kitchen_section_uuid']), fn ($query) => $query->where('kitchen_section_id', $sectionId ?? 0))
            ->when(isset($data['status']), fn ($query) => $query->where('status', $data['status']))
            ->when(isset($data['search']), fn ($query) => $query->where('ticket_number', 'like', trim($data['search']).'%'))
            ->orderByDesc('opened_at')->simplePaginate($this->perPage($request));

        return ApiResponse::resource(KitchenTicketResource::collection($tickets), ApiMessages::listRetrieved('kitchen tickets'));
    }

    public function show(KitchenTicket $kitchenTicket): JsonResponse
    {
        $branch = $this->currentBranch();
        $this->authorize('view', [$kitchenTicket, $branch]);

        return ApiResponse::resource(new KitchenTicketResource($kitchenTicket->load(['kitchenSection:id,uuid,name', 'order:id,uuid,order_number,status', 'items.orderItem:id,uuid'])), ApiMessages::detailsRetrieved('kitchen ticket'));
    }

    public function start(UpdateKitchenTicketItemStatusRequest $request, KitchenTicket $kitchenTicket, KitchenTicketItem $kitchenTicketItem): JsonResponse
    {
        return $this->update($request, $kitchenTicket, $kitchenTicketItem, 'start');
    }

    public function ready(UpdateKitchenTicketItemStatusRequest $request, KitchenTicket $kitchenTicket, KitchenTicketItem $kitchenTicketItem): JsonResponse
    {
        return $this->update($request, $kitchenTicket, $kitchenTicketItem, 'ready');
    }

    private function update(UpdateKitchenTicketItemStatusRequest $request, KitchenTicket $ticket, KitchenTicketItem $item, string $action): JsonResponse
    {
        $branch = $this->currentBranch();
        $this->authorize('update', [$ticket, $branch]);
        $actor = $request->user('tenant_api')->tenantUser()->firstOrFail();
        $ticket = $action === 'start' ? $this->production->start($ticket, $item, $branch, $actor) : $this->production->ready($ticket, $item, $branch, $actor);

        return ApiResponse::resource(new KitchenTicketResource($ticket), ApiMessages::updated('kitchen ticket item'));
    }
}
