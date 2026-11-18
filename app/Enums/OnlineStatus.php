<?php

namespace App\Enums;

/**
 * Статус присутствия на сайте или в админке. В БД не хранится: считается по last_seen_at
 * (см. HasOnlineStatus). Значения служат ключами для MoonShine Enum.
 */
enum OnlineStatus: string
{
    /** Визитов ещё не было (last_seen_at пуст): старые и импортированные клиенты. */
    case Unknown = 'unknown';

    /** Был на сайте не позже HasOnlineStatus::ONLINE_MINUTES минут назад. */
    case Online = 'online';

    case Offline = 'offline';

    /**
     * Статус по времени последнего визита.
     */
    public static function fromLastSeen(?\DateTimeInterface $lastSeenAt, int $onlineMinutes): self
    {
        if ($lastSeenAt === null) {
            return self::Unknown;
        }

        $threshold = now()->subMinutes($onlineMinutes);

        return $lastSeenAt >= $threshold ? self::Online : self::Offline;
    }

    /**
     * Подпись в админке; её же показывает MoonShine Enum.
     */
    public function toString(): string
    {
        return match ($this) {
            self::Unknown => 'Нет данных',
            self::Online => 'Онлайн',
            self::Offline => 'Не в сети',
        };
    }
}
