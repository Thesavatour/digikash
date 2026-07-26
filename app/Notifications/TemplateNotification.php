<?php

namespace App\Notifications;

use App\Enums\NotificationChannelType;
use App\Models\NotificationTemplate;
use App\Notifications\Channels\WebPushChannel;
use App\Services\WebPush\WebPushSender;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\Twilio\TwilioChannel;
use NotificationChannels\Twilio\TwilioSmsMessage;

class TemplateNotification extends Notification implements ShouldBroadcast, ShouldQueue
{
    use Queueable;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        protected string $identifier,
        protected array $data = [],
        protected mixed $sender = null,
        protected mixed $action = null,
    ) {}

    public function via(object $notifiable): array
    {
        $pusherStatus   = pluginCredentials('pusher')['status'] ?? false;
        $twilioStatus   = pluginCredentials('twilio')['status'] ?? false;
        $webPushEnabled = app(WebPushSender::class)->isConfigured();
        $pushEnabled    = ! method_exists($notifiable, 'notificationDeliveryEnabled')
            || $notifiable->notificationDeliveryEnabled();

        $template = NotificationTemplate::where('identifier', $this->identifier)
            ->with('channels')
            ->firstOrFail();

        return collect($template->channels)
            ->where('is_active', true)
            ->flatMap(function ($channel) use ($pusherStatus, $twilioStatus, $webPushEnabled, $pushEnabled) {
                return match ($channel->channel) {
                    NotificationChannelType::EMAIL => ['mail'],
                    NotificationChannelType::SMS   => $twilioStatus ? [TwilioChannel::class] : [],
                    NotificationChannelType::PUSH  => $pushEnabled
                        ? array_values(array_filter([
                            'database',
                            $pusherStatus ? 'broadcast' : null,
                            $webPushEnabled ? WebPushChannel::class : null,
                        ]))
                        : [],
                    default => [],
                };
            })
            ->unique()
            ->values()
            ->toArray();
    }

    public function toMail(object $notifiable): ?MailMessage
    {
        $template = $this->getTemplate('email');
        if (! $template) {
            return null;
        }

        return (new MailMessage)
            ->subject($template['title'] ?? 'Notification')
            ->line(str_replace_placeholders($template['message'], $this->data));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $template = $this->getTemplate('push');
        if (! $template) {
            return [];
        }

        $base       = $this->getBase();
        $senderInfo = null;
        if ($this->sender) {
            $senderInfo = [
                'id'     => $this->sender->id,
                'name'   => $this->sender->name       ?? null,
                'avatar' => $this->sender->avatar_alt ?? null,
            ];
        }

        return [
            'title'       => $template['title'] ?? '',
            'message'     => str_replace_placeholders($template['message'] ?? '', $this->data),
            'icon'        => $base->icon,
            'action_type' => $base->action_type->value ?? '',
            'action_link' => $this->resolveStoredActionLink(),
            'sender'      => $senderInfo,
            'trx'         => $this->data['trx'] ?? null,
        ];

    }

    /**
     * Persist a clickable destination for in-app notification history.
     */
    protected function resolveStoredActionLink(): string
    {
        if (filled($this->action)) {
            return (string) $this->action;
        }

        if (filled($this->data['trx'] ?? null)) {
            return user_transaction_receipt_url((string) $this->data['trx']);
        }

        return route('user.notifications.index');
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->broadcastWith());
    }

    /**
     * OS-level Web Push payload shown by the service worker.
     *
     * @return array<string, mixed>
     */
    public function toWebPush(object $notifiable): array
    {
        $data = $this->toArray($notifiable);
        if ($data === []) {
            return [];
        }

        $url = $this->resolveActionUrl($data);

        $icon = $data['icon'] ?? null;
        if (is_string($icon) && (str_starts_with($icon, 'http://') || str_starts_with($icon, 'https://') || str_starts_with($icon, '//'))) {
            $iconUrl = $icon;
        } elseif (is_string($icon) && $icon !== '') {
            $iconUrl = url($icon);
        } else {
            $iconUrl = url('/favicon.ico');
        }

        return [
            'title' => $data['title'] ?: (setting('site_title') ?: config('app.name')),
            'body'  => $data['message'] ?? '',
            'icon'  => $iconUrl,
            'badge' => url('/favicon.ico'),
            'data'  => [
                'url'         => $url,
                'action_type' => $data['action_type'] ?? '',
                'trx'         => $this->data['trx'] ?? null,
            ],
            'tag'      => 'digikash-'.$this->identifier.'-'.md5(($data['message'] ?? '').$url),
            'renotify' => true,
        ];
    }

    /**
     * Prefer the explicit action, then a trx receipt deep link, then notification history.
     */
    protected function resolveActionUrl(array $data): string
    {
        $url = $data['action_link'] ?? $this->action ?? '';

        if (($url === '' || $url === null) && filled($this->data['trx'] ?? null)) {
            $url = user_transaction_receipt_url((string) $this->data['trx']);
        }

        if ($url === '' || $url === null) {
            $url = route('user.notifications.index');
        }

        if (! str_starts_with((string) $url, 'http://') && ! str_starts_with((string) $url, 'https://')) {
            $url = url($url);
        }

        return (string) $url;
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $t = $this->getTemplate('push');

        return [
            'title'       => $t['title'] ?? '',
            'message'     => str_replace_placeholders($t['message'] ?? '', $this->data),
            'icon'        => $this->getBase()->icon,
            'action_type' => $this->getBase()->action_type->value,
            'action_link' => $this->resolveStoredActionLink(),
            'timestamp'   => now()->toISOString(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'notification.received';
    }

    public function toTwilio(object $notifiable): TwilioSmsMessage
    {
        $sms = $this->getTemplate('sms');

        return (new TwilioSmsMessage)
            ->content(str_replace_placeholders($sms['message'], $this->data));
    }

    protected function getBase(): NotificationTemplate
    {
        return NotificationTemplate::where('identifier', $this->identifier)->firstOrFail();
    }

    protected function getTemplate(string $channel): ?array
    {
        $base     = $this->getBase();
        $template = $base->channels()->where('channel', $channel)->where('is_active', true)->first();

        return $template ? $template->toArray() : null;
    }
}
