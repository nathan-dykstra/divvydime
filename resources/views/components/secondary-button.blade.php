@props(['type' => 'button', 'class' => '', 'id' => null, 'icon' => null, 'iconId' => null, 'form' => null, 'href' => null, 'onclick' => null])

@if ($href)
    <a {{ $attributes->merge(['class' => 'secondary-btn ' . $class, 'id' => $id, 'onclick' => $onclick, 'href' => $href]) }}>
        @if ($icon)
            <i class="{{ $icon }}" id="{{ $iconId }}"></i>
        @endif
        {{ $slot }}
    </a>
@elseif ($form)  
    <button {{ $attributes->merge(['class' => 'secondary-btn ' . $class, 'id' => $id, 'form' => $form]) }}>
        @if ($icon)
            <i class="{{ $icon }}" id="{{ $iconId }}"></i>
        @endif
        {{ $slot }}
    </button>
@else
    <button {{ $attributes->merge(['type' => $type, 'class' => 'secondary-btn ' . $class, 'id' => $id, 'onclick' => $onclick]) }}>
        @if ($icon)
            <i class="{{ $icon }}" id="{{ $iconId }}"></i>
        @endif
        {{ $slot }}
    </button>
@endif

<style>
    .secondary-btn {
        display: inline-flex;
        justify-content: center;
        align-items: center;
        height: 36px;
        border: 1px solid var(--icon-grey);
        border-radius: var(--border-radius);
        padding: 8px 16px;
        transition: border 0.3s, background-color 0.3s ease-in-out, outline 0.1s ease-in-out, outline-offset 0.1s;
        font-size: 0.8em;
        font-weight: 700;
        color: var(--text-heading);
        text-transform: uppercase;
        letter-spacing: 1px;
        outline: none;
    }

    .secondary-btn:hover {
        background-color: var(--primary-grey-hover);
        cursor: pointer;
    }

    .secondary-btn:focus-visible {
        outline: 3px solid var(--blue-text);
        outline-offset: 1px;
        border-radius: var(--border-radius);
    }
</style>
