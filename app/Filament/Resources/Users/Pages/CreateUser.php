<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $emailLocalPart = Str::before($data['email'], '@');

        $data['name'] = Str::headline(str_replace(['.', '_', '-'], ' ', $emailLocalPart));

        return $data;
    }
}
