@php
    $record = $getRecord();

    $statusOptions = collect(\App\Enums\AssetStatus::cases())
        ->mapWithKeys(fn ($s) => [$s->value => $s->getLabel()])
        ->toArray();

    $statusBadgeClasses = [
        'available' => 'bg-green-100 text-green-700 dark:bg-green-500/20 dark:text-green-400',
        'assigned' => 'bg-blue-100 text-blue-700 dark:bg-blue-500/20 dark:text-blue-400',
        'under_maintenance' => 'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-400',
        'retired' => 'bg-gray-100 text-gray-700 dark:bg-gray-500/20 dark:text-gray-400',
        'lost_stolen' => 'bg-red-100 text-red-700 dark:bg-red-500/20 dark:text-red-400',
        'disposed' => 'bg-gray-100 text-gray-700 dark:bg-gray-500/20 dark:text-gray-400',
    ];

    $fields = [
        [
            'name' => 'name',
            'label' => 'Name',
            'type' => 'text',
            'value' => $record->name,
            'required' => true,
        ],
        [
            'name' => 'category_id',
            'label' => 'Category',
            'type' => 'select',
            'value' => $record->category_id,
            'options' => \App\Models\AssetCategory::pluck('name', 'id')->toArray(),
            'required' => true,
        ],
        [
            'name' => 'manufacturer_id',
            'label' => 'Manufacturer',
            'type' => 'select',
            'value' => $record->manufacturer_id,
            'options' => \App\Models\Manufacturer::pluck('name', 'id')->toArray(),
        ],
        [
            'name' => 'status',
            'label' => 'Status',
            'type' => 'select',
            'value' => $record->status?->value,
            'options' => $statusOptions,
            'required' => true,
            'badge' => true,
            'badgeClasses' => $statusBadgeClasses,
        ],
        [
            'name' => 'location_id',
            'label' => 'Location',
            'type' => 'select',
            'value' => $record->location_id,
            'options' => \App\Models\Location::pluck('name', 'id')->toArray(),
            'required' => true,
        ],
        [
            'name' => 'department_id',
            'label' => 'Department',
            'type' => 'select',
            'value' => $record->department_id,
            'options' => \App\Models\Department::pluck('name', 'id')->toArray(),
        ],
        [
            'name' => 'serial_number',
            'label' => 'Serial Number',
            'type' => 'text',
            'value' => $record->serial_number,
        ],
        [
            'name' => 'barcode',
            'label' => 'Barcode',
            'type' => 'text',
            'value' => $record->barcode,
        ],
    ];
@endphp

<div class="grid grid-cols-2 gap-x-6 gap-y-1">
    @foreach($fields as $field)
        @php
            $isBadge = $field['badge'] ?? false;
            $badgeClasses = $field['badgeClasses'] ?? [];
        @endphp
        <div
            x-data="{
                editing: false,
                saving: false,
                value: @js($field['value'] ?? ''),
                originalValue: @js($field['value'] ?? ''),
                options: @js($field['options'] ?? []),
                required: @js($field['required'] ?? false),

                get displayValue() {
                    const v = this.value;
                    if (!v && v !== 0 && v !== '0') return '—';
                    if (Object.keys(this.options).length) return this.options[v] || '—';
                    return v;
                },

                startEdit() {
                    this.editing = true;
                    this.$nextTick(() => {
                        const el = this.$refs.input;
                        if (el) {
                            el.focus();
                            if (el.tagName === 'INPUT') el.select();
                        }
                    });
                },

                async save() {
                    if (this.required && !this.value) {
                        this.cancel();
                        return;
                    }
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
                    @if($isBadge)
                        <span
                            x-text="displayValue"
                            :class="{
                                @foreach($badgeClasses as $val => $classes)
                                    '{{ $classes }}': value === '{{ $val }}',
                                @endforeach
                            }"
                            class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold"
                        ></span>
                    @else
                        <span
                            x-text="displayValue"
                            class="text-sm"
                            :class="(value && value !== '') ? 'text-gray-900 dark:text-white' : 'text-gray-400 dark:text-gray-500 italic'"
                        ></span>
                    @endif

                    <svg class="w-3.5 h-3.5 text-gray-400 opacity-0 group-hover:opacity-100 transition-opacity flex-shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M2.695 14.763l-1.262 3.154a.5.5 0 00.65.65l3.155-1.262a4 4 0 001.343-.885L17.5 5.5a2.121 2.121 0 00-3-3L3.58 13.42a4 4 0 00-.885 1.343z" />
                    </svg>
                </div>

                {{-- Edit mode --}}
                <div x-show="editing" x-cloak class="-mx-3">
                    @if($field['type'] === 'text')
                        <input
                            type="text"
                            x-model="value"
                            x-ref="input"
                            @blur="save()"
                            @keydown.enter.prevent="save()"
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
                            @if(!($field['required'] ?? false))
                                <option value="">—</option>
                            @endif
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
