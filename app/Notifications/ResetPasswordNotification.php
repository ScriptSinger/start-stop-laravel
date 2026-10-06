<?php

namespace App\Notifications;

use App\Models\Customer;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Письмо «Восстановление пароля» покупателю. Текст — по-русски здесь же,
 * чтобы не зависеть от языка приложения (APP_LOCALE).
 */
class ResetPasswordNotification extends Notification
{
    public function __construct(private readonly string $token) {}

    /**
     * @return array<int, string>
     */
    public function via(Customer $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(Customer $notifiable): MailMessage
    {
        $url = route('password.reset', ['token' => $this->token, 'email' => $notifiable->email]);
        $minutes = config('auth.passwords.customers.expire');

        return (new MailMessage)
            ->subject('Восстановление пароля — '.config('shop.name'))
            ->greeting('Здравствуйте!')
            ->line('Вы запросили восстановление пароля на сайте «'.config('shop.name').'».')
            ->action('Задать новый пароль', $url)
            ->line("Ссылка действует {$minutes} минут.")
            ->line('Если вы не запрашивали восстановление, просто проигнорируйте это письмо.')
            ->salutation('Магазин «'.config('shop.name').'», '.config('shop.phone'));
    }
}
