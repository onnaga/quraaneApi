<?php

namespace App\Http\Requests\Api\Test;

use App\Http\Requests\Api\BaseApiRequest;

class UpdateAukafTestRequest extends BaseApiRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'rating' => 'required|numeric|min:0|max:100',
            'notes' => 'required|string',
            'the_part_to_test_in' => 'required|array',
        ];
    }
    
    /**
     * Override failedValidation.
     */
    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator)
    {
        // 422 JSON exactly as before
        $response = response()->json([
            'errors'   => $validator->errors()
        ], 422);

        throw new \Illuminate\Http\Exceptions\HttpResponseException($response);
    }
}
