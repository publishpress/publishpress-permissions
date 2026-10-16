<?php

require_once __DIR__ . '/bootstrap.php';

use PublishPress\Welcome\ChecklistTask;
use PublishPress\Welcome\Contract\StepInterface;
use PublishPress\Welcome\EmailQueue;
use PublishPress\Welcome\EmailSubscriber;
use PublishPress\Welcome\ExperienceConfig;
use PublishPress\Welcome\NoticePolicy;
use PublishPress\Welcome\Privacy;
use PublishPress\Welcome\RenderContext;
use PublishPress\Welcome\SharedProfile;
use PublishPress\Welcome\WhatsNew;
use PublishPress\Welcome\Stats;
use PublishPress\Welcome\VersionLoader;

$assertions = 0;

$assertSame = function ($expected, $actual, $message) use (&$assertions) {
    $assertions++;
    if ($expected !== $actual) {
        throw new RuntimeException($message . sprintf(' Expected %s, got %s.', var_export($expected, true), var_export($actual, true)));
    }
};

$assertTrue = function ($value, $message) use (&$assertions) {
    $assertions++;
    if (true !== $value) {
        throw new RuntimeException($message);
    }
};

$config = new ExperienceConfig(
    [
        'id' => 'demo-plugin',
        'page_slug' => 'demo-welcome',
        'parent_slug' => 'demo-settings',
        'capability' => 'manage_options',
        'page_title' => 'Demo',
        'labels' => ['next' => 'Continue'],
    ]
);

$assertSame('demo-plugin_welcome_enrolled', $config->get('enrollment_option'), 'Enrollment state must be isolated by experience.');
$assertSame('Continue', $config->label('next'), 'Consumer labels must override defaults.');
$assertSame('Back', $config->label('back'), 'Unchanged labels must retain defaults.');

$invalidIdRejected = false;
try {
    new ExperienceConfig(
        [
            'id' => 'Demo Plugin',
            'page_slug' => 'demo-welcome',
            'parent_slug' => 'demo-settings',
            'capability' => 'manage_options',
            'page_title' => 'Demo',
        ]
    );
} catch (InvalidArgumentException $exception) {
    $invalidIdRejected = true;
}
$assertTrue($invalidIdRejected, 'Unsanitized experience IDs must be rejected.');

$assertSame('screen', $config->get('mode'), 'The in-screen layout must be the default mode.');

$fullscreen = new ExperienceConfig(
    [
        'id' => 'demo-plugin',
        'page_slug' => 'demo-welcome',
        'parent_slug' => 'demo-settings',
        'capability' => 'manage_options',
        'page_title' => 'Demo',
        'mode' => 'fullscreen',
        'accent_color' => '#0af',
    ]
);
$assertSame('fullscreen', $fullscreen->get('mode'), 'The fullscreen mode must be accepted.');
$assertSame('screen', ExperienceConfig::normalizeMode('sideways'), 'Unknown modes must fall back to the in-screen layout.');
$assertSame('--ppw-accent:#00aaff;--ppw-accent-rgb:0,170,255;--ppw-accent-dark:#0084c6;', $fullscreen->styleVariables(), 'Short hex colours must expand and derive the CSS variables.');

$badAccent = new ExperienceConfig(
    [
        'id' => 'demo-plugin',
        'page_slug' => 'demo-welcome',
        'parent_slug' => 'demo-settings',
        'capability' => 'manage_options',
        'page_title' => 'Demo',
        'accent_color' => 'red;background:url(x)',
    ]
);
$assertSame('--ppw-accent:#655997;--ppw-accent-rgb:101,89,151;--ppw-accent-dark:#4e4575;', $badAccent->styleVariables(), 'Invalid accent colours must never reach the stylesheet.');

$assertTrue($config->get('email_capture')['enabled'], 'The email step must be enabled by default.');
$assertSame('before_last', $config->get('email_capture')['position'], 'The email step must default to just before the final step.');
$assertSame(EmailSubscriber::DEFAULT_ENDPOINT, $config->get('email_capture')['endpoint'], 'The email step must default to the connector endpoint.');
$assertSame('welcome-email-v1', $config->get('email_capture')['consent_statement'], 'Default wording must identify its registered statement.');
$assertSame(false, ExperienceConfig::mergeEmailCapture(['copy' => ['consent_label' => 'Custom consent']])['enabled'], 'Custom wording without a distinct ID must disable email capture.');
$assertSame(true, ExperienceConfig::mergeEmailCapture(['consent_statement' => 'custom-email-v1', 'copy' => ['consent_label' => 'Custom consent']])['enabled'], 'A valid explicit custom statement ID must allow custom wording.');
$assertSame(false, ExperienceConfig::mergeEmailCapture(['consent_statement' => '<b>bad</b>'])['enabled'], 'Statement IDs must be bounded plain IDs.');

