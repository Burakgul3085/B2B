<?php

namespace App\Console\Commands;

use App\Models\PlatformAdmin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/** Platform yöneticisi hesabı yalnızca sunucu üzerinden (SSH) oluşturulabilir. */
class CreatePlatformAdmin extends Command
{
    protected $signature = 'platform:create-admin';

    protected $description = 'Yeni bir platform yöneticisi hesabı oluşturur';

    public function handle(): int
    {
        $name = text('Ad soyad', required: true);
        $email = Str::lower(text('E-posta', required: true));
        $pass = password('Parola (en az 10 karakter, harf ve rakam)', required: true);

        $validator = Validator::make(compact('name', 'email') + ['password' => $pass], [
            'name' => ['required', 'max:120'],
            'email' => ['required', 'email', 'unique:platform_admins,email'],
            'password' => ['required', Password::defaults()],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        PlatformAdmin::create(['name' => $name, 'email' => $email, 'password' => $pass, 'is_active' => true]);
        $this->info('Platform yöneticisi oluşturuldu. İlk girişte iki adımlı doğrulama kurulumu istenecek.');

        return self::SUCCESS;
    }
}
