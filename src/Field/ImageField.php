<?php

namespace App\Field;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\FieldTrait;
use Symfony\Component\Form\Extension\Core\Type\FileType;

final class ImageField implements FieldInterface
{
    use FieldTrait;

    public const OPTION_UPLOAD_DIR = 'uploadDir';
    public const OPTION_UPLOAD_PATH = 'uploadPath';
    public const OPTION_MAX_SIZE = 'maxSize';
    public const OPTION_ALLOWED_EXTENSIONS = 'allowedExtensions';

    public static function new(string $propertyName, ?string $label = null): self
    {
        return (new self())
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setTemplateName('crud/field/image')
            ->setFormType(FileType::class)
            ->addCssClass('field-image')
            ->setCustomOption(self::OPTION_MAX_SIZE, '5M')
            ->setCustomOption(self::OPTION_ALLOWED_EXTENSIONS, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
    }

    public function setUploadDir(string $dir): self
    {
        return $this->setCustomOption(self::OPTION_UPLOAD_DIR, $dir);
    }

    public function setBasePath(string $path): self
    {
        return $this->setCustomOption(self::OPTION_UPLOAD_PATH, $path);
    }

    public function setMaxSize(string $maxSize): self
    {
        return $this->setCustomOption(self::OPTION_MAX_SIZE, $maxSize);
    }

    public function setAllowedExtensions(array $extensions): self
    {
        return $this->setCustomOption(self::OPTION_ALLOWED_EXTENSIONS, $extensions);
    }
}
