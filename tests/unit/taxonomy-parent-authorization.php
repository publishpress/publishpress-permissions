<?php
// Run: php tests/unit/taxonomy-parent-authorization.php
define('ABSPATH', __DIR__ . '/../../');
require ABSPATH . 'modules/presspermit-collaboration/classes/Permissions/Collab/TermParent.php';
function get_taxonomy($tx) { return (object) ['hierarchical' => true, 'cap' => (object) ['manage_terms' => 'manage_categories']]; }
function get_term($id, $tx) { return $id === 999 ? false : (object) ['term_taxonomy_id' => $id + 100, 'parent' => 10]; }
function is_wp_error($value) { return false; }
function apply_filters($name, $value, ...$args) { return $value; }
function get_terms($args) { return [10]; }
function presspermit() { return $GLOBALS['pp']; }
$user = new class {
    public $allcaps = ['manage_categories' => true], $exceptions = [];
    public function getExceptionTerms($op, $mode, ...$args) { return $this->exceptions[$mode] ?? []; }
};
$GLOBALS['pp'] = new class($user) {
    private $user;
    public function __construct($user) { $this->user = $user; }
    public function isTaxonomyEnabled($tx) { return true; }
    public function isUserUnfiltered() { return false; }
    public function getUser() { return $this->user; }
};
function checkParent($parent, $expected, $label, $id = 0) {
    if (PublishPress\Permissions\Collab\TermParent::canSelect('category', $parent, $id) !== $expected) throw new RuntimeException($label);
}
$user->exceptions = ['exclude' => [110, 0]];
checkParent(10, false, 'Excluded parent');
checkParent(0, false, 'Excluded root');
checkParent(11, true, 'Unrestricted parent');
checkParent(10, true, 'Unchanged parent', 30);
$user->exceptions = ['include' => [110]];
checkParent(10, true, 'Included parent');
checkParent(11, false, 'Outside inclusion');
$user->exceptions['additional'] = [111];
checkParent(11, true, 'Additional parent grant');
checkParent(999, false, 'Nonexistent parent');
$user->allcaps = [];
$user->exceptions = [];
checkParent(0, false, 'Scoped manager cannot create at root');
checkParent(10, true, 'Scoped manageable parent');
checkParent(11, false, 'Scoped unrelated parent');
echo "OK: taxonomy parent authorization\n";
