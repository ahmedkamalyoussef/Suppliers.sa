<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Illuminate\Notifications\Messages\MailMessage;

class CustomNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $data;

    /**
     * Create a new notification instance.
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];

        // Add email channel if email notifications are enabled
        if (isset($notifiable->notification_settings['email_notifications']) && 
            $notifiable->notification_settings['email_notifications']) {
            $channels[] = 'mail';
        }

        // Add push notification channel if enabled
        if (isset($notifiable->notification_settings['push_notifications']) && 
            $notifiable->notification_settings['push_notifications']) {
            $channels[] = 'broadcast';
        }

        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->data['title'] ?? 'Notification from Suppliers.sa')
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line($this->data['message'] ?? 'You have a new notification.')
            ->action('View Details', $this->data['action_url'] ?? route('dashboard'))
            ->line('Thank you for using Suppliers.sa!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => $this->data['type'] ?? 'system',
            'title' => $this->data['title'] ?? '',
            'message' => $this->data['message'] ?? '',
            'icon' => $this->data['icon'] ?? 'bell',
            'action_url' => $this->data['action_url'] ?? null,
            'data' => $this->data,
        ];
    }

    /**
     * Get the database representation of the notification.
     */
    public function toDatabase(object $notifiable): DatabaseMessage
    {
        return new DatabaseMessage([
            'type' => $this->data['type'] ?? 'system',
            'title' => $this->data['title'] ?? '',
            'message' => $this->data['message'] ?? '',
            'icon' => $this->data['icon'] ?? 'bell',
            'action_url' => $this->data['action_url'] ?? null,
            'data' => $this->data,
        ]);
    }
}
