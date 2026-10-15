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
        $userId = $this->route('id') ?? \Illuminate\Support\Facades\Auth::id();

        return [
            'name' => 'required|string|max:255|unique:users,name,'.$userId,
            'phone_number' => 'required|string|max:20',
            'birth_date' => 'nullable|date',
            'ended_quraan_in_aukaf' => 'nullable|string',

            // حقول المستخدم الجديدة
            'family_members_count' => 'nullable|integer|min:0',
            'notes' => 'nullable|string|max:1000',
            'skills' => 'nullable|string|max:1000',
            'certificates' => 'nullable|string|max:1000',
            'enrollment_date' => 'nullable|date', // ✅ إضافة التحقق هنا
            'job' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:255',
            'family_status' => 'nullable|string|max:255',
            // حقول الطالب الجديدة
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
