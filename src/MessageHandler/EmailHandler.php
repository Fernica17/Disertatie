<?php

namespace App\MessageHandler;

use App\Helper\Mailer;
use App\Message\Email;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class EmailHandler
{
    private Mailer $mailer;

    public function __construct(Mailer $mailer)
    {
        $this->mailer = $mailer;
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function __invoke(Email $message): void
    {
        $this->mailer->sendSync($message->getTo(), $message->getSubject(), $message->getTemplate(), $message->getLocale(), $message->getContext(), $message->getAttachments(), [], $message->getNotification(), $message->getRenderFromTwig());
    }
}
