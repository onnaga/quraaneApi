<?php

namespace App\Http\Requests\Api\Daora;

use App\Http\Requests\Api\BaseApiRequest;

class ChangeDaoraRequest extends BaseApiRequest
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
            'user_id'      => 'required|exists:users,id',
            'new_daora_id' => 'required|exists:daoras,id',
        ];
    }
    
     /**
     * カスタム Validation response 
     * التطبيق القديم كان يرجع 403 مع رسالة "هناك خطأ بالبيانات المدخلة"
     */
    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator)
    {
        $response = $this->errorResponse(
            'هناك خطأ بالبيانات المدخلة',
            $validator->errors(),
            403 // حسب الكود القديم
        );

        throw new \Illuminate\Http\Exceptions\HttpResponseException($response);
    }
}
