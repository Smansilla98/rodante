<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WeeklyReportNotification extends Notification
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $report
     */
    public function __construct(public array $report) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $from = $this->report['from']->format('d/m/Y');
        $to = $this->report['to']->format('d/m/Y');

        return (new MailMessage)
            ->subject("Informe semanal {$from} – {$to} — ".config('app.name', 'Rodante'))
            ->markdown('emails.weekly-report', [
                'report' => $this->report,
                'recipientName' => $notifiable->name ?? null,
            ]);
    }
}
