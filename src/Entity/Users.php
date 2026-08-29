<?php

namespace App\Entity;

use App\Enum\UserRole;
use App\Repository\UsersRepository;
use App\Traits\TimestampTrait;
use App\Traits\UuidTrait;
use App\Validator\NotSelfDeactivation;
use App\Validator\ValidUserCompany;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UsersRepository::class)]
#[ORM\Table(name: 'users')]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(
    fields: ['email'],
    message: 'validation.user.email_unique'
)]
#[NotSelfDeactivation]
#[ValidUserCompany]
class Users implements UserInterface, PasswordAuthenticatedUserInterface
{
    use TimestampTrait;
    use UuidTrait;

    public const string ROLE_ADMIN = 'ROLE_ADMIN';
    public const string ROLE_MANAGER = 'ROLE_MANAGER';
    public const string ROLE_CLIENT = 'ROLE_CLIENT';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank(message: 'validation.email.required', groups: ['Default', 'profile'])]
    #[Assert\Email(message: 'validation.email.invalid', groups: ['Default', 'profile'])]
    private ?string $email = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(groups: ['Default', 'profile'])]
    #[Assert\Length(min: 2, max: 255, groups: ['Default', 'profile'])]
    private ?string $firstName = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(groups: ['Default', 'profile'])]
    #[Assert\Length(min: 2, max: 255, groups: ['Default', 'profile'])]
    private ?string $lastName = null;

    #[ORM\Column(type: 'json')]
    private array $roles = [];

    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isActive = true;

    private ?string $plainPassword = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isVerified = false;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $isChangePasswordRequired = true;

    #[ORM\ManyToOne(targetEntity: Companies::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Companies $company = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Assert\Length(min: 7, max: 20, groups: ['Default', 'profile'])]
    #[Assert\Regex(pattern: '/^[0-9+\-\s()]+$/', message: 'validation.phone.invalid')]
    private ?string $phone = null;

    /**
     * @var Collection<int, Notifications>
     */
    #[ORM\OneToMany(targetEntity: Notifications::class, mappedBy: 'user')]
    private Collection $notifications;

    public function __construct()
    {
        $this->roles = [self::ROLE_CLIENT];
        $this->notifications = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    public function getRoles(): array
    {
        $roles = $this->roles;

        return array_unique($roles);
    }

    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function eraseCredentials(): void
    {
        // Clear any temporary, sensitive data
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): static
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): static
    {
        $this->lastName = $lastName;

        return $this;
    }

    public function getAvatar(): ?string
    {
        return null;
    }

    public function getFullName(): string
    {
        return $this->firstName . ' ' . $this->lastName;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getPlainPassword(): ?string
    {
        return $this->plainPassword;
    }

    public function setPlainPassword(?string $plainPassword): self
    {
        $this->plainPassword = $plainPassword;

        return $this;
    }

    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    public function setIsVerified(bool $isVerified): static
    {
        $this->isVerified = $isVerified;

        return $this;
    }

    public function isChangePasswordRequired(): bool
    {
        return $this->isChangePasswordRequired;
    }

    public function setIsChangePasswordRequired(bool $isChangePasswordRequired): static
    {
        $this->isChangePasswordRequired = $isChangePasswordRequired;

        return $this;
    }

    public function getCompany(): ?Companies
    {
        return $this->company;
    }

    public function setCompany(?Companies $company): static
    {
        $this->company = $company;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;

        return $this;
    }

    public function getPrimaryRole(): string
    {
        if (in_array(self::ROLE_ADMIN, $this->roles, true)) {
            return self::ROLE_ADMIN;
        }
        if (in_array(self::ROLE_MANAGER, $this->roles, true)) {
            return self::ROLE_MANAGER;
        }

        return self::ROLE_CLIENT;
    }

    public function setPrimaryRole(string $role): static
    {
        $this->roles = [$role];

        return $this;
    }

    public function getPrimaryRoleEnum(): UserRole
    {
        return UserRole::from($this->getPrimaryRole());
    }

    /**
     * @return Collection<int, Notifications>
     */
    public function getNotifications(): Collection
    {
        return $this->notifications;
    }

    public function addNotifications(Notifications $type): static
    {
        if (!$this->notifications->contains($type)) {
            $this->notifications->add($type);
            $type->setUser($this);
        }

        return $this;
    }

    public function removeNotifications(Notifications $notifications): static
    {
        if ($this->notifications->removeElement($notifications)) {
            // set the owning side to null (unless already changed)
            if ($notifications->getUser() === $this) {
                $notifications->setUser(null);
            }
        }

        return $this;
    }

    public function __toString(): string
    {
        return $this->getFullName();
    }
}
