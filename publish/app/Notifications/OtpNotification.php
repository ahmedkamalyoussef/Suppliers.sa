<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OtpNotification extends Notification
{
    use Queueable;

    public readonly string $otp;

    public readonly int $expiresIn;

    public readonly string $purpose;

    /**
     * Create a new notification instance.
     *
     * @param  int  $expiresIn  Expiration time in minutes
     */
    public function __construct(string $otp, int $expiresIn = 10, string $purpose = 'verification')
    {
        $this->otp = $otp;
        $this->expiresIn = $expiresIn;
        $this->purpose = $purpose;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail']; // يمكنك إضافة 'database' أو 'nexmo' لاحقًا
    }

    /**
     * Determine if the notification should be sent immediately (not queued).
     */
    public function shouldQueue(object $notifiable): bool
    {
        return false; // Send immediately, don't queue
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     */
    public function toMail(object $notifiable): MailMessage
    {
        // Use purpose to customize message
        $subject = 'Your OTP Verification Code';
        $message = 'Your OTP verification code is:';
        
        if ($this->purpose === 'password_reset') {
            $subject = 'Password Reset Code';
            $message = 'Your password reset code is:';
        } elseif ($this->purpose === 'verification') {
            $subject = 'Account Verification Code';
            $message = 'Your account verification code is:';
        }

        return (new MailMessage)
            ->subject($subject)
            ->greeting('Hello!')
            ->line($message)
            ->line($this->otp)
            ->line("This code will expire in {$this->expiresIn} minutes.")
            ->line('If you did not request this code, please ignore this email.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'otp' => $this->otp,
            'expires_in' => $this->expiresIn,
        ];
    }
}
