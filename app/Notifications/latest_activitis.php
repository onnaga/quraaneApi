<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class latest_activitis extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    private $activitis_name = [];
    public function __construct($data)
    {
        foreach ($data as $activity) {
            array_push($this->activitis_name, $activity->name);
        }
    }
    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
                    ->line('The introduction to the notification.')
                    ->action('Notification Action', url('/'))
                    ->line('Thank you for using our application!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title'=> 'new activity achievements',
            'body'=>'the activities is : '.json_encode($this->activitis_name),
            'footer'=>'enter now and see the details',
        ];
    }
}




