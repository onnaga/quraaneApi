<?php

namespace App\Http\Requests\Api;

use App\Traits\ApiResponseTrait;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class BaseApiRequest extends FormRequest
{
    use ApiResponseTrait;

    /**
     * Handle a failed validation attempt.
     * هذا دالة ضرورية جداً للحفاظ على Zero Regression.
     * الموبايل يتوقع أخطاء التحقق بصيغة معينة وكود 403 أو 422 حسب الكود القديم.
     * أغلب أخطاء التحقق القديمة كانت تُرجع 403، أو مصفوفة محددة.
     * 
     * @param  \Illuminate\Contracts\Validation\Validator  $validator
     * @return void
     *
     * @throws \Illuminate\Http\Exceptions\HttpResponseException
     */
    protected function failedValidation(Validator $validator)
    {
        // استخدام الخطأ القديم تماماً لتجنب كسر تطبيق الموبايل
        $response = $this->errorResponse(
            'الصيغة المُدخلة للبيانات غير صالحة', // رسالة عامة للتحقق
            $validator->errors(),
            422 // نضع 422 كمعيار للاخطاء، ولكن يمكن للوراثة تغيير الرسالة والرمز حسب اللزوم
        );

        throw new HttpResponseException($response);
    }
}
