<?php

namespace App\Http\Requests\Api\User;

use App\Http\Requests\Api\BaseApiRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class RegisterStudentRequest extends BaseApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255|unique:users,name',
            'password' => 'required|string',
            'phone_number' => 'required|string|max:20',
            'age' => 'required|integer',
            'job' => 'required|string|max:255',
            'address' => 'required|string|max:255',
            'family_status' => 'nullable|string|max:255',
            'ended_quraan_in_aukaf' => 'required',
            'photo' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'daora_id' => 'required|exists:daoras,id',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        $response = response()->json([
            'message' => 'البيانات المدخلة غير صحيحة , من الممكن أن يكون الاسم مكررا حاول كتابة الاسم الثلاثي ',
            'errors' => $validator->errors(),
        ], 422);

        throw new HttpResponseException($response);
    }
}
