<?php

namespace PublishPress\Welcome\Contract;

interface ChecklistTaskInterface
{
    /** @return string */
    public function getId();

    /** @return string */
    public function getLabel();

    /** @return string */
    public function getUrl();

    /** @return bool */
    public function isComplete();

    /** @return string */
    public function getDetail();
}
