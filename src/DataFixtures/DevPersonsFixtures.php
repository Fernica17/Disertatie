<?php

namespace App\DataFixtures;

use App\Entity\Cities;
use App\Entity\Countries;
use App\Entity\Persons;
use App\Service\PersonsService;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Demo records for the face-searchable person registry.
 *
 * The photos come from Labeled Faces in the Wild and are of real people; the
 * identity data attached to them here is invented and belongs to nobody. See
 * data/persons/README.md for provenance and citation.
 *
 * Each photo was checked against the detector before being committed, so a
 * failed enrolment here means the recogniser is unreachable, not that the
 * picture is unusable.
 */
class DevPersonsFixtures extends Fixture implements FixtureGroupInterface, DependentFixtureInterface
{
    /**
     * @var list<array{slug: string, firstName: string, lastName: string, nationalId: string, idDocument: string, birthDate: string, phone: string, email: string, address: string, city: string, notes: ?string, isActive: bool}>
     */
    private const array PEOPLE = [
        [
            'slug' => 'elena-vasilescu', 'firstName' => 'Elena', 'lastName' => 'Vasilescu',
            'nationalId' => '2940317402319', 'idDocument' => 'RD 448216', 'birthDate' => '1994-03-17',
            'phone' => '0722 145 903', 'email' => 'elena.vasilescu@example.ro',
            'address' => 'Str. Mihai Eminescu 42, ap. 7', 'city' => 'Sector 1',
            'notes' => 'Acces temporar la sediul central, valabil până la finalul contractului.',
            'isActive' => true,
        ],
        [
            'slug' => 'ioana-dobre', 'firstName' => 'Ioana', 'lastName' => 'Dobre',
            'nationalId' => '2831105121180', 'idDocument' => 'KX 702415', 'birthDate' => '1983-11-05',
            'phone' => '0731 208 447', 'email' => 'ioana.dobre@example.ro',
            'address' => 'Bd. 21 Decembrie 1989 nr. 58', 'city' => 'Cluj-Napoca',
            'notes' => null,
            'isActive' => true,
        ],
        [
            'slug' => 'mihai-ionescu', 'firstName' => 'Mihai', 'lastName' => 'Ionescu',
            'nationalId' => '1800629354024', 'idDocument' => 'TZ 315880', 'birthDate' => '1980-06-29',
            'phone' => '0745 611 320', 'email' => 'mihai.ionescu@example.ro',
            'address' => 'Str. Alba Iulia 12', 'city' => 'Timisoara',
            'notes' => 'Reprezentant furnizor. Se legitimează la fiecare vizită.',
            'isActive' => true,
        ],
        [
            'slug' => 'carmen-stanciu', 'firstName' => 'Carmen', 'lastName' => 'Stanciu',
            'nationalId' => '2750112220771', 'idDocument' => 'MX 190264', 'birthDate' => '1975-01-12',
            'phone' => '0723 877 105', 'email' => 'carmen.stanciu@example.ro',
            'address' => 'Str. Palat 5, bl. C2', 'city' => 'Iasi',
            'notes' => null,
            'isActive' => true,
        ],
        [
            'slug' => 'radu-petrescu', 'firstName' => 'Radu', 'lastName' => 'Petrescu',
            'nationalId' => '1650903083153', 'idDocument' => 'BV 554071', 'birthDate' => '1965-09-03',
            'phone' => '0740 332 918', 'email' => 'radu.petrescu@example.ro',
            'address' => 'Str. Lungă 118', 'city' => 'Brasov',
            'notes' => 'Contract încheiat în 2024. Acces retras.',
            'isActive' => false,
        ],
        [
            'slug' => 'adriana-marin', 'firstName' => 'Adriana', 'lastName' => 'Marin',
            'nationalId' => '2800421292642', 'idDocument' => 'PH 628440', 'birthDate' => '1980-04-21',
            'phone' => '0726 490 173', 'email' => 'adriana.marin@example.ro',
            'address' => 'Str. Republicii 30, ap. 15', 'city' => 'Ploiesti',
            'notes' => null,
            'isActive' => true,
        ],
        [
            'slug' => 'victor-georgescu', 'firstName' => 'Victor', 'lastName' => 'Georgescu',
            'nationalId' => '1631208131900', 'idDocument' => 'KT 087512', 'birthDate' => '1963-12-08',
            'phone' => '0744 205 663', 'email' => 'victor.georgescu@example.ro',
            'address' => 'Bd. Tomis 214', 'city' => 'Constanta',
            'notes' => 'Auditor extern.',
            'isActive' => true,
        ],
        [
            'slug' => 'andrei-munteanu', 'firstName' => 'Andrei', 'lastName' => 'Munteanu',
            'nationalId' => '1980714325215', 'idDocument' => 'SB 733901', 'birthDate' => '1998-07-14',
            'phone' => '0751 118 240', 'email' => 'andrei.munteanu@example.ro',
            'address' => 'Str. Nicolae Bălcescu 9', 'city' => 'Sibiu',
            'notes' => null,
            'isActive' => true,
        ],
        [
            'slug' => 'constantin-barbu', 'firstName' => 'Constantin', 'lastName' => 'Barbu',
            'nationalId' => '1550226260838', 'idDocument' => 'MS 411697', 'birthDate' => '1955-02-26',
            'phone' => '0742 907 511', 'email' => 'constantin.barbu@example.ro',
            'address' => 'Piața Trandafirilor 22', 'city' => 'Targu Mures',
            'notes' => 'Pensionar. Vizite ocazionale la arhivă.',
            'isActive' => true,
        ],
        [
            'slug' => 'mariana-nistor', 'firstName' => 'Mariana', 'lastName' => 'Nistor',
            'nationalId' => '2690809173473', 'idDocument' => 'GL 265338', 'birthDate' => '1969-08-09',
            'phone' => '0727 604 882', 'email' => 'mariana.nistor@example.ro',
            'address' => 'Str. Brăilei 161, bl. A4', 'city' => 'Galati',
            'notes' => null,
            'isActive' => true,
        ],
        [
            'slug' => 'rodica-anghel', 'firstName' => 'Rodica', 'lastName' => 'Anghel',
            'nationalId' => '2630530021569', 'idDocument' => 'AR 970124', 'birthDate' => '1963-05-30',
            'phone' => '0748 351 776', 'email' => 'rodica.anghel@example.ro',
            'address' => 'Str. Episcopiei 4', 'city' => 'Arad',
            'notes' => null,
            'isActive' => true,
        ],
        [
            'slug' => 'cristian-toma', 'firstName' => 'Cristian', 'lastName' => 'Toma',
            'nationalId' => '1831002064283', 'idDocument' => 'BH 542089', 'birthDate' => '1983-10-02',
            'phone' => '0733 812 095', 'email' => 'cristian.toma@example.ro',
            'address' => 'Str. Republicii 47', 'city' => 'Oradea',
            'notes' => 'Solicitare de acces în curs de aprobare.',
            'isActive' => true,
        ],
    ];

