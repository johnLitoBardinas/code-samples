<?php

/*
 * Source: app/Livewire/ApplyForm.php
 *
 * Context: TrabahongPinoy: live job-application portal for Filipino blue-collar workers
 * (Laravel 12, PHP 8.4, Livewire 3, PostgreSQL, Cloudflare R2, Brevo). Private repo.
 *
 * Problem solved: one Livewire component serves both the public application form
 * and the signed reapply link. In reapply mode, mount() pre-fills every field from
 * the rejected record and resolves stored address names back to cascading PSGC
 * dropdown codes. Validation lives on properties via #[Validate], with backed enums.
 * submit() throttles per IP inside the component (5 tries / 5 min), saves a NEW
 * applicant linked to the original (audit trail), stores uploads to R2 and queues
 * the confirmation email.
 *
 * My part: sole developer. I designed, built and deployed the app.
 */

namespace App\Livewire;

use App\Enums\ApplicantStatus;
use App\Enums\EducationalAttainment;
use App\Enums\EmergencyContactRelationship;
use App\Enums\FileType;
use App\Enums\MaritalStatus;
use App\Enums\Religion;
use App\Enums\SuffixName;
use App\Jobs\SendApplicationReceivedEmail;
use App\Livewire\Concerns\WithApplicantForm;
use App\Models\Applicant;
use App\Models\Source;
use App\Services\PsgcService;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Enum;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;
use App\Services\ApplicantFileService;

class ApplyForm extends Component
{
    use WithFileUploads, WithApplicantForm;

    #[Validate('required|string|max:255')]
    public string $first_name = '';

    #[Validate('nullable|string|max:255')]
    public string $middle_name = '';

    #[Validate('required|string|max:255')]
    public string $last_name = '';

    #[Validate(['nullable', new Enum(SuffixName::class)])]
    public string $suffix_name = '';

    #[Validate(['required', new Enum(MaritalStatus::class)])]
    public string $marital_status = 'single';

    #[Validate(['required', new Enum(EducationalAttainment::class)])]
    public string $educational_attainment = 'high_school_graduate';

    #[Validate(['nullable', new Enum(Religion::class)])]
    public string $religion = '';

    #[Validate('nullable|numeric|min:50|max:250')]
    public ?float $height_cm = null;

    #[Validate('nullable|numeric|min:20|max:300')]
    public ?float $weight_kg = null;

    #[Validate('required|string|max:255')]
    public string $emergency_contact_name = '';

    #[Validate('required|string|max:20')]
    public string $emergency_contact_mobile = '';

    #[Validate(['required', new Enum(EmergencyContactRelationship::class)])]
    public string $emergency_contact_relationship = '';

    #[Validate('required|string|max:255')]
    public ?string $region = '';

    #[Validate('required|string|max:255')]
    public ?string $province = '';

    #[Validate('required|string|max:255')]
    public ?string $city = '';

    #[Validate('required|string|max:255')]
    public ?string $barangay = '';

    #[Validate('required|string|max:255')]
    public string $address = '';

    #[Validate('required|string|max:10')]
    public string $zip_code = '';

    #[Validate('required|integer|min:15|max:65')]
    public ?int $age = null;

    #[Validate('required|date|before:today')]
    public string $date_of_birth = '';

    #[Validate('required|string|max:20')]
    public string $mobile_number = '';

    #[Validate('required|email|max:255')]
    public string $email = '';

    #[Validate(['nullable', 'string', 'regex:/^\d{12}$/'])]
    public string $tin_number = '';

    #[Validate(['nullable', 'string', 'regex:/^\d{10}$/'])]
    public string $sss_number = '';

    #[Validate(['nullable', 'string', 'regex:/^\d{12}$/'])]
    public string $philhealth_number = '';

    #[Validate(['nullable', 'string', 'regex:/^\d{12}$/'])]
    public string $pagibig_number = '';

    #[Validate(['government_ids' => 'nullable|array|max:3', 'government_ids.*' => 'file|mimes:png,jpg,jpeg,pdf|max:5120'])]
    public array $government_ids = [];

    #[Validate(['certificates' => 'nullable|array|max:6', 'certificates.*' => 'file|mimes:pdf,png,jpg,jpeg|max:5120'])]
    public array $certificates = [];

    #[Validate('required|image|mimes:jpeg,png|max:5120')]
    public $face_photo = null;

    public ?int $sourceId = null;

    // Prefill data for reapplication
    public ?string $reapplyUuid = null;

    public ?Applicant $originalApplicant = null;

    public function mount(?string $uuid = null): void
    {
        $slug = request()->query('source');
        $source = Source::active()->where('slug', $slug)->first();
        $this->sourceId = $source?->id ?? Source::where('slug', 'general')->value('id');

        $this->regions = PsgcService::regions();

        // Reapply mode: uuid comes from route parameter
        if ($uuid) {
            $this->reapplyUuid = $uuid;
            $this->prefillFromPrevious($uuid);
        }
    }

