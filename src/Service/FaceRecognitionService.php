<?php

namespace App\Service;

use App\Entity\Users;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Client for the Python face service (see face-service/).
 *
 * The service scores faces; it never decides who may log in. Every call here
 * returns a result object instead of throwing, because enrolling a face must
 * never be able to block saving a user: a missing photo or an unreachable
 * container is a warning, not a failure of the whole operation.
 */
class FaceRecognitionService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $faceServiceUrl,
        private readonly string $faceApiKey,
    ) {
    }

    /**
     * Registers a photo as the reference face for a user.
     *
     * @return array{ok: bool, reason: ?string} reason is a translation key when ok is false
     */
    public function enroll(Users $user, File $photo): array
    {
        $userId = $user->getId();

        if ($userId === null) {
            return ['ok' => false, 'reason' => 'users.face.error.unsaved_user'];
        }

        // Replace rather than accumulate: the admin form offers a single photo,
        // so a new upload is a correction of the previous one.
        $this->deleteEnrollment($user);

        return $this->request('POST', sprintf('/faces/enroll/%d', $userId), $photo);
    }

    /**
     * Checks a frame against one specific user (1:1).
     *
     * Preferred over identify() for login: the caller states who it expects, so
     * a false match can only ever be against that one person.
     *
     * @return array{ok: bool, matched: bool, score: ?float, reason: ?string}
     */
    public function verify(Users $user, File $frame): array
    {
        $userId = $user->getId();

        if ($userId === null) {
            return ['ok' => false, 'matched' => false, 'score' => null, 'reason' => 'users.face.error.unsaved_user'];
        }

        $result = $this->request('POST', sprintf('/faces/verify/%d', $userId), $frame);

        return [
            'ok' => $result['ok'],
            'matched' => (bool) ($result['data']['matched'] ?? false),
            'score' => isset($result['data']['score']) ? (float) $result['data']['score'] : null,
            'reason' => $result['reason'],
        ];
    }

    /**
     * How many reference faces a user has. Zero means face login is unavailable.
     */
    public function enrolledSamples(Users $user): int
    {
        $userId = $user->getId();

        if ($userId === null) {
            return 0;
        }

        try {
            $response = $this->httpClient->request('GET', $this->url(sprintf('/faces/%d', $userId)), [
                'headers' => ['X-API-Key' => $this->faceApiKey],
                'timeout' => 5,
            ]);

            return (int) ($response->toArray(false)['samples'] ?? 0);
        } catch (ExceptionInterface $e) {
            $this->logger->warning('Face service unreachable while reading enrolment', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * Erases every face stored for a user.
     *
     * Biometric data is special-category personal data, so deletion has to be
     * wired into user deletion rather than left to a manual cleanup.
     */
    public function deleteEnrollment(Users $user): bool
    {
        $userId = $user->getId();

        if ($userId === null) {
            return false;
        }

        try {
            $this->httpClient->request('DELETE', $this->url(sprintf('/faces/%d', $userId)), [
                'headers' => ['X-API-Key' => $this->faceApiKey],
                'timeout' => 5,
            ])->getStatusCode();

            return true;
        } catch (ExceptionInterface $e) {
            $this->logger->warning('Could not delete face enrolment', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function isAvailable(): bool
    {
        try {
            $response = $this->httpClient->request('GET', $this->url('/health'), ['timeout' => 3]);

            return ($response->toArray(false)['status'] ?? '') === 'ok';
        } catch (ExceptionInterface) {
            return false;
        }
    }

    /**
     * @return array{ok: bool, reason: ?string, data: array}
     */
    private function request(string $method, string $path, File $photo): array
    {
        try {
            $response = $this->httpClient->request($method, $this->url($path), [
                'headers' => ['X-API-Key' => $this->faceApiKey],
                'body' => ['image' => fopen($photo->getPathname(), 'r')],
                'timeout' => 20,
            ]);

            $status = $response->getStatusCode();
            $data = $response->toArray(false);

            if ($status >= 200 && $status < 300) {
                return ['ok' => true, 'reason' => null, 'data' => $data];
            }

            return ['ok' => false, 'reason' => $this->reasonFor($status, $data), 'data' => $data];
        } catch (ExceptionInterface $e) {
            $this->logger->error('Face service call failed', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            return ['ok' => false, 'reason' => 'users.face.error.unavailable', 'data' => []];
        }
    }

    /**
     * Maps the service's HTTP codes onto messages an admin can act on.
     */
    private function reasonFor(int $status, array $data): string
    {
        $detail = (string) ($data['detail'] ?? '');

        return match (true) {
            $status === 422 && str_contains($detail, 'No face') => 'users.face.error.no_face',
            $status === 422 => 'users.face.error.multiple_faces',
            $status === 413 => 'users.face.error.too_large',
            $status === 400 => 'users.face.error.bad_image',
            $status === 404 => 'users.face.error.not_enrolled',
            $status === 401 => 'users.face.error.unauthorized',
            default => 'users.face.error.unavailable',
        };
    }

    private function url(string $path): string
    {
        return rtrim($this->faceServiceUrl, '/') . $path;
    }
}
