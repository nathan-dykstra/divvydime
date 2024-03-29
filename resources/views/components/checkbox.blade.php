@props(['class' => '', 'id' => null, 'name' => null, 'disabled' => false])

<label for="{{ $id }}" class="checkbox-label">
    <input {{ $attributes->merge([
        'id' => $id,
        'type' => 'checkbox',
        'class' => 'checkbox ' . $class,
        'name' => $name
    ]) }} {{ $disabled ? 'disabled' : '' }}>
    <span class="checkbox-label-text">{{ $slot }}</span>
</label>
