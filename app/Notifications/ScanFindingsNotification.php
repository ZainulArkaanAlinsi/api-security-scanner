<?php

namespace App\Notifications;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ScanFindingsNotification extends Notification
{
    use Queueable;

    /**
     * @param  array<int, array{title: string, severity: string, detail: string}>  $findings
     */
    public function __construct(public Ticket $ticket, public array $findings)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $count = count($this->findings);

        $mail = (new MailMessage)
            ->subject("[API Scanner] {$count} temuan berisiko tinggi di {$this->ticket->title}")
            ->greeting("Halo {$notifiable->name},")
            ->line("Scan otomatis pada {$this->ticket->title} ({$this->ticket->api_url}) menemukan {$count} masalah baru dengan risiko tinggi:");

        foreach ($this->findings as $finding) {
            $mail->line('• '.strtoupper($finding['severity']).' — '.$finding['title']);
        }

        return $mail
            ->action('Lihat hasil scan', route('tickets.show', $this->ticket))
            ->line('Kamu menerima email ini karena monitoring otomatis aktif untuk ticket tersebut.');
    }
}