    private function prefillFromPrevious(string $uuid): void
    {
        $applicant = Applicant::find($uuid);
        if (! $applicant) {
            return;
        }

        $this->originalApplicant = $applicant;
        $this->first_name = $applicant->first_name;
        $this->middle_name = $applicant->middle_name ?? '';
        $this->last_name = $applicant->last_name;
        $this->suffix_name = $applicant->suffix_name?->value ?? '';
        $this->marital_status = $applicant->marital_status?->value ?? 'single';
        $this->educational_attainment = $applicant->educational_attainment?->value ?? 'high_school_graduate';
        $this->religion = $applicant->religion?->value ?? '';
        $this->height_cm = $applicant->height_cm;
        $this->weight_kg = $applicant->weight_kg;
        $this->emergency_contact_name = $applicant->emergency_contact_name ?? '';
        $this->emergency_contact_mobile = $applicant->emergency_contact_mobile ?? '';
        $this->emergency_contact_relationship = $applicant->emergency_contact_relationship?->value ?? '';
        $this->tin_number = $applicant->tin_number ?? '';
        $this->sss_number = $applicant->sss_number ?? '';
        $this->philhealth_number = $applicant->philhealth_number ?? '';
        $this->pagibig_number = $applicant->pagibig_number ?? '';
        $this->address = $applicant->address;
        $this->zip_code = $applicant->zip_code;
        $this->age = $applicant->age;
        $this->date_of_birth = $applicant->date_of_birth->format('Y-m-d');
        $this->mobile_number = $applicant->mobile_number;
        $this->email = $applicant->email;
        $this->sourceId = $applicant->source_id;

        // Resolve stored names back to codes for cascade
        $this->prefillPsgcFromNames($applicant->region, $applicant->province, $applicant->city, $applicant->barangay);
    }

    public function removeGovernmentId(int $index): void
    {
        unset($this->government_ids[$index]);
        $this->government_ids = array_values($this->government_ids);
    }

    public function removeCertificate(int $index): void
    {
        unset($this->certificates[$index]);
        $this->certificates = array_values($this->certificates);
    }

    public function submit(): void
    {
        $key = 'apply.' . request()->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            $this->addError('email', __('validation.throttle_submit', ['seconds' => $seconds]));
            return;
        }

        RateLimiter::hit($key, 300); // 5 attempts per 5 minutes per IP

        $this->validate();

        $applicant = Applicant::create([
            'first_name' => $this->first_name,
            'middle_name' => $this->middle_name ?: null,
            'last_name' => $this->last_name,
            'suffix_name' => $this->suffix_name ?: null,
            'marital_status' => $this->marital_status ?: null,
            'educational_attainment' => $this->educational_attainment,
            'religion' => $this->religion ?: null,
            'height_cm' => $this->height_cm,
            'weight_kg' => $this->weight_kg,
            'emergency_contact_name' => $this->emergency_contact_name,
            'emergency_contact_mobile' => $this->emergency_contact_mobile,
            'emergency_contact_relationship' => $this->emergency_contact_relationship,
            'tin_number' => $this->tin_number ?: null,
            'sss_number' => $this->sss_number ?: null,
            'philhealth_number' => $this->philhealth_number ?: null,
            'pagibig_number' => $this->pagibig_number ?: null,
            'region' => PsgcService::resolveName($this->region) ?? $this->region,
            'province' => PsgcService::resolveName($this->province) ?? $this->province,
            'city' => PsgcService::resolveName($this->city) ?? $this->city,
            'barangay' => PsgcService::resolveName($this->barangay) ?? $this->barangay,
            'address' => $this->address,
            'zip_code' => $this->zip_code,
            'age' => $this->age,
            'date_of_birth' => $this->date_of_birth,
            'mobile_number' => $this->mobile_number,
            'email' => $this->email,
            'source_id' => $this->sourceId,
            'status' => ApplicantStatus::New,
            'original_applicant_id' => $this->reapplyUuid,
        ]);

        ApplicantFileService::store($applicant, $this->government_ids, FileType::GovernmentId);
        ApplicantFileService::store($applicant, $this->certificates, FileType::Certificate);
        ApplicantFileService::store($applicant, [$this->face_photo], FileType::FacePhoto);

        SendApplicationReceivedEmail::dispatch($applicant);

        session()->flash('applicant_mobile', $this->mobile_number);
        session()->flash('applicant_email', $this->email);

        $this->redirect(route('success'));
    }

    public function render()
    {
        return view('livewire.apply-form')
            ->layout('layouts.public');
    }
}
