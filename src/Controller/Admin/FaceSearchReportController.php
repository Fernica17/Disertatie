<?php

namespace App\Controller\Admin;

use App\Entity\FaceSearchReports;
use App\Entity\Users;
use App\Security\Voter\UsersVoter;
use App\Service\FaceRecognitionService;
use App\Service\FaceSearchReportService;
use App\Service\FilesUploadService;
use App\Service\FoldersService;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Saving and reading face search reports.
 *
 * The frame is matched again here rather than trusting scores posted by the
 * browser: a report is a record of what the recogniser said, so the numbers in
 * it have to come from the recogniser.
 */
#[IsGranted('IS_AUTHENTICATED_REMEMBERED')]
class FaceSearchReportController extends AbstractController
{
    private const int MAX_FRAME_BYTES = 4 * 1024 * 1024;
    private const array ALLOWED_FRAME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    public function __construct(
        private readonly FaceRecognitionService $faceRecognition,
        private readonly FaceSearchReportService $reportService,
        private readonly FilesUploadService $filesUploadService,
        private readonly FoldersService $foldersService,
        private readonly TranslatorInterface $translator,
        private readonly RateLimiterFactoryInterface $faceIdentifyLimiter,
    ) {
    }

    #[AdminRoute('/face-recognition/report', name: 'face_search_report_create', options: ['methods' => ['POST']])]
    public function create(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(UsersVoter::USERS_VIEW);

        if (!$this->faceIdentifyLimiter->create($request->getClientIp() ?? 'anonymous')->consume()->isAccepted()) {
            return $this->json([
                'ok' => false,
                'message' => $this->trans('users.face.error.rate_limited'),
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $frame = $this->frameOrNull($request);

        if ($frame === null) {
            return $this->json([
                'ok' => false,
                'message' => $this->trans('users.face.error.bad_image'),
            ], Response::HTTP_BAD_REQUEST);
        }

        $result = $this->faceRecognition->identify(
            $frame,
            FaceRecognitionService::COLLECTION_PERSONS,
            FaceSearchReportService::MAX_CANDIDATES,
        );

        if (!$result['ok']) {
            return $this->json([
                'ok' => false,
                'message' => $this->translator->trans('users.face.result.failed', [
                    '{reason}' => $this->trans($result['reason'] ?? 'users.face.error.unavailable'),
                ], 'users'),
            ]);
        }

        /** @var Users $user */
        $user = $this->getUser();

        $report = $this->reportService->save(
            $frame,
            $result,
            $user,
            $result['threshold'] ?? 0.0,
        );

        return $this->json([
            'ok' => true,
            'reportUrl' => $this->generateUrl('admin_face_report_show', ['id' => $report->getId()]),
            'message' => $this->trans('reports.flash.saved', 'persons'),
        ]);
    }

    #[AdminRoute('/face-reports/{id}', name: 'face_report_show', options: ['methods' => ['GET']])]
    public function show(FaceSearchReports $report): Response
    {
        $this->denyAccessUnlessGranted(UsersVoter::USERS_VIEW);

        $folder = $report->getFolder();

        return $this->render('admin/face_reports/report.html.twig', [
            'report' => $report,
            'maxCandidates' => FaceSearchReportService::MAX_CANDIDATES,
            'folderPath' => $folder === null
                ? []
                : array_map(static fn ($f) => $f->getName(), $this->foldersService->getFolderPath($folder)),
        ]);
    }

    #[AdminRoute('/face-reports/{id}/pdf', name: 'face_report_pdf', options: ['methods' => ['GET']])]
    public function pdf(FaceSearchReports $report): BinaryFileResponse
    {
        $this->denyAccessUnlessGranted(UsersVoter::USERS_VIEW);

        $file = $report->getPdfFile();
        $path = $file === null ? null : $this->filesUploadService->absolutePath($file);

        if ($file === null || $path === null) {
            throw $this->createNotFoundException('The report has no stored PDF.');
        }

        $response = new BinaryFileResponse($path);
        $response->headers->set('Content-Type', 'application/pdf');
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, $file->getOriginalName());

        return $response;
    }

    private function frameOrNull(Request $request): ?UploadedFile
    {
        $frame = $request->files->get('frame');

        if (!$frame instanceof UploadedFile || $frame->getSize() > self::MAX_FRAME_BYTES) {
            return null;
        }

        if (!\in_array($frame->getMimeType(), self::ALLOWED_FRAME_TYPES, true)) {
            return null;
        }

        return $frame;
    }

    private function trans(string $key, string $domain = 'users'): string
    {
        return $this->translator->trans($key, [], $domain);
    }
}
