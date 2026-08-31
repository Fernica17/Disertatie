<?php

namespace App\Service;

use App\Entity\Persons;
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
    /** Login accounts. Only this collection may ever answer an authentication question. */
    public const string COLLECTION_USERS = 'users';

    /** The lookup registry, searched by face but never able to sign anyone in. */
    public const string COLLECTION_PERSONS = 'persons';

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

        return $this->request('POST', sprintf('/faces/enroll/%d', $userId), $photo, self::COLLECTION_USERS);
    }

    /**
     * Registers a photo as the reference face for a registry person.
     *
     * @return array{ok: bool, reason: ?string}
     */
    public function enrollPerson(Persons $person, File $photo): array
    {
        $personId = $person->getId();

        if ($personId === null) {
            return ['ok' => false, 'reason' => 'persons.face.error.unsaved'];
        }

        $this->deletePersonEnrollment($person);

        return $this->request(
            'POST',
            sprintf('/faces/enroll/%d', $personId),
            $photo,
            self::COLLECTION_PERSONS,
        );
    }

    /** Erases a registry person's biometric data. */
    public function deletePersonEnrollment(Persons $person): bool
    {
        return $this->deleteEnrollmentFor($person->getId(), self::COLLECTION_PERSONS);
    }

    /**
     * Is there exactly one usable face in the frame?
     *
     * Cheap enough to poll from a camera preview: detection only, no embedding.
     *
     * @return array{ok: bool, usable: bool, face: ?array, faces: int, reason: ?string}
     */
    public function detect(File $frame): array
    {
        $result = $this->request('POST', '/faces/detect', $frame);

        return [
            'ok' => $result['ok'],
            'usable' => (bool) ($result['data']['usable'] ?? false),
            'faces' => (int) ($result['data']['faces'] ?? 0),
            'face' => $result['data']['face'] ?? null,
            'reason' => $result['reason'],
        ];
    }

    /**
     * Who is in the frame? Searches every enrolled face (1:N).
     *
     * `candidates` asks the recogniser for the ranked runners-up as well, which
     * a report needs: the verdict alone does not show how close the field was.
     *
     * @return array{ok: bool, matched: bool, userId: ?int, score: ?float, threshold: ?float, reason: ?string, candidates: list<array{userId: int, score: float}>}
     */
    public function identify(File $frame, string $collection = self::COLLECTION_USERS, int $candidates = 0): array
    {
        $query = $candidates > 0 ? ['candidates' => $candidates] : [];
        $result = $this->request('POST', '/faces/identify', $frame, $collection, $query);
        $data = $result['data'];

        return [
            'ok' => $result['ok'],
            'matched' => (bool) ($data['matched'] ?? false),
            'userId' => isset($data['user_id']) ? (int) $data['user_id'] : null,
            'score' => isset($data['score']) ? (float) $data['score'] : null,
            'threshold' => isset($data['threshold']) ? (float) $data['threshold'] : null,
            'reason' => $result['reason'],
            'candidates' => array_values(array_map(
                static fn (array $c): array => [
                    'userId' => (int) $c['user_id'],
                    'score' => (float) $c['score'],
                ],
                $data['candidates'] ?? [],
            )),
        ];
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

        $result = $this->request('POST', sprintf('/faces/verify/%d', $userId), $frame, self::COLLECTION_USERS);

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
        return $this->samplesFor($user->getId(), self::COLLECTION_USERS);
    }

    /**
     * How many reference faces the registry holds for this person.
     *
     * Zero means the record exists but cannot be found by face: either no photo
     * was uploaded, or the recogniser rejected the one that was.
     */
    public function enrolledSamplesForPerson(Persons $person): int
    {
        return $this->samplesFor($person->getId(), self::COLLECTION_PERSONS);
    }

    private function samplesFor(?int $subjectId, string $collection): int
    {
        if ($subjectId === null) {
            return 0;
        }

        try {
            $response = $this->httpClient->request('GET', $this->url(sprintf('/faces/%d?collection=%s', $subjectId, $collection)), [
                'headers' => ['X-API-Key' => $this->faceApiKey],
                'timeout' => 5,
            ]);

            return (int) ($response->toArray(false)['samples'] ?? 0);
        } catch (ExceptionInterface $e) {
            $this->logger->warning('Face service unreachable while reading enrolment', [
                'subject_id' => $subjectId,
                'collection' => $collection,
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * User ids that currently have a reference face.
     *
     * @return list<int>
     */
    public function enrolledUserIds(): array
    {
        try {
            $response = $this->httpClient->request('GET', $this->url('/faces?collection=' . self::COLLECTION_USERS), [
                'headers' => ['X-API-Key' => $this->faceApiKey],
                'timeout' => 5,
            ]);

            return array_map(intval(...), $response->toArray(false));
        } catch (ExceptionInterface $e) {
            $this->logger->warning('Face service unreachable while listing enrolments', [
                'error' => $e->getMessage(),
            ]);

            return [];
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
        return $this->deleteEnrollmentFor($user->getId(), self::COLLECTION_USERS);
    }

    private function deleteEnrollmentFor(?int $subjectId, string $collection): bool
    {
        if ($subjectId === null) {
            return false;
        }

        try {
            $this->httpClient->request('DELETE', $this->url(sprintf('/faces/%d?collection=%s', $subjectId, $collection)), [
                'headers' => ['X-API-Key' => $this->faceApiKey],
                'timeout' => 5,
            ])->getStatusCode();

            return true;
        } catch (ExceptionInterface $e) {
            $this->logger->warning('Could not delete face enrolment', [
                'subject_id' => $subjectId,
                'collection' => $collection,
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
    /**
     * @param array<string, scalar> $query
     */
    private function request(string $method, string $path, File $photo, ?string $collection = null, array $query = []): array
    {
        $url = $this->url($path);

        if ($collection !== null) {
            $query = ['collection' => $collection] + $query;
        }

        if ($query !== []) {
            $url .= '?' . http_build_query($query);
        }

        try {
            $response = $this->httpClient->request($method, $url, [
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
