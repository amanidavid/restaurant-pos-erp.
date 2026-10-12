<?php

namespace App\Http\Controllers\Web\Reservations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Reservations\ChangeReservationStatusRequest;
use App\Http\Requests\Web\Reservations\IndexReservationsRequest;
use App\Http\Requests\Web\Reservations\SeatReservationRequest;
use App\Http\Requests\Web\Reservations\StoreReservationRequest;
use App\Http\Resources\Web\Reservations\ReservationResource;
use App\Models\Tenant\Reservation;
use App\Services\Tenant\Reservations\ReservationService;
use App\Support\ApiMessages;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class ReservationController extends Controller
{
    public function __construct(private readonly ReservationService $service) {}

    public function index(IndexReservationsRequest $request): JsonResponse
    {
        $branch = $this->currentBranch();
        $this->authorize('viewAny', [Reservation::class, $branch]);

        $query = Reservation::query()
            ->where('branch_id', $branch->id)
            ->with([
                'reservationTables.diningTable:id,uuid,name,capacity',
                'payments.paymentMethod:id,uuid,name,method_type',
                'orders:id,uuid,reservation_id,order_number,status,total_amount,paid_amount',
            ])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('search'), fn ($query) => $query->where(
                'reservation_number',
                'like',
                trim($request->string('search')->toString()).'%'
            ));

        $this->applyDateFilter($query, $request);

        return ApiResponse::resource(
            ReservationResource::collection(
                $query->orderBy('reserved_from')->orderBy('id')->paginate($this->perPage($request))
            ),
            ApiMessages::listRetrieved('reservations')
        );
    }

    public function store(StoreReservationRequest $request): JsonResponse
    {
        $branch = $this->currentBranch();
        $this->authorize('create', [Reservation::class, $branch]);

        return ApiResponse::resource(
            new ReservationResource($this->service->create(
                $branch,
                $request->user('tenant_api')->tenantUser()->firstOrFail(),
                $request->validated(),
            )),
            ApiMessages::created('reservation'),
            201,
        );
    }

    public function show(Reservation $reservation): JsonResponse
    {
        $branch = $this->currentBranch();
        $this->authorize('view', [$reservation, $branch]);

        return ApiResponse::resource(
            new ReservationResource($reservation->load([
                'customer',
                'reservationTables.diningTable',
                'payments.paymentMethod',
                'payments.refunds',
                'orders',
            ])),
            ApiMessages::detailsRetrieved('reservation'),
        );
    }

    public function seat(SeatReservationRequest $request, Reservation $reservation): JsonResponse
    {
        $branch = $this->currentBranch();
        $this->authorize('seat', [$reservation, $branch]);

        return ApiResponse::resource(
            new ReservationResource($this->service->seat(
                $reservation,
                $branch,
                $request->user('tenant_api')->tenantUser()->firstOrFail(),
                $request->validated(),
            )),
            ApiMessages::updated('reservation seated'),
        );
    }

    public function complete(ChangeReservationStatusRequest $request, Reservation $reservation): JsonResponse
    {
        return $this->change($request, $reservation, 'completed', 'complete');
    }

    public function cancel(ChangeReservationStatusRequest $request, Reservation $reservation): JsonResponse
    {
        return $this->change($request, $reservation, 'cancelled', 'cancel');
    }

    public function noShow(ChangeReservationStatusRequest $request, Reservation $reservation): JsonResponse
    {
        return $this->change($request, $reservation, 'no_show', 'noShow');
    }

    private function change(ChangeReservationStatusRequest $request, Reservation $reservation, string $status, string $ability): JsonResponse
    {
        $branch = $this->currentBranch();
        $this->authorize($ability, [$reservation, $branch]);

        return ApiResponse::resource(
            new ReservationResource($this->service->transition(
                $reservation,
                $branch,
                $request->user('tenant_api')->tenantUser()->firstOrFail(),
                $status,
                $request->validated('reason'),
            )),
            ApiMessages::updated('reservation status'),
        );
    }

    private function applyDateFilter($query, IndexReservationsRequest $request): void
    {
        if ($request->filled('period')) {
            $period = $request->string('period')->toString();
            $start = match ($period) {
                'today' => now()->startOfDay(),
                'this_week' => now()->startOfWeek(),
                'last_7_days' => now()->subDays(6)->startOfDay(),
                default => now()->startOfMonth(),
            };
            $end = match ($period) {
                'today' => now()->addDay()->startOfDay(),
                'this_week' => now()->addWeek()->startOfWeek(),
                'last_7_days' => now()->addDay()->startOfDay(),
                default => now()->addMonth()->startOfMonth(),
            };

            $query->where('reserved_from', '>=', $start)->where('reserved_from', '<', $end);

            return;
        }

        if ($request->filled('date_from')) {
            $query->whereBetween('reserved_from', [
                Carbon::parse($request->string('date_from')->toString())->startOfDay(),
                Carbon::parse($request->string('date_to')->toString())->addDay()->startOfDay(),
            ]);
        }
    }
}
