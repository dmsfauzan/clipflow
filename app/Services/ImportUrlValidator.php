<?php

namespace App\Services;

use App\Exceptions\MediaProcessingException;
use Illuminate\Support\Str;

class ImportUrlValidator
{
    public function validate(string $url): string
    {
        $url = trim($url);

        if (mb_strlen($url) > 2048) {
            throw new MediaProcessingException('URL terlalu panjang.', 'url_validation');
        }

        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            throw new MediaProcessingException('Masukkan URL yang valid.', 'url_validation');
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            throw new MediaProcessingException('URL harus menggunakan http atau https.', 'url_validation');
        }

        if ($this->isBlockedHost($host)) {
            throw new MediaProcessingException('URL lokal atau jaringan privat tidak diizinkan.', 'url_security');
        }

        $allowedHosts = config('clipflow.download.allowed_hosts', []);

        if (is_array($allowedHosts) && $allowedHosts !== [] && ! $this->matchesAllowedHost($host, $allowedHosts)) {
            throw new MediaProcessingException('Host URL tidak termasuk dalam allowlist konfigurasi.', 'url_allowlist');
        }

        return $url;
    }

    public function redacted(string $url): string
    {
        $parts = parse_url($url);

        if (! is_array($parts) || ! isset($parts['host'])) {
            return '[url tidak valid]';
        }

        return sprintf(
            '%s://%s%s',
            $parts['scheme'] ?? 'https',
            $parts['host'],
            isset($parts['path']) ? Str::limit($parts['path'], 80, '…') : '',
        );
    }

    private function isBlockedHost(string $host): bool
    {
        if (in_array($host, ['localhost', 'localhost.localdomain', '0.0.0.0', '::1'], true) || str_ends_with($host, '.localhost')) {
            return true;
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return ! $this->isPublicIp($host);
        }

        if (! config('clipflow.download.dns_check', true)) {
            return false;
        }

        $ip = gethostbyname($host);

        if ($ip === $host) {
            return false;
        }

        return ! $this->isPublicIp($ip);
    }

    private function isPublicIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }

    /**
     * @param  array<int, string>  $allowedHosts
     */
    private function matchesAllowedHost(string $host, array $allowedHosts): bool
    {
        foreach ($allowedHosts as $allowed) {
            $allowed = strtolower(trim($allowed));

            if ($allowed !== '' && ($host === $allowed || str_ends_with($host, '.'.$allowed))) {
                return true;
            }
        }

        return false;
    }
}
