<?php

namespace Tests\Unit\Rules;

use App\Rules\CommaSeparatedEmails;
use PHPUnit\Framework\TestCase;

class CommaSeparatedEmailsTest extends TestCase
{
    public function test_validates_single_email()
    {
        $rule = new CommaSeparatedEmails();
        $failed = false;

        $rule->validate('emails', 'test@gmail.com', function () use (&$failed) {
            $failed = true;
        });

        $this->assertFalse($failed);
    }

    public function test_validates_multiple_emails()
    {
        $rule = new CommaSeparatedEmails();
        $failed = false;

        $rule->validate('emails', 'test1@gmail.com, test2@gmail.com, test3@gmail.com', function () use (&$failed) {
            $failed = true;
        });

        $this->assertFalse($failed);
    }

    public function test_validates_emails_with_whitespace()
    {
        $rule = new CommaSeparatedEmails();
        $failed = false;

        $rule->validate('emails', '  test1@gmail.com  ,  test2@gmail.com  ', function () use (&$failed) {
            $failed = true;
        });

        $this->assertFalse($failed);
    }

    public function test_fails_with_invalid_email()
    {
        $rule = new CommaSeparatedEmails();
        $failed = false;
        $errorMessage = '';

        $rule->validate('emails', 'invalid-email, test@gmail.com', function ($message) use (&$failed, &$errorMessage) {
            $failed = true;
            $errorMessage = $message;
        });

        $this->assertTrue($failed);
        $this->assertStringContainsString('invalid-email', $errorMessage);
    }

    public function test_fails_with_empty_emails()
    {
        $rule = new CommaSeparatedEmails();
        $failed = false;

        $rule->validate('emails', 'test@gmail.com, , test2@gmail.com', function () use (&$failed) {
            $failed = true;
        });

        $this->assertFalse($failed); // Empty emails are filtered out
    }

    public function test_fails_when_exceeding_max_emails()
    {
        $rule = new CommaSeparatedEmails(2); // Max 2 emails
        $failed = false;
        $errorMessage = '';

        $rule->validate('emails', 'test1@gmail.com, test2@gmail.com, test3@gmail.com', function ($message) use (&$failed, &$errorMessage) {
            $failed = true;
            $errorMessage = $message;
        });

        $this->assertTrue($failed);
        $this->assertStringContainsString('cannot contain more than 2', $errorMessage);
    }

    public function test_validates_null_value()
    {
        $rule = new CommaSeparatedEmails();
        $failed = false;

        $rule->validate('emails', null, function () use (&$failed) {
            $failed = true;
        });

        $this->assertFalse($failed);
    }

    public function test_validates_empty_string()
    {
        $rule = new CommaSeparatedEmails();
        $failed = false;

        $rule->validate('emails', '', function () use (&$failed) {
            $failed = true;
        });

        $this->assertFalse($failed);
    }
}
