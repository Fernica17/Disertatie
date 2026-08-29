<?php

namespace App\Service\Audit;

class DataSanitizerService
{
    private const array SENSITIVE_KEYS = [
        'password', 'passwd', 'pwd', 'old_password', 'new_password',
        'plainpassword', '_password', '_csrf_token',
        'token', 'access_token', 'refresh_token', 'api_key', 'secret',
        'email', 'mail',
        'phone', 'telephone', 'mobile', 'tel',
        'cnp', 'ssn',
        'card_number', 'cvv', 'cvc', 'iban', 'account_number',
    ];

    private const string REDACTED = '[REDACTED]';

    public function sanitize(array $data): array
    {
        $sanitized = [];

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $sanitized[$key] = $this->sanitize($value);
                continue;
            }

            if ($this->isSensitiveKey($key)) {
                $sanitized[$key] = self::REDACTED;
                continue;
            }

            if (is_string($value) && $this->isSensitiveValue($value)) {
                $sanitized[$key] = self::REDACTED;
                continue;
            }

            $sanitized[$key] = $value;
        }

        return $sanitized;
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalizedKey = strtolower(str_replace(['-', '.'], '_', $key));

        return in_array($normalizedKey, self::SENSITIVE_KEYS, true);
    }

    private function isSensitiveValue(string $value): bool
    {
        // Email pattern
        if (preg_match('/^[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}$/', $value)) {
            return true;
        }

        // Romanian phone number
        if (preg_match('/^0[0-9]{9}$/', $value)) {
            return true;
        }

        // CNP (Romanian personal identification number)
        if (preg_match('/^[1-9][0-9]{12}$/', $value)) {
            return true;
        }

        // Card number pattern
        if (preg_match('/^[0-9\s\-]{13,19}$/', $value)) {
            return true;
        }

        return false;
    }

    public function anonymizeIp(string $ip): string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = explode('.', $ip);
            $parts[3] = '0';

            return implode('.', $parts);
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $binary = inet_pton($ip);
            $mask = inet_pton('ffff:ffff:ffff::');

            return inet_ntop($binary & $mask);
        }

        return $ip;
    }
}
