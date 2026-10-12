<?php

namespace App\Http\Controllers\Web\Customers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Customers\IndexCustomerRequest;
use App\Http\Requests\Web\Customers\StoreCustomerRequest;
use App\Http\Requests\Web\Customers\UpdateCustomerRequest;
use App\Http\Resources\Web\Customers\CustomerResource;
use App\Models\Tenant\Customer;
use App\Services\Tenant\Customers\CustomerService;
use App\Support\ApiMessages;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class CustomerController extends Controller
{
    public function __construct(private readonly CustomerService $service) {}

    public function index(IndexCustomerRequest $request): JsonResponse
    {
        $branch = $this->currentBranch();
        $this->authorize('viewAny', [Customer::class, $branch]);
        $search = Str::lower(trim($request->string('search')->toString()));

        $customers = Customer::query()
            ->where('company_id', $branch->company_id)
            ->select(['id', 'uuid', 'company_id', 'customer_group_id', 'name', 'email', 'phone', 'tax_number', 'status', 'created_at', 'updated_at'])
            ->with('customerGroup:id,uuid,name')
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->whereRaw('LOWER(name) LIKE ?', [$search.'%'])
                        ->orWhere('phone', 'like', $search.'%')
                        ->orWhereRaw('LOWER(email) LIKE ?', [$search.'%']);
                });
            })
            ->orderBy('name')
            ->orderBy('id')
            ->simplePaginate($this->perPage($request));

        return ApiResponse::resource(CustomerResource::collection($customers), ApiMessages::listRetrieved('customers'));
    }

    public function store(StoreCustomerRequest $request): JsonResponse
    {
        $branch = $this->currentBranch();
        $this->authorize('create', [Customer::class, $branch]);

        return ApiResponse::resource(new CustomerResource($this->service->create($branch, $request->validated())), ApiMessages::created('customer'), 201);
    }

    public function show(Customer $customer): JsonResponse
    {
        $branch = $this->currentBranch();
        $this->authorize('view', [$customer, $branch]);

        return ApiResponse::resource(new CustomerResource($customer->load('customerGroup:id,uuid,name')), ApiMessages::detailsRetrieved('customer'));
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): JsonResponse
    {
        $branch = $this->currentBranch();
        $this->authorize('update', [$customer, $branch]);

        return ApiResponse::resource(new CustomerResource($this->service->update($customer, $branch, $request->validated())), ApiMessages::updated('customer'));
    }
}
