<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * Cria o primeiro administrador em produção (onde o seed de demonstração fica desligado).
 * A senha é pedida de forma oculta, para não ficar no histórico do terminal.
 */
class CreateAdminCommand extends Command
{
    protected $signature = 'user:create-admin {email : E-mail do administrador} {--name=Administrador : Nome exibido}';

    protected $description = 'Cria um usuário administrador (a senha é pedida sem aparecer na tela)';

    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $password = (string) $this->secret('Senha (mín. 8 caracteres, com letras e números)');
        $confirmation = (string) $this->secret('Confirme a senha');

        $validator = Validator::make(
            ['email' => $email, 'password' => $password, 'password_confirmation' => $confirmation],
            [
                'email' => ['required', 'email', 'max:255', 'unique:users,email'],
                'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $user = User::create([
            'name' => (string) $this->option('name'),
            'email' => $email,
            'password' => $password,
            'role' => UserRole::Admin,
        ]);

        $this->components->info("Administrador criado: {$user->email} (id {$user->id}).");

        return self::SUCCESS;
    }
}
