<?php

namespace App\Controller;

use App\Repository\UsersRepository;
use App\Service\FaceRecognitionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Face-assisted sign in.
 *
 * The camera identifies which account you are, then the normal password form
 * completes the login. Face recognition alone is not an authentication factor:
 * a printed photo passes, and there is no liveness check yet. Keeping the
 * password step means this page adds convenience without weakening anything,
 * and it reuses the existing, tested authenticator rather than introducing a
 * second way into the application.
 *
 * Everything here is reachable without a session, so both endpoints are
 * rate limited per client address.
 */
class FaceLoginController extends AbstractController
{
    private const int MAX_FRAME_BYTES = 4 * 1024 * 1024;

    public function __construct(
        private readonly FaceRecognitionService $faceRecognition,
        private readonly UsersRepository $usersRepository,
        private readonly TranslatorInterface $translator,
        private readonly RateLimiterFactoryInterface $faceLoginDetectLimiter,
        private readonly RateLimiterFactoryInterface $faceLoginIdentifyLimiter,
    ) {
    }

    #[Route('/login/face', name: 'app_login_face', methods: ['GET'])]
    public function page(): Response
    {
        // Someone already signed in has no business on a login page.
        if ($this->getUser() !== null) {
            return $this->redirectToRoute('admin');
        }

        return $this->render('security/face_login.html.twig', [
            'serviceAvailable' => $this->faceRecognition->isAvailable(),
        ]);
    }

    #[Route('/login/face/detect', name: 'app_login_face_detect', methods: ['POST'])]
    public function detect(Request $request): JsonResponse
    {
        if (!$this->faceLoginDetectLimiter->create($this->clientKey($request))->consume()->isAccepted()) {
            return $this->tooManyRequests();
        }

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
            'message' => match (true) {
                !$result['ok'] => $this->trans('users.face.error.unavailable'),
                $result['usable'] => $this->trans('users.face.detect.ready'),
                $result['faces'] > 1 => $this->trans('users.face.detect.multiple'),
                default => $this->trans('users.face.detect.searching'),
            },
        ]);
    }

    /**
     * Identifies the account in the frame. Never signs anyone in.
     */
    #[Route('/login/face/identify', name: 'app_login_face_identify', methods: ['POST'])]
    public function identify(Request $request): JsonResponse
    {
        if (!$this->faceLoginIdentifyLimiter->create($this->clientKey($request))->consume()->isAccepted()) {
            return $this->tooManyRequests();
        }

        $frame = $this->frameOrNull($request);

        if ($frame === null) {
            return $this->json(['ok' => false, 'matched' => false, 'message' => $this->trans('users.face.error.bad_image')], Response::HTTP_BAD_REQUEST);
        }

        $result = $this->faceRecognition->identify($frame);

        if (!$result['ok'] || !$result['matched'] || $result['userId'] === null) {
            return $this->json([
                'ok' => $result['ok'],
                'matched' => false,
                'message' => $this->trans('security.face_login.no_match'),
            ]);
        }

        $user = $this->usersRepository->find($result['userId']);

        // An inactive or deleted account must not be revealed as recognisable.
        if ($user === null || !$user->isActive()) {
            return $this->json([
                'ok' => true,
                'matched' => false,
                'message' => $this->trans('security.face_login.no_match'),
            ]);
        }

        return $this->json([
            'ok' => true,
            'matched' => true,
            // The score stays server-side: it is a tuning detail, and publishing
            // it on an anonymous endpoint helps someone calibrate a spoof.
            'user' => [
                'name' => $user->getFirstName(),
                'email' => $user->getEmail(),
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

    private function clientKey(Request $request): string
    {
        return $request->getClientIp() ?? 'anonymous';
    }

    private function tooManyRequests(): JsonResponse
    {
        return $this->json([
            'ok' => false,
            'matched' => false,
            'usable' => false,
            'message' => $this->trans('users.face.error.rate_limited'),
        ], Response::HTTP_TOO_MANY_REQUESTS);
    }

    private function trans(string $key): string
    {
        $domain = str_starts_with($key, 'security.') ? 'security' : 'users';

        return $this->translator->trans($key, [], $domain);
    }
}
