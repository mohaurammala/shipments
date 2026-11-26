<?php
/**
 * The shipment details form, for a new booking or editing a draft. After a failed
 * save, every value comes back exactly as typed, parcels included, with each message next
 * to its field.
 *
 * Field names follow the shape ShipmentValidator expects: "collection.address.street" is
 * the input named collection[address][street], with the id collection-address-street.
 *
 * @var App\Domain\Booking|null $booking Null for a new booking.
 * @var array<string, mixed> $values
 * @var App\Validation\ValidationResult $errors
 * @var int $maxParcels
 * @var string $csrfToken
 */

use App\Domain\Enums\AddressType;
use App\Domain\Enums\Province;

// Helpers shared by this form and the parts it includes (_party.php, _parcel.php).
$value = static function (string $path) use ($values): string {
    $current = $values;

    foreach (explode('.', $path) as $key) {
        if (!is_array($current) || !array_key_exists($key, $current)) {
            return '';
        }

        $current = $current[$key];
    }

    return is_scalar($current) ? (string) $current : '';
};
$fieldName = static fn (string $path): string => (string) preg_replace('/\.([^.]+)/', '[$1]', $path);
$fieldId = static fn (string $path): string => str_replace('.', '-', $path);
$errorFor = static fn (string $path): ?string => $errors->error($path);

/**
 * One labelled input with its error. $attributes are added as given (already safe values).
 *
 * @param array<string, string> $attributes
 */
$input = static function (string $path, string $label, array $attributes = [], string $hint = '', string $class = 'col-md-6') use ($value, $fieldName, $fieldId, $errorFor): void {
    $id = $fieldId($path);
    $error = $errorFor($path);
    $describedBy = trim(($hint !== '' ? "{$id}-hint " : '') . ($error !== null ? "{$id}-error" : ''));
    ?>
    <div class="<?= escape($class) ?>">
        <label class="form-label" for="<?= escape($id) ?>"><?= escape($label) ?></label>
        <input class="form-control<?= $error !== null ? ' is-invalid' : '' ?>" id="<?= escape($id) ?>"
               name="<?= escape($fieldName($path)) ?>" value="<?= escape($value($path)) ?>"
            <?php foreach ($attributes as $attribute => $attributeValue): ?>
                <?= escape($attribute) ?>="<?= escape($attributeValue) ?>"
            <?php endforeach ?>
            <?php if ($describedBy !== ''): ?>aria-describedby="<?= escape($describedBy) ?>"<?php endif ?>>
        <?php if ($hint !== ''): ?>
            <div class="form-text" id="<?= escape($id) ?>-hint"><?= escape($hint) ?></div>
        <?php endif ?>
        <?php if ($error !== null): ?>
            <div class="invalid-feedback" id="<?= escape($id) ?>-error"><?= escape($error) ?></div>
        <?php endif ?>
    </div>
    <?php
};

/**
 * One labelled select with its error.
 *
 * @param array<string, string> $options value => label
 */
$select = static function (string $path, string $label, array $options, string $class = 'col-md-6') use ($value, $fieldName, $fieldId, $errorFor): void {
    $id = $fieldId($path);
    $error = $errorFor($path);
    $selected = $value($path);
    ?>
    <div class="<?= escape($class) ?>">
        <label class="form-label" for="<?= escape($id) ?>"><?= escape($label) ?></label>
        <select class="form-select<?= $error !== null ? ' is-invalid' : '' ?>" id="<?= escape($id) ?>"
                name="<?= escape($fieldName($path)) ?>"<?php if ($error !== null): ?> aria-describedby="<?= escape($id) ?>-error"<?php endif ?>>
            <?php if (!array_key_exists($selected, $options)): ?>
                <option value="" selected>Choose…</option>
            <?php endif ?>
            <?php foreach ($options as $optionValue => $optionLabel): ?>
                <option value="<?= escape($optionValue) ?>"<?= (string) $optionValue === $selected ? ' selected' : '' ?>><?= escape($optionLabel) ?></option>
            <?php endforeach ?>
        </select>
        <?php if ($error !== null): ?>
            <div class="invalid-feedback" id="<?= escape($id) ?>-error"><?= escape($error) ?></div>
        <?php endif ?>
    </div>
    <?php
};

$addressTypes = [];
foreach (AddressType::cases() as $type) {
    $addressTypes[$type->value] = $type->label();
}