$overridden = ExperienceConfig::mergeEmailCapture(['personas' => ['a' => 'A'], 'copy' => ['title' => 'Custom']]);
$assertSame(['a' => 'A'], $overridden['personas'], 'Consumer personas must replace the defaults.');
$assertSame('Custom', $overridden['copy']['title'], 'Consumer copy must override the matching default.');
$assertSame('Subscribe', $overridden['copy']['submit'], 'Unspecified copy must keep its default.');
$assertSame(false, ExperienceConfig::mergeEmailCapture(false)['enabled'], 'Passing false must disable the email step.');

$consented = ['email' => 'a@example.test', 'consent' => true, 'consent_statement' => 'welcome-email-v1'];
$dryRun = (new EmailSubscriber(['endpoint' => EmailSubscriber::PLACEHOLDER_ENDPOINT]))->send($consented);
$assertSame(true, $dryRun, 'The placeholder endpoint must be a successful dry run that sends nothing.');

$received = null;
$handled = (new EmailSubscriber(['handler' => function ($payload) use (&$received) {
    $received = $payload;
    return true;
}]))->send($consented);
$assertSame(true, $handled, 'A consumer handler must be able to take over delivery.');
$assertSame($consented, $received, 'The handler must receive the consented contract payload.');

$failed = (new EmailSubscriber(['handler' => '__return_false']))->send([]);
$assertTrue($failed instanceof WP_Error, 'A handler returning false must be reported as an error.');

$GLOBALS['ppw_http_response'] = ['status' => 202, 'headers' => []];
$assertSame(true, (new EmailSubscriber(['endpoint' => EmailSubscriber::DEFAULT_ENDPOINT]))->send($consented + ['consent_text' => 'Must not travel', 'consented_at' => 'Client time', 'license_key' => 'Must not queue']), 'A valid connector 202 must be accepted.');
$assertSame($consented, json_decode($GLOBALS['ppw_last_http'][1]['body'], true), 'HTTP payload must exclude client consent wording/time and license keys.');
$assertSame(0, $GLOBALS['ppw_last_http'][1]['redirection'], 'Subscriber requests must not follow redirects.');
$GLOBALS['ppw_http_response'] = ['status' => 400, 'headers' => []];
$bad = (new EmailSubscriber(['endpoint' => EmailSubscriber::DEFAULT_ENDPOINT]))->send($consented);
$assertSame(false, EmailQueue::retryable($bad), 'A permanent 400 must not retry.');
$GLOBALS['ppw_http_response'] = ['status' => 429, 'headers' => ['retry-after' => '900']];
$limited = (new EmailSubscriber(['endpoint' => EmailSubscriber::DEFAULT_ENDPOINT]))->send($consented);
$assertSame(true, EmailQueue::retryable($limited), 'A 429 must remain retryable.');
EmailQueue::enqueue('http-test', $consented, 1000, $limited);
$assertSame(1900, EmailQueue::pending()[0]['next'], 'Initial retry must respect Retry-After.');
EmailQueue::registerSender('http-test', function () use ($bad) { return $bad; });
EmailQueue::process(1900);
$assertSame([], EmailQueue::pending(), 'Permanent errors during queued delivery must drop the item.');
EmailQueue::enqueue('http-test', $consented, 1000, $bad);
$assertSame([], EmailQueue::pending(), 'Permanent initial failures must not be queued.');

$assertTrue(SharedProfile::shouldAsk(7), 'A user who has not chosen must be asked.');
SharedProfile::recordSubscribed(7, 'agency');
$assertSame('subscribed', SharedProfile::status(7), 'Subscribing must be remembered.');
$assertSame('agency', SharedProfile::persona(7), 'The persona must be remembered with the subscription.');
$assertSame(false, SharedProfile::shouldAsk(7, 30), 'A subscribed user must never be asked again, even with a snooze.');
$assertTrue(SharedProfile::shouldAsk(8), 'Other users must be unaffected.');

