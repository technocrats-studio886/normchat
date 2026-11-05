<?php

namespace App\Services\Links;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Membaca judul, nama situs, deskripsi, dan gambar sebuah tautan (og:/twitter:/<title>).
 * Aman terhadap SSRF: hanya http/https, host yang mengarah ke alamat privat/loopback/link-local ditolak,
 * koneksi dipaku ke IP yang sudah dicek (tidak bisa dibelokkan DNS rebinding), redirect dicek ulang,
 * batas waktu 6 detik, dan hanya 512 KB pertama yang dibaca.
 */
class LinkPreviewFetcher
{
    private const MAX_BYTES = 524288;
    private const MAX_REDIRECTS = 3;
    private const USER_AGENT = 'NormiChatBot/1.0 (+https://normi.chat)';

    public function __construct(private readonly HostResolver $resolver)
    {
    }

    /** @return array{title: ?string, site_name: ?string, description: ?string, image_url: ?string}|null */
    public function fetch(string $url): ?array
    {
        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            $target = $this->safeTarget($url);
            if ($target === null) {
                return null;
            }
            [$host, $port, $ip] = $target;
            try {
                $response = Http::withHeaders(['User-Agent' => self::USER_AGENT, 'Accept' => 'text/html,application/xhtml+xml'])
                    ->timeout(6)
                    ->connectTimeout(4)
                    ->withOptions([
                        'allow_redirects' => false,
                        'stream' => true,
                        'curl' => [CURLOPT_RESOLVE => ["{$host}:{$port}:" . (str_contains($ip, ':') ? "[{$ip}]" : $ip)]],
                    ])
                    ->get($url);
            } catch (Throwable) {
                return null;
            }
            if ($response->redirect()) {
                $location = (string) $response->header('Location');
                if ($location === '') {
                    return null;
                }
                $url = $this->absolute($location, $url);
                continue;
            }
            if (! $response->successful() || ! str_contains(strtolower((string) $response->header('Content-Type')), 'html')) {
                return null;
            }
            $body = $response->toPsrResponse()->getBody();
            $html = '';
            while (! $body->eof() && strlen($html) < self::MAX_BYTES) {
                $html .= $body->read(65536);
            }

            return $this->parse(substr($html, 0, self::MAX_BYTES), $url);
        }

        return null;
    }

    /** @return array{title: ?string, site_name: ?string, description: ?string, image_url: ?string}|null */
    public function parse(string $html, string $url): ?array
    {
        if (trim($html) === '') {
            return null;
        }
        $document = new DOMDocument();
        // Paksa UTF-8 bila halaman tidak menyebut charset.
        @$document->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);
        $xpath = new DOMXPath($document);
        $meta = function (array $names) use ($xpath): ?string {
            foreach ($names as $name) {
                foreach (['property', 'name'] as $attribute) {
                    $node = $xpath->query("//meta[@{$attribute}='{$name}']/@content")->item(0);
                    $value = $node ? $this->clean($node->nodeValue) : null;
                    if ($value) {
                        return $value;
                    }
                }
            }

            return null;
        };
        $titleNode = $xpath->query('//title')->item(0);
        $title = $meta(['og:title', 'twitter:title']) ?? ($titleNode ? $this->clean($titleNode->textContent) : null);
        $image = $meta(['og:image:secure_url', 'og:image', 'twitter:image', 'twitter:image:src']);
        $image = $image ? $this->absolute($image, $url) : null;

        $result = [
            'title' => $title ? Str::limit($title, 290, '…') : null,
            'site_name' => ($site = $meta(['og:site_name'])) ? Str::limit($site, 110, '…') : null,
            'description' => ($description = $meta(['og:description', 'twitter:description', 'description'])) ? Str::limit($description, 290, '…') : null,
            // Halaman HTTPS: hanya gambar https yang bisa tampil.
            'image_url' => $image && str_starts_with(strtolower($image), 'https://') && strlen($image) <= 2000 ? $image : null,
        ];

        return $result['title'] || $result['image_url'] || $result['description'] ? $result : null;
    }

    /** [host, port, ip yang aman] atau null bila URL tidak boleh dibuka. */
    private function safeTarget(string $url): ?array
    {
        $parts = parse_url($url);
        $scheme = strtolower($parts['scheme'] ?? '');
        $host = strtolower(trim((string) ($parts['host'] ?? ''), '[]'));
        if (! in_array($scheme, ['http', 'https'], true) || $host === '' || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }
        $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));
        if (! in_array($port, [80, 443, 8080, 8443], true)) {
            return null;
        }
        $ips = $this->resolver->resolve($host);
        if ($ips === []) {
            return null;
        }
        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return null;
            }
        }

        return [$host, $port, $ips[0]];
    }

    private function absolute(string $value, string $base): string
    {
        $value = trim($value);
        if (preg_match('#^https?://#i', $value)) {
            return $value;
        }
        $parts = parse_url($base);
        $origin = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '') . (isset($parts['port']) ? ':' . $parts['port'] : '');
        if (str_starts_with($value, '//')) {
            return ($parts['scheme'] ?? 'https') . ':' . $value;
        }
        if (str_starts_with($value, '/')) {
            return $origin . $value;
        }
        $path = $parts['path'] ?? '/';

        return $origin . rtrim(substr($path, 0, (int) strrpos($path, '/')), '/') . '/' . $value;
    }

    private function clean(?string $value): ?string
    {
        $value = trim(preg_replace('/\s+/u', ' ', html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8')));

        return $value === '' ? null : $value;
    }
}
