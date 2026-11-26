<?php
/**
 * A summary of everything wrong with a submitted form, each linking to its field. Screen
 * reader users hear it first (role="alert") and can jump straight to each problem.
 * Included by other templates.
 *
 * @var App\Validation\ValidationResult $errors
 * @var callable(string): string $fieldId
 */
?>
<?php if (!$errors->isValid()): ?>
    <div class="alert alert-danger" role="alert">
        <h2 class="h6">
            <?= count($errors->errors) === 1 ? 'One thing needs' : count($errors->errors) . ' things need' ?> fixing before this can be saved:
        </h2>
        <ul class="mb-0">
            <?php foreach ($errors->errors as $path => $message): ?>
                <li><a class="alert-link" href="#<?= escape($fieldId($path)) ?>"><?= escape($message) ?></a></li>
            <?php endforeach ?>
        </ul>
    </div>
<?php endif ?>
