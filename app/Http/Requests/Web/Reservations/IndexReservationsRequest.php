<?php
namespace App\Http\Requests\Web\Reservations;
use Illuminate\Foundation\Http\FormRequest; use Illuminate\Validation\Rule;
class IndexReservationsRequest extends FormRequest { public function authorize():bool{return true;} public function rules():array{return ['status'=>['nullable',Rule::in(['pending','confirmed','seated','completed','cancelled','no_show'])],'period'=>['nullable',Rule::in(['today','this_week','last_7_days','this_month'])],'date_from'=>['nullable','date_format:Y-m-d','required_with:date_to','prohibited_with:period'],'date_to'=>['nullable','date_format:Y-m-d','required_with:date_from','after_or_equal:date_from','prohibited_with:period'],'search'=>['nullable','string','max:160'],'per_page'=>['nullable','integer','min:1','max:100']];}}