SharedProfile::recordDeclined(9, 1000000);
$assertSame(false, SharedProfile::shouldAsk(9, 0, 1000000 + 86400 * 365), 'Without a snooze a decline must be permanent.');
$assertSame(false, SharedProfile::shouldAsk(9, 30, 1000000 + 86400 * 29), 'A decline must be respected during the snooze window.');
$assertTrue(SharedProfile::shouldAsk(9, 30, 1000000 + 86400 * 30), 'A decline must expire after the snooze window.');

$assertSame(false, NoticePolicy::isExpired(0, 14, 5000000), 'A notice that was never shown cannot be expired.');
$assertSame(false, NoticePolicy::isExpired(1000000, 14, 1000000 + 86400 * 13), 'The notice must stay within its lifetime.');
$assertTrue(NoticePolicy::isExpired(1000000, 14, 1000000 + 86400 * 14), 'The notice must expire after its lifetime.');
$assertSame(false, NoticePolicy::isExpired(1000000, 0, 9000000), 'A zero lifetime must disable expiry.');
$assertTrue(NoticePolicy::allowsScreen('demo-settings', ['demo-settings'], false, false), 'The notice must appear on listed screens.');
$assertSame(false, NoticePolicy::allowsScreen('other-plugin', ['demo-settings'], false, false), 'The notice must not appear on unrelated screens.');
$assertSame(false, NoticePolicy::allowsScreen('', ['demo-settings'], false, true), 'The Dashboard must be excluded unless enabled.');
$assertTrue(NoticePolicy::allowsScreen('', ['demo-settings'], true, true), 'The Dashboard must be allowed when enabled.');
$assertSame(['demo-settings'], (new ExperienceConfig(['id' => 'demo-plugin', 'page_slug' => 'demo-welcome', 'parent_slug' => 'demo-settings', 'capability' => 'manage_options', 'page_title' => 'Demo', 'checklist_pages' => ['demo-settings']]))->get('notice_pages'), 'Notice pages must default to the checklist pages.');

Privacy::track('demo-plugin', 'demo-plugin_welcome');
SharedProfile::recordSubscribed(21, 'developer');
update_user_option(21, 'demo-plugin_welcome_step', 3);
$exported = Privacy::exportItems(21);
$assertSame(2, count($exported), 'Export must include the shared profile and each experience.');
$assertSame('subscribed', $exported[0]['data'][0]['value'], 'Export must include the subscription choice.');
$assertSame('3', $exported[1]['data'][0]['value'], 'Export must include per-experience progress.');
$assertSame([], Privacy::exportItems(22), 'A user with no stored data must export nothing.');
$assertTrue(Privacy::erase(21), 'Erasure must report that data was removed.');
$assertSame('', SharedProfile::status(21), 'Erasure must remove the subscription choice.');
$assertSame(false, get_user_option('demo-plugin_welcome_step', 21), 'Erasure must remove per-experience progress.');
$assertSame(false, Privacy::erase(21), 'Erasing again must report that nothing was removed.');

$sendResults = [false, true];
$sendCalls = 0;
EmailQueue::registerSender('demo-plugin', function ($payload) use (&$sendResults, &$sendCalls) {
    $sendCalls++;
    return array_shift($sendResults);
});
EmailQueue::enqueue('demo-plugin', ['email' => 'queued@example.test'], 1000);
$assertSame(1, count(EmailQueue::pending()), 'A failed delivery must be queued.');
$assertSame(0, EmailQueue::process(1000 + 299), 'An item must not be retried before its delay.');
$assertSame(0, $sendCalls, 'Nothing must be sent before the item is due.');
$assertSame(0, EmailQueue::process(1000 + 300), 'A failed retry must not count as delivered.');
$assertSame(2, EmailQueue::pending()[0]['attempts'], 'A failed retry must increase the attempt count.');
$assertSame(1000 + 300 + 1800, EmailQueue::pending()[0]['next'], 'The delay must grow after each failure.');
$assertSame(1, EmailQueue::process(1000 + 300 + 1800), 'A successful retry must report the delivery.');
$assertSame([], EmailQueue::pending(), 'A delivered item must be removed.');

EmailQueue::enqueue('demo-plugin', ['email' => 'stale@example.test'], 2000);
EmailQueue::process(2000 + 7 * 86400);
$assertSame([], EmailQueue::pending(), 'An item older than the maximum age must be discarded.');

