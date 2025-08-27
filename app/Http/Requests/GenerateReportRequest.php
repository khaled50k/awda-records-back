<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerateReportRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return \Illuminate\Support\Facades\Auth::check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'report_type' => ['required', 'string', Rule::in(['daily_transfers'])],
            'format' => ['sometimes', 'string', Rule::in(['csv', 'excel', 'pdf'])],
            'filters' => ['sometimes', 'array'],
            'filters.from_date' => ['sometimes', 'date', 'before_or_equal:filters.to_date'],
            'filters.to_date' => ['sometimes', 'date', 'after_or_equal:filters.from_date'],
            'filters.health_center_code' => ['sometimes', 'string', 'max:50'],
            'filters.problem_type_code' => ['sometimes', 'string', 'max:50'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'report_type.required' => 'نوع التقرير مطلوب',
            'report_type.in' => 'نوع التقرير غير صحيح',
            'format.in' => 'صيغة الملف غير مدعومة',
            'filters.from_date.date' => 'تاريخ البداية يجب أن يكون تاريخ صحيح',
            'filters.to_date.date' => 'تاريخ النهاية يجب أن يكون تاريخ صحيح',
            'filters.from_date.before_or_equal' => 'تاريخ البداية يجب أن يكون قبل أو يساوي تاريخ النهاية',
            'filters.to_date.after_or_equal' => 'تاريخ النهاية يجب أن يكون بعد أو يساوي تاريخ البداية',
        ];
    }

    /**
     * Get custom attribute names for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'report_type' => 'نوع التقرير',
            'format' => 'صيغة الملف',
            'filters.from_date' => 'تاريخ البداية',
            'filters.to_date' => 'تاريخ النهاية',
            'filters.health_center_code' => 'رمز المركز الصحي',
            'filters.problem_type_code' => 'رمز نوع المشكلة',
        ];
    }
}
