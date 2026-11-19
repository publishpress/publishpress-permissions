<?php
// Run: php tests/unit/frontend-compat-authorization.php
define('ABSPATH', __DIR__ . '/../../');
class DS_Public_Post_Preview {}
class Fusion_Panel {}
function is_admin() { return false; }
function wp_get_theme() { return new class {
    public $Name = 'Avada';
    public function parent() { return false; }
}; }
function add_filter(...$args) {}
require ABSPATH . 'classes/PublishPress/Permissions.php';
require ABSPATH . 'classes/PublishPress/Permissions/Compat/ThemeCompat.php';
$pp = (new ReflectionClass('PublishPress\\Permissions'))->newInstanceWithoutConstructor();
$_SERVER['REQUEST_URI'] = '/?page_id=12&et_fb=1&et_bfb=1';
$_REQUEST = ['page_id' => 12, 'et_fb' => 1, 'et_bfb' => 1, '_ppp' => 'invalid'];
if ($pp->checkInitInterrupt()) throw new RuntimeException('Spoofed builder flags disabled initialization');
foreach ([false, true] as $authorized) {
    if ($pp->fltPluginCompatUnfilteredContent($authorized) !== $authorized) {
        throw new RuntimeException('Preview token changed global authorization');
    }
    $theme = new PublishPress\Permissions\Compat\ThemeCompat();
    if ($theme->fltAvadaUnfiltered($authorized, []) !== $authorized) {
        throw new RuntimeException('Avada changed global authorization');
    }
}
echo "OK: frontend compatibility authorization\n";
