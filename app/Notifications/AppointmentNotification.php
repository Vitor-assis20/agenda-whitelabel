<?php

namespace App\Notifications;

use App\Scheduling\AppointmentSummary;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * E-mail ao cliente sobre um agendamento, com a marca do negócio.
 *
 * Carrega um AppointmentSummary (dados prontos), nunca models: na fila não
 * há tenant no contexto para recarregá-los.
 */
abstract class AppointmentNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly AppointmentSummary $summary) {}

    abstract protected function subject(): string;

    abstract protected function title(): string;

    abstract protected function intro(): string;

    /**
     * @return array{0: string, 1: string}|null [texto, url] do botão
     */
    protected function action(): ?array
    {
        return null;
    }

    protected function outro(): ?string
    {
        return null;
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        [$actionText, $actionUrl] = $this->action() ?? [null, null];

        return (new MailMessage)
            ->from(config('mail.from.address'), $this->summary->tenantName)
            ->subject("{$this->subject()} · {$this->summary->tenantName}")
            ->markdown('mail.appointment', [
                'summary' => $this->summary,
                'title' => $this->title(),
                'intro' => $this->intro(),
                'actionText' => $actionText,
                'actionUrl' => $actionUrl,
                'outro' => $this->outro(),
            ]);
    }
}
