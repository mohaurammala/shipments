<?php
/**
 * One parcel row. Included by form.php for each parcel, and once inside a <template> with
 * $key "__INDEX__", which app.js copies (with a fresh number) when "Add a parcel" is clicked.
 *
 * @var int|string $key The parcel's key in the form: parcels[{key}][...]
 * @var int $number Its position as shown to the user (app.js renumbers after changes).
 * @var callable $input
 * @var callable $errorFor
 */
$parcel = "parcels.{$key}";
?>
<div class="border rounded p-3 mb-3" data-parcel="<?= escape((string) $key) ?>">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h3 class="h6 mb-0">Parcel <span data-parcel-number><?= $number ?></span></h3>
        <button type="button" class="btn btn-outline-danger btn-sm" data-remove-parcel hidden>Remove</button>
    </div>
    <?php if ($errorFor($parcel) !== null): ?>
        <div class="text-danger small mb-2"><?= escape($errorFor($parcel)) ?></div>
    <?php endif ?>
    <div class="row g-3">
        <?php $input("{$parcel}.description", 'What is in it', ['maxlength' => '254', 'required' => 'required'], '', 'col-12'); ?>
        <?php foreach (['length_cm' => 'Length (cm)', 'width_cm' => 'Width (cm)', 'height_cm' => 'Height (cm)', 'weight_kg' => 'Weight (kg)'] as $field => $label): ?>
            <?php $input("{$parcel}.{$field}", $label, ['inputmode' => 'decimal', 'required' => 'required'], '', 'col-6 col-md-3'); ?>
        <?php endforeach ?>
    </div>
</div>
