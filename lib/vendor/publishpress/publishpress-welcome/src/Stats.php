<?php

namespace PublishPress\Welcome;

/**
 * Anonymous, site-local funnel counters: how many times each step was viewed, where people left and how many
 * finished or answered the email step. No user, email or persona data is recorded and nothing is transmitted.
 */
class Stats
{
    const VIEWS = 'views';
    const EXITS = 'exits';
    const COUNTERS = 'counters';

    /** Counter names used by the controller. */
    const COMPLETED = 'completed';
    const EMAIL_SUBSCRIBED = 'email_subscribed';
    const EMAIL_DECLINED = 'email_declined';

    private static function option($experience)
    {
        return 'publishpress_welcome_stats_' . $experience;
    }

    /**
     * @param string $experience
     * @param string $bucket     One of VIEWS, EXITS or COUNTERS.
     * @param string $key        Step ID or counter name.
     */
    public static function increment($experience, $bucket, $key)
    {
        $stats = self::get($experience);
        $key = (string) $key;

        $stats[$bucket][$key] = (isset($stats[$bucket][$key]) ? (int) $stats[$bucket][$key] : 0) + 1;

        update_option(self::option($experience), $stats, false);
    }

    /** @return array{views: array, exits: array, counters: array} */
    public static function get($experience)
    {
        $stored = get_option(self::option($experience), []);
        $stored = is_array($stored) ? $stored : [];

        return [
            self::VIEWS => isset($stored[self::VIEWS]) ? (array) $stored[self::VIEWS] : [],
            self::EXITS => isset($stored[self::EXITS]) ? (array) $stored[self::EXITS] : [],
            self::COUNTERS => isset($stored[self::COUNTERS]) ? (array) $stored[self::COUNTERS] : [],
        ];
    }

    /**
     * Rows for the Site Health "Info" screen.
     *
     * @param string   $experience
     * @param string[] $stepIds    Ordered step IDs, so the funnel reads in tour order.
     * @return array<string,array{label:string,value:string}>
     */
    public static function siteHealthFields($experience, array $stepIds)
    {
        $stats = self::get($experience);
        $fields = [];

        foreach ($stepIds as $index => $stepId) {
            $views = isset($stats[self::VIEWS][$stepId]) ? (int) $stats[self::VIEWS][$stepId] : 0;
            $exits = isset($stats[self::EXITS][$stepId]) ? (int) $stats[self::EXITS][$stepId] : 0;

            $fields['step_' . $stepId] = [
                'label' => sprintf(
                    /* translators: 1: step number, 2: step ID */
                    __('Step %1$d (%2$s)', 'publishpress-welcome'),
                    $index + 1,
                    $stepId
                ),
                'value' => sprintf(
                    /* translators: 1: number of views, 2: number of exits */
                    __('%1$d views, %2$d exits', 'publishpress-welcome'),
                    $views,
                    $exits
                ),
            ];
        }

        foreach ([self::COMPLETED => __('Completed the guide', 'publishpress-welcome'), self::EMAIL_SUBSCRIBED => __('Subscribed by email', 'publishpress-welcome'), self::EMAIL_DECLINED => __('Declined the email step', 'publishpress-welcome')] as $counter => $label) {
            $fields[$counter] = ['label' => $label, 'value' => (string) (isset($stats[self::COUNTERS][$counter]) ? (int) $stats[self::COUNTERS][$counter] : 0)];
        }

        return $fields;
    }
}
