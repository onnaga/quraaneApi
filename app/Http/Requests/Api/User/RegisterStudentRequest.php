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
            'birth_date' => 'required|date',
            'job' => 'required|string|max:255',
            'address' => 'required|string|max:255',
            'family_status' => 'nullable|string|max:255',
            'ended_quraan_in_aukaf' => 'nullable',
            'photo' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'daora_id' => 'required|exists:daoras,id',
            'family_members_count' => 'nullable|integer|min:0',
            'notes' => 'nullable|string|max:1000',
            'skills' => 'nullable|string|max:1000',
            'certificates' => 'nullable|string|max:1000',
            'enrollment_date' => 'nullable|date',
            'current_grade' => 'nullable|string|max:100',
            'school_name' => 'nullable|string|max:255',
            'father_phone' => 'nullable|string|max:20',
            'mother_phone' => 'nullable|string|max:20',
            'landline_phone' => 'nullable|string|max:20',
            'other_phone' => 'nullable|string|max:20',
            'preferred_contact' => 'nullable|string|max:255',
            'previous_mosques' => 'nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'حقل الاسم مطلوب.',
            'name.unique' => 'هذا الاسم مستخدم بالفعل، الرجاء كتابة الاسم الثلاثي.',
            'password.required' => 'حقل كلمة المرور مطلوب.',
            'phone_number.required' => 'حقل رقم الهاتف مطلوب.',
            'birth_date.required' => 'حقل تاريخ الميلاد مطلوب.',
            'job.required' => 'حقل العمل مطلوب.',
            'address.required' => 'حقل مكان السكن مطلوب.',
            'daora_id.required' => 'الرجاء اختيار الدورة.',
            'daora_id.exists' => 'الدورة المختارة غير موجودة.',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        $response = response()->json([
            'message' => $validator->errors()->first(),
            'errors' => $validator->errors(),
        ], 422);

        throw new HttpResponseException($response);
    }
}
