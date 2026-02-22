<x-filament-panels::page>
    {{-- Current Plan & Status --}}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <x-filament::section>
                <x-slot name="heading">Plan actuel</x-slot>

                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-2xl font-bold text-gray-900 dark:text-white">
                            {{ $this->currentPlan?->name ?? 'Freemium' }}
                        </h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                            @if ($this->subscriptionStatus === 'trialing')
                                <x-filament::badge color="info">Essai gratuit</x-filament::badge>
                            @elseif ($this->subscriptionStatus === 'active')
                                <x-filament::badge color="success">Actif</x-filament::badge>
                            @elseif ($this->subscriptionStatus === 'cancelling')
                                <x-filament::badge color="warning">Annulation en cours</x-filament::badge>
                            @elseif ($this->subscriptionStatus === 'paused')
                                <x-filament::badge color="gray">En pause</x-filament::badge>
                            @else
                                <x-filament::badge color="gray">Gratuit</x-filament::badge>
                            @endif
                        </p>
                    </div>

                    <div class="text-right">
                        <p class="text-3xl font-bold text-primary-600">
                            {{ $this->currentPlan?->formatted_monthly_price ?? 'Gratuit' }}
                        </p>
                        <p class="text-sm text-gray-500">/mois</p>
                    </div>
                </div>

                @if ($this->subscriptionStatus === 'active')
                    <div class="mt-4 flex gap-2">
                        <x-filament::button color="warning" size="sm" wire:click="pauseSubscription">
                            Mettre en pause
                        </x-filament::button>
                        <x-filament::button color="danger" size="sm" wire:click="cancelSubscription">
                            Annuler
                        </x-filament::button>
                    </div>
                @elseif ($this->subscriptionStatus === 'paused')
                    <div class="mt-4">
                        <x-filament::button color="success" size="sm" wire:click="resumeSubscription">
                            Reprendre
                        </x-filament::button>
                    </div>
                @elseif ($this->subscriptionStatus === 'cancelling')
                    <div class="mt-4">
                        <x-filament::button color="success" size="sm" wire:click="resumeSubscription">
                            Reprendre l'abonnement
                        </x-filament::button>
                    </div>
                @endif
            </x-filament::section>
        </div>

        {{-- Quick Usage Stats --}}
        <div>
            <x-filament::section>
                <x-slot name="heading">Utilisation</x-slot>

                <div class="space-y-3">
                    @foreach ($this->usageStats as $key => $stat)
                        <div>
                            <div class="flex justify-between text-sm mb-1">
                                <span class="text-gray-600 dark:text-gray-400">{{ $stat['label'] }}</span>
                                <span class="font-medium text-gray-900 dark:text-white">
                                    @if ($stat['is_unlimited'])
                                        {{ $stat['current'] }} / &infin;
                                    @elseif ($stat['is_disabled'])
                                        Désactivé
                                    @else
                                        {{ $stat['current'] }} / {{ $stat['limit'] }}
                                    @endif
                                </span>
                            </div>
                            @unless ($stat['is_disabled'] || $stat['is_unlimited'])
                                <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                    <div class="h-2 rounded-full transition-all {{ $stat['percentage'] >= 90 ? 'bg-danger-500' : ($stat['percentage'] >= 70 ? 'bg-warning-500' : 'bg-primary-500') }}"
                                         style="width: {{ $stat['percentage'] }}%">
                                    </div>
                                </div>
                            @endunless
                        </div>
                    @endforeach
                </div>
            </x-filament::section>
        </div>
    </div>

    {{-- Billing Cycle Toggle --}}
    <div class="flex justify-center my-6">
        <div class="inline-flex items-center rounded-lg bg-gray-100 dark:bg-gray-800 p-1">
            <button
                wire:click="switchCycle('monthly')"
                class="px-4 py-2 rounded-md text-sm font-medium transition {{ $selectedCycle === 'monthly' ? 'bg-white dark:bg-gray-700 text-primary-600 shadow' : 'text-gray-500' }}"
            >
                Mensuel
            </button>
            <button
                wire:click="switchCycle('yearly')"
                class="px-4 py-2 rounded-md text-sm font-medium transition {{ $selectedCycle === 'yearly' ? 'bg-white dark:bg-gray-700 text-primary-600 shadow' : 'text-gray-500' }}"
            >
                Annuel <span class="text-xs text-success-600 font-bold">-17%</span>
            </button>
        </div>
    </div>

    {{-- Plans Grid --}}
    <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-4">
        @foreach ($this->plans as $plan)
            <div class="relative overflow-visible rounded-xl border {{ $this->currentPlan?->id === $plan->id ? 'border-primary-500 ring-2 ring-primary-500' : 'border-gray-200 dark:border-gray-700' }} bg-white dark:bg-gray-900 p-6 flex flex-col">
                @if ($this->currentPlan?->id === $plan->id)
                    <div class="absolute -top-3 left-1/2 -translate-x-1/2 z-10">
                        <span class="bg-primary-500 text-white text-xs font-bold px-3 py-1 rounded-full">Plan actuel</span>
                    </div>
                @endif

                @if ($plan->slug === 'pro')
                    <div class="absolute -top-3 right-4 z-10">
                        <span class="bg-primary-500 text-white text-xs font-bold px-3 py-1 rounded-full">Recommandé</span>
                    </div>
                @endif

                <h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ $plan->name }}</h3>

                <div class="mt-4">
                    <span class="text-3xl font-bold text-gray-900 dark:text-white">
                        {{ $selectedCycle === 'monthly' ? $plan->formatted_monthly_price : $plan->formatted_yearly_price }}
                    </span>
                    <span class="text-sm text-gray-500">
                        /{{ $selectedCycle === 'monthly' ? 'mois' : 'an' }}
                    </span>
                </div>

                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ $plan->description }}</p>

                <ul class="mt-6 space-y-3 flex-grow">
                    @php $limits = $plan->limits; @endphp
                    <li class="flex items-center text-sm">
                        <x-heroicon-m-check class="w-4 h-4 text-success-500 mr-2 flex-shrink-0" />
                        <span>{{ $limits['max_users'] == -1 ? 'Utilisateurs illimités' : $limits['max_users'] . ' utilisateurs' }}</span>
                    </li>
                    <li class="flex items-center text-sm">
                        <x-heroicon-m-check class="w-4 h-4 text-success-500 mr-2 flex-shrink-0" />
                        <span>{{ $limits['max_assets'] == -1 ? 'Actifs illimités' : $limits['max_assets'] . ' actifs' }}</span>
                    </li>
                    <li class="flex items-center text-sm">
                        <x-heroicon-m-check class="w-4 h-4 text-success-500 mr-2 flex-shrink-0" />
                        <span>{{ $limits['max_locations'] == -1 ? 'Sites illimités' : $limits['max_locations'] . ' site(s)' }}</span>
                    </li>
                    <li class="flex items-center text-sm">
                        <x-heroicon-m-check class="w-4 h-4 text-success-500 mr-2 flex-shrink-0" />
                        <span>{{ $limits['max_active_inventory_sessions'] == -1 ? 'Sessions illimitées' : $limits['max_active_inventory_sessions'] . ' session(s) active(s)' }}</span>
                    </li>
                    @if ($limits['max_ai_requests_monthly'] != 0)
                        <li class="flex items-center text-sm">
                            <x-heroicon-m-check class="w-4 h-4 text-success-500 mr-2 flex-shrink-0" />
                            <span>{{ $limits['max_ai_requests_monthly'] == -1 ? 'IA illimitée' : $limits['max_ai_requests_monthly'] . ' requêtes IA/mois' }}</span>
                        </li>
                    @else
                        <li class="flex items-center text-sm text-gray-400">
                            <x-heroicon-m-x-mark class="w-4 h-4 text-gray-400 mr-2 flex-shrink-0" />
                            <span>Pas d'accès IA</span>
                        </li>
                    @endif
                    <li class="flex items-center text-sm {{ $limits['has_api_access'] ? '' : 'text-gray-400' }}">
                        @if ($limits['has_api_access'])
                            <x-heroicon-m-check class="w-4 h-4 text-success-500 mr-2 flex-shrink-0" />
                        @else
                            <x-heroicon-m-x-mark class="w-4 h-4 text-gray-400 mr-2 flex-shrink-0" />
                        @endif
                        <span>Accès API mobile</span>
                    </li>
                    <li class="flex items-center text-sm {{ $limits['has_export'] ? '' : 'text-gray-400' }}">
                        @if ($limits['has_export'])
                            <x-heroicon-m-check class="w-4 h-4 text-success-500 mr-2 flex-shrink-0" />
                        @else
                            <x-heroicon-m-x-mark class="w-4 h-4 text-gray-400 mr-2 flex-shrink-0" />
                        @endif
                        <span>Export de données</span>
                    </li>
                    <li class="flex items-center text-sm {{ $limits['has_advanced_analytics'] ? '' : 'text-gray-400' }}">
                        @if ($limits['has_advanced_analytics'])
                            <x-heroicon-m-check class="w-4 h-4 text-success-500 mr-2 flex-shrink-0" />
                        @else
                            <x-heroicon-m-x-mark class="w-4 h-4 text-gray-400 mr-2 flex-shrink-0" />
                        @endif
                        <span>Analyses avancées</span>
                    </li>
                    <li class="flex items-center text-sm {{ $limits['has_priority_support'] ? '' : 'text-gray-400' }}">
                        @if ($limits['has_priority_support'])
                            <x-heroicon-m-check class="w-4 h-4 text-success-500 mr-2 flex-shrink-0" />
                        @else
                            <x-heroicon-m-x-mark class="w-4 h-4 text-gray-400 mr-2 flex-shrink-0" />
                        @endif
                        <span>Support prioritaire</span>
                    </li>
                </ul>

                <div class="mt-6">
                    @if ($this->currentPlan?->id === $plan->id)
                        <x-filament::button color="gray" class="w-full" disabled>
                            Plan actuel
                        </x-filament::button>
                    @elseif ($plan->isFreemium())
                        {{-- Can't subscribe to freemium, it's the default --}}
                    @elseif ($plan->paddle_monthly_price_id || $plan->paddle_yearly_price_id)
                        @if ($this->subscriptionStatus === 'active' || $this->subscriptionStatus === 'trialing')
                            <x-filament::button color="primary" class="w-full" wire:click="changePlan('{{ $plan->slug }}')">
                                Changer de plan
                            </x-filament::button>
                        @else
                            {{-- Paddle checkout button would go here --}}
                            <x-filament::button color="primary" class="w-full" tag="a" href="#">
                                Souscrire
                            </x-filament::button>
                        @endif
                    @else
                        <x-filament::button color="primary" class="w-full" disabled>
                            Bientôt disponible
                        </x-filament::button>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</x-filament-panels::page>
