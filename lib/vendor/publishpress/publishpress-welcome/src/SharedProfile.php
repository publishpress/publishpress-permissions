<?php

namespace PublishPress\Welcome;

/**
 * Per-user email-capture choice shared by every plugin that bundles this library.
 *
 * Without it each PublishPress plugin would ask the same person for the same email again. The choice is stored
 * under plugin-independent user options, so subscribing or declining once is respected everywhere on the site.
 */
class SharedProfile
{
    const STATUS_OPTION = 'publishpress_welcome_email_status';
    const PERSONA_OPTION = 'publishpress_welcome_persona';
    const DECLINED_AT_OPTION = 'publishpress_welcome_email_declined_at';

    const SUBSCRIBED = 'subscribed';
    const DECLINED = 'declined';

    /** @return string "subscribed", "declined" or an empty string when the user has not chosen. */
    public static function status($userId)
    {
        $status = (string) get_user_option(self::STATUS_OPTION, $userId);

        return in_array($status, [self::SUBSCRIBED, self::DECLINED], true) ? $status : '';
    }

    /** @return string */
    public static function persona($userId)
    {
        return (string) get_user_option(self::PERSONA_OPTION, $userId);
    }

    public static function recordSubscribed($userId, $persona)
    {
        update_user_option($userId, self::STATUS_OPTION, self::SUBSCRIBED);
        update_user_option($userId, self::PERSONA_OPTION, (string) $persona);
    }

    public static function recordDeclined($userId, $now = null)
    {
        update_user_option($userId, self::STATUS_OPTION, self::DECLINED);
        update_user_option($userId, self::DECLINED_AT_OPTION, null === $now ? time() : (int) $now);
    }

    /**
     * Whether the email step should still be offered to this user.
     *
     * @param int      $userId
     * @param int      $snoozeDays Days after a decline before asking again; 0 means never ask again.
     * @param int|null $now        Unix time, injectable for tests.
     * @return bool
     */
    public static function shouldAsk($userId, $snoozeDays = 0, $now = null)
    {
        $status = self::status($userId);

        if ('' === $status) {
            return true;
        }

        if (self::SUBSCRIBED === $status || (int) $snoozeDays <= 0) {
            return false;
        }

        $declinedAt = (int) get_user_option(self::DECLINED_AT_OPTION, $userId);
        $now = null === $now ? time() : (int) $now;

        return $declinedAt > 0 && ($now - $declinedAt) >= (int) $snoozeDays * 86400;
    }
}
