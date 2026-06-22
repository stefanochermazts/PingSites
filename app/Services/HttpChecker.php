<?php

namespace App\Services;

use App\DTOs\CheckResult;
use App\Enums\ErrorType;
use App\Models\Monitor;
use App\Settings\MonitorSettings;
use App\Support\SsrfGuard;
use Illuminate\Http\Client\ConnectionException;
use InvalidArgumentException;
use Throwable;

class HttpChecker
{
    public function __construct(
        private readonly SsrfGuard $ssrfGuard,
        private readonly MonitorSettings $settings,
    ) {}

    public function check(Monitor $monitor): CheckResult
    {
        try {
            $this->ssrfGuard->validateUrl($monitor->url);
        } catch (InvalidArgumentException $exception) {
            return CheckResult::failure(ErrorType::Unknown, $exception->getMessage());
        }

        $redirectCount = 0;
        $visitedUrls = [$monitor->url];
        $start = microtime(true);

        try {
            $client = $this->ssrfGuard->createHttpClient(
                timeout: $monitor->timeout,
                followRedirects: $monitor->follow_redirects,
                verifySsl: $monitor->verify_ssl,
                userAgent: $this->settings->user_agent,
                onRedirect: function (string $uri) use (&$redirectCount, &$visitedUrls): void {
                    $redirectCount++;

                    if ($redirectCount > 5) {
                        throw new RedirectLoopException('Troppi redirect.');
                    }

                    if (in_array($uri, $visitedUrls, true)) {
                        throw new RedirectLoopException('Redirect loop rilevato.');
                    }

                    $this->ssrfGuard->validateRedirectUrl($uri);
                    $visitedUrls[] = $uri;
                },
            );

            $response = $client->get($monitor->url);
            $responseTimeMs = (int) round((microtime(true) - $start) * 1000);
            $statusCode = $response->status();
            $body = $response->body();

            if ($body === '') {
                return CheckResult::failure(ErrorType::EmptyResponse, 'Risposta vuota.', $statusCode, $responseTimeMs);
            }

            $validCodes = $monitor->valid_status_codes ?? [200, 301, 302];

            if (! in_array($statusCode, $validCodes, true)) {
                $errorType = $this->classifyHttpCode($statusCode);

                return CheckResult::failure(
                    $errorType,
                    "Codice HTTP {$statusCode} non valido.",
                    $statusCode,
                    $responseTimeMs,
                );
            }

            if ($monitor->keyword && ! str_contains($body, $monitor->keyword)) {
                return CheckResult::failure(
                    ErrorType::KeywordMissing,
                    'La keyword configurata non è presente nella risposta.',
                    $statusCode,
                    $responseTimeMs,
                );
            }

            return CheckResult::success($statusCode, $responseTimeMs);
        } catch (RedirectLoopException $exception) {
            return CheckResult::failure(ErrorType::RedirectLoop, $exception->getMessage());
        } catch (ConnectionException $exception) {
            return $this->classifyConnectionException($exception);
        } catch (Throwable $exception) {
            return $this->classifyGenericException($exception);
        }
    }

    private function classifyHttpCode(int $statusCode): ErrorType
    {
        if ($statusCode >= 500) {
            return ErrorType::Http5xx;
        }

        if ($statusCode >= 400) {
            return ErrorType::Http4xx;
        }

        return ErrorType::InvalidHttpCode;
    }

    private function classifyConnectionException(ConnectionException $exception): CheckResult
    {
        $message = strtolower($exception->getMessage());

        if (str_contains($message, 'timed out') || str_contains($message, 'timeout')) {
            return CheckResult::failure(ErrorType::Timeout, 'Il sito non ha risposto entro il timeout configurato.');
        }

        if (str_contains($message, 'could not resolve host') || str_contains($message, 'getaddrinfo')) {
            return CheckResult::failure(ErrorType::DnsError, 'Impossibile risolvere il DNS.');
        }

        if (str_contains($message, 'connection refused')) {
            return CheckResult::failure(ErrorType::ConnectionRefused, 'Connessione rifiutata.');
        }

        if (str_contains($message, 'ssl') || str_contains($message, 'certificate')) {
            return CheckResult::failure(ErrorType::SslError, 'Errore SSL/TLS.');
        }

        return CheckResult::failure(ErrorType::Unknown, $exception->getMessage());
    }

    private function classifyGenericException(Throwable $exception): CheckResult
    {
        $message = strtolower($exception->getMessage());

        if (str_contains($message, 'ssl') || str_contains($message, 'certificate')) {
            return CheckResult::failure(ErrorType::SslError, 'Errore SSL/TLS.');
        }

        return CheckResult::failure(ErrorType::Unknown, $exception->getMessage());
    }
}

class RedirectLoopException extends \RuntimeException {}
