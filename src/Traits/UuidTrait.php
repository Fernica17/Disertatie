<?php

namespace App\Traits;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

trait UuidTrait
{
    #[ORM\Column(type: 'uuid', unique: true)]
    private ?Uuid $uid = null;

    #[ORM\PrePersist]
    public function generateUid(): void
    {
        if ($this->uid === null) {
            $this->uid = Uuid::v4();
        }
    }

    public function getUid(): ?Uuid
    {
        return $this->uid;
    }
}
