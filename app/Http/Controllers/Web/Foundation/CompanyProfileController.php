<?php

namespace App\Http\Controllers\Web\Foundation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Foundation\UpdateCompanyProfileRequest;
use App\Http\Resources\Web\Foundation\CompanyResource;
use App\Models\Tenant\Company;
use App\Support\ApiMessages;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $company = Company::query()->orderBy('id')->firstOrFail();
        $this->authorize('view', [$company, $this->currentBranch()]);

        return ApiResponse::resource(
            new CompanyResource($company),
            ApiMessages::detailsRetrieved('company'),
        );
    }

    public function update(UpdateCompanyProfileRequest $request): JsonResponse
    {
        $company = Company::query()->orderBy('id')->firstOrFail();
        $this->authorize('update', [$company, $this->currentBranch()]);
        $company->update($this->payload($request->validated()));

        return ApiResponse::resource(
            new CompanyResource($company->refresh()),
            ApiMessages::updated('company'),
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function payload(array $data): array
    {
        if (! empty($data['phone'])) {
            $data['phone'] = $this->normalizeTanzaniaPhone((string) $data['phone']);
        }

        return $data;
    }

    private function normalizeTanzaniaPhone(string $phone): string
    {
        $phone = preg_replace('/\s+/', '', $phone) ?? $phone;

        if (str_starts_with($phone, '+255')) {
            return $phone;
        }

        if (str_starts_with($phone, '255')) {
            return '+'.$phone;
        }

        if (str_starts_with($phone, '0')) {
            return '+255'.substr($phone, 1);
        }

        return '+255'.$phone;
    }

}