$provinces = [];
foreach (Province::cases() as $province) {
    $provinces[$province->value] = $province->label();
}

$parcels = $values['parcels'] ?? [];
$parcels = is_array($parcels) && $parcels !== [] ? $parcels : [0 => []];
$action = $booking === null ? '/bookings' : "/bookings/{$booking->publicId}/details";
$status = $booking?->status;
?>
<h1 class="h3 mb-3"><?= $booking === null ? 'New booking' : 'Edit booking' ?></h1>
<?php require __DIR__ . '/../partials/stepper.php'; ?>

<div class="row g-4">
    <div class="col-lg-8">
        <?php require __DIR__ . '/../partials/form-errors.php'; ?>

        <form method="post" action="<?= escape($action) ?>" novalidate data-lock-on-submit>
            <input type="hidden" name="_token" value="<?= escape($csrfToken) ?>">

            <?php foreach (['collection' => 'Collect from', 'delivery' => 'Deliver to'] as $side => $heading): ?>
                <?php require __DIR__ . '/_party.php'; ?>
            <?php endforeach ?>

            <fieldset class="card mb-4">
                <div class="card-body">
                    <legend class="h5">Parcels</legend>
                    <?php if ($errorFor('parcels') !== null): ?>
                        <div class="alert alert-danger py-2" id="parcels"><?= escape($errorFor('parcels')) ?></div>
                    <?php endif ?>
                    <div data-parcel-list data-max="<?= $maxParcels ?>">
                        <?php $number = 0; ?>
                        <?php foreach ($parcels as $key => $parcel): ?>
                            <?php $number++; require __DIR__ . '/_parcel.php'; ?>
                        <?php endforeach ?>
                    </div>
                    <button type="button" class="btn btn-outline-primary btn-sm" data-add-parcel hidden>Add a parcel</button>
                    <noscript><p class="form-text">To add more parcels, turn on JavaScript.</p></noscript>
                    <template id="parcel-template">
                        <?php $key = '__INDEX__'; $number = 0; require __DIR__ . '/_parcel.php'; ?>
                    </template>
                </div>
            </fieldset>

            <fieldset class="card mb-4">
                <div class="card-body">
                    <legend class="h5">Value and instructions</legend>
                    <div class="row g-3">
                        <?php $input('declared_value', 'Declared value (R)', ['inputmode' => 'decimal', 'required' => 'required', 'placeholder' => '1500.00'], 'What the contents are worth, for insurance. Enter 0 if there is nothing to insure.'); ?>
                        <?php foreach (['collection_instructions' => 'Collection instructions', 'delivery_instructions' => 'Delivery instructions'] as $path => $label): ?>
                            <?php $id = $fieldId($path); $error = $errorFor($path); ?>
                            <div class="col-12">
                                <label class="form-label" for="<?= escape($id) ?>"><?= escape($label) ?> <span class="text-body-secondary">(optional)</span></label>
                                <textarea class="form-control<?= $error !== null ? ' is-invalid' : '' ?>" id="<?= escape($id) ?>"
                                          name="<?= escape($fieldName($path)) ?>" rows="2" maxlength="500"<?php if ($error !== null): ?> aria-describedby="<?= escape($id) ?>-error"<?php endif ?>><?= escape($value($path)) ?></textarea>
                                <?php if ($error !== null): ?>
                                    <div class="invalid-feedback" id="<?= escape($id) ?>-error"><?= escape($error) ?></div>
                                <?php endif ?>
                            </div>
                        <?php endforeach ?>
                    </div>
                </div>
            </fieldset>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary"><?= $booking === null ? 'Save draft' : 'Save changes' ?></button>
                <a class="btn btn-link" href="<?= escape($booking === null ? '/' : "/bookings/{$booking->publicId}") ?>">Cancel</a>
            </div>
        </form>
    </div>

    <div class="col-lg-4">
        <?php if ($booking !== null): ?>
            <?php require __DIR__ . '/../partials/summary.php'; ?>
        <?php else: ?>
            <div class="card">
                <div class="card-body small">
                    <h2 class="h6">What happens next</h2>
                    <p class="mb-0">Save the details as a draft, then get a quote. Nothing is sent to the courier until you book.</p>
                </div>
            </div>
        <?php endif ?>
    </div>
</div>
