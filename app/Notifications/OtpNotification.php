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

    /**
     * Create a new notification instance.
     *
     * @param string $otp
     * @param int $expiresIn Expiration time in minutes
     */
    public function __construct(string $otp, int $expiresIn = 10)
    {
        $this->otp = $otp;
        $this->expiresIn = $expiresIn;
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
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your OTP Verification Code')
            ->greeting('Hello!')
            ->line('Your OTP verification code is:')
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
