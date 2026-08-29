<?php

namespace App\Service;

use App\Repository\CitiesRepository;
use App\Repository\CountiesRepository;
use App\Repository\CountriesRepository;

class LocationService
{
    public function __construct(
        private readonly CountriesRepository $countriesRepository,
        private readonly CountiesRepository $countiesRepository,
        private readonly CitiesRepository $citiesRepository,
    ) {
    }

    public function getActiveCountries(): array
    {
        return $this->countriesRepository->findBy(
            ['isActive' => true],
            ['name' => 'ASC']
        );
    }

    public function getCountiesByCountry(int $countryId): array
    {
        return $this->countiesRepository->findBy(
            ['country' => $countryId, 'isActive' => true],
            ['name' => 'ASC']
        );
    }

    public function getCitiesByCounty(int $countyId): array
    {
        return $this->citiesRepository->findBy(
            ['county' => $countyId, 'isActive' => true],
            ['name' => 'ASC']
        );
    }
}
