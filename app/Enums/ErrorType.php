<?php

namespace App\Enums;

enum ErrorType: string
{
    case DnsError = 'dns_error';
    case Timeout = 'timeout';
    case ConnectionRefused = 'connection_refused';
    case SslError = 'ssl_error';
    case InvalidHttpCode = 'invalid_http_code';
    case Http4xx = 'http_4xx';
    case Http5xx = 'http_5xx';
    case RedirectLoop = 'redirect_loop';
    case KeywordMissing = 'keyword_missing';
    case EmptyResponse = 'empty_response';
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::DnsError => 'DNS non risolto',
            self::Timeout => 'Timeout',
            self::ConnectionRefused => 'Connessione rifiutata',
            self::SslError => 'Errore SSL/TLS',
            self::InvalidHttpCode => 'Codice HTTP non valido',
            self::Http4xx => 'Errore HTTP 4xx',
            self::Http5xx => 'Errore HTTP 5xx',
            self::RedirectLoop => 'Redirect loop',
            self::KeywordMissing => 'Keyword assente',
            self::EmptyResponse => 'Risposta vuota',
            self::Unknown => 'Errore sconosciuto',
        };
    }
}
