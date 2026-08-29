<?php

namespace App\Helper;

use App\Message\Email;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Mime\Address;
use Twig\Environment;

class Mailer
{
    private MailerInterface $mailer;
    private string $senderEmail;
    private string $senderName;
    private MessageBusInterface $bus;
    private string $defaultLocale;
    private Environment $environment;
    private LoggerInterface $logger;

    public function __construct(
        MailerInterface $mailer,
        string $senderEmail,
        string $senderName,
        MessageBusInterface $bus,
        string $defaultLocale,
        Environment $environment,
        LoggerInterface $logger,
    ) {
        $this->mailer = $mailer;
        $this->senderEmail = $senderEmail;
        $this->senderName = $senderName;
        $this->bus = $bus;
        $this->defaultLocale = $defaultLocale;
        $this->environment = $environment;
        $this->logger = $logger;
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function sendEmail(array $toAddresses, string $subject, string $template, ?string $locale = null, array $context = [], array $attachments = [], array $ccAddresses = [], ?int $notification = null, bool $renderFromTwig = true): void
    {
        $syncTemplates = [];

        if (empty($toAddresses)) {
            return;
        }

        if (in_array($template, $syncTemplates)) {
            $this->sendSync($toAddresses, $subject, $template, $locale, $context, $attachments, $ccAddresses, $notification, $renderFromTwig);
        } else {
            $this->sendAsync($toAddresses, $subject, $template, $locale, $context, $attachments, $ccAddresses, $notification, $renderFromTwig);
        }
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function sendSync(array $toAddresses, string $subject, string $template, ?string $locale = null, array $context = [], array $attachments = [], array $ccAddresses = [], ?int $notification = null, bool $renderFromTwig = true): void
    {
        $context['locale'] = null === $locale ? $this->defaultLocale : $locale;
        if (!empty($_ENV['TESTING_EMAIL_ADDRESS'])) {
            $toAddresses = $ccAddresses = [$_ENV['TESTING_EMAIL_ADDRESS']];
        }

        $content = $renderFromTwig ? $this->environment->render($template, $context) : $template;

        $email = (new TemplatedEmail())
            ->from(new Address($this->senderEmail, $this->senderName))
            ->to(...$toAddresses)
            ->cc(...$ccAddresses)
            ->subject($subject)
            ->html($content);
        foreach ($attachments as $attachment) {
            $email->attachFromPath($attachment);
        }

        try {
            $this->mailer->send($email);
            $this->logger->notice('Success send email for user: ' . json_encode($toAddresses));
        } catch (TransportExceptionInterface $e) {
            $this->logger->critical('sendSync: ' . $e->getMessage());
            throw $e;
        }
    }

    public function sendAsync(array $toAddresses, string $subject, string $template, ?string $locale = null, array $context = [], array $attachments = [], array $ccAddresses = [], ?int $notification = null, bool $renderFromTwig = true): void
    {
        $this->bus->dispatch(new Email($toAddresses, $subject, $template, $locale, $context, $attachments, $notification, $renderFromTwig));
    }
}
