<?php

namespace App\Service;

use App\Controller\Admin\CompaniesCrudController;
use App\Entity\Users;
use App\Enum\CompanyStatus;
use App\Enum\UserRole;
use App\Repository\CitiesRepository;
use App\Repository\CompaniesRepository;
use App\Repository\CountiesRepository;
use App\Repository\CountriesRepository;
use App\Repository\NotificationsRepository;
use App\Repository\UsersRepository;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Builds the per-role dashboard payload.
 *
 * Scoped to the modules this application currently ships: companies, users
 * and the location nomenclator. Add a section here as each new module lands.
 */
class DashboardService
{
    public function __construct(
        private readonly CompaniesRepository $companiesRepository,
        private readonly UsersRepository $usersRepository,
        private readonly CountriesRepository $countriesRepository,
        private readonly CountiesRepository $countiesRepository,
        private readonly CitiesRepository $citiesRepository,
        private readonly NotificationsRepository $notificationsRepository,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function getAdminData(Users $user): array
    {
        $statusCounts = $this->companiesRepository->countByStatus();
        $roleCounts = $this->usersRepository->countByRole();

        return [
            'kpi' => [
                'companies_total' => array_sum($statusCounts),
                'companies_active' => $statusCounts[CompanyStatus::ACTIVE->value] ?? 0,
                'companies_prospect' => $statusCounts[CompanyStatus::PROSPECT->value] ?? 0,
                'users_active' => $this->usersRepository->countActive(),
                'users_inactive' => $this->usersRepository->countInactive(),
            ],
            'charts' => [
                'companies_by_status' => $this->buildCompaniesByStatusChart($statusCounts),
                'users_by_role' => $this->buildUsersByRoleChart($roleCounts),
                'companies_per_month' => $this->buildMonthlyChart($this->companiesRepository->countPerMonth()),
                'users_per_month' => $this->buildMonthlyChart($this->usersRepository->countPerMonth()),
            ],
            'recent_companies' => $this->companiesRepository->findRecent(10),
            'recent_users' => $this->usersRepository->findRecent(10),
            'nomenclator' => $this->buildNomenclatorCounts(),
            'alerts' => $this->buildAlerts(),
            'notifications' => $this->notificationsRepository->getUnreadNotifications($user, 10),
        ];
    }

    public function getManagerData(Users $user): array
    {
        $statusCounts = $this->companiesRepository->countByStatus();

        return [
            'kpi' => [
                'companies_total' => array_sum($statusCounts),
                'companies_active' => $statusCounts[CompanyStatus::ACTIVE->value] ?? 0,
                'companies_prospect' => $statusCounts[CompanyStatus::PROSPECT->value] ?? 0,
            ],
            'charts' => [
                'companies_by_status' => $this->buildCompaniesByStatusChart($statusCounts),
                'companies_per_month' => $this->buildMonthlyChart($this->companiesRepository->countPerMonth()),
            ],
            'recent_companies' => $this->companiesRepository->findRecent(10),
            'alerts' => $this->buildAlerts(),
            'notifications' => $this->notificationsRepository->getUnreadNotifications($user, 10),
        ];
    }

    public function getClientData(Users $user): array
    {
        $company = $user->getCompany();

        return [
            'company' => $company,
            'colleagues' => $company !== null
                ? $this->usersRepository->findBy(['company' => $company], ['lastName' => 'ASC', 'firstName' => 'ASC'])
                : [],
            'notifications' => $this->notificationsRepository->getUnreadNotifications($user, 10),
        ];
    }

    /**
     * @param array<string, int> $statusCounts
     */
    private function buildCompaniesByStatusChart(array $statusCounts): array
    {
        $labels = [];
        $series = [];

        foreach (CompanyStatus::cases() as $status) {
            $count = $statusCounts[$status->value] ?? 0;
            if ($count === 0) {
                continue;
            }
            $labels[] = $this->translator->trans($status->label(), [], 'companies');
            $series[] = $count;
        }

        return ['labels' => $labels, 'series' => $series];
    }

    /**
     * @param array<string, int> $roleCounts
     */
    private function buildUsersByRoleChart(array $roleCounts): array
    {
        $labels = [];
        $series = [];

        foreach (UserRole::cases() as $role) {
            $count = $roleCounts[$role->value] ?? 0;
            if ($count === 0) {
                continue;
            }
            $labels[] = $this->translator->trans($role->label(), [], 'users');
            $series[] = $count;
        }

        return ['labels' => $labels, 'series' => $series];
    }

    /**
     * @param array<string, float> $monthly 'YYYY-MM' => value
     */
    private function buildMonthlyChart(array $monthly): array
    {
        $labels = [];
        $series = [];

        foreach ($monthly as $month => $value) {
            $labels[] = (new \DateTimeImmutable($month . '-01'))->format('M Y');
            $series[] = $value;
        }

        return ['labels' => $labels, 'series' => $series];
    }

    private function buildNomenclatorCounts(): array
    {
        return [
            'countries' => $this->countriesRepository->count([]),
            'counties' => $this->countiesRepository->count([]),
            'cities' => $this->citiesRepository->count([]),
        ];
    }

    /**
     * Companies that need attention: blocked, suspended, or missing a fiscal code.
     */
    private function buildAlerts(): array
    {
        $alerts = [];

        foreach ([CompanyStatus::BLOCKED, CompanyStatus::SUSPENDED] as $status) {
            $count = $this->companiesRepository->countByStatusValue($status);
            if ($count === 0) {
                continue;
            }

            $alerts[] = [
                'type' => $status === CompanyStatus::BLOCKED ? 'danger' : 'warning',
                'icon' => $status === CompanyStatus::BLOCKED ? 'fa-ban' : 'fa-pause',
                'title' => $this->translator->trans($status->label(), [], 'companies'),
                'subtitle' => $this->translator->trans('admin.dashboard.alert.companies_count', ['%count%' => $count], 'admin'),
                'controller' => CompaniesCrudController::class,
            ];
        }

        return $alerts;
    }
}
