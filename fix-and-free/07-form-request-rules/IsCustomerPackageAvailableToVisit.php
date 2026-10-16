<?php

/*
 * Source: app/Rules/IsCustomerPackageAvailableToVisit.php
 *
 * Context: Fix and Free: subscription/visit system for a gym, salon and spa business
 * (Laravel 7, PHP 7.4, MySQL), built 2020-2021. Private repo.
 *
 * Problem solved: packages are limited in two ways. Gym packages are time-bound
 * (valid until the service end date). Salon and spa packages are count-bound
 * (a fixed number of visits). One rule handles both, given the package type.
 *
 * Today I would guard the null from find() and inject the repository instead of
 * calling it statically.
 *
 * My part: sole developer (384 of 385 commits).
 */

namespace App\Rules;

use App\CustomerPackage;
use App\Enums\PackageType;
use App\Repositories\CustomerPackageRepository;
use Illuminate\Contracts\Validation\Rule;

class IsCustomerPackageAvailableToVisit implements Rule
{
    private $packageType;

    /**
     * Create a new rule instance.
     */
    public function __construct(string $packageType)
    {
        $this->packageType = $packageType;
    }

    /**
     * Determine if the validation rule passes.
     */
    public function passes($attribute, $value)
    {
        $customerPackage = CustomerPackage::find($value);

        // Package Type is Gym make sure the service is not expired
        if ($this->packageType === PackageType::GYM && ! $customerPackage->isGymServiceExpired()) {
            return true;
        }

        return CustomerPackageRepository::totalVisitation($value, $this->packageType) > count(CustomerPackageRepository::customerTotalVisits($value, $this->packageType));
    }

    /**
     * Get the validation error message.
     */
    public function message()
    {
        return 'Visitation for this package is exceeded.';
    }
}
