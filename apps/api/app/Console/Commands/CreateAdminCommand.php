<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreateAdminCommand extends Command
{
    protected $signature = 'app:create-admin
        {--name= : Nome do administrador}
        {--email= : E-mail do administrador}
        {--password= : Senha do administrador}';

    protected $description = 'Cria (ou atualiza) um usuário administrador, usando as opções informadas ou as variáveis DEMO_ADMIN_*';

    public function handle(): int
    {
        $name = $this->option('name') ?: env('DEMO_ADMIN_NAME', 'Administrador');
        $email = $this->option('email') ?: env('DEMO_ADMIN_EMAIL');
        $password = $this->option('password') ?: env('DEMO_ADMIN_PASSWORD');

        if (! $email) {
            $this->error('Informe um e-mail via --email ou defina DEMO_ADMIN_EMAIL.');

            return self::FAILURE;
        }

        if (! $password) {
            $password = Str::password(16);
            $this->warn("Nenhuma senha informada. Gerada automaticamente: {$password}");
        }

        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make($password),
                'role' => User::ROLE_ADMIN,
                'active' => true,
            ]
        );

        $this->info("Administrador '{$user->email}' pronto para uso.");

        return self::SUCCESS;
    }
}
