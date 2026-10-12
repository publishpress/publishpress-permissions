<?php

namespace PublishPress\Welcome;

/**
 * Keeps the activation notice sparing, as WordPress.org guideline 11 requires of promotional admin notices.
 */
class NoticePolicy
{
    /**
     * Whether the notice has been visible for longer than its allowed lifetime.
     *
     * @param int      $firstSeen Unix time the notice was first displayed to the user; 0 when never shown.
     * @param int      $days      Lifetime in days; 0 disables expiry.
     * @param int|null $now       Unix time, injectable for tests.
     * @return bool
     */
    public static function isExpired($firstSeen, $days, $now = null)
    {
        if ((int) $days <= 0 || (int) $firstSeen <= 0) {
            return false;
        }

        $now = null === $now ? time() : (int) $now;

        return ($now - (int) $firstSeen) >= (int) $days * 86400;
    }

    /**
     * Whether the current admin screen is one the notice may appear on.
     *
     * @param string   $currentPage The ?page= slug, or an empty string.
     * @param string[] $pages       Allowed page slugs.
     * @param bool     $onDashboard Whether the main Dashboard is also allowed.
     * @param bool     $isDashboard Whether the current screen is the Dashboard.
     * @return bool
     */
    public static function allowsScreen($currentPage, array $pages, $onDashboard, $isDashboard)
    {
        if ($isDashboard) {
            return (bool) $onDashboard;
        }

        return '' !== $currentPage && in_array($currentPage, $pages, true);
    }
}
