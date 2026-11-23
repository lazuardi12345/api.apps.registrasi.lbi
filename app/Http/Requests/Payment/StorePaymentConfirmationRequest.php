<?php

namespace App\Http\Requests\Payment;

use App\Models\CourseEnrollment;
use Illuminate\Foundation\Http\FormRequest;

class StorePaymentConfirmationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('enrollment_id') && !empty($this->enrollment_id)) {
            $raw = trim((string) $this->enrollment_id);
            // Search by registration_number (e.g., PPB-2026-0001) or integer id
            $enrollment = CourseEnrollment::where('registration_number', $raw)
                ->orWhere('id', is_numeric($raw) ? (int) $raw : 0)
                ->first();

            if ($enrollment) {
                $this->merge([
                    'enrollment_id' => $enrollment->id,
                    'course_name' => $this->course_name ?: $enrollment->course_name,
                    'student_name' => $this->student_name ?: $enrollment->student_name,
                    'student_email' => $this->student_email ?: $enrollment->student_email,
                    'payment_for' => $this->input('payment_for') ?: ($enrollment->course_schedule_id ? 'tuition' : 'registration'),
                ]);
            } else {
                $this->merge([
                    'enrollment_id' => is_numeric($raw) && CourseEnrollment::where('id', (int) $raw)->exists() ? (int) $raw : null,
                ]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'enrollment_id' => ['nullable', 'integer', 'exists:course_enrollments,id'],
            'student_name' => ['required', 'string', 'max:255'],
            'student_email' => ['required', 'email', 'max:191'],
            'course_name' => ['nullable', 'string', 'max:255'],
            'payment_for' => ['nullable', 'in:registration,tuition'],
            'bank_name' => ['required', 'string', 'max:100'],
            'account_number' => ['nullable', 'string', 'max:100'],
            'account_holder' => ['nullable', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:1'],
            'payment_date' => ['required', 'date'],
            'proof_file' => ['nullable', 'file', 'mimes:jpeg,png,jpg,pdf', 'max:5120'],
        ];
    }
}
