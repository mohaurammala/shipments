<?php
/**
 * @var string $content
 * @var string|null $title
 */
?>
[layout:<?= escape($title ?? 'none') ?>]<?= $content ?>[/layout]
