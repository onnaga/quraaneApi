<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class taken_by_teacher extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    private $name ;
    private $phone_number ;

    public function __construct($data)
    {
    $this->name = $data->name;
    $this->phone_number = $data->phone_number;

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

                'title'=> "the teacher ".$this->name.' accepted you',
                'body'=>'you can contact with teacher on his number:'.$this->phone_number,
                'footer'=>'do the best ',

        ];
    }
}
