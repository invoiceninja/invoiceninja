<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Lang;

class ClientContactResetPassword extends Notification
{
    use Queueable;

    /**
     * The password reset token.
     *
     * @var string
     */
    public $token;

    /**
     * For sending password reset, after locking account.
     *
     * @var boolean
     */
    public $is_locked;

    /**
     * The callback that should be used to build the mail message.
     *
     * @var \Closure|null
     */
    public static $toMailCallback;

    /**
     * Create a notification instance.
     *
     * @param string $token
     * @param bool $is_locked
     */
    public function __construct($token, $is_locked = false)
    {
        $this->token = $token;
        $this->is_locked = $is_locked;
    }

    /**
     * Get the notification's channels.
     *
     * @param  mixed  $notifiable
     * @return array|string
     */
    public function via($notifiable)
    {
        return ['mail'];
    }

    /**
     * Build the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        if (static::$toMailCallback) {
            return call_user_func(static::$toMailCallback, $notifiable, $this->token);
        }

        $mail = (new MailMessage)
            ->subject(__('texts.reset_notification'));

        if ($this->is_locked) {
            $mail->line(__('texts.locked_reset'));
        } else {
            $mail->line(__('texts.requested_reset'));
        }

        $mail->action(__('texts.reset_password'), url(config('app.url') . route('client.password.reset', ['token' => $this->token, 'email' => $notifiable->getEmailForPasswordReset()], false)))
            ->line(__('texts.password_will_expire_in', ['count' => config('auth.passwords.users.expire')]));

        if (!$this->is_locked) $mail->line(__('ignore_password_request'));

        return $mail;
    }

    /**
     * Set a callback that should be used when building the notification mail message.
     *
     * @param  \Closure  $callback
     * @return void
     */
    public static function toMailUsing($callback)
    {
        static::$toMailCallback = $callback;
    }
}
