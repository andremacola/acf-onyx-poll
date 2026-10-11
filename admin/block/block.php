<?php
// Compatibility for integrations that still include the former render template.
if (!defined('ABSPATH')) { exit; }
echo onyx_poll_render_block(isset($block) ? $block : array());
