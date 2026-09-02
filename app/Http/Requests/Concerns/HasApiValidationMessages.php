<?php

namespace App\Http\Requests\Concerns;

trait HasApiValidationMessages
{
    public const SAFE_NAME_REGEX = "regex:/^[\\pL\\pM][\\pL\\pM\\s.'-]*$/u";
    public const SAFE_BUSINESS_NAME_REGEX = "regex:/^[\\pL\\pM][\\pL\\pM\\s.'&()\\/-]*$/u";
    public const SAFE_LABEL_REGEX = "regex:/^[\\pL\\pM][\\pL\\pM\\s.'&()\\/-]*$/u";
    public const SAFE_ADDRESS_REGEX = "regex:/^[\\pL\\pM\\pN\\s.,#'\\/-]*$/u";
    public const SAFE_REFERENCE_REGEX = 'regex:/^[A-Za-z0-9._\\/-]+$/';
    public const SAFE_SLUG_REGEX = 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/';
    public const SAFE_DEVICE_TYPE_REGEX = 'regex:/^[a-z][a-z0-9_-]{1,31}$/';
    public const SAFE_SEARCH_REGEX = "regex:/^[\\pL\\pM\\pN\\s.'\\/-]*$/u";

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge([
            'required' => ':Attribute is required.',
            'required_without' => 'Please provide :attribute or :values.',
            'required_with' => ':Attribute is required.',
            'present' => ':Attribute must be included.',
            'string' => ':Attribute must be text.',
            'array' => ':Attribute must be a list.',
            'boolean' => ':Attribute must be true or false.',
            'integer' => ':Attribute must be a whole number.',
            'numeric' => ':Attribute must be a number.',
            'uuid' => ':Attribute is not valid.',
            'email' => 'Please enter a valid email address.',
            'confirmed' => ':Attribute confirmation does not match.',
            'digits' => ':Attribute must be :digits digits.',
            'image' => ':Attribute must be a valid image file.',
            'file' => ':Attribute must be a file.',
            'mimes' => ':Attribute must be one of the allowed file types.',
            'max' => ':Attribute is too long.',
            'min' => ':Attribute is too small.',
            'gt' => ':Attribute must be greater than zero.',
            'between' => ':Attribute is outside the allowed range.',
            'decimal' => ':Attribute must be a valid decimal number.',
            'date' => ':Attribute must be a valid date.',
            'date_format' => ':Attribute has an invalid format.',
            'before' => ':Attribute must be before today.',
            'before_or_equal' => ':Attribute cannot be in the future.',
            'after_or_equal' => ':Attribute must be after or equal to the start date.',
            'in' => ':Attribute has an invalid value.',
            'distinct' => ':Attribute is duplicated.',
            'regex' => ':Attribute contains unsupported characters.',
            'phone.regex' => 'Please enter a valid Tanzanian phone number.',
            'password.min' => 'Password must have at least 8 characters.',
            'password.letters' => 'Password must include at least one letter.',
            'password.numbers' => 'Password must include at least one number.',
        ], method_exists($this, 'customMessages') ? $this->customMessages() : []);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return array_merge([
            'business_name' => 'business name',
            'legal_name' => 'legal name',
            'trading_name' => 'trading name',
            'first_name' => 'first name',
            'last_name' => 'last name',
            'display_name' => 'display name',
            'name' => 'name',
            'description' => 'description',
            'status' => 'status',
            'search' => 'search text',
            'per_page' => 'page size',
            'login' => 'login',
            'channel' => 'reset channel',
            'otp' => 'verification code',
            'password' => 'password',
            'device_name' => 'device name',
            'device_type' => 'device type',
            'email' => 'email address',
            'phone' => 'phone number',
            'branch_uuid' => 'branch',
            'role_uuids' => 'roles',
            'role_uuids.*' => 'role',
            'permission_uuids' => 'permissions',
            'permission_uuids.*' => 'permission',
            'branches' => 'branches',
            'branches.*.branch_uuid' => 'branch',
            'branches.*.is_primary' => 'primary branch setting',
            'branches.*.valid_from' => 'valid from date',
            'branches.*.valid_to' => 'valid to date',
            'address_line_1' => 'address line 1',
            'address_line_2' => 'address line 2',
            'city' => 'city',
            'region' => 'region',
            'postal_code' => 'postal code',
            'latitude' => 'latitude',
            'longitude' => 'longitude',
            'opened_on' => 'opening date',
            'configuration' => 'configuration',
            'configuration.business_day_opens_at' => 'business day opening time',
            'configuration.business_day_closes_at' => 'business day closing time',
            'configuration.allow_negative_stock' => 'negative stock setting',
            'effective_from' => 'effective date',
            'business_day_opens_at' => 'business day opening time',
            'business_day_closes_at' => 'business day closing time',
            'allow_negative_stock' => 'negative stock setting',
            'business_date' => 'business date',
            'opening_float_amount' => 'opening float amount',
            'counted_cash_amount' => 'counted cash amount',
            'employee_number' => 'employee number',
            'employment_type_uuid' => 'employment type',
            'home_branch_uuid' => 'home branch',
            'department_uuid' => 'department',
            'parent_uuid' => 'parent record',
            'job_position_uuid' => 'job position',
            'base_salary_amount' => 'base salary amount',
            'level_rank' => 'level rank',
            'date_of_birth' => 'date of birth',
            'hired_on' => 'hire date',
            'confirmed_on' => 'confirmation date',
            'ended_on' => 'end date',
            'employment_status' => 'employment status',
            'gender' => 'gender',
            'national_id_number' => 'national ID number',
            'tax_number' => 'tax number',
            'nssf_number' => 'NSSF number',
            'nhif_number' => 'NHIF number',
            'warehouse_uuid' => 'warehouse',
            'ingredient_uuid' => 'ingredient',
            'movement_type_uuid' => 'movement type',
            'type' => 'type',
            'expires_from' => 'expiry start date',
            'expires_to' => 'expiry end date',
            'moved_from' => 'movement start date',
            'moved_to' => 'movement end date',
            'reason_type' => 'reason type',
            'adjusted_on' => 'adjustment date',
            'adjusted_from' => 'adjustment start date',
            'adjusted_to' => 'adjustment end date',
            'notes' => 'notes',
            'items' => 'items',
            'items.*.ingredient_uuid' => 'ingredient',
            'items.*.location_uuid' => 'location',
            'items.*.unit_uuid' => 'unit',
            'items.*.quantity_delta' => 'adjustment quantity',
            'items.*.unit_cost' => 'unit cost',
            'items.*.reason' => 'item reason',
            'direction' => 'direction',
            'from_branch_uuid' => 'source branch',
            'from_warehouse_uuid' => 'source warehouse',
            'to_branch_uuid' => 'receiving branch',
            'to_warehouse_uuid' => 'receiving warehouse',
            'requested_on' => 'request date',
            'requested_from' => 'request start date',
            'requested_to' => 'request end date',
            'items.*.quantity_requested' => 'requested quantity',
            'counted_on' => 'count date',
            'counted_from' => 'count start date',
            'counted_to' => 'count end date',
            'items.*.counted_quantity' => 'counted quantity',
        ], method_exists($this, 'customAttributes') ? $this->customAttributes() : []);
    }
}
