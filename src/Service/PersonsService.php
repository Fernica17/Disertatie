<?php

namespace App\Service;

use App\Entity\Files;
use App\Entity\Persons;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Registry of people who can be looked up by face.
 *
 * Photos are enrolled into the `persons` collection of the face service, kept
 * apart from login accounts so a registry hit can never answer an
 * authentication question.
 */
class PersonsService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly FilesUploadService $filesUploadService,
        private readonly FaceRecognitionService $faceRecognition,
        private readonly LoggerInterface $logger,
        private readonly string $personsFilesDir,
    ) {
    }

    public function create(Persons $person): Persons
    {
        $this->entityManager->persist($person);
        $this->entityManager->flush();

        $this->logger->info('Person created', ['id' => $person->getId()]);

        return $person;
    }

    public function update(Persons $person): Persons
    {
        $this->entityManager->flush();

        return $person;
    }

    /**
     * Deletes a person and everything derived from their photo.
     *
     * Biometric data has to go with the record, not linger in the face service.
     */
    public function delete(Persons $person): void
    {
        $personId = $person->getId();

        $this->removePhoto($person);

        $this->entityManager->remove($person);
        $this->entityManager->flush();

        $this->logger->info('Person deleted', ['id' => $personId]);
    }

    /**
     * Stores the photo and registers the face for lookup.
     *
     * Mirrors the user flow: a photo the recogniser rejects is still kept, and
     * the caller gets a translation key back so it can explain why searching by
     * face will not find this person.
     *
     * @return ?string translation key describing the problem, null on success
     */
    public function storePhoto(Persons $person, UploadedFile $photo): ?string
    {
        foreach ($this->filesUploadService->getFilesForEntity($person, Files::TYPE_PERSON_PHOTO) as $old) {
            $this->filesUploadService->remove($old);
        }

        $stored = $this->filesUploadService->upload(
            $photo,
            $person,
            $this->personsFilesDir,
            Files::TYPE_PERSON_PHOTO,
        );

        $this->entityManager->flush();

        $path = $this->filesUploadService->absolutePath($stored);

        if ($path === null) {
            return 'persons.face.error.bad_image';
        }

        $result = $this->faceRecognition->enrollPerson($person, new File($path));

        if (!$result['ok']) {
            $this->logger->warning('Person face enrolment failed', [
                'person_id' => $person->getId(),
                'reason' => $result['reason'],
            ]);
        }

        return $result['ok'] ? null : $result['reason'];
    }

    public function removePhoto(Persons $person): void
    {
        foreach ($this->filesUploadService->getFilesForEntity($person, Files::TYPE_PERSON_PHOTO) as $file) {
            $this->filesUploadService->remove($file);
        }

        $this->entityManager->flush();
        $this->faceRecognition->deletePersonEnrollment($person);
    }

    public function photoOf(Persons $person): ?Files
    {
        return $this->filesUploadService->getFilesForEntity($person, Files::TYPE_PERSON_PHOTO)[0] ?? null;
    }
}
