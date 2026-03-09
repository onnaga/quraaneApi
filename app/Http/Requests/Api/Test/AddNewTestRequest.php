<?php

namespace App\Http\Requests\Api\Test;

use App\Http\Requests\Api\BaseApiRequest;

class AddNewTestRequest extends BaseApiRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // We handle role check inside controller or we can do it here.
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'End_time' => 'required|date',
            'notes' => 'required|string',
            'aukaf' => 'required|boolean',
            'daora_id' => 'required|integer|exists:daoras,id',
        ];
    }

    /**
     * Override failedValidation.
     */
    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator)
    {
        // 422 JSON exactly as before
        $response = response()->json([
            'status' => 'error',
            'message' => 'يوجد خطأ بالبيانات المضافة',
            'errors' => $validator->errors(),
        ], 422);

        throw new \Illuminate\Http\Exceptions\HttpResponseException($response);
    }
}
