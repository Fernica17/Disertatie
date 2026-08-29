<?php

namespace App\Controller\Admin;

use App\Entity\Files;
use App\Enum\UserRole;
use App\Repository\UsersRepository;
use App\Security\Voter\UsersVoter;
use App\Service\FaceRecognitionService;
use App\Service\FilesUploadService;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Camera-based recognition.
 *
 * The browser never talks to the face service directly: that would put the
 * service's API key in JavaScript. Frames go through here, which holds the key,
 * throttles the matching endpoint and resolves a user id into something worth
 * showing.
 */
#[IsGranted('IS_AUTHENTICATED_REMEMBERED')]
class FaceRecognitionController extends AbstractController
{
    /** Frames larger than this are refused before reaching the face service. */
    private const int MAX_FRAME_BYTES = 4 * 1024 * 1024;

    public function __construct(
        private readonly FaceRecognitionService $faceRecognition,
        private readonly UsersRepository $usersRepository,
        private readonly FilesUploadService $filesUploadService,
        private readonly TranslatorInterface $translator,
        private readonly RateLimiterFactoryInterface $faceIdentifyLimiter,
    ) {
    }

    #[AdminRoute('/face-recognition', name: 'face_recognition', options: ['methods' => ['GET']])]
    public function page(): Response
    {
        $this->denyAccessUnlessGranted(UsersVoter::USERS_VIEW);

        return $this->render('admin/face/recognition.html.twig', [
            'enrolledCount' => \count($this->faceRecognition->enrolledUserIds()),
            'serviceAvailable' => $this->faceRecognition->isAvailable(),
        ]);
    }

    /**
     * Polled by the camera preview to decide whether the capture button is live.
     */
    #[AdminRoute('/face-recognition/detect', name: 'face_recognition_detect', options: ['methods' => ['POST']])]
    public function detect(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(UsersVoter::USERS_VIEW);

        $frame = $this->frameOrNull($request);

        if ($frame === null) {
            return $this->json(['ok' => false, 'usable' => false, 'message' => $this->trans('users.face.error.bad_image')], Response::HTTP_BAD_REQUEST);
        }

        $result = $this->faceRecognition->detect($frame);

        return $this->json([
            'ok' => $result['ok'],
            'usable' => $result['usable'],
            'faces' => $result['faces'],
            'face' => $result['face'],
            'message' => $this->detectMessage($result),
        ]);
    }

    /**
     * Matches a captured frame against every enrolled face.
     */
    #[AdminRoute('/face-recognition/identify', name: 'face_recognition_identify', options: ['methods' => ['POST']])]
    public function identify(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(UsersVoter::USERS_VIEW);

        // Matching is the expensive call and, once wired into login, the one
        // worth brute-forcing. Throttle it per client.
        $limiter = $this->faceIdentifyLimiter->create($request->getClientIp() ?? 'anonymous');

        if (!$limiter->consume()->isAccepted()) {
            return $this->json([
                'ok' => false,
                'matched' => false,
                'message' => $this->trans('users.face.error.rate_limited'),
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $frame = $this->frameOrNull($request);

        if ($frame === null) {
            return $this->json(['ok' => false, 'matched' => false, 'message' => $this->trans('users.face.error.bad_image')], Response::HTTP_BAD_REQUEST);
        }

        $result = $this->faceRecognition->identify($frame);

        if (!$result['ok']) {
            return $this->json([
                'ok' => false,
                'matched' => false,
                'message' => $this->translator->trans('users.face.result.failed', [
                    '{reason}' => $this->trans($result['reason'] ?? 'users.face.error.unavailable'),
                ], 'users'),
            ]);
        }

        if (!$result['matched'] || $result['userId'] === null) {
            return $this->json([
                'ok' => true,
                'matched' => false,
                'score' => $result['score'],
                'message' => $this->trans('users.face.result.no_match'),
            ]);
        }

        $user = $this->usersRepository->find($result['userId']);

        if ($user === null) {
            // Enrolled id with no user behind it: stale biometric data.
            return $this->json([
                'ok' => true,
                'matched' => false,
                'score' => $result['score'],
                'message' => $this->trans('users.face.result.unknown_user'),
            ]);
        }

        $avatar = $this->filesUploadService->getFilesForEntity($user, Files::TYPE_USER_AVATAR)[0] ?? null;

        return $this->json([
            'ok' => true,
            'matched' => true,
            'score' => $result['score'],
            'user' => [
                'id' => $user->getId(),
                'name' => $user->getFullName(),
                'email' => $user->getEmail(),
                'role' => $this->translator->trans(
                    UserRole::from($user->getPrimaryRole())->label(),
                    [],
                    'users',
                ),
                'avatarUrl' => $avatar
                    ? $this->generateUrl('admin_file_view', ['id' => $avatar->getId()])
                    : null,
            ],
        ]);
    }

    private function frameOrNull(Request $request): ?UploadedFile
    {
        $frame = $request->files->get('frame');

        if (!$frame instanceof UploadedFile || $frame->getSize() > self::MAX_FRAME_BYTES) {
            return null;
        }

        return $frame;
    }

    /**
     * @param array{usable: bool, faces: int, ok: bool} $result
     */
    private function detectMessage(array $result): string
    {
        return match (true) {
            !$result['ok'] => $this->translator->trans('users.face.result.failed', [
                '{reason}' => $this->trans('users.face.error.unavailable'),
            ], 'users'),
            $result['usable'] => $this->trans('users.face.detect.ready'),
            $result['faces'] > 1 => $this->trans('users.face.detect.multiple'),
            default => $this->trans('users.face.detect.searching'),
        };
    }

    private function trans(string $key): string
    {
        return $this->translator->trans($key, [], 'users');
    }
}
