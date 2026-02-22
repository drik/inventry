<?php

namespace App\Filament\App\Pages;

use App\Models\Organization;
use App\Models\Plan;
use App\Services\PaddleSubscriptionService;
use App\Services\PlanLimitService;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Facades\Filament;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Tenancy\RegisterTenant;
use Illuminate\Support\Str;

class RegisterOrganization extends RegisterTenant
{
    public static function getLabel(): string
    {
        return 'New Organization';
    }

    public function mount(): void
    {
        $user = auth()->user();
        $service = app(PlanLimitService::class);

        if (! $service->canCreateOrganization($user)) {
            $tenant = Filament::getTenant() ?? $user->organization;

            $notification = Notification::make()
                ->title('Limite du plan atteinte')
                ->body('Vous avez atteint le nombre maximum d\'organisations pour votre plan. Passez à un plan supérieur pour créer une nouvelle organisation.')
                ->warning()
                ->persistent();

            if ($tenant) {
                $notification->actions([
                    Action::make('upgrade')
                        ->label('Voir les plans')
                        ->url(Subscription::getUrl(tenant: $tenant))
                        ->button()
                        ->color('primary'),
                ]);
            }

            $notification->send();

            $this->redirect('/app');
        }

        parent::mount();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->live(debounce: 500)
                    ->afterStateUpdated(fn ($state, callable $set) => $set('slug', Str::slug($state))),

                TextInput::make('slug')
                    ->required()
                    ->maxLength(255)
                    ->unique(Organization::class, 'slug')
                    ->rules(['alpha_dash']),

                TextInput::make('phone')
                    ->tel(),

                Textarea::make('address')
                    ->rows(3),

                FileUpload::make('logo_path')
                    ->label('Logo')
                    ->image()
                    ->directory('logos')
                    ->maxSize(1024),
            ]);
    }

    protected function handleRegistration(array $data): Organization
    {
        $organization = Organization::create($data);

        $user = auth()->user();
        $organization->update(['owner_id' => $user->id]);
        $user->update(['organization_id' => $organization->id]);

        // Start a 14-day Pro trial for new organizations
        $proPlan = Plan::where('slug', 'pro')->first();
        if ($proPlan) {
            $paddleService = app(PaddleSubscriptionService::class);
            $paddleService->startGenericTrial($organization, $proPlan);
        }

        return $organization;
    }
}
