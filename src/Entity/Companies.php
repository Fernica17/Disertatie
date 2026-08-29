<?php

namespace App\Entity;

use App\Enum\CompanyStatus;
use App\Enum\PersonType;
use App\Repository\CompaniesRepository;
use App\Traits\TimestampTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CompaniesRepository::class)]
#[ORM\Table(name: 'companies')]
#[ORM\Index(columns: ['name'], name: 'idx_companies_name')]
#[ORM\Index(columns: ['status'], name: 'idx_companies_status')]
#[ORM\Index(columns: ['person_type'], name: 'idx_companies_person_type')]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['fiscalCode'], message: 'validation.company.fiscal_code_unique', ignoreNull: true)]
class Companies
{
    use TimestampTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'validation.company.name.required')]
    #[Assert\Length(min: 2, max: 255, minMessage: 'validation.company.name.min_length', maxMessage: 'validation.company.name.max_length')]
    private ?string $name = null;

    #[ORM\Column(length: 20, unique: true, nullable: true)]
    #[Assert\Length(max: 20, maxMessage: 'validation.company.fiscal_code.max_length')]
    private ?string $fiscalCode = null;

    #[ORM\Column(length: 30, nullable: true)]
    #[Assert\Length(max: 30, maxMessage: 'validation.company.trade_register.max_length')]
    private ?string $tradeRegisterNumber = null;

    #[ORM\Column(length: 20, enumType: PersonType::class)]
    private PersonType $personType = PersonType::LEGAL;

    // --- Adresa ---

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $address = null;

    #[ORM\ManyToOne(targetEntity: Cities::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Cities $city = null;

    #[ORM\ManyToOne(targetEntity: Countries::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Countries $country = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $postalCode = null;

    // --- Contact ---

    #[ORM\Column(length: 20, nullable: true)]
    #[Assert\Regex(pattern: '/^[0-9+\-\s()]+$/', message: 'validation.phone.invalid')]
    private ?string $phone = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Email(message: 'validation.email.invalid')]
    private ?string $email = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Regex(pattern: '/^(https?:\/\/)?[\w\-]+(\.[\w\-]+)+[^\s]*$/', message: 'validation.url.invalid')]
    private ?string $website = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $fax = null;

    // --- Financiar ---

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $bankName = null;

    #[ORM\Column(length: 34, nullable: true)]
    #[Assert\Length(max: 34, maxMessage: 'validation.company.iban.max_length')]
    private ?string $bankAccount = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)]
    #[Assert\PositiveOrZero(message: 'validation.company.credit_limit.positive')]
    private ?string $creditLimit = null;

    #[ORM\Column(nullable: true)]
    #[Assert\PositiveOrZero(message: 'validation.company.payment_term.positive')]
    private ?int $paymentTermDays = 30;

    // --- Operational ---

    #[ORM\Column(length: 20, enumType: CompanyStatus::class)]
    private CompanyStatus $status = CompanyStatus::PROSPECT;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    // --- Persoana de contact principala ---

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $contactPersonName = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $contactPersonPhone = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Email(message: 'validation.email.invalid_contact')]
    private ?string $contactPersonEmail = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $contactPersonPosition = null;

    // --- Relatii ---

    /** @var Collection<int, CompanyContacts> */
    #[ORM\OneToMany(targetEntity: CompanyContacts::class, mappedBy: 'company', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $contacts;

    private ?UploadedFile $logoUpload = null;

    private ?array $documentsUpload = null;

    #[ORM\ManyToOne(targetEntity: Elements::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Elements $clientType = null;
    #[ORM\ManyToOne(targetEntity: Elements::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Elements $industry = null;
    #[ORM\ManyToOne(targetEntity: Elements::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Elements $companySize = null;

    #[ORM\ManyToOne(targetEntity: Users::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Users $accountManager = null;

    public function __construct()
    {
        $this->contacts = new ArrayCollection();
    }

    // --- Getters & Setters ---

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getFiscalCode(): ?string
    {
        return $this->fiscalCode;
    }

    public function setFiscalCode(?string $fiscalCode): static
    {
        $this->fiscalCode = $fiscalCode;

        return $this;
    }

    public function getTradeRegisterNumber(): ?string
    {
        return $this->tradeRegisterNumber;
    }

    public function setTradeRegisterNumber(?string $tradeRegisterNumber): static
    {
        $this->tradeRegisterNumber = $tradeRegisterNumber;

        return $this;
    }

    public function getPersonType(): PersonType
    {
        return $this->personType;
    }

    public function setPersonType(PersonType $personType): static
    {
        $this->personType = $personType;

        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): static
    {
        $this->address = $address;

        return $this;
    }

    public function getCity(): ?Cities
    {
        return $this->city;
    }

    public function setCity(?Cities $city): static
    {
        $this->city = $city;

        return $this;
    }

    public function getCountry(): ?Countries
    {
        return $this->country;
    }

    public function setCountry(?Countries $country): static
    {
        $this->country = $country;

        return $this;
    }

    public function getCountyName(): ?string
    {
        return $this->city?->getCounty()?->getName();
    }

    public function getPostalCode(): ?string
    {
        return $this->postalCode;
    }

    public function setPostalCode(?string $postalCode): static
    {
        $this->postalCode = $postalCode;

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

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getWebsite(): ?string
    {
        return $this->website;
    }

    public function setWebsite(?string $website): static
    {
        $this->website = $website;

        return $this;
    }

    public function getFax(): ?string
    {
        return $this->fax;
    }

    public function setFax(?string $fax): static
    {
        $this->fax = $fax;

        return $this;
    }

    public function getBankName(): ?string
    {
        return $this->bankName;
    }

    public function setBankName(?string $bankName): static
    {
        $this->bankName = $bankName;

        return $this;
    }

    public function getBankAccount(): ?string
    {
        return $this->bankAccount;
    }

    public function setBankAccount(?string $bankAccount): static
    {
        $this->bankAccount = $bankAccount;

        return $this;
    }

    public function getCreditLimit(): ?string
    {
        return $this->creditLimit;
    }

    public function setCreditLimit(?string $creditLimit): static
    {
        $this->creditLimit = $creditLimit;

        return $this;
    }

    public function getPaymentTermDays(): ?int
    {
        return $this->paymentTermDays;
    }

    public function setPaymentTermDays(?int $paymentTermDays): static
    {
        $this->paymentTermDays = $paymentTermDays;

        return $this;
    }

    public function getStatus(): CompanyStatus
    {
        return $this->status;
    }

    public function setStatus(CompanyStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function isActive(): bool
    {
        return in_array($this->status, [CompanyStatus::ACTIVE, CompanyStatus::PROSPECT], true);
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;

        return $this;
    }

    public function getContactPersonName(): ?string
    {
        return $this->contactPersonName;
    }

    public function setContactPersonName(?string $contactPersonName): static
    {
        $this->contactPersonName = $contactPersonName;

        return $this;
    }

    public function getContactPersonPhone(): ?string
    {
        return $this->contactPersonPhone;
    }

    public function setContactPersonPhone(?string $contactPersonPhone): static
    {
        $this->contactPersonPhone = $contactPersonPhone;

        return $this;
    }

    public function getContactPersonEmail(): ?string
    {
        return $this->contactPersonEmail;
    }

    public function setContactPersonEmail(?string $contactPersonEmail): static
    {
        $this->contactPersonEmail = $contactPersonEmail;

        return $this;
    }

    public function getContactPersonPosition(): ?string
    {
        return $this->contactPersonPosition;
    }

    public function setContactPersonPosition(?string $contactPersonPosition): static
    {
        $this->contactPersonPosition = $contactPersonPosition;

        return $this;
    }

    public function getLogoUpload(): ?UploadedFile
    {
        return $this->logoUpload;
    }

    public function setLogoUpload(?UploadedFile $logoUpload): static
    {
        $this->logoUpload = $logoUpload;

        return $this;
    }

    public function getDocumentsUpload(): ?array
    {
        return $this->documentsUpload;
    }

    public function setDocumentsUpload(?array $documentsUpload): static
    {
        $this->documentsUpload = $documentsUpload;

        return $this;
    }

    public function getClientType(): ?Elements
    {
        return $this->clientType;
    }

    public function setClientType(?Elements $clientType): static
    {
        $this->clientType = $clientType;

        return $this;
    }

    public function getIndustry(): ?Elements
    {
        return $this->industry;
    }

    public function setIndustry(?Elements $industry): static
    {
        $this->industry = $industry;

        return $this;
    }

    public function getCompanySize(): ?Elements
    {
        return $this->companySize;
    }

    public function setCompanySize(?Elements $companySize): static
    {
        $this->companySize = $companySize;

        return $this;
    }

    public function getAccountManager(): ?Users
    {
        return $this->accountManager;
    }

    public function setAccountManager(?Users $accountManager): static
    {
        $this->accountManager = $accountManager;

        return $this;
    }

    // --- Relatii: Contacts ---

    /**
     * @return Collection<int, CompanyContacts>
     */
    public function getContacts(): Collection
    {
        return $this->contacts;
    }

    public function addContact(CompanyContacts $contact): static
    {
        if (!$this->contacts->contains($contact)) {
            $this->contacts->add($contact);
            $contact->setCompany($this);
        }

        return $this;
    }

    public function removeContact(CompanyContacts $contact): static
    {
        if ($this->contacts->removeElement($contact)) {
            if ($contact->getCompany() === $this) {
                $contact->setCompany(null);
            }
        }

        return $this;
    }

    // --- Helper methods ---

    /**
     * Returneaza adresa completa formatata.
     */
    public function getFullAddress(): string
    {
        $countryName = $this->country?->getName();

        $parts = array_filter([
            $this->address,
            $this->city?->getName(),
            $this->getCountyName(),
            $this->postalCode,
            $countryName !== 'Romania' ? $countryName : null,
        ]);

        return implode(', ', $parts);
    }

    /**
     * Returneaza pretul formatat al plafonului de credit.
     */
    public function getFormattedCreditLimit(): ?string
    {
        if ($this->creditLimit === null) {
            return null;
        }

        return \App\Helper\NumberHelper::formatNumber($this->creditLimit) . ' RON';
    }

    public function __toString(): string
    {
        return $this->name ?? '';
    }
}
