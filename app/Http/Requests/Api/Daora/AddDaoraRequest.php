<?php

namespace App\Http\Requests\Api\Daora;

use App\Http\Requests\Api\BaseApiRequest;
use Illuminate\Support\Facades\Auth;

class AddDaoraRequest extends BaseApiRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // 4 تعني المشرف العام (مسموح له فقط بإضافة دورة)
        return Auth::check() && Auth::user()->privilege == 4;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'daora_name' => 'required|string|max:255',
            'photo' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'name' => 'required|string|max:255|unique:users,name',
            'password' => 'required|string',
        ];
    }

    /**
     * カスタム Authorization response
     * التطبيق القديم كان يرجع 403 مع رسالة معينة لو ما عنده صلاحية
     */
    protected function failedAuthorization()
    {
        $response = $this->errorSimpleMessage('لا يمكنك اضافة دورة', 403);
        throw new \Illuminate\Http\Exceptions\HttpResponseException($response);
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
            403
        );

        throw new \Illuminate\Http\Exceptions\HttpResponseException($response);
    }
}
