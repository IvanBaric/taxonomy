@props([
    'field',
    'modelPrefix' => 'taxonomy',
    'searchPrefix' => 'taxonomySearch',
    'createMethod' => 'createTaxonomyOption',
    'modalPrefix' => 'create-taxonomy-',
    'searchPlaceholder' => __('Traži'),
    'createLabel' => __('Dodaj novi unos'),
    'modalTitle' => __('Dodaj novi unos'),
    'modalInputLabel' => __('Naziv'),
    'modalInputPlaceholder' => __('Novi unos'),
    'modalSubmitLabel' => __('Novo'),
    'selectedValues' => [],
    'showLabel' => true,
    'showModal' => true,
])

@php
    $type = (string) $field->type;
    $fieldName = __($field->name);
    $modelName = $modelPrefix.'.'.$type;
    $searchName = $searchPrefix.'.'.$type;
    $modalName = $modalPrefix.$type;
    $isMultiple = (bool) $field->is_multiple;
    $isRequired = (bool) $field->is_required;
    $placeholder = __('Odaberi').' '.\Illuminate\Support\Str::lower($fieldName);
@endphp

<div {{ $attributes->merge(['wire:key' => 'taxonomy-field-'.$type]) }}>
    @if ($showLabel)
        <div class="mb-1.5 flex items-baseline justify-between gap-2">
            <label class="text-[13px] font-medium text-zinc-700 dark:text-zinc-200">
                {{ $fieldName }}
                @if ($isRequired)
                    <span class="text-red-400 dark:text-red-300" aria-hidden="true">*</span>
                @endif
            </label>

            @if ($isMultiple)
                @php($selectedCount = count(\Illuminate\Support\Arr::wrap($selectedValues)))
                @if ($selectedCount > 0)
                    <span class="text-[11px] tabular-nums text-zinc-400 dark:text-zinc-500">{{ $selectedCount }}</span>
                @endif
            @endif
        </div>
    @endif

    @if (! $isMultiple)
        <flux:select variant="listbox" wire:model="{{ $modelName }}" name="{{ $modelName }}" :invalid="$errors->has($modelName)" :placeholder="$placeholder" searchable clearable>
            <x-slot name="search">
                <flux:select.search class="px-4" :placeholder="$searchPlaceholder" />
            </x-slot>

            @foreach ($field->items as $item)
                <flux:select.option value="{{ $item->id }}">{{ __($item->name) }}</flux:select.option>
            @endforeach

            <flux:select.option.create modal="{{ $modalName }}">
                {{ $createLabel }}
            </flux:select.option.create>
        </flux:select>
    @else
        <flux:pillbox wire:model="{{ $modelName }}" name="{{ $modelName }}" :invalid="$errors->has($modelName)" variant="combobox" multiple wire:key="taxonomy-pillbox-{{ $type }}">
            <x-slot name="input">
                <flux:pillbox.input wire:model.live.debounce.250ms="{{ $searchName }}" :placeholder="$searchPlaceholder" />
            </x-slot>

            @foreach ($field->items as $item)
                <flux:pillbox.option :value="$item->id">{{ __($item->name) }}</flux:pillbox.option>
            @endforeach

            <flux:pillbox.option.create modal="{{ $modalName }}">
                {{ $createLabel }}
            </flux:pillbox.option.create>
        </flux:pillbox>
    @endif

    @error($modelName) <p class="mt-1.5 text-[12px] text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
    @error($searchName) <p class="mt-1.5 text-[12px] text-red-600 dark:text-red-400">{{ $message }}</p> @enderror

    @if ($showModal)
        <flux:modal name="{{ $modalName }}" class="md:w-96">
            <form wire:submit="{{ $createMethod }}('{{ $type }}')" class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ $modalTitle }}</flux:heading>
                    <flux:text class="mt-2">{{ __('Unesite naziv za :field.', ['field' => \Illuminate\Support\Str::lower($fieldName)]) }}</flux:text>
                </div>

                <flux:input wire:model="{{ $searchName }}" :label="$modalInputLabel" :placeholder="$modalInputPlaceholder" />

                @error($searchName)
                    <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror

                <div class="flex">
                    <flux:spacer />
                    <flux:button type="submit" variant="primary">{{ $modalSubmitLabel }}</flux:button>
                </div>
            </form>
        </flux:modal>
    @endif
</div>
