<?php

namespace App\Support\MasterData;

use App\Enums\CompanyStatus;
use App\Models\Company;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class CompanyService
{
    /** @param  array<string, mixed>  $data */
    public function create(array $data, User $actor): Company
    {
        return Company::query()->create([
            'name' => $data['name'],
            'location' => $data['location'] ?? null,
            'contact_info' => $data['contact_info'] ?? null,
            'notes' => $data['notes'] ?? null,
            'status' => CompanyStatus::Active,
            'created_by' => $actor->id,
        ]);
    }

    /** @param  array<string, mixed>  $data */
    public function update(Company $company, array $data): Company
    {
        $company->fill([
            'name' => $data['name'] ?? $company->name,
            'location' => $data['location'] ?? $company->location,
            'contact_info' => $data['contact_info'] ?? $company->contact_info,
            'notes' => $data['notes'] ?? $company->notes,
        ])->save();

        return $company->refresh();
    }

    public function suspend(Company $company): Company
    {
        if ($company->status !== CompanyStatus::Active) {
            throw ValidationException::withMessages(['status' => ['Company is not active.']]);
        }

        $company->update(['status' => CompanyStatus::Suspended]);

        return $company->refresh();
    }

    public function activate(Company $company): Company
    {
        if ($company->status === CompanyStatus::Archived) {
            throw ValidationException::withMessages(['status' => ['Archived company cannot be reactivated.']]);
        }

        $company->update(['status' => CompanyStatus::Active]);

        return $company->refresh();
    }

    public function archive(Company $company): Company
    {
        $company->update(['status' => CompanyStatus::Archived]);
        $company->delete();

        return $company->refresh();
    }

    public function assertActive(Company $company): void
    {
        if ($company->status !== CompanyStatus::Active) {
            throw ValidationException::withMessages([
                'company_id' => ['Company must be active.'],
            ]);
        }
    }
}