    public function __construct(
        private readonly PersonsService $personsService,
    ) {
    }

    /**
     * Also its own group, so the registry can be reloaded on its own with
     * `--group=persons --append` without purging the rest of the database.
     */
    public static function getGroups(): array
    {
        return ['dev', 'persons'];
    }

    public function getDependencies(): array
    {
        return [
            CitiesFixtures::class,
        ];
    }

    public function load(ObjectManager $manager): void
    {
        $romania = $manager->getRepository(Countries::class)->findOneBy(['code' => 'RO']);
        $photosDir = __DIR__ . '/data/persons';

        foreach (self::PEOPLE as $definition) {
            $person = new Persons();
            $person->setFirstName($definition['firstName']);
            $person->setLastName($definition['lastName']);
            $person->setNationalId($definition['nationalId']);
            $person->setIdDocument($definition['idDocument']);
            $person->setBirthDate(new \DateTimeImmutable($definition['birthDate']));
            $person->setPhone($definition['phone']);
            $person->setEmail($definition['email']);
            $person->setAddress($definition['address']);
            $person->setNotes($definition['notes']);
            $person->setIsActive($definition['isActive']);
            $person->setCountry($romania);

            // The city list stores names without diacritics, and Bucharest as
            // one county per sector, so 'city' above holds the stored spelling
            // rather than the one written in the address line.
            $city = $manager->getRepository(Cities::class)->findOneBy(['name' => $definition['city']]);

            if ($city !== null) {
                $person->setCity($city);
            }

            $this->personsService->create($person);

            $this->attachPhoto($person, $photosDir . '/' . $definition['slug'] . '.jpg');
        }
    }

    /**
     * The service moves the file it is given, so it works on a copy: a fixture
     * that consumed its own source data would only ever load once.
     */
    private function attachPhoto(Persons $person, string $source): void
    {
        if (!is_file($source)) {
            return;
        }

        $temporary = tempnam(sys_get_temp_dir(), 'person_photo_') . '.jpg';
        copy($source, $temporary);

        $this->personsService->storePhoto(
            $person,
            new UploadedFile($temporary, basename($source), 'image/jpeg', null, true),
        );
    }
}
