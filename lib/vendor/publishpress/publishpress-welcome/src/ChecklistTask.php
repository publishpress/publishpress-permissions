<?php

namespace PublishPress\Welcome;

use InvalidArgumentException;
use PublishPress\Welcome\Contract\ChecklistTaskInterface;

class ChecklistTask implements ChecklistTaskInterface
{
    private $id;
    private $label;
    private $url;
    private $completeCallback;
    private $detailCallback;

    public function __construct($id, $label, $url, callable $completeCallback, callable $detailCallback = null)
    {
        $id = sanitize_key($id);

        if (! $id) {
            throw new InvalidArgumentException('Checklist task ID cannot be empty.');
        }

        $this->id = $id;
        $this->label = (string) $label;
        $this->url = (string) $url;
        $this->completeCallback = $completeCallback;
        $this->detailCallback = $detailCallback;
    }

    public function getId()
    {
        return $this->id;
    }

    public function getLabel()
    {
        return $this->label;
    }

    public function getUrl()
    {
        return $this->url;
    }

    public function isComplete()
    {
        return (bool) call_user_func($this->completeCallback);
    }

    public function getDetail()
    {
        return $this->detailCallback ? (string) call_user_func($this->detailCallback) : '';
    }
}
