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
    public function testRecipientSmtpCodesFail(string $exception_class, string $message, int $code): void
    {
        $e = new $exception_class($message, $code);

        $this->assertSame(SmtpFailure::FAIL, $this->classifier->action($e, 1, 4));
        $this->assertSame(SmtpFailure::FAIL, $this->classifier->action($e, 4, 4));
    }

    public static function failProvider(): array
    {
        return [
            '550 mailbox unavailable' => [UnexpectedResponseException::class, 'Expected response code "250" but got code "550", with message "550 5.1.1 User unknown".', 550],
            '551 user not local' => [UnexpectedResponseException::class, 'Expected response code "250" but got code "551".', 551],
            '552 mailbox full' => [UnexpectedResponseException::class, 'Expected response code "250" but got code "552".', 552],
            '553 mailbox name not allowed' => [UnexpectedResponseException::class, 'Expected response code "250" but got code "553".', 553],
            '554 transaction failed' => [UnexpectedResponseException::class, 'Expected response code "250" but got code "554".', 554],
            '550 wins over auth wording' => [UnexpectedResponseException::class, 'Failed to authenticate: 550 5.7.1 Relay denied.', 550],
        ];
    }

    #[DataProvider('fallbackProvider')]
    public function testPermanentSmtpProblemsFallback(string $exception_class, string $message, int $code): void
    {
        $e = new $exception_class($message, $code);

        $this->assertSame(SmtpFailure::FALLBACK, $this->classifier->action($e, 1, 4));
    }

    public static function fallbackProvider(): array
    {
        return [
            '535 auth wrapped' => [TransportException::class, 'Failed to authenticate on SMTP server with username "user" using the following authenticators: "LOGIN". Authenticator "LOGIN" returned "Expected response code "235" but got code "535".".', 535],
            '534 auth code' => [TransportException::class, 'Failed to authenticate on SMTP server with username "user".', 534],
            '530 auth required' => [TransportException::class, 'Expected response code "250" but got code "530".', 530],
            '504 no authenticator code' => [TransportException::class, 'Failed to find an authenticator supported by the SMTP server, which currently supports: "XOAUTH2".', 504],
            '504 message without matching code' => [TransportException::class, 'Failed to find an authenticator supported by the SMTP server, which currently supports: "XOAUTH2".', 0],
            'auth message code 0' => [TransportException::class, 'Failed to authenticate on SMTP server with username "user" using the following authenticators: "PLAIN".', 0],
            'starttls connect' => [TransportException::class, 'Unable to connect with STARTTLS.', 0],
            'starttls openssl' => [TransportException::class, 'Unable to connect with STARTTLS: stream_socket_enable_crypto(): SSL operation failed.', 0],
            'tls required' => [TransportException::class, 'TLS required but neither TLS or STARTTLS are in use.', 0],
            'starttls case insensitive' => [TransportException::class, 'unable to connect with starttls.', 0],
            'dsn case insensitive' => [TransportException::class, 'The mailer DSN is invalid.', 0],
            'incomplete dsn' => [IncompleteDsnException::class, 'The "user" DSN option is required.', 0],
        ];
    }

    #[DataProvider('retryProvider')]
    public function testTransientProblemsRetryBeforeLastAttempt(string $exception_class, string $message, int $code): void
    {
        $e = new $exception_class($message, $code);

        $this->assertSame(SmtpFailure::RETRY, $this->classifier->action($e, 1, 4));
        $this->assertSame(SmtpFailure::RETRY, $this->classifier->action($e, 3, 4));
        $this->assertSame(SmtpFailure::FALLBACK, $this->classifier->action($e, 4, 4));
        $this->assertSame(SmtpFailure::FALLBACK, $this->classifier->action($e, 5, 4));
    }

    public static function retryProvider(): array
    {
        return [
            'connection refused' => [TransportException::class, 'Connection could not be established with host "ssl://smtp.example.com:465": stream_socket_client(): Unable to connect.', 0],
            'timeout' => [TransportException::class, 'Connection to "ssl://smtp.example.com:465" timed out.', 0],
            'closed' => [TransportException::class, 'Connection to "smtp.example.com:587" has been closed unexpectedly.', 0],
            'write' => [TransportException::class, 'Unable to write bytes on the wire.', 0],
            '421 service not available' => [UnexpectedResponseException::class, 'Expected response code "250" but got code "421".', 421],
            '450 mailbox busy' => [UnexpectedResponseException::class, 'Expected response code "250" but got code "450".', 450],
            '451 local error' => [UnexpectedResponseException::class, 'Expected response code "250" but got code "451".', 451],
            '452 insufficient storage' => [UnexpectedResponseException::class, 'Expected response code "250" but got code "452".', 452],
            '454 starttls reply without substring' => [UnexpectedResponseException::class, 'Expected response code "220" but got code "454".', 454],
            'generic transport' => [TransportException::class, 'Temporary failure.', 0],
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
