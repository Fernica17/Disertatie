<?php

namespace App\Service;

use App\Entity\Companies;
use App\Entity\Files;
use App\Repository\CompaniesRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class CompaniesService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CompaniesRepository $companiesRepository,
        private readonly LoggerInterface $logger,
        private readonly FilesUploadService $filesUploadService,
        private readonly string $companiesFilesDir,
    ) {
    }

    /**
     * Create a new company.
     */
    public function create(Companies $company): void
    {
        $this->entityManager->beginTransaction();

        try {
            $this->entityManager->persist($company);
            $this->entityManager->flush();
            $this->entityManager->commit();
        } catch (\Exception $e) {
            $this->entityManager->rollback();

            $this->logger->error('Failed to create company', [
                'error' => $e->getMessage(),
                'name' => $company->getName() ?? 'unknown',
            ]);

            throw $e;
        }

        $this->handleFileUploads($company);

        $this->logger->info('Company created', [
            'id' => $company->getId(),
            'name' => $company->getName(),
            'fiscal_code' => $company->getFiscalCode(),
        ]);
    }

    /**
     * Update existing company.
     */
    public function update(Companies $company): void
    {
        $this->entityManager->beginTransaction();

        try {
            $this->entityManager->flush();
            $this->entityManager->commit();
        } catch (\Exception $e) {
            $this->entityManager->rollback();

            $this->logger->error('Failed to update company', [
                'error' => $e->getMessage(),
                'company_id' => $company->getId(),
            ]);

            throw $e;
        }

        // Replace old logo if a new one was uploaded
        $logo = $company->getLogoUpload();
        if ($logo instanceof UploadedFile) {
            $oldLogos = $this->filesUploadService->getFilesForEntity($company, Files::TYPE_COMPANIES_LOGO);
            foreach ($oldLogos as $oldLogo) {
                $this->filesUploadService->remove($oldLogo, $this->companiesFilesDir);
            }
        }

        $this->handleFileUploads($company);

        $this->logger->info('Company updated', [
            'id' => $company->getId(),
            'name' => $company->getName(),
            'fiscal_code' => $company->getFiscalCode(),
        ]);
    }

    /**
     * Delete company.
     */
    public function delete(Companies $company): void
    {
        $this->entityManager->beginTransaction();

        try {
            $companyId = $company->getId();
            $companyName = $company->getName();

            $this->entityManager->remove($company);
            $this->entityManager->flush();
            $this->entityManager->commit();

            $this->logger->info('Company deleted', [
                'id' => $companyId,
                'name' => $companyName,
            ]);
        } catch (\Exception $e) {
            $this->entityManager->rollback();

            $this->logger->error('Failed to delete company', [
                'error' => $e->getMessage(),
                'company_id' => $company->getId(),
            ]);

            throw $e;
        }
    }

    /**
     * Find company by ID.
     */
    public function findById(int $id): ?Companies
    {
        return $this->companiesRepository->find($id);
    }

    /**
     * Find all active companies.
     */
    public function findAllActive(): array
    {
        return $this->companiesRepository->findAllActive();
    }

    private function handleFileUploads(Companies $company): void
    {
        $logo = $company->getLogoUpload();
        if ($logo instanceof UploadedFile) {
            $this->filesUploadService->upload(
                $logo,
                $company,
                $this->companiesFilesDir,
                Files::TYPE_COMPANIES_LOGO
            );
        }

        $documents = $company->getDocumentsUpload();
        if (!empty($documents)) {
            $this->filesUploadService->uploadBatch(
                $documents,
                $company,
                $this->companiesFilesDir,
                Files::TYPE_COMPANIES_DOCUMENT
            );
        }
    }
}
