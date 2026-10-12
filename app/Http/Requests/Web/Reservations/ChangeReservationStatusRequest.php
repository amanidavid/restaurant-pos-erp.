<?php
namespace App\Http\Requests\Web\Reservations;
use Illuminate\Foundation\Http\FormRequest;
class ChangeReservationStatusRequest extends FormRequest { public function authorize():bool{return true;} public function rules():array{return ['reason'=>['nullable','string','max:1000']];}}
