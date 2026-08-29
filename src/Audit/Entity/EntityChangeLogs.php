<?php

namespace App\Audit\Entity;

use App\Audit\Repository\EntityChangeLogsRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EntityChangeLogsRepository::class)]
#[ORM\Table(name: 'entity_change_logs')]
#[ORM\Index(columns: ['entity_class', 'entity_id'], name: 'idx_change_entity')]
#[ORM\Index(columns: ['user_id'], name: 'idx_change_user_id')]
#[ORM\Index(columns: ['created_at'], name: 'idx_change_created_at')]
#[ORM\Index(columns: ['action'], name: 'idx_change_action')]
#[ORM\Index(columns: ['entity_class', 'created_at'], name: 'idx_change_entity_date')]
class EntityChangeLogs
{
    public const string ACTION_CREATE = 'CREATE';
    public const string ACTION_UPDATE = 'UPDATE';
    public const string ACTION_DELETE = 'DELETE';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $entityClass = null;

    #[ORM\Column(type: Types::INTEGER)]
    private ?int $entityId = null;

    #[ORM\Column(length: 10)]
    private ?string $action = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $changes = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $userId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $username = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $entityLabel = null;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ipAddress = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEntityClass(): ?string
    {
        return $this->entityClass;
    }

    public function setEntityClass(string $entityClass): static
    {
        $this->entityClass = $entityClass;

        return $this;
    }

    public function getEntityId(): ?int
    {
        return $this->entityId;
    }

    public function setEntityId(int $entityId): static
    {
        $this->entityId = $entityId;

        return $this;
    }

    public function getAction(): ?string
    {
        return $this->action;
    }

    public function setAction(string $action): static
    {
        $this->action = $action;

        return $this;
    }

    public function getChanges(): ?array
    {
        return $this->changes;
    }

    public function setChanges(?array $changes): static
    {
        $this->changes = $changes;

        return $this;
    }

    public function getUserId(): ?int
    {
        return $this->userId;
    }

    public function setUserId(?int $userId): static
    {
        $this->userId = $userId;

        return $this;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(?string $username): static
    {
        $this->username = $username;

        return $this;
    }

    public function getEntityLabel(): ?string
    {
        return $this->entityLabel;
    }

    public function setEntityLabel(?string $entityLabel): static
    {
        $this->entityLabel = $entityLabel;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(?string $ipAddress): static
    {
        $this->ipAddress = $ipAddress;

        return $this;
    }

    public function getFormattedChanges(): string
    {
        if (empty($this->changes)) {
            return '<em>—</em>';
        }

        $lines = [];
        foreach ($this->changes as $field => $change) {
            if (!is_array($change)) {
                continue;
            }

            $old = $change['old'] ?? '—';
            $new = $change['new'] ?? '—';

            if (is_array($old)) {
                $old = json_encode($old, JSON_UNESCAPED_UNICODE);
            }
            if (is_array($new)) {
                $new = json_encode($new, JSON_UNESCAPED_UNICODE);
            }

            $old = htmlspecialchars((string) $old);
            $new = htmlspecialchars((string) $new);

            $lines[] = sprintf(
                '<strong>%s</strong>: <span style="color:#dc3545;text-decoration:line-through">%s</span> → <span style="color:#198754">%s</span>',
                htmlspecialchars($field),
                $old,
                $new
            );
        }

        $maxVisible = 3;
        $uid = 'ch_' . bin2hex(random_bytes(4));

        if (count($lines) <= $maxVisible) {
            return implode('<br>', $lines);
        }

        $visible = implode('<br>', array_slice($lines, 0, $maxVisible));
        $hidden = implode('<br>', array_slice($lines, $maxVisible));
        $moreCount = count($lines) - $maxVisible;

        return sprintf(
            '%s<span id="%s_rest" style="display:none"><br>%s</span><br>'
            . '<a href="#" onclick="var r=document.getElementById(\'%s_rest\'),t=this;'
            . 'if(r.style.display===\'none\'){r.style.display=\'inline\';t.textContent=\'↑ Restrânge\'}'
            . 'else{r.style.display=\'none\';t.textContent=\'↓ +%d modificări\'}'
            . ';return false" style="font-size:0.85em;color:#0d6efd">↓ +%d modificări</a>',
            $visible,
            $uid,
            $hidden,
            $uid,
            $moreCount,
            $moreCount
        );
    }
}
