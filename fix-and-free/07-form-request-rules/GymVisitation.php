<?php

/*
 * Source: app/Http/Requests/GymVisitation.php
 *
 * Context: Fix and Free: subscription/visit system for a gym, salon and spa business
 * (Laravel 7, PHP 7.4, MySQL), built 2020-2021. Private repo.
 *
 * Problem solved: logging a gym visit has many preconditions. The package must
 * have an active gym service, the branch must exist, be active and be a gym
 * branch, and the user must exist, be active and belong to a gym branch. Each
 * check is its own reusable Rule object, so this FormRequest only composes 7 rules
 * plus an enum-backed `in:` list, and the controller receives validated data.
 *
 * My part: sole developer (384 of 385 commits).
 */

namespace App\Http\Requests;

use App\Enums\GymVisitationType;
use App\Rules\IsBranchActive;
use App\Rules\IsBranchGymType;
use App\Rules\IsBranchIdExist;
use App\Rules\IsCustomerPackageGymServiceActive;
use App\Rules\IsUserActiveById;
use App\Rules\IsUserIdExist;
use App\Rules\IsUserInGymTypeBranch;
use Illuminate\Foundation\Http\FormRequest;

class GymVisitation extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules()
    {
        return [
            'customer_package_id' => [
                'required',
                new IsCustomerPackageGymServiceActive(),
            ],
            'branch_id' => [
                'required',
                new IsBranchIdExist(),
                new IsBranchActive(),
                new IsBranchGymType(),
            ],
            'user_id' => [
                'required',
                new IsUserIdExist(),
                new IsUserActiveById(),
                new IsUserInGymTypeBranch(),
            ],
            'visitation_type' => [
                'required',
                'in:' . implode(',', GymVisitationType::getValues()),
            ],
        ];
    }
}
