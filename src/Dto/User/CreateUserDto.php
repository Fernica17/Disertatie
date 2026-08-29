<?php

namespace App\Dto\User;

use App\Entity\Companies;

class CreateUserDto
{
    public string $email = '';

    public string $firstName = '';

    public string $lastName = '';

    public string $plainPassword = '';

    public ?string $phone = null;

    public ?string $role = null;

    public bool $isActive = true;

    public ?Companies $company = null;

    /**
     * Create DTO from request data.
     */
    public static function fromRequest(array $data): self
    {
        $dto = new self();
        $dto->email = trim((string) ($data['email'] ?? ''));
        $dto->firstName = trim((string) ($data['firstName'] ?? ''));
        $dto->lastName = trim((string) ($data['lastName'] ?? ''));
        $dto->plainPassword = (string) ($data['plainPassword'] ?? '');
        $dto->isActive = (bool) ($data['isActive'] ?? true);

        return $dto;
    }
}
