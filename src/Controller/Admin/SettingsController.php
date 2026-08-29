<?php

namespace App\Controller\Admin;

use App\Entity\Settings;
use App\Form\Type\SettingsFormType;
use App\Security\Voter\AdministrationVoter;
use App\Service\SettingsService;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

class SettingsController extends AbstractController
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly SettingsService $settingsService,
    ) {
    }

    #[AdminRoute('/settings', name: 'settings')]
    #[IsGranted(AdministrationVoter::ADMINISTRATION_EDIT)]
    public function settings(Request $request): Response
    {
        $currentValues = $this->settingsService->getAll();
        $defaults = SettingsService::getDefaults();

        $mergedValues = [];
        foreach ($defaults as $key => $def) {
            $mergedValues[$key] = $currentValues[$key] ?? $def['value'];
        }

        $form = $this->createForm(SettingsFormType::class, null, [
            'current_values' => $mergedValues,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $toSave = [];
            foreach ($defaults as $key => $def) {
                $value = $form->get($key)->getData();
                $toSave[$key] = $value !== null ? (string) $value : '';
            }

            $this->settingsService->saveAll($toSave);

            $this->addFlash('success', $this->translator->trans('admin.settings.saved', [], 'admin'));

            return $this->redirectToRoute('admin_settings');
        }

        return $this->render('admin/settings/index.html.twig', [
            'form' => $form,
            'fieldGroups' => $this->getFieldGroups($defaults),
            'groupLabels' => $this->getGroupLabels(),
            'groupIcons' => $this->getGroupIcons(),
        ]);
    }

    /**
     * @return array<string, list<string>>
     */
    private function getFieldGroups(array $defaults): array
    {
        $groups = [];
        foreach ($defaults as $key => $def) {
            $groups[$def['group']][] = $key;
        }

        $order = [
            Settings::GROUP_GENERAL,
            Settings::GROUP_FINANCIAL,
            Settings::GROUP_SERIES,
            Settings::GROUP_NOTIFICATIONS,
        ];
        $sorted = [];
        foreach ($order as $group) {
            if (isset($groups[$group])) {
                $sorted[$group] = $groups[$group];
            }
        }
        foreach ($groups as $group => $fields) {
            if (!isset($sorted[$group])) {
                $sorted[$group] = $fields;
            }
        }

        return $sorted;
    }

    /**
     * @return array<string, string>
     */
    private function getGroupLabels(): array
    {
        return [
            Settings::GROUP_GENERAL => $this->translator->trans('admin.settings.group.general', [], 'admin'),
            Settings::GROUP_FINANCIAL => $this->translator->trans('admin.settings.group.financial', [], 'admin'),
            Settings::GROUP_SERIES => $this->translator->trans('admin.settings.group.series', [], 'admin'),
            Settings::GROUP_NOTIFICATIONS => $this->translator->trans('admin.settings.group.notifications', [], 'admin'),
            Settings::GROUP_FILES => $this->translator->trans('admin.settings.group.files', [], 'admin'),
            Settings::GROUP_DISPLAY => $this->translator->trans('admin.settings.group.display', [], 'admin'),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function getGroupIcons(): array
    {
        return [
            Settings::GROUP_GENERAL => 'fa-solid fa-gear',
            Settings::GROUP_FINANCIAL => 'fa-solid fa-calculator',
            Settings::GROUP_SERIES => 'fa-solid fa-hashtag',
            Settings::GROUP_NOTIFICATIONS => 'fa-solid fa-bell',
            Settings::GROUP_FILES => 'fa-solid fa-file',
            Settings::GROUP_DISPLAY => 'fa-solid fa-display',
        ];
    }
}
