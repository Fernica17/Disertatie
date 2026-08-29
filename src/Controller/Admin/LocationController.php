<?php

namespace App\Controller\Admin;

use App\Repository\CitiesRepository;
use App\Service\LocationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/location')]
#[IsGranted('IS_AUTHENTICATED_REMEMBERED')]
class LocationController extends AbstractController
{
    public function __construct(
        private readonly LocationService $locationService,
        private readonly CitiesRepository $citiesRepository,
    ) {
    }

    #[Route('/countries', name: 'api_location_countries', methods: ['GET'])]
    public function countries(): JsonResponse
    {
        $countries = $this->locationService->getActiveCountries();

        $data = array_map(fn ($c) => [
            'id' => $c->getId(),
            'name' => $c->getName(),
        ], $countries);

        return $this->json($data);
    }

    #[Route('/counties/{countryId}', name: 'api_location_counties', methods: ['GET'])]
    public function counties(int $countryId): JsonResponse
    {
        $counties = $this->locationService->getCountiesByCountry($countryId);

        $data = array_map(fn ($c) => [
            'id' => $c->getId(),
            'name' => $c->getName(),
        ], $counties);

        return $this->json($data);
    }

    #[Route('/cities/{countyId}', name: 'api_location_cities', methods: ['GET'])]
    public function cities(int $countyId): JsonResponse
    {
        $cities = $this->locationService->getCitiesByCounty($countyId);

        $data = array_map(fn ($c) => [
            'id' => $c->getId(),
            'name' => $c->getName(),
        ], $cities);

        return $this->json($data);
    }

    #[Route('/city-context/{cityId}', name: 'api_location_city_context', methods: ['GET'])]
    public function cityContext(int $cityId): JsonResponse
    {
        $city = $this->citiesRepository->find($cityId);

        if (!$city) {
            return $this->json(['error' => 'City not found'], 404);
        }

        $county = $city->getCounty();
        $country = $county?->getCountry();

        return $this->json([
            'countryId' => $country?->getId(),
            'countyId' => $county?->getId(),
        ]);
    }
}
