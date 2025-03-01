<?php

namespace NotificationChannels\Telegram;

use GuzzleHttp\Psr7\Response;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Notification;
use NotificationChannels\Telegram\Exceptions\CouldNotSendNotification;

/**
 * Class TelegramChannel.
 */
class TelegramChannel
{
    public function __construct(
        private readonly Dispatcher $dispatcher
    ) {}

    /**
     * Send the given notification.
     *
     *
     * @throws CouldNotSendNotification|\JsonException
     */
    public function send(mixed $notifiable, Notification $notification): ?array
    {
        // @phpstan-ignore-next-line
        $message = $notification->toTelegram($notifiable);

        if (is_string($message)) {
            $message = TelegramMessage::create($message);
        }

        if (! $message->canSend()) {
            return null;
        }

        $to = $message->getPayloadValue('chat_id') ?:
              ($notifiable->routeNotificationFor('telegram', $notification) ?:
              $notifiable->routeNotificationFor(self::class, $notification));

        if (! $to) {
            return null;
        }

        $message->to($to);

        if ($message->hasToken()) {
            $message->telegram->setToken($message->token);
        }

        try {
            $response = $message->send();
        } catch (CouldNotSendNotification $exception) {
            $data = [
                'to' => $message->getPayloadValue('chat_id'),
                'request' => $message->toArray(),
                'exception' => $exception,
            ];

            if ($message->exceptionHandler) {
                ($message->exceptionHandler)($data);
            }

            $this->dispatcher->dispatch(new NotificationFailed($notifiable, $notification, 'telegram', $data));

            throw $exception;
        }

        return $response instanceof Response
                ? json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR)
                : $response;
    }
}
