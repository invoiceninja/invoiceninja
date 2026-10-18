<?php

namespace Tests\Unit;

use App\Services\Email\SmtpFailure;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Exception\IncompleteDsnException;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Exception\UnexpectedResponseException;

class SmtpFailureTest extends TestCase
{
    private SmtpFailure $classifier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->classifier = new SmtpFailure();
    }

    #[DataProvider('failProvider')]
    public function testRecipientSmtpCodesFail(\Throwable $e): void
    {
        $this->assertSame(SmtpFailure::FAIL, $this->classifier->action($e, 1, 4));
        $this->assertSame(SmtpFailure::FAIL, $this->classifier->action($e, 4, 4));
    }

    public static function failProvider(): array
    {
        return [
            '550 mailbox unavailable' => [new UnexpectedResponseException('Expected response code "250" but got code "550", with message "550 5.1.1 User unknown".', 550)],
            '551 user not local' => [new UnexpectedResponseException('Expected response code "250" but got code "551".', 551)],
            '552 mailbox full' => [new UnexpectedResponseException('Expected response code "250" but got code "552".', 552)],
            '553 mailbox name not allowed' => [new UnexpectedResponseException('Expected response code "250" but got code "553".', 553)],
            '554 transaction failed' => [new UnexpectedResponseException('Expected response code "250" but got code "554".', 554)],
            '550 wins over auth wording' => [new UnexpectedResponseException('Failed to authenticate: 550 5.7.1 Relay denied.', 550)],
        ];
    }

    #[DataProvider('fallbackProvider')]
    public function testPermanentSmtpProblemsFallback(\Throwable $e): void
    {
        $this->assertSame(SmtpFailure::FALLBACK, $this->classifier->action($e, 1, 4));
    }

    public static function fallbackProvider(): array
    {
        return [
            '535 auth wrapped' => [new TransportException('Failed to authenticate on SMTP server with username "user" using the following authenticators: "LOGIN". Authenticator "LOGIN" returned "Expected response code "235" but got code "535".".', 535)],
            '534 auth code' => [new TransportException('Failed to authenticate on SMTP server with username "user".', 534)],
            '530 auth required' => [new TransportException('Expected response code "250" but got code "530".', 530)],
            '504 no authenticator code' => [new TransportException('Failed to find an authenticator supported by the SMTP server, which currently supports: "XOAUTH2".', 504)],
            '504 message without matching code' => [new TransportException('Failed to find an authenticator supported by the SMTP server, which currently supports: "XOAUTH2".')],
            'auth message code 0' => [new TransportException('Failed to authenticate on SMTP server with username "user" using the following authenticators: "PLAIN".')],
            'starttls connect' => [new TransportException('Unable to connect with STARTTLS.')],
            'starttls openssl' => [new TransportException('Unable to connect with STARTTLS: stream_socket_enable_crypto(): SSL operation failed.')],
            'tls required' => [new TransportException('TLS required but neither TLS or STARTTLS are in use.')],
            'starttls case insensitive' => [new TransportException('unable to connect with starttls.')],
            'dsn case insensitive' => [new TransportException('The mailer DSN is invalid.')],
            'incomplete dsn' => [new IncompleteDsnException('The "user" DSN option is required.')],
        ];
    }

    #[DataProvider('retryProvider')]
    public function testTransientProblemsRetryBeforeLastAttempt(\Throwable $e): void
    {
        $this->assertSame(SmtpFailure::RETRY, $this->classifier->action($e, 1, 4));
        $this->assertSame(SmtpFailure::RETRY, $this->classifier->action($e, 3, 4));
        $this->assertSame(SmtpFailure::FALLBACK, $this->classifier->action($e, 4, 4));
        $this->assertSame(SmtpFailure::FALLBACK, $this->classifier->action($e, 5, 4));
    }

    public static function retryProvider(): array
    {
        return [
            'connection refused' => [new TransportException('Connection could not be established with host "ssl://smtp.example.com:465": stream_socket_client(): Unable to connect.')],
            'timeout' => [new TransportException('Connection to "ssl://smtp.example.com:465" timed out.')],
            'closed' => [new TransportException('Connection to "smtp.example.com:587" has been closed unexpectedly.')],
            'write' => [new TransportException('Unable to write bytes on the wire.')],
            '421 service not available' => [new UnexpectedResponseException('Expected response code "250" but got code "421".', 421)],
            '450 mailbox busy' => [new UnexpectedResponseException('Expected response code "250" but got code "450".', 450)],
            '451 local error' => [new UnexpectedResponseException('Expected response code "250" but got code "451".', 451)],
            '452 insufficient storage' => [new UnexpectedResponseException('Expected response code "250" but got code "452".', 452)],
            '454 starttls reply without substring' => [new UnexpectedResponseException('Expected response code "220" but got code "454".', 454)],
            'generic transport' => [new TransportException('Temporary failure.')],
        ];
    }

    public function testSingleAttemptFallsBackOnTransient(): void
    {
        $e = new TransportException('Connection could not be established with host "smtp.example.com:587": Connection refused.');

        $this->assertSame(SmtpFailure::FALLBACK, $this->classifier->action($e, 1, 1));
    }

    public function testAuthCodeOnUnexpectedResponseFallsBack(): void
    {
        $e = new UnexpectedResponseException('Expected response code "235" but got code "535", with message "535 5.7.8 Authentication failed".', 535);

        $this->assertSame(SmtpFailure::FALLBACK, $this->classifier->action($e, 1, 4));
    }
}
