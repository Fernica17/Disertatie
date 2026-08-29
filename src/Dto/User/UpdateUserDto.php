<?php

namespace App\Dto\User;

use App\Entity\Companies;

class UpdateUserDto
{
    public string $email = '';

    public string $firstName = '';

    public string $lastName = '';

    public ?string $phone = null;

    public ?string $plainPassword = null;

    public ?string $role = null;

    public bool $isActive = true;

    public ?Companies $company = null;
}
