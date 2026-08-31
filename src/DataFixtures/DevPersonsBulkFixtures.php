<?php

namespace App\DataFixtures;

use App\Entity\Cities;
use App\Entity\Countries;
use App\Entity\Persons;
use App\Service\PersonsService;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use Faker\Generator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Fills the registry with as many faces as `app:demo:fetch-faces` left in
 * var/demo-faces/photos, so the search can be exercised at a realistic size.
 *
 * Deliberately kept out of the `dev` group: enrolling thousands of faces takes
 * minutes and depends on files that are not in the repository, so it must be
 * asked for by name.
 *
 * The photos are real people from Labeled Faces in the Wild; every identity
 * here is generated and belongs to nobody. See data/persons/README.md.
 */
class DevPersonsBulkFixtures extends Fixture implements FixtureGroupInterface, DependentFixtureInterface
{
    /** Flush the identity map this often so 3k records do not grow unbounded. */
    private const int BATCH_SIZE = 100;

    public function __construct(
        private readonly PersonsService $personsService,
        private readonly EntityManagerInterface $entityManager,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    public static function getGroups(): array
    {
        return ['persons-bulk'];
    }

    public function getDependencies(): array
    {
        return [
            CitiesFixtures::class,
        ];
    }

    public function load(ObjectManager $manager): void
    {
        $photos = glob($this->projectDir . '/var/demo-faces/photos/*.jpg') ?: [];

        if ($photos === []) {
            echo "No photos in var/demo-faces/photos. Run: bin/console app:demo:fetch-faces\n";

            return;
        }

        $faker = Factory::create('ro_RO');
        $faker->seed(20260830);

        $romania = $manager->getRepository(Countries::class)->findOneBy(['code' => 'RO']);
        $cityIds = $this->cityIds();
        $total = \count($photos);

        echo sprintf("Enrolling %d faces. This talks to the recogniser once per face.\n", $total);

        $enrolled = 0;
        $rejected = 0;

        $skipped = 0;

        foreach ($photos as $i => $path) {
            $name = $this->nameFromFile($path);

            if ($name === null) {
                ++$skipped;

                continue;
            }

            $person = $this->makePerson($faker, $romania, $name[0], $name[1]);

            if ($cityIds !== []) {
                $person->setCity($this->entityManager->getReference(Cities::class, $cityIds[array_rand($cityIds)]));
            }

            $this->personsService->create($person);

            // A registry record nobody can find by face is worse than no record:
            // it looks like the search is broken. Some LFW frames contain a
            // bystander, so drop whatever the recogniser will not take.
            if ($this->attachPhoto($person, $path) === null) {
                ++$enrolled;
            } else {
                $this->personsService->delete($person);
                ++$rejected;
            }

            if (($i + 1) % self::BATCH_SIZE === 0) {
                echo sprintf("  %d / %d\n", $i + 1, $total);
            }
        }

        echo sprintf(
            "Done: %d enrolled, %d rejected by the recogniser, %d skipped for a one-word name.\n",
            $enrolled,
            $rejected,
            $skipped,
        );
    }

    /**
     * Name and photo are the real person's; everything else is generated.
     *
     * No CNP or ID series here, unlike the twelve hand-picked records whose
     * names are invented too. Minting a national identity number for a real,
     * named person is a different thing from filling in a fictional one.
     */
    private function makePerson(Generator $faker, ?Countries $romania, string $firstName, string $lastName): Persons
    {
        $person = new Persons();
        $person->setFirstName($firstName);
        $person->setLastName($lastName);
        $person->setBirthDate(\DateTimeImmutable::createFromMutable($faker->dateTimeBetween('-70 years', '-19 years')));
        $person->setPhone($faker->phoneNumber());
        $person->setEmail($faker->unique()->safeEmail());
        $person->setAddress($faker->streetAddress());
        $person->setNotes($faker->boolean(20) ? $faker->sentence() : null);
        $person->setIsActive($faker->boolean(90));
        $person->setCountry($romania);

        return $person;
    }

    /**
     * Reads the person's name out of the file name, which the fetch command
     * took from the archive: `Aaron_Patterson.jpg` is the portrait of Aaron
     * Patterson, so the registry can say so rather than inventing a name.
     *
     * The first word is the given name and the rest is the family name, which
     * keeps particles together: `Fernando_Leon_de_Aranoa` reads as Fernando /
     * Leon de Aranoa rather than being split down the middle.
     *
     * @return array{0: string, 1: string}|null null when there is only one word
     */
    private function nameFromFile(string $path): ?array
    {
        $parts = preg_split('/[_-]+/', pathinfo($path, \PATHINFO_FILENAME)) ?: [];
        $parts = array_values(array_filter($parts, static fn (string $p): bool => $p !== ''));

        if (\count($parts) < 2) {
            return null;
        }

        // Older extracts lowercased the name; leave anything already cased alone
        $parts = array_map(
            static fn (string $p): string => $p === strtolower($p) ? ucfirst($p) : $p,
            $parts,
        );

        return [array_shift($parts), implode(' ', $parts)];
    }

    /**
     * Ids only: loading 16k city entities to pick one at random would cost more
     * memory than the rest of the fixture put together.
     *
     * @return list<int>
     */
    private function cityIds(): array
    {
        $rows = $this->entityManager->createQuery('SELECT c.id FROM ' . Cities::class . ' c')
            ->setMaxResults(2000)
            ->getScalarResult();

        return array_map(static fn (array $row): int => (int) $row['id'], $rows);
    }

    /**
     * The upload handler moves the file it is given, so hand it a copy or the
     * fixture would eat its own source data on the first run.
     *
     * @return string|null a translation key when the recogniser refused the photo
     */
    private function attachPhoto(Persons $person, string $source): ?string
    {
        $temporary = tempnam(sys_get_temp_dir(), 'demo_face_') . '.jpg';
        copy($source, $temporary);

        return $this->personsService->storePhoto(
            $person,
            new UploadedFile($temporary, basename($source), 'image/jpeg', null, true),
        );
    }
}
