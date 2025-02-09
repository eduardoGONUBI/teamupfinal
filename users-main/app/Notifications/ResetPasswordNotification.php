<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Config;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends Notification
{
    /**
     * The password reset token.
     */
    public $token;

    /**
     * The callback that should be used to create the reset password URL.
     */
    public static $createUrlCallback;

    /**
     * Create a notification instance.
     */
    public function __construct($token)
    {
        $this->token = $token;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable)
    {
        return ['mail'];
    }

    /**
     * Build the mail representation of the notification.
     */
    public function toMail($notifiable)
{
    return (new MailMessage)
        ->subject('Reset Password Notification')
        ->line('You are receiving this email because we received a password reset request for your account.')
        ->line('Your password reset token is:')
        ->line($this->token)
        ->line('This password reset token will expire in ' . Config::get('auth.passwords.users.expire') . ' minutes.')
        ->line('If you did not request a password reset, no further action is required.');
}

}
