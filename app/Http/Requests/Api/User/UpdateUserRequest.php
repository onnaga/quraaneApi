<?php

namespace App\Http\Requests\Api\User;

use App\Http\Requests\Api\BaseApiRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateUserRequest extends BaseApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // the rule handles dynamic ID based on the route
        $userId = $this->route('id') ?? \Illuminate\Support\Facades\Auth::id();

        return [
            'name' => 'required|string|max:255|unique:users,name,'.$userId,
            'phone_number' => 'required|string|max:20',
            'age' => 'required|integer|min:1',
            'ended_quraan_in_aukaf' => 'nullable|string',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        $response = response()->json([
            'status' => 'error',
            'messages' => 'البيانات غير صالحة',
            'errors' => $validator->errors(),
        ], 403);

        throw new HttpResponseException($response);
    }
}
