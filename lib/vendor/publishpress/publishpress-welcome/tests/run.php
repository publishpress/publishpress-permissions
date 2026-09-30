<?php

require_once __DIR__ . '/bootstrap.php';

use PublishPress\Welcome\ChecklistTask;
use PublishPress\Welcome\Contract\StepInterface;
use PublishPress\Welcome\ExperienceConfig;
use PublishPress\Welcome\RenderContext;
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
