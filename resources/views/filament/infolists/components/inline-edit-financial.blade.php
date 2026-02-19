@php
    $record = $getRecord();

    $depreciationOptions = collect(\App\Enums\DepreciationMethod::cases())
        ->mapWithKeys(fn ($m) => [$m->value => $m->getLabel()])
        ->toArray();

    $fields = [
        [
            'name' => 'purchase_cost',
            'label' => 'Purchase Cost',
            'type' => 'number',
            'value' => $record->purchase_cost,
            'format' => 'money',
            'step' => '0.01',
        ],
        [
            'name' => 'purchase_date',
            'label' => 'Purchase Date',
            'type' => 'date',
            'value' => $record->purchase_date?->format('Y-m-d'),
            'format' => 'date',
        ],
        [
            'name' => 'warranty_expiry',
            'label' => 'Warranty Expiry',
            'type' => 'date',
            'value' => $record->warranty_expiry?->format('Y-m-d'),
            'format' => 'date',
            'danger' => $record->warranty_expiry?->isPast(),
        ],
        [
            'name' => 'depreciation_method',
            'label' => 'Depreciation Method',
            'type' => 'select',
            'value' => $record->depreciation_method?->value,
            'options' => $depreciationOptions,
        ],
        [
            'name' => 'useful_life_months',
            'label' => 'Useful Life',
            'type' => 'number',
            'value' => $record->useful_life_months,
            'format' => 'months',
            'step' => '1',
        ],
        [
            'name' => 'salvage_value',
            'label' => 'Salvage Value',
            'type' => 'number',
            'value' => $record->salvage_value,
            'format' => 'money',
            'step' => '0.01',
        ],
    ];
@endphp

<div class="grid grid-cols-2 gap-x-6 gap-y-1">
    @foreach($fields as $field)
        <div
            x-data="{
                editing: false,
                saving: false,
                value: @js($field['value'] ?? ''),
                originalValue: @js($field['value'] ?? ''),
                options: @js($field['options'] ?? []),
                format: @js($field['format'] ?? null),

                get displayValue() {
                    const v = this.value;
                    if (!v && v !== 0 && v !== '0') return '—';

                    switch (this.format) {
                        case 'money':
                            return '$' + parseFloat(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                        case 'months':
                            return v + ' months';
                        case 'date': {
                            const d = new Date(v + 'T00:00:00');
                            return d.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
                        }
                        default:
                            if (Object.keys(this.options).length) return this.options[v] || '—';
                            return v;
                    }
                },

                startEdit() {
                    this.editing = true;
                    this.$nextTick(() => {
                        const el = this.$refs.input;
                        if (el) {
                            el.focus();
                            if (el.tagName === 'INPUT' && el.type !== 'date') el.select();
                        }
                    });
                },

                async save() {
                    if (this.value == this.originalValue) {
                        this.editing = false;
                        return;
                    }
                    this.saving = true;
                    await $wire.updateAssetField('{{ $field['name'] }}', this.value);
                    this.originalValue = this.value;
                    this.saving = false;
                    this.editing = false;
                },

                cancel() {
                    this.value = this.originalValue;
                    this.editing = false;
                },
            }"
            class="py-2"
        >
            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">{{ $field['label'] }}</dt>
            <dd class="relative">
                {{-- Display mode --}}
                <div
                    x-show="!editing"
                    @click="startEdit()"
                    class="group flex items-center gap-2 rounded-lg px-3 py-1.5 -mx-3 cursor-pointer transition-all duration-150 border border-transparent hover:bg-gray-50 dark:hover:bg-white/5 hover:border-gray-300 dark:hover:border-gray-600"
                >
                    <span
                        x-text="displayValue"
                        class="text-sm"
                        :class="{
                            'text-gray-900 dark:text-white': value && value !== '',
                            'text-gray-400 dark:text-gray-500 italic': !value || value === '',
                            'text-red-600 dark:text-red-400': {{ json_encode($field['danger'] ?? false) }} && value,
                        }"
                    ></span>

                    <svg class="w-3.5 h-3.5 text-gray-400 opacity-0 group-hover:opacity-100 transition-opacity flex-shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M2.695 14.763l-1.262 3.154a.5.5 0 00.65.65l3.155-1.262a4 4 0 001.343-.885L17.5 5.5a2.121 2.121 0 00-3-3L3.58 13.42a4 4 0 00-.885 1.343z" />
                    </svg>
                </div>

                {{-- Edit mode --}}
                <div x-show="editing" x-cloak class="-mx-3">
                    @if($field['type'] === 'number')
                        <input
                            type="number"
                            step="{{ $field['step'] ?? 'any' }}"
                            x-model="value"
                            x-ref="input"
                            @blur="save()"
                            @keydown.enter.prevent="save()"
                            @keydown.escape.prevent="cancel()"
                            class="w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 shadow-sm text-sm text-gray-900 dark:text-white px-3 py-1.5 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 transition"
                        />
                    @elseif($field['type'] === 'date')
                        <input
                            type="date"
                            x-model="value"
                            x-ref="input"
                            @change="save()"
                            @blur="save()"
                            @keydown.escape.prevent="cancel()"
                            class="w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 shadow-sm text-sm text-gray-900 dark:text-white px-3 py-1.5 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 transition"
                        />
                    @elseif($field['type'] === 'select')
                        <select
                            x-model="value"
                            x-ref="input"
                            @change="save()"
                            @blur="save()"
                            @keydown.escape.prevent="cancel()"
                            class="w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 shadow-sm text-sm text-gray-900 dark:text-white px-3 py-1.5 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 transition"
                        >
                            <option value="">—</option>
                            @foreach($field['options'] as $optValue => $optLabel)
                                <option value="{{ $optValue }}">{{ $optLabel }}</option>
                            @endforeach
                        </select>
                    @endif
                </div>

                {{-- Saving indicator --}}
                <div x-show="saving" class="absolute right-0 top-1/2 -translate-y-1/2 pr-2">
                    <svg class="animate-spin w-4 h-4 text-primary-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </div>
            </dd>
        </div>
    @endforeach
</div>
