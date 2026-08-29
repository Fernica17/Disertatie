<?php

namespace App\EntityListener;

use App\Entity\Companies;
use App\Service\FoldersService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsDoctrineListener(event: 'postPersist')]
#[AsDoctrineListener(event: 'preRemove')]
#[AsEventListener(event: KernelEvents::RESPONSE, method: 'onKernelResponse')]
class CompanyFolderListener
{
    /** @var Companies[] */
    private array $pendingCompanies = [];

    public function __construct(
        private readonly FoldersService $foldersService,
    ) {
    }

    public function postPersist(PostPersistEventArgs $event): void
    {
        $entity = $event->getObject();

        if (!$entity instanceof Companies) {
            return;
        }

        $this->pendingCompanies[] = $entity;
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest() || empty($this->pendingCompanies)) {
            return;
        }

        $companies = $this->pendingCompanies;
        $this->pendingCompanies = [];

        foreach ($companies as $company) {
            $this->foldersService->createClientFolder($company);
        }
    }

    public function preRemove(PreRemoveEventArgs $event): void
    {
        $entity = $event->getObject();

        if (!$entity instanceof Companies) {
            return;
        }

        $this->foldersService->deleteClientFolder($entity);
    }
}
