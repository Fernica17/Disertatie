<?php

namespace App\EntityListener;

use App\Entity\Notifications;
use App\Helper\Mailer;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Events;

#[AsDoctrineListener(event: Events::postPersist, priority: 500, connection: 'default')]
readonly class NotificationsListener
{
    public function __construct(
        private Mailer $mailer,
    ) {
    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        $notification = $args->getObject();

        if (!$notification instanceof Notifications) {
            return;
        }

        $meta = $notification->getMeta() ?? [];

        $context = array_merge([
            'user' => $notification->getUser(),
            'title' => $notification->getTitle(),
            'meta' => $meta,
        ], $meta);

        $template = 'emails/notifications/' . $notification->getType() . '.html.twig';

        $this->mailer->sendEmail(
            [$notification->getUserChannel()],
            $notification->getTitle(),
            $template,
            null,
            $context,
            [],
            [],
            $notification->getId()
        );
    }
}