EmailQueue::enqueue('other-plugin', ['email' => 'Keep@Example.test'], 3000);
$assertSame(0, EmailQueue::process(3000 + 400), 'Items of an unregistered experience must not be processed.');
$assertSame(1, count(EmailQueue::pending()), 'Items of an unregistered experience must be left in the queue.');
$assertSame(1, count(EmailQueue::itemsForEmail('keep@example.test')), 'Queued items must be found case-insensitively by email.');
$assertTrue(EmailQueue::eraseByEmail('keep@example.test'), 'Erasing by email must remove the queued item.');
$assertSame([], EmailQueue::pending(), 'The queue must be empty after erasure.');

Stats::increment('demo-plugin', Stats::VIEWS, 'welcome');
Stats::increment('demo-plugin', Stats::VIEWS, 'welcome');
Stats::increment('demo-plugin', Stats::EXITS, 'concepts');
Stats::increment('demo-plugin', Stats::COUNTERS, Stats::COMPLETED);
$stats = Stats::get('demo-plugin');
$assertSame(2, $stats['views']['welcome'], 'Step views must be counted.');
$assertSame(1, $stats['exits']['concepts'], 'Exits must be counted per step.');
$assertSame(['views' => [], 'exits' => [], 'counters' => []], Stats::get('never-used'), 'Unknown experiences must start empty.');
$fields = Stats::siteHealthFields('demo-plugin', ['welcome', 'concepts']);
$assertSame('2 views, 0 exits', $fields['step_welcome']['value'], 'Site Health must summarise views and exits per step.');
$assertSame('1', $fields['completed']['value'], 'Site Health must include the completion counter.');
$assertSame('0', $fields['email_subscribed']['value'], 'Unused counters must read zero.');

$assertSame(null, WhatsNew::normalize(false), 'Disabled highlights must normalize to null.');
$assertSame(null, WhatsNew::normalize(['version' => '1.0', 'title' => 'T', 'items' => []]), 'Highlights without items must be rejected.');
$whatsNew = WhatsNew::normalize(['version' => '4.9.1 <b>', 'title' => 'T', 'items' => [['title' => 'A'], ['title' => ''], ['title' => 'B', 'body' => 'b'], ['title' => 'C'], ['title' => 'D']]]);
$assertSame('4.9.1b', $whatsNew['version'], 'The version must be sanitised.');
$assertSame(3, count($whatsNew['items']), 'Highlights must be limited to three valid items.');
$assertTrue(WhatsNew::isNewerThan('', '4.9.1'), 'A user who never saw highlights must see them.');
$assertTrue(WhatsNew::isNewerThan('4.9.0', '4.9.1'), 'A newer version must be shown again.');
$assertSame(false, WhatsNew::isNewerThan('4.9.1', '4.9.1'), 'The same version must not be shown twice.');
$assertSame(false, WhatsNew::isNewerThan('5.0.0', '4.9.1'), 'An older version must never be shown.');

$calls = 0;
$task = new ChecklistTask(
    'configure',
    'Configure',
    'https://example.test/settings',
    function () use (&$calls) {
        $calls++;
        return true;
    },
    function () {
        return 'Configured';
    }
);
$assertSame(0, $calls, 'Checklist callbacks must remain lazy.');
$assertTrue($task->isComplete(), 'Checklist completion callback must be evaluated.');
$assertSame(1, $calls, 'Checklist completion callback must run exactly once per evaluation.');
$assertSame('Configured', $task->getDetail(), 'Checklist detail callback must be supported.');

$customStep = new class implements StepInterface {
    public function getId()
    {
        return 'custom';
    }

    public function render(RenderContext $context)
    {
        echo '<p>Independent consumer</p>';
    }
};
$assertSame('custom', $customStep->getId(), 'Consumers must be able to supply custom step implementations.');

$loader = new VersionLoader();
$assertTrue($loader->register('1.0.0.1', '__return_null'), 'First version registration must succeed.');
$assertTrue($loader->register('1.2.0.1', '__return_null'), 'A second version registration must succeed.');
$assertSame('1.2.0.1', $loader->latestVersion(), 'Newest-wins loading must use version comparison.');
$assertSame(false, $loader->register('1.2.0.1', '__return_null'), 'Duplicate version registration must be rejected.');

fwrite(STDOUT, sprintf("OK (%d assertions)\n", $assertions));
