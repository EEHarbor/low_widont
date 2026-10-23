<?php

/**
 * Model only the inherited EE_Fieldtype state used by the shipped adapter.
 */
abstract class EE_Fieldtype
{
    public $settings = [];
    protected $content_id = null;

    /**
     * Apply request field settings and reset the content ID between initializations.
     *
     * @param array $config
     * @return void
     */
    public function _init($config = [])
    {
        if (array_key_exists('settings', $config)) {
            $this->settings = $config['settings'];
        }
        $this->content_id = isset($config['content_id']) ? $config['content_id'] : null;
    }

    /**
     * Return the persisted parent-content identifier, including the unsaved sentinel.
     *
     * @return int|null
     */
    public function content_id()
    {
        return $this->content_id;
    }
}
