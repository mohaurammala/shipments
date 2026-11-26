<?php
/**
 * The address and contact for one side of the shipment. Included by form.php, whose
 * helpers ($input, $select, ...) it uses.
 *
 * @var string $side "collection" or "delivery"
 * @var string $heading
 * @var callable $input
 * @var callable $select
 * @var array<string, string> $addressTypes
 * @var array<string, string> $provinces
 */
$address = "{$side}.address";
$contact = "{$side}.contact";
?>
<fieldset class="card mb-4">
    <div class="card-body">
        <legend class="h5"><?= escape($heading) ?></legend>
        <div class="row g-3">
            <?php $select("{$address}.type", 'Address type', $addressTypes); ?>
            <?php $input("{$address}.company", 'Company', ['maxlength' => '254', 'autocomplete' => 'organization'], 'Required for business addresses.'); ?>
            <?php $input("{$address}.street", 'Street address', ['maxlength' => '254', 'required' => 'required', 'autocomplete' => 'address-line1'], '', 'col-12'); ?>
            <?php $input("{$address}.suburb", 'Suburb', ['maxlength' => '254', 'required' => 'required']); ?>
            <?php $input("{$address}.city", 'City or town', ['maxlength' => '254', 'required' => 'required', 'autocomplete' => 'address-level2']); ?>
            <?php $select("{$address}.province", 'Province', $provinces, 'col-md-5'); ?>
            <?php $input("{$address}.postcode", 'Postal code', ['inputmode' => 'numeric', 'maxlength' => '4', 'required' => 'required', 'autocomplete' => 'postal-code'], '', 'col-6 col-md-3'); ?>
            <?php $select("{$address}.country", 'Country', ['ZA' => 'South Africa'], 'col-6 col-md-4'); ?>
        </div>

        <h3 class="h6 mt-4">Contact person</h3>
        <div class="row g-3">
            <?php $input("{$contact}.name", 'Name', ['maxlength' => '254', 'required' => 'required', 'autocomplete' => 'name'], '', 'col-12'); ?>
            <?php $input("{$contact}.mobile", 'Mobile number', ['type' => 'tel', 'required' => 'required', 'autocomplete' => 'tel', 'placeholder' => '082 123 4567']); ?>
            <?php $input("{$contact}.email", 'Email', ['type' => 'email', 'maxlength' => '254', 'required' => 'required', 'autocomplete' => 'email']); ?>
        </div>
    </div>
</fieldset>
