<?php
namespace App\Http\Requests\Web\Pos;
use Illuminate\Foundation\Http\FormRequest;
class VoidReceiptRequest extends FormRequest { public function authorize(): bool { return true; } public function rules(): array { return ['reason' => ['required', 'string', 'min:3', 'max:1000']]; } }
