<?php

namespace App\Message;

class Email
{
    private array $to;
    private string $subject;
    private string $template;
    private ?string $locale;
    private array $context;
    private array $attachments;
    private ?int $notification;
    private ?bool $renderFromTwig;

    public function __construct(array $to, string $subject, string $template, ?string $locale = null, array $context = [], array $attachments = [], ?int $notification = null, ?bool $renderFromTwig = true)
    {
        $this->to = $to;
        $this->subject = $subject;
        $this->template = $template;
        $this->locale = $locale;
        $this->context = $context;
        $this->attachments = $attachments;
        $this->notification = $notification;
        $this->renderFromTwig = $renderFromTwig;
    }

    public function getTo(): array
    {
        return $this->to;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function getTemplate(): string
    {
        return $this->template;
    }

    public function getLocale(): ?string
    {
        return $this->locale;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public function getAttachments(): array
    {
        return $this->attachments;
    }

    public function getNotification(): ?int
    {
        return $this->notification;
    }

    public function getRenderFromTwig(): ?bool
    {
        return $this->renderFromTwig;
    }
}
