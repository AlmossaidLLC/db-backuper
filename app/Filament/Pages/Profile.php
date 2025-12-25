<?php

namespace App\Filament\Pages;

use App\Models\User;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class Profile extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::UserCircle;

    protected string $view = 'filament.pages.profile';

    protected static ?string $navigationLabel = 'Profile';

    protected static ?int $navigationSort = 100;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public ?array $data = [];

    public function mount(): void
    {
        $user = Auth::user();

        if ($user) {
            $this->form->fill([
                'email' => $user->email,
                'name' => $user->name,
            ]);
        }
    }

    public function form(Schema $schema): Schema
    {
        $user = Auth::user();

        return $schema
            ->components([
                Section::make('Profile Information')
                    ->description('Update your account profile information.')
                    ->schema([
                        TextInput::make('name')
                            ->label('Name')
                            ->required()
                            ->maxLength(255)
                            ->autocomplete('name'),

                        TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(User::class, 'email', modifyRuleUsing: fn ($rule) => $rule->ignore($user?->id))
                            ->autocomplete('email'),
                    ])
                    ->columns(2),

                Section::make('Change Password')
                    ->description('Leave blank if you don\'t want to change your password.')
                    ->schema([
                        TextInput::make('current_password')
                            ->label('Current Password')
                            ->password()
                            ->revealable()
                            ->required(fn ($get) => !empty($get('password')))
                            ->currentPassword()
                            ->dehydrated(false)
                            ->columnSpanFull(),

                        TextInput::make('password')
                            ->label('New Password')
                            ->password()
                            ->revealable()
                            ->minLength(8)
                            ->rules([Password::default()])
                            ->confirmed()
                            ->dehydrated(fn ($state) => filled($state)),

                        TextInput::make('password_confirmation')
                            ->label('Confirm New Password')
                            ->password()
                            ->revealable()
                            ->required(fn ($get) => !empty($get('password')))
                            ->dehydrated(false),
                    ])
                    ->columns(2)
                    ->extraAttributes(['class' => 'mb-8']),
            ])
            ->statePath('data')
            ->model($user);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $user = Auth::user();

        if (!$user) {
            return;
        }

        // Update email and name
        $user->email = $data['email'];
        $user->name = $data['name'];

        // Update password if provided
        if (!empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        $user->save();

        Notification::make()
            ->title('Profile updated successfully')
            ->success()
            ->send();

        // Refresh form to clear password fields
        $this->form->fill([
            'email' => $user->email,
            'name' => $user->name,
        ]);
    }

}
