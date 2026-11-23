<?php

namespace App\Http\Requests\Enrollment;

use Illuminate\Foundation\Http\FormRequest;

class StoreEnrollmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $program = $this->route('program') ?: basename($this->path());
        if (in_array($program, ['ppb', 'ppp', 'bipa', 'testing'], true)) {
            $this->merge(['division' => $program]);
        }

        $nationality = strtolower(trim((string) $this->input('nationality', '')));
        if ($nationality) {
            if (in_array($nationality, ['wni', 'indonesia', 'indonesian'], true)) {
                $this->merge(['nationality' => 'wni']);
            } elseif (in_array($nationality, ['wna', 'foreigner', 'foreign'], true)) {
                $this->merge(['nationality' => 'wna']);
            } else {
                // If it's a specific country name like "Japan", keep it in metadata and set wna
                $metadata = $this->input('metadata', []);
                if (is_array($metadata)) {
                    $metadata['country_of_origin'] = $this->input('nationality');
                    $this->merge(['metadata' => $metadata]);
                }
                $this->merge(['nationality' => 'wna']);
            }
        }
    }

    public function rules(): array
    {
        return [
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'course_catalog_id' => ['nullable', 'required_if:division,testing', 'integer', 'exists:course_catalogs,id'],
            'course_schedule_id' => ['nullable', 'integer', 'exists:course_schedules,id'],
            'term_id' => ['nullable', 'integer', 'exists:terms,id'],
            'metadata' => ['nullable', 'array'],
            'division' => ['nullable', 'string', 'in:bipa,ppb,ppb_english,ppb_foreign,ppp,testing'],
            'student_type' => ['nullable', 'string', 'max:50'],
            'registration_track' => ['nullable', 'string', 'in:no_test,placement_test,language_test'],
            'term_period' => ['nullable', 'string', 'max:50'],
            'student_name' => ['required', 'string', 'max:255'],
            'student_email' => ['required', 'email', 'max:191'],
            'student_phone' => ['nullable', 'string', 'max:50'],
            'nationality' => ['nullable', 'string', 'in:wni,wna'],
            'identity_type' => ['nullable', 'string', 'max:50'],
            'identity_document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'kitas_number' => ['nullable', 'string', 'max:100'],
            'kitas_document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'passport_number' => ['nullable', 'string', 'max:100'],
            'passport_document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'birth_place' => ['nullable', 'string', 'max:100'],
            'birth_date' => ['nullable', 'date'],
            'gender' => ['nullable', 'string', 'max:50'],
            'education' => ['nullable', 'string', 'max:100'],
            'occupation' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string'],
            'emergency_phone' => ['nullable', 'string', 'max:50'],
            'bipa_photo' => ['required_if:division,bipa', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
            'bipa_passport_copy' => ['required_if:division,bipa', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'bipa_sponsor_letter' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'metadata.identity_number' => ['nullable', 'string', 'max:100'],
            'metadata.identity_type' => ['nullable', 'string', 'max:50'],
            'metadata.nationality' => ['nullable', 'string', 'max:50'],
            'metadata.emergency_phone' => ['nullable', 'string', 'max:50'],
            'metadata.information_sources' => ['nullable'],
            'metadata.terms_accepted' => ['accepted_if:division,ppb'],
            'metadata.student_category' => ['nullable', 'string', 'max:50'],
            'metadata.relief_requested' => ['nullable'],
            'metadata.notes' => ['nullable', 'string'],
            'metadata.profile' => ['nullable', 'array'],
            'metadata.profile.birth_place' => ['nullable', 'string', 'max:100'],
            'metadata.profile.birth_date' => ['nullable', 'date'],
            'metadata.profile.gender' => ['nullable', 'string', 'max:50'],
            'metadata.profile.education' => ['nullable', 'string', 'max:100'],
            'metadata.profile.occupation' => ['nullable', 'string', 'max:100'],
            'metadata.profile.address' => ['nullable', 'string'],
            'metadata.address' => ['nullable', 'string'],
            'metadata.test_date' => ['required_if:registration_track,placement_test', 'nullable', 'date'],
            'metadata.test_date_preference' => ['nullable', 'date', 'after_or_equal:today'],
            'metadata.test_location' => ['nullable', 'in:depok,salemba,online'],
            'metadata.bipa.terms_accepted' => ['accepted_if:division,bipa'],
            'metadata.ppp.terms_accepted' => ['accepted_if:division,ppp'],
            'metadata.ppp.identity_number' => ['required_if:metadata.ppp.service_type,ept_toefl,special_test', 'nullable', 'string', 'max:100'],
            'metadata.ppp.birth_date' => ['required_if:metadata.ppp.service_type,ept_toefl,special_test', 'nullable', 'date'],
            'metadata.ppp.test_date_preference' => ['required_if:metadata.ppp.service_type,ept_toefl,special_test', 'nullable', 'date', 'after_or_equal:today'],
            'metadata.ppp.test_location' => ['required_if:metadata.ppp.service_type,ept_toefl,special_test', 'nullable', 'in:depok,salemba'],
            'metadata.ppp.document_language_from' => ['required_if:metadata.ppp.service_type,translation,interpreting', 'nullable', 'string', 'max:50'],
            'metadata.ppp.document_language_to' => ['required_if:metadata.ppp.service_type,translation,interpreting', 'nullable', 'string', 'max:50'],
            'metadata.ppp.document_type' => ['required_if:metadata.ppp.service_type,translation,interpreting', 'nullable', 'string', 'max:100'],
            'metadata.ppp.document_count' => ['required_if:metadata.ppp.service_type,translation,interpreting', 'nullable', 'integer', 'min:1'],
            'metadata.ppp.deadline_date' => ['required_if:metadata.ppp.service_type,translation,interpreting,in_house_training', 'nullable', 'date', 'after_or_equal:today'],
            'metadata.ppp.participant_count' => ['required_if:metadata.ppp.service_type,in_house_training', 'nullable', 'integer', 'min:1'],
            'metadata.ppp.applicant_category' => ['nullable', 'in:individual,corporate,institution'],
            'metadata.ppp.organization_name' => ['required_if:metadata.ppp.applicant_category,corporate,institution', 'nullable', 'string', 'max:255'],
            'course_name' => ['required', 'string', 'max:255'],
            'level_name' => ['nullable', 'string', 'max:255'],
            'campus_location' => ['nullable', 'string', 'in:depok,salemba,online', 'required_if:division,ppb'],
            'learning_mode'   => ['nullable', 'string', 'in:daring,luring,hybrid'],
            'schedule_days' => ['nullable', 'string', 'max:100'],
            'schedule_time' => ['nullable', 'string', 'max:100'],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'registration_fee' => ['nullable', 'numeric', 'min:0'],
            'postpone_reason' => ['nullable', 'string'],
        ];
    }
}
