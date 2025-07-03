<?php
namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AppointmentNotification extends Notification
{
    use Queueable;

    public $message;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($message)
    {
        $this->message = $message;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['mail', 'database']; // يمكنك استخدام البريد الإلكتروني و/أو قاعدة البيانات
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        $actionUrl = $notifiable ? url('/appointments/'.$notifiable->id) : url('/appointments');

        if (isset($this->message['appointment_id'])) {
            $actionUrl = url('/appointments/'.$this->message['appointment_id']);
        }

        return (new MailMessage)
            ->subject($this->message['type'] ?? 'Appointment Notification')
            ->line($this->message['message'] ?? 'Appointment update')
            ->action('View Appointment', $actionUrl)
            ->line('Thank you for using our application!');
    }

    /**
     * Get the database representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toDatabase($notifiable)
    {
        // قم بتضمين بيانات المستخدم في الرسالة
        return [
            'message' => $this->message,  // تخزين الرسالة في قاعدة البيانات
            'user_id' => $this->message['user_id'] ?? null,  // إرسال user_id
            'user_name' => $this->message['userName'] ?? null,  // إرسال user_name
            'appointment_id' => $this->message['appointment_id'] ?? null, // تأكد من إرسال appointment_id
            'latitude' => $this->message['latitude'] ?? null, // إذا كانت موجودة
            'longitude' => $this->message['longitude'] ?? null, // إذا كانت موجودة
        ];
    }
}
