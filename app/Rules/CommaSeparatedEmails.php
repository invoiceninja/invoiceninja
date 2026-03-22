<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Custom validation rule for comma-separated email addresses
 *
 * This rule validates that a string contains valid email addresses
 * separated by commas, with optional whitespace around commas.
 *
 * Usage examples:
 * - new CommaSeparatedEmails() - basic validation (max 10 emails)
 * - new CommaSeparatedEmails(5) - max 5 emails
 * - new CommaSeparatedEmails(10, [';', ',']) - max 10 emails, separated by ; or ,
 *
 * Example input formats:
 * - "user@example.com"
 * - "user1@example.com, user2@example.com"
 * - "user1@example.com,user2@example.com,user3@example.com"
 * - "  user1@example.com  ,  user2@example.com  " (whitespace is trimmed)
 */
class CommaSeparatedEmails implements ValidationRule
{
    /**
     * Maximum number of email addresses allowed
     */
    protected int $maxEmails;

    /**
     * Array of valid separators
     */
    protected array $separators;

    /**
     * Create a new rule instance.
     */
    public function __construct(int $maxEmails = 10, array $separators = [','])
    {
        $this->maxEmails = $maxEmails;
        $this->separators = $separators;
    }

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // If the value is null or empty, it's valid (nullable rule handles this)
        if (empty($value)) {
            return;
        }

        // Split the value by the first separator and trim whitespace
        $emails = array_map('trim', explode($this->separators[0], $value));

        // Filter out empty strings
        $emails = array_filter($emails);

        // If no valid emails after splitting, fail validation
        if (empty($emails)) {
            $fail('The :attribute must contain at least one valid email address.');
            return;
        }

        // Check if we exceed the maximum number of emails
        if (count($emails) > $this->maxEmails) {
            $fail("The :attribute cannot contain more than {$this->maxEmails} email addresses.");
            return;
        }

        // Validate each email address
        foreach ($emails as $email) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $fail("The email address '{$email}' in :attribute is not valid.");
                return;
            }
        }
    }

    /**
     * Get the validation error message.
     */
    public function message(): string
    {
        return 'The :attribute must contain valid email addresses separated by commas.';
    }

    /**
     * Static method to parse comma-separated emails into an array
     * Useful for processing the validated data
     */
    public static function parseEmails(string $emailString): array
    {
        if (empty($emailString)) {
            return [];
        }

        $emails = array_map('trim', explode(',', $emailString));
        return array_filter($emails);
    }

    /**
     * Static method to validate a single email address
     */
    public static function isValidEmail(string $email): bool
    {
        return filter_var(trim($email), FILTER_VALIDATE_EMAIL) !== false;
    }
}
