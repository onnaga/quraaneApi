<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class latest_quraan_with_homework extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    private $num  = [];
    private $homework_num = [];

    public function __construct($data)
    {
        foreach ($data[0] as $sora) {
            array_push($this->num, $sora->num);

        }
        foreach ($data[1] as $homework) {
            array_push($this->homework_num, $homework->num);
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
            'title'=> 'new quraan achievements',
            'body'=>'the ended soar is : '.json_encode($this->num).' the homework is : '.json_encode($this->homework_num),
            'footer'=>'enter now and see the details',
        ];
    }
}
